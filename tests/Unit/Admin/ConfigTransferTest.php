<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;

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
