<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;

final class SettingsPageTest extends AdminTestCase {

	private function page(): SettingsPage {
		return new SettingsPage( $this->plugin() );
	}

	public function test_save_only_current_tab_and_pro_fields_unchanged(): void {
		update_option(
			Settings::OPTION,
			array(
				'display'  => array( 'show_date' => false ),
				'requests' => array( 'daily_limit' => 50 ),
			)
		);
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->post(
			'save_settings',
			array(
				'tab'                      => 'general',
				SettingsPage::INPUT => array(
					'general'  => array( 'brand_name' => 'Musterladen', 'new_review_status' => 'pending' ),
					'display'  => array( 'show_date' => '1' ),
					'requests' => array( 'daily_limit' => '999' ),
				),
			)
		);
		$msg = $this->run_handler( array( $this->page(), 'handle_save' ) );
		$this->assertStringStartsWith( 'redirect:', $msg );
		$this->assertStringContainsString( 'rc_notice=saved', $msg );

		$stored = get_option( Settings::OPTION );
		$this->assertSame( 'Musterladen', $stored['general']['brand_name'] );
		$this->assertFalse( $stored['display']['show_date'], 'other tab untouched' );
		$this->assertSame( 50, $stored['requests']['daily_limit'], 'pro field untouched' );

		$audit = $this->audit_rows( 'settings.changed' );
		$this->assertCount( 1, $audit );
		$context = json_decode( $audit[0]['context'], true );
		$this->assertSame( array( 'general.brand_name' ), $context['fields'] );
		$this->assertStringNotContainsString( 'Musterladen', $audit[0]['context'], 'audit stores keys only' );
	}

	public function test_pro_tab_cannot_be_written_without_pro(): void {
		update_option( Settings::OPTION, array( 'requests' => array( 'daily_limit' => 50, 'sender_email' => 'a@example.test' ) ) );
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$result = $this->page()->save_tab( 'requests', array( 'requests' => array( 'daily_limit' => '7', 'sender_email' => 'evil@example.test', 'reminders_enabled' => '1' ) ) );
		$this->assertSame( array(), $result['changed'] );
		$stored = ( new Settings() )->all();
		$this->assertSame( 50, $stored['requests']['daily_limit'] );
		$this->assertSame( 'a@example.test', $stored['requests']['sender_email'] );
		$this->assertFalse( $stored['requests']['reminders_enabled'] );
	}

	public function test_invalid_values_are_reported(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$result = $this->page()->save_tab( 'submissions', array( 'submissions' => array( 'rate_limit_per_hour' => '99999', 'min_text_length' => '30', 'enabled' => '1' ) ) );
		$this->assertSame( array( 'submissions-rate_limit_per_hour' ), $result['invalid'] );
		$this->assertSame( 1000, ( new Settings() )->get( 'submissions', 'rate_limit_per_hour' ) );
		$this->assertSame( 30, ( new Settings() )->get( 'submissions', 'min_text_length' ) );
	}

	public function test_save_requires_capability_and_nonce(): void {
		$this->login_with( Capabilities::MODERATE );
		$this->post( 'save_settings', array( 'tab' => 'general', SettingsPage::INPUT => array( 'general' => array( 'brand_name' => 'X' ) ) ) );
		$msg = $this->run_handler( array( $this->page(), 'handle_save' ) );
		$this->assertStringNotContainsString( 'redirect:', $msg );

		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->post( 'save_settings', array( 'tab' => 'general', SettingsPage::INPUT => array( 'general' => array( 'brand_name' => 'X' ) ) ), false );
		$this->assertSame( 'nonce_failed', $this->run_handler( array( $this->page(), 'handle_save' ) ) );
		$this->assertFalse( get_option( Settings::OPTION ) );
	}

	public function test_privacy_tab_needs_manage_privacy(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->post( 'save_settings', array( 'tab' => 'privacy', SettingsPage::INPUT => array( 'privacy' => array( 'retention_logs_days' => '5' ) ) ) );
		$this->assertStringContainsString( 'rc_notice=tab_forbidden', $this->run_handler( array( $this->page(), 'handle_save' ) ) );
		$this->assertSame( 90, ( new Settings() )->get( 'privacy', 'retention_logs_days' ) );

		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MANAGE_PRIVACY );
		$this->post( 'save_settings', array( 'tab' => 'privacy', SettingsPage::INPUT => array( 'privacy' => array( 'retention_logs_days' => '5' ) ) ) );
		$this->assertStringContainsString( 'rc_notice=saved', $this->run_handler( array( $this->page(), 'handle_save' ) ) );
		$this->assertSame( 5, ( new Settings() )->get( 'privacy', 'retention_logs_days' ) );
	}

	public function test_roles_tab_syncs_capabilities_and_keeps_administrator(): void {
		Capabilities::install();
		$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::MODERATE ) );

		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->post( 'save_settings', array( 'tab' => 'access', SettingsPage::INPUT => array( 'access' => array( 'moderate_roles' => array( '', 'author' ) ) ) ) );
		$this->assertStringContainsString( 'tab_forbidden', $this->run_handler( array( $this->page(), 'handle_save' ) ), 'roles need promote_users' );

		$this->login_with( Capabilities::MANAGE_SETTINGS, 'promote_users' );
		$this->post(
			'save_settings',
			array(
				'tab'               => 'access',
				SettingsPage::INPUT => array(
					'access' => array(
						'moderate_roles' => array( '', 'author', 'ghost_role' ),
						'settings_roles' => array( '' ),
					),
				),
			)
		);
		$this->assertStringContainsString( 'rc_notice=saved', $this->run_handler( array( $this->page(), 'handle_save' ) ) );

		$this->assertTrue( get_role( 'author' )->has_cap( Capabilities::MODERATE ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( Capabilities::MODERATE ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::MODERATE ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::MANAGE_SETTINGS ) );

		$access = ( new Settings() )->get( 'access', 'moderate_roles' );
		$this->assertSame( array( 'administrator', 'author' ), $access, 'unknown roles dropped, administrator always' );
		$this->assertSame( array( 'administrator' ), ( new Settings() )->get( 'access', 'settings_roles' ) );
		$this->assertNotEmpty( $this->audit_rows( 'roles.capabilities_changed' ) );
	}

	public function test_reset_backs_up_and_keeps_reviews(): void {
		update_option( Settings::OPTION, array( 'display' => array( 'show_date' => false ) ) );
		$this->db->insert( \ReviewCaptain\Install\Schema::table( 'reviews' ), array( 'uuid' => 'u1', 'body' => 'x', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00' ) );
		$this->login_with( Capabilities::MANAGE_SETTINGS );

		$this->post( 'reset_settings', array( 'tab' => 'display', 'scope' => 'tab' ) );
		$this->assertStringContainsString( 'confirm_required', $this->run_handler( array( $this->page(), 'handle_reset' ) ) );
		$this->assertFalse( ( new Settings() )->get( 'display', 'show_date' ) );

		$this->post( 'reset_settings', array( 'tab' => 'display', 'scope' => 'tab', 'confirm' => '1' ) );
		$this->assertStringContainsString( 'reset_done', $this->run_handler( array( $this->page(), 'handle_reset' ) ) );
		$this->assertTrue( ( new Settings() )->get( 'display', 'show_date' ) );
		$this->assertSame( 1, $this->rows( 'reviews' ) );
		$backup = get_option( ConfigTransfer::BACKUP_OPTION );
		$this->assertFalse( $backup['settings']['display']['show_date'] );
	}

	public function test_render_tabs_with_pro_lock_and_advanced_disclosure(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		foreach ( array( 'general', 'sources', 'requests', 'submissions', 'display', 'privacy', 'access', 'advanced' ) as $tab ) {
			$_GET = array( 'page' => SettingsPage::SLUG, 'tab' => $tab );
			ob_start();
			$this->page()->render();
			$html = (string) ob_get_clean();
			$this->assertStringContainsString( 'nav-tab-active', $html, $tab );
			if ( 'requests' === $tab ) {
				$this->assertStringContainsString( 'Benötigt Review Captain Pro', $html );
				$this->assertStringContainsString( 'disabled="disabled"', $html );
				$this->assertStringNotContainsString( 'Änderungen speichern', $html );
			}
			if ( 'sources' === $tab ) {
				$this->assertStringContainsString( '<details class="rc-admin-advanced">', $html );
				$this->assertStringContainsString( 'Erweitert anzeigen', $html );
			}
			if ( 'privacy' === $tab ) {
				$this->assertStringContainsString( 'review_captain_reset_settings', $html );
				$this->assertStringContainsString( 'Deinstallieren', $html );
			}
			if ( 'advanced' === $tab ) {
				$this->assertStringContainsString( 'review_captain_export_settings', $html );
				$this->assertStringContainsString( 'review_captain_import_settings', $html );
			}
		}
	}
}
