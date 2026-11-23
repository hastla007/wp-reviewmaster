<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\Support\ProRequestsFields;

final class ConfigTransferTest extends AdminTestCase {

	private function add_sensitive_fields(): void {
		add_filter(
			'review_captain_settings_schema',
			static function ( $schema ) {
				$schema['sources']['api_token']     = array( 'type' => 'string', 'default' => '', 'owner' => 'pro', 'privacy' => 'none', 'advanced' => false, 'scope' => 'site' );
				$schema['sources']['client_secret'] = array( 'type' => 'string', 'default' => '', 'owner' => 'pro', 'privacy' => 'none', 'advanced' => false, 'scope' => 'site' );
				$schema['general']['owner_phone']   = array( 'type' => 'string', 'default' => '', 'owner' => 'core', 'privacy' => 'personal', 'advanced' => false, 'scope' => 'site' );
				return $schema;
			}
		);
	}

	public function test_export_contains_schema_version_and_no_secrets_or_personal_data(): void {
		$this->add_sensitive_fields();
		update_option(
			Settings::OPTION,
			array(
				'sources' => array( 'api_token' => 'tok_live_123', 'client_secret' => 's3cr3t' ),
				'general' => array( 'owner_phone' => '+43 1 234', 'brand_name' => 'Laden' ),
			)
		);
		$doc  = ( new ConfigTransfer( new Settings() ) )->export();
		$json = wp_json_encode( $doc );

		$this->assertSame( ConfigTransfer::SCHEMA, $doc['schema'] );
		$this->assertSame( SettingsSchema::VERSION, $doc['schema_version'] );
		$this->assertArrayNotHasKey( 'api_token', $doc['settings']['sources'] );
		$this->assertArrayNotHasKey( 'client_secret', $doc['settings']['sources'] );
		$this->assertArrayNotHasKey( 'owner_phone', $doc['settings']['general'] );
		$this->assertStringNotContainsString( 'tok_live_123', $json );
		$this->assertStringNotContainsString( 's3cr3t', $json );
		$this->assertStringNotContainsString( '+43 1 234', $json );
		$this->assertSame( 5, $doc['settings']['sources']['max_pages_per_run'] );
	}

	public function test_preview_validates_and_builds_diff(): void {
		$this->add_sensitive_fields();
		$transfer = new ConfigTransfer( new Settings() );
		$doc      = $transfer->export();
		$doc['settings']['submissions']['rate_limit_per_hour'] = 99999;
		$doc['settings']['display']['date_style']               = 'nonsense';
		$doc['settings']['display']['show_avatar']              = true;
		$doc['settings']['requests']['daily_limit']             = 1;
		$doc['settings']['sources']['api_token']                = 'injected';
		$doc['settings']['access']['moderate_roles']            = array( 'author', 'ghost' );
		$doc['settings']['unknown_tab']                         = array( 'x' => 1 );

		$preview = $transfer->preview( (string) wp_json_encode( $doc ) );
		$this->assertNotNull( $preview );
		$diff = array();
		foreach ( $preview['diff'] as $row ) {
			$diff[ $row['tab'] . '.' . $row['key'] ] = array( $row['old'], $row['new'] );
		}
		$this->assertSame( array( 5, 1000 ), $diff['submissions.rate_limit_per_hour'], 'clamped by sanitize' );
		$this->assertSame( array( false, true ), $diff['display.show_avatar'] );
		$this->assertSame( array( array( 'administrator', 'editor' ), array( 'administrator', 'author' ) ), $diff['access.moderate_roles'] );
		$this->assertArrayNotHasKey( 'display.date_style', $diff, 'invalid enum falls back to current/default' );
		$this->assertArrayNotHasKey( 'requests.daily_limit', $diff, 'pro fields are never imported' );
		$this->assertArrayNotHasKey( 'sources.api_token', $diff, 'secrets never imported' );
		$this->assertArrayNotHasKey( 'display.show_date', $diff, 'bools missing in file keep current value' );

		$this->assertNull( $transfer->preview( '{"schema":"other"}' ) );
		$this->assertNull( $transfer->preview( 'not json' ) );
		$newer                   = $doc;
		$newer['schema_version'] = SettingsSchema::VERSION + 1;
		$this->assertNull( $transfer->preview( (string) wp_json_encode( $newer ) ) );
	}

	public function test_export_and_import_keep_addon_request_values(): void {
		$requests = array(
			'sender_name'       => 'Laden',
			'sender_email'      => 'shop@example.test',
			'send_window_start' => '08:00',
			'send_window_end'   => '20:00',
			'daily_limit'       => 50,
			'cooldown_days'     => 30,
			'reminders_enabled' => true,
		);
		update_option( Settings::OPTION, array( 'requests' => $requests ) );
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$page = new SettingsPage( $this->plugin() );

		// Without the add-on: nothing of the tab is exported, an import never touches it.
		$transfer = new ConfigTransfer( new Settings() );
		$doc      = $transfer->export();
		$this->assertArrayNotHasKey( 'requests', $doc['settings'] );
		$doc['settings']['requests']               = array( 'daily_limit' => 1 );
		$doc['settings']['display']['show_avatar'] = true;
		$preview = $transfer->preview( (string) wp_json_encode( $doc ) );
		$page->apply_import( $preview['values'], $preview['tabs'] );
		$this->assertSame( $requests, get_option( Settings::OPTION )['requests'] );

		// Add-on registered but not writable (e.g. no license): exported, a re-import keeps them unchanged.
		ProRequestsFields::register( false );
		$transfer = new ConfigTransfer( new Settings() );
		$doc      = $transfer->export();
		$this->assertEquals( $requests, $doc['settings']['requests'] );
		$doc['settings']['requests']['daily_limit'] = 1;
		$doc['settings']['display']['show_avatar']  = false;
		$preview = $transfer->preview( (string) wp_json_encode( $doc ), null, SettingsPage::pro_writable() );
		$page->apply_import( $preview['values'], $preview['tabs'] );
		$this->assertEquals( $requests, get_option( Settings::OPTION )['requests'], 'add-on fields are not imported while the add-on refuses writes' );
		$this->assertFalse( ( new Settings() )->get( 'display', 'show_avatar' ) );

		// SET-RC-P1: add-on writable – the export/import round trip carries its fields like the settings page does.
		add_filter( 'review_captain_pro_settings_writable', '__return_true' );
		$preview = $transfer->preview( (string) wp_json_encode( $doc ), null, SettingsPage::pro_writable() );
		$this->assertContains( array( 'tab' => 'requests', 'key' => 'daily_limit', 'old' => 50, 'new' => 1 ), $preview['diff'] );
		// The add-on stops allowing writes between preview and apply: its stored value stays.
		remove_all_filters( 'review_captain_pro_settings_writable' );
		$page->apply_import( $preview['values'], $preview['tabs'] );
		$this->assertSame( 50, get_option( Settings::OPTION )['requests']['daily_limit'] );
		add_filter( 'review_captain_pro_settings_writable', '__return_true' );
		$page->apply_import( $preview['values'], $preview['tabs'] );
		$this->assertSame( 1, get_option( Settings::OPTION )['requests']['daily_limit'] );
		$this->assertSame( 'shop@example.test', get_option( Settings::OPTION )['requests']['sender_email'] );
	}

	public function test_import_keeps_values_of_a_failed_cross_check(): void {
		// SET-RC-P1: an import could store minimum ≥ maximum text length (every submission refused).
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$transfer = new ConfigTransfer( new Settings() );
		$doc      = $transfer->export();
		$doc['settings']['submissions']['min_text_length'] = 900;
		$doc['settings']['submissions']['max_text_length'] = 100;
		$doc['settings']['submissions']['photo_max_kb']    = 4096;
		$doc['settings']['sources']['sync_window_start']   = '03:00';
		$doc['settings']['sources']['sync_window_end']     = '03:00';
		$doc['settings']['advanced']['lease_seconds']      = 60;
		$doc['settings']['advanced']['job_time_budget']    = 120;
		$preview = $transfer->preview( (string) wp_json_encode( $doc ) );
		$this->assertSame( array( 'submissions.photo_max_kb' ), array_map( static fn( $row ) => $row['tab'] . '.' . $row['key'], $preview['diff'] ) );
		$this->assertCount( 3, $preview['kept'] );
		$this->assertSame( array( 'min_text_length', 'max_text_length' ), $preview['kept'][1]['fields'] );
		$this->assertSame( 'Die Mindestlänge muss kleiner als die Höchstlänge sein.', $preview['kept'][1]['message'] );
		( new SettingsPage( $this->plugin() ) )->apply_import( $preview['values'], $preview['tabs'] );
		$s = new Settings();
		$this->assertSame( 20, $s->get( 'submissions', 'min_text_length' ) );
		$this->assertSame( 5000, $s->get( 'submissions', 'max_text_length' ) );
		$this->assertSame( 4096, $s->get( 'submissions', 'photo_max_kb' ) );
		$this->assertSame( '02:00', $s->get( 'sources', 'sync_window_start' ) );
		$this->assertSame( 300, $s->get( 'advanced', 'lease_seconds' ) );

		// A valid pair is taken.
		$doc['settings']['submissions']['min_text_length'] = 50;
		$doc['settings']['submissions']['max_text_length'] = 800;
		$preview = $transfer->preview( (string) wp_json_encode( $doc ) );
		$this->assertCount( 2, $preview['kept'] );
		$this->assertSame( 800, $preview['values']['submissions']['max_text_length'] );
	}

	public function test_preview_respects_allowed_tabs(): void {
		$transfer = new ConfigTransfer( new Settings() );
		$doc      = $transfer->export();
		$doc['settings']['privacy']['retention_logs_days'] = 7;
		$doc['settings']['display']['show_avatar']         = true;
		$preview = $transfer->preview( (string) wp_json_encode( $doc ), array( 'display' ) );
		$this->assertSame( array( 'display' ), $preview['tabs'] );
		$this->assertCount( 1, $preview['diff'] );
	}

	public function test_import_flow_with_confirmation_backup_and_rollback(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MANAGE_PRIVACY, 'promote_users' );
		update_option( Settings::OPTION, array( 'display' => array( 'show_avatar' => false ) ) );
		$page = new SettingsPage( $this->plugin() );
		$doc  = ( new ConfigTransfer( new Settings() ) )->export();
		$doc['settings']['display']['show_avatar'] = true;

		$this->post( 'import_settings', array( 'config_json' => wp_slash( (string) wp_json_encode( $doc ) ) ) );
		$this->assertStringContainsString( 'import=preview', $this->run_handler( array( $page, 'handle_import_preview' ) ) );
		$this->plugin()->settings()->flush();
		$this->assertFalse( $this->plugin()->settings()->get( 'display', 'show_avatar' ), 'preview changes nothing' );

		// Render shows the diff.
		$_GET = array( 'page' => SettingsPage::SLUG, 'tab' => 'advanced', 'import' => 'preview' );
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'rc-admin-diff', $html );
		$this->assertStringContainsString( 'Profilbild anzeigen', $html );

		$this->post( 'import_apply', array( 'decision' => 'apply' ) );
		$this->assertStringContainsString( 'import_applied', $this->run_handler( array( $page, 'handle_import_apply' ) ) );
		$this->plugin()->settings()->flush();
		$this->assertTrue( $this->plugin()->settings()->get( 'display', 'show_avatar' ) );
		$this->assertNotEmpty( get_option( ConfigTransfer::BACKUP_OPTION ) );
		$this->assertCount( 1, $this->audit_rows( 'settings.imported' ) );

		$this->post( 'rollback_settings', array() );
		$this->assertStringContainsString( 'rollback_done', $this->run_handler( array( $page, 'handle_rollback' ) ) );
		$this->plugin()->settings()->flush();
		$this->assertFalse( $this->plugin()->settings()->get( 'display', 'show_avatar' ) );
	}

	public function test_import_rejects_invalid_document_and_discard_changes_nothing(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$page = new SettingsPage( $this->plugin() );
		$this->post( 'import_settings', array( 'config_json' => '{"schema":"review_captain.settings.v1","schema_version":"1"}' ) );
		$this->assertStringContainsString( 'import_invalid', $this->run_handler( array( $page, 'handle_import_preview' ) ) );

		$this->post( 'import_apply', array( 'decision' => 'discard' ) );
		$this->assertStringContainsString( 'import_discarded', $this->run_handler( array( $page, 'handle_import_apply' ) ) );
		$this->assertFalse( get_option( Settings::OPTION ) );

		$this->post( 'import_settings', array( 'config_json' => '{}' ), false );
		$this->assertSame( 'nonce_failed', $this->run_handler( array( $page, 'handle_import_preview' ) ) );
	}
}
