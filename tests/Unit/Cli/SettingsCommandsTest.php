<?php
/**
 * SET-RC-18: `wp review-captain settings list|get|set|reset|export|import`.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Cli;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Cli\CliModule;
use ReviewCaptain\Cli\Commands;
use ReviewCaptain\Cli\Output;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Tests\Support\ProRequestsFields;
use ReviewCaptain\Tests\TestCase;

final class SettingsCommandsTest extends TestCase {

	/** @var object{lines: list<array{0:string,1:string}>, tables: list<array>} */
	private object $out;

	private string $file = '';

	protected function tearDown(): void {
		if ( '' !== $this->file && file_exists( $this->file ) ) {
			unlink( $this->file );
		}
		parent::tearDown();
	}

	private function commands(): Commands {
		$this->out = new class() implements Output {
			public array $lines  = array();
			public array $tables = array();
			public function log( string $m ): void { $this->lines[] = array( 'log', $m ); }
			public function success( string $m ): void { $this->lines[] = array( 'success', $m ); }
			public function warning( string $m ): void { $this->lines[] = array( 'warning', $m ); }
			public function error( string $m ): void { throw new \RuntimeException( 'cli-error: ' . $m ); }
			public function table( array $rows, array $fields, string $format = 'table' ): void { $this->tables[] = $rows; }
		};
		return new Commands( Plugin::instance(), $this->out );
	}

	private function settings(): Settings {
		$s = Plugin::instance()->settings();
		$s->flush();
		return $s;
	}

	private function expect_error( callable $call, string $contains = '' ): void {
		try {
			$call();
			$this->fail( 'Expected a CLI error' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'cli-error', $e->getMessage() );
			if ( '' !== $contains ) {
				$this->assertStringContainsString( $contains, $e->getMessage() );
			}
		}
	}

	private function temp_file( string $content = '' ): string {
		$this->file = (string) tempnam( sys_get_temp_dir(), 'rc-settings-' );
		file_put_contents( $this->file, $content );
		return $this->file;
	}

	public function test_definitions_cover_all_settings_commands(): void {
		$defs = CliModule::definitions();
		foreach ( array( 'list', 'get', 'set', 'reset', 'export', 'import' ) as $sub ) {
			$this->assertArrayHasKey( 'settings ' . $sub, $defs );
			$this->assertTrue( method_exists( Commands::class, $defs[ 'settings ' . $sub ]['method'] ) );
		}
		$names = array_column( $defs['settings import']['synopsis'], 'name' );
		$this->assertContains( 'dry-run', $names );
	}

	public function test_list_and_get_without_user(): void {
		$c    = $this->commands();
		$rows = $c->settings_list( array(), array( 'tab' => 'privacy', 'format' => 'json' ) );
		$byk  = array_column( $rows, null, 'key' );
		$this->assertSame( 365, $byk['privacy.retention_audit_days']['value'] );
		$this->assertSame( 'core (intern)', $byk['privacy.delete_data_confirmed_at']['owner'] );
		$this->assertArrayNotHasKey( 'general.brand_name', $byk );

		$this->assertSame( 20, $c->settings_get( array( 'submissions.min_text_length' ), array() ) );
		$this->assertSame( array( 'log', '20' ), end( $this->out->lines ) );
		$c->settings_get( array( 'access.moderate_roles' ), array( 'format' => 'json' ) );
		$this->assertSame( array( 'log', '["administrator","editor"]' ), end( $this->out->lines ) );

		$this->expect_error( fn() => $c->settings_get( array( 'nope.key' ), array() ), 'Unknown setting' );
		$this->expect_error( fn() => $c->settings_list( array(), array( 'tab' => 'nope' ) ) );
	}

	public function test_set_uses_page_validation_rights_and_audit(): void {
		$c = $this->commands();
		$this->expect_error( fn() => $c->settings_set( array( 'general.brand_name', 'Laden' ), array() ), '--user' );

		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$c->settings_set( array( 'general.brand_name', 'Laden' ), array() );
		$this->assertSame( 'Laden', $this->settings()->get( 'general', 'brand_name' ) );
		$this->assertSame( 'success', end( $this->out->lines )[0] );

		// Booleans of the tab are untouched when another key is set.
		$c->settings_set( array( 'submissions.allow_title', 'no' ), array() );
		$c->settings_set( array( 'submissions.min_text_length', '40' ), array() );
		$this->assertFalse( $this->settings()->get( 'submissions', 'allow_title' ) );
		$this->assertTrue( $this->settings()->get( 'submissions', 'enabled' ) );
		$this->assertSame( 40, $this->settings()->get( 'submissions', 'min_text_length' ) );

		// Not corrected: invalid values are an error and nothing changes.
		$this->expect_error( fn() => $c->settings_set( array( 'submissions.rate_limit_per_hour', '5000' ), array() ), '1 to 1.000' );
		$this->assertSame( 5, $this->settings()->get( 'submissions', 'rate_limit_per_hour' ) );
		$this->expect_error( fn() => $c->settings_set( array( 'submissions.min_text_length', '6000' ), array() ) );
		$c->settings_set( array( 'advanced.job_time_budget', '100' ), array() );
		$this->expect_error( fn() => $c->settings_set( array( 'advanced.lease_seconds', '90' ), array() ), 'time budget' );
		$this->assertSame( 300, $this->settings()->get( 'advanced', 'lease_seconds' ) );
		$this->expect_error( fn() => $c->settings_set( array( 'display.show_date', 'vielleicht' ), array() ) );

		// Same rights as the page: privacy needs manage_privacy, Pro fields need Pro, internal fields are read-only.
		$this->expect_error( fn() => $c->settings_set( array( 'privacy.retention_logs_days', '30' ), array() ), 'privacy' );
		$this->expect_error( fn() => $c->settings_set( array( 'requests.daily_limit', '10' ), array() ) );
		ProRequestsFields::register( false );
		$this->expect_error( fn() => $c->settings_set( array( 'requests.daily_limit', '10' ), array() ), 'extension' );
		$this->expect_error( fn() => $c->settings_set( array( 'privacy.delete_data_confirmed_at', 'x' ), array() ) );

		$rows = $this->db->get_results( $this->db->prepare( 'SELECT context FROM ' . Schema::table( 'audit_log' ) . ' WHERE event = %s ORDER BY id ASC', 'settings.changed' ), ARRAY_A );
		$ctx  = json_decode( $rows[0]['context'], true );
		$this->assertSame( array( array( 'field' => 'general.brand_name', 'from' => '', 'to' => 'Laden' ) ), $ctx['changes'] );
	}

	public function test_set_roles_and_confirmation_word(): void {
		Capabilities::install();
		$c = $this->commands();
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MANAGE_PRIVACY, Capabilities::VIEW_REPORTS, 'promote_users' );
		$c->settings_set( array( 'access.reports_roles', 'editor, ghost' ), array() );
		$this->assertSame( array( 'administrator', 'editor' ), $this->settings()->get( 'access', 'reports_roles' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::VIEW_REPORTS ) );

		$this->expect_error( fn() => $c->settings_set( array( 'privacy.delete_data_on_uninstall', '1' ), array() ), 'DELETE' );
		$this->assertFalse( $this->settings()->get( 'privacy', 'delete_data_on_uninstall' ) );
		$c->settings_set( array( 'privacy.delete_data_on_uninstall', '1' ), array( 'confirm-word' => 'LÖSCHEN' ) );
		$this->assertTrue( $this->settings()->get( 'privacy', 'delete_data_on_uninstall' ) );
		$this->assertNotSame( '', $this->settings()->get( 'privacy', 'delete_data_confirmed_at' ) );

		// S10 suite standard: --confirm is the documented flag (case-insensitive word).
		$c->settings_set( array( 'privacy.delete_data_on_uninstall', '0' ), array() );
		$this->assertFalse( $this->settings()->get( 'privacy', 'delete_data_on_uninstall' ) );
		$this->expect_error( fn() => $c->settings_set( array( 'privacy.delete_data_on_uninstall', '1' ), array( 'confirm' => 'ja' ) ), 'DELETE' );
		$c->settings_set( array( 'privacy.delete_data_on_uninstall', '1' ), array( 'confirm' => 'löschen' ) );
		$this->assertTrue( $this->settings()->get( 'privacy', 'delete_data_on_uninstall' ) );
	}

	public function test_reset_per_tab_and_all(): void {
		update_option( Settings::OPTION, array( 'display' => array( 'show_date' => false ), 'general' => array( 'brand_name' => 'X' ) ) );
		$c = $this->commands();
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->expect_error( fn() => $c->settings_reset( array( 'display' ), array() ), '--yes' );
		$this->expect_error( fn() => $c->settings_reset( array( 'bogus' ), array( 'yes' => true ) ) );
		$this->expect_error( fn() => $c->settings_reset( array( 'privacy' ), array( 'yes' => true ) ) );

		$this->assertSame( array( 'display' ), $c->settings_reset( array( 'display' ), array( 'yes' => true ) ) );
		$this->assertTrue( $this->settings()->get( 'display', 'show_date' ) );
		$this->assertSame( 'X', $this->settings()->get( 'general', 'brand_name' ) );
		$this->assertNotEmpty( get_option( ConfigTransfer::BACKUP_OPTION ) );

		$done = $c->settings_reset( array(), array( 'all' => true, 'yes' => true ) );
		$this->assertNotContains( 'privacy', $done, 'tabs without rights are skipped' );
		$this->assertContains( 'general', $done );
		$this->assertSame( '', $this->settings()->get( 'general', 'brand_name' ) );
		$this->assertSame( 'warning', $this->out->lines[ count( $this->out->lines ) - 2 ][0] );
		$this->assertNotContains( 'requests', $done, 'a tab with add-on fields only is not reported as reset' );
	}

	public function test_reset_of_a_tab_with_addon_fields_only_changes_nothing(): void {
		// SET-RC-P1: `settings reset requests` reported "Zurückgesetzt: requests", wrote a backup and an audit entry.
		ProRequestsFields::register( true );
		update_option( Settings::OPTION, array( 'requests' => array( 'daily_limit' => 77 ) ) );
		$c = $this->commands();
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->expect_error( fn() => $c->settings_reset( array( 'requests' ), array( 'yes' => true ) ) );
		$this->assertSame( 77, $this->settings()->get( 'requests', 'daily_limit' ) );
		$this->assertFalse( get_option( ConfigTransfer::BACKUP_OPTION ) );
		$this->assertStringContainsString( 'requests', $this->out->lines[0][1] );
		$this->assertSame( 'warning', $this->out->lines[0][0] );
	}

	public function test_export_and_import_with_dry_run(): void {
		$c = $this->commands();
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$file = $this->temp_file();
		$doc  = $c->settings_export( array(), array( 'file' => $file ) );
		$this->assertSame( ConfigTransfer::SCHEMA, json_decode( (string) file_get_contents( $file ), true )['schema'] );
		$this->assertSame( $doc['settings']['general'], json_decode( (string) file_get_contents( $file ), true )['settings']['general'] );

		$doc['settings']['display']['show_avatar']            = true;
		$doc['settings']['submissions']['rate_limit_per_hour'] = 99999;
		file_put_contents( $file, (string) wp_json_encode( $doc ) );

		$this->expect_error( fn() => $c->settings_import( array( $file ), array() ), '--yes' );

		$res = $c->settings_import( array( $file ), array( 'dry-run' => true ) );
		$keys = array_column( $res['diff'], null, 'key' );
		$this->assertSame( 'on', $keys['display.show_avatar']['new'] );
		$this->assertSame( '1000', $keys['submissions.rate_limit_per_hour']['new'], 'import keeps correcting like the page import' );
		$this->assertSame( array(), $res['changed'] );
		$this->assertFalse( $this->settings()->get( 'display', 'show_avatar' ), 'dry run changes nothing' );
		$this->assertStringContainsString( 'Dry run', end( $this->out->lines )[1] );

		$res = $c->settings_import( array( $file ), array( 'yes' => true ) );
		$this->assertContains( 'display.show_avatar', $res['changed'] );
		$this->assertTrue( $this->settings()->get( 'display', 'show_avatar' ) );
		$this->assertNotEmpty( get_option( ConfigTransfer::BACKUP_OPTION ) );

		file_put_contents( $file, '{"schema":"other"}' );
		$this->expect_error( fn() => $c->settings_import( array( $file ), array( 'dry-run' => true ) ), 'Not a valid' );
		$this->expect_error( fn() => $c->settings_import( array( '/nonexistent/file.json' ), array( 'dry-run' => true ) ) );
	}

	public function test_export_to_stdout_needs_no_user(): void {
		$c = $this->commands();
		$c->settings_export( array(), array() );
		$json = json_decode( $this->out->lines[0][1], true );
		$this->assertSame( ConfigTransfer::SCHEMA, $json['schema'] );
		$this->assertArrayNotHasKey( 'delete_data_confirmed_at', $json['settings']['privacy'] );
		$this->assertArrayNotHasKey( 'delete_data_on_uninstall', $json['settings']['privacy'] );
	}
}
