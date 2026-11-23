<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsHints;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\Support\ProRequestsFields;

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
					'general'  => array( 'brand_name' => 'Musterladen', 'new_review_status' => 'pending', 'show_pro_hints' => '1' ),
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
		// SET-RC-17: old and new value are logged (short texts in full).
		$this->assertSame( array( array( 'field' => 'general.brand_name', 'from' => '', 'to' => 'Musterladen' ) ), $context['changes'] );
	}

	public function test_pro_tab_cannot_be_written_without_pro(): void {
		$stored_requests = array( 'daily_limit' => 50, 'sender_email' => 'a@example.test', 'reminders_enabled' => false );
		update_option( Settings::OPTION, array( 'requests' => $stored_requests ) );
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		// Without the add-on the core knows no field of this tab at all.
		$result = $this->page()->save_tab( 'requests', array( 'requests' => array( 'daily_limit' => '7', 'sender_email' => 'evil@example.test', 'reminders_enabled' => '1' ) ) );
		$this->assertSame( array(), $result['changed'] );
		$this->assertSame( $stored_requests, ( new Settings() )->all()['requests'] );

		// Add-on registered its fields but does not allow writing them (e.g. user without the right).
		ProRequestsFields::register( false );
		$result = $this->page()->save_tab( 'requests', array( 'requests' => array( 'daily_limit' => '7', 'sender_email' => 'evil@example.test', 'reminders_enabled' => '1' ) ) );
		$this->assertSame( array(), $result['changed'] );
		$this->assertEquals( $stored_requests, array_intersect_key( ( new Settings() )->all()['requests'], $stored_requests ) );
	}

	public function test_core_defines_no_addon_fields_and_stored_values_survive(): void {
		$schema = SettingsSchema::fields();
		$this->assertArrayHasKey( 'requests', SettingsSchema::tabs() );
		$this->assertSame( array(), $schema['requests'], 'the core defines no fields of the tab requests' );
		foreach ( $schema as $tab => $fields ) {
			foreach ( $fields as $key => $field ) {
				$this->assertSame( 'core', $field['owner'], "{$tab}.{$key}" );
				$this->assertArrayNotHasKey( 'entitlement', $field, "{$tab}.{$key}" );
			}
		}

		// Values stored by earlier versions or by the add-on stay unchanged when other tabs are saved or reset.
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
		$this->page()->save_tab( 'general', array( 'general' => array( 'brand_name' => 'Laden', 'show_pro_hints' => '' ) ) );
		$this->page()->save_tab( 'requests', array( 'requests' => array( 'daily_limit' => '1' ) ) );
		$this->page()->reset_tabs( array( 'general', 'requests', 'display' ) );
		$this->assertSame( $requests, get_option( Settings::OPTION )['requests'] );
		$this->assertSame( $requests, ( new Settings() )->all()['requests'] );
	}

	public function test_addon_fields_are_written_while_the_addon_allows_it(): void {
		ProRequestsFields::register( true );
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$result = $this->page()->save_tab( 'requests', array( 'requests' => array( 'daily_limit' => '7', 'sender_email' => 'shop@example.test', 'reminders_enabled' => '1', 'send_window_start' => '08:00', 'send_window_end' => '17:00' ) ) );
		$this->assertContains( 'requests.daily_limit', $result['changed'] );
		$settings = new Settings();
		$this->assertSame( 7, $settings->get( 'requests', 'daily_limit' ) );
		$this->assertSame( 'shop@example.test', $settings->get( 'requests', 'sender_email' ) );
		$this->assertTrue( $settings->get( 'requests', 'reminders_enabled' ) );
		$this->assertSame( 'pro', SettingsSchema::fields()['requests']['daily_limit']['owner'] );
		// A core reset never touches add-on fields.
		$this->page()->reset_tabs( array( 'requests' ) );
		$this->assertSame( 7, ( new Settings() )->get( 'requests', 'daily_limit' ) );
	}

	public function test_addon_cannot_redefine_core_fields_and_malformed_fields_are_dropped(): void {
		add_filter(
			'review_captain_settings_schema',
			static function ( array $schema ): array {
				$schema['general']['brand_name']  = array( 'type' => 'int', 'default' => 1 );
				$schema['requests']['no_default'] = array( 'type' => 'int' );
				$schema['requests']['broken']     = 'x';
				$schema['requests']['own']        = array( 'type' => 'bool', 'default' => true );
				unset( $schema['display'] );
				return $schema;
			}
		);
		$fields = SettingsSchema::fields();
		$this->assertSame( 'string', $fields['general']['brand_name']['type'] );
		$this->assertArrayHasKey( 'show_stars', $fields['display'] );
		$this->assertSame( array( 'own' ), array_keys( $fields['requests'] ) );
		$this->assertSame( 'pro', $fields['requests']['own']['owner'], 'owner of an add-on field defaults to pro' );
	}

	public function test_invalid_values_are_reported(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$result = $this->page()->save_tab( 'submissions', array( 'submissions' => array( 'rate_limit_per_hour' => '99999', 'min_text_length' => '30', 'enabled' => '1' ) ) );
		$this->assertSame( array( 'submissions-rate_limit_per_hour' ), $result['invalid'] );
		// SET-RC-19: not silently clamped – the stored value stays, the field reports the range.
		$this->assertSame( 5, ( new Settings() )->get( 'submissions', 'rate_limit_per_hour' ) );
		$this->assertStringContainsString( '1 to 1.000', $result['errors']['submissions-rate_limit_per_hour'] );
		$this->assertSame( '99999', $result['given']['rate_limit_per_hour'] );
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

		// The acting user must hold every capability it assigns (see Security/RolesTabTest).
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MODERATE, 'promote_users' );
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

	/**
	 * Render one tab.
	 *
	 * @param string $tab Tab.
	 */
	private function render_tab( string $tab ): string {
		$_GET = array( 'page' => SettingsPage::SLUG, 'tab' => $tab );
		ob_start();
		$this->page()->render();
		return (string) ob_get_clean();
	}

	public function test_hints_off_hides_every_hint_and_leaves_a_neutral_sentence(): void {
		update_option( Settings::OPTION, array( 'general' => array( 'show_pro_hints' => false ) ) );
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		foreach ( array( 'general', 'sources', 'display' ) as $tab ) {
			$this->assertStringNotContainsString( 'rc-admin-hints', $this->render_tab( $tab ), $tab );
		}
		$html = $this->render_tab( 'requests' );
		$this->assertStringNotContainsString( 'rc-admin-hints', $html );
		$this->assertStringNotContainsString( 'dashicons-lock', $html );
		$this->assertStringContainsString( 'Settings of extensions appear here.', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( 'Save changes', $html );
	}

	public function test_addon_fields_replace_their_hints(): void {
		ProRequestsFields::register( true );
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$html = $this->render_tab( 'requests' );
		$this->assertStringNotContainsString( 'rc-admin-hints', $html, 'all request hints are replaced by fields' );
		$this->assertStringContainsString( 'name="review_captain_settings[requests][daily_limit]"', $html );
		$this->assertStringContainsString( '<h2>Grenzen</h2>', $html, 'section heading of the add-on field' );
		$this->assertStringContainsString( 'Tageslimit', $html );
		$this->assertStringContainsString( 'Name im Absender von Einladungen. Leer = Markenname.', $html );
		$this->assertStringContainsString( 'Always applies:', $html );
		$this->assertStringContainsString( 'Save changes', $html );
		$this->assertStringNotContainsString( 'disabled="disabled"', $html );
		$this->assertStringNotContainsString( 'dashicons-lock', $html );

		// Only the replaced lines go: the other tabs keep theirs.
		$this->assertStringContainsString( 'data-hint="' . SettingsHints::REPORTS . '"', $this->render_tab( 'general' ) );
	}

	public function test_addon_fields_not_writable_are_shown_disabled_with_a_neutral_note(): void {
		ProRequestsFields::register( false );
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$html = $this->render_tab( 'requests' );
		$this->assertStringContainsString( 'disabled="disabled"', $html );
		$this->assertStringContainsString( 'Managed by an extension; cannot be changed right now.', $html );
		$this->assertStringNotContainsString( 'Save changes', $html );
	}

	public function test_hint_filter_link_and_validation(): void {
		add_filter( 'review_captain_settings_hint_link', static fn( $link, $id ) => SettingsHints::REPORTS === $id ? 'https://shop.example.test/pro' : $link, 10, 2 );
		add_filter(
			'review_captain_settings_hints',
			static function ( array $hints ): array {
				$hints[] = array( 'id' => 'workflow.extra', 'tab' => 'advanced', 'title' => 'Extra', 'text' => 'Ein Satz.', 'link' => 'javascript:alert(1)' );
				$hints[] = array( 'id' => 'Bad Id', 'tab' => 'general', 'title' => 'X', 'text' => 'Y' );
				$hints[] = array( 'id' => 'x.unknown_tab', 'tab' => 'nope', 'title' => 'X', 'text' => 'Y' );
				$hints[] = array( 'id' => SettingsHints::REPORTS, 'tab' => 'general', 'title' => 'Doppelt', 'text' => 'Y' );
				return $hints;
			}
		);
		$all = array_column( SettingsHints::all(), null, 'id' );
		$this->assertSame( 'https://shop.example.test/pro', $all[ SettingsHints::REPORTS ]['link'] );
		$this->assertSame( 'Analytics', $all[ SettingsHints::REPORTS ]['title'], 'first line with an id wins' );
		$this->assertSame( '', $all['workflow.extra']['link'] );
		$this->assertArrayNotHasKey( 'Bad Id', $all );
		$this->assertArrayNotHasKey( 'x.unknown_tab', $all );
		foreach ( SettingsHints::defaults() as $hint ) {
			$this->assertSame( array( 'id', 'tab', 'title', 'text', 'link' ), array_keys( $hint ) );
			$this->assertStringEndsWith( 'Available with Review Captain Pro.', $hint['text'] );
		}

		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->assertStringContainsString( 'href="https://shop.example.test/pro"', $this->render_tab( 'general' ) );
		$advanced = $this->render_tab( 'advanced' );
		$this->assertStringContainsString( 'data-hint="workflow.extra"', $advanced );
		$this->assertStringNotContainsString( 'javascript:', $advanced );
	}

	public function test_render_tabs_with_hints_and_advanced_disclosure(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		foreach ( array( 'general', 'sources', 'requests', 'submissions', 'display', 'privacy', 'access', 'advanced' ) as $tab ) {
			$html = $this->render_tab( $tab );
			$this->assertStringContainsString( 'nav-tab-active', $html, $tab );
			$this->assertStringNotContainsString( 'dashicons-lock', $html, "{$tab}: no lock without add-on fields" );
			$this->assertStringNotContainsString( 'require Review Captain Pro', $html, $tab );
			if ( 'requests' === $tab ) {
				// Only quiet hint lines: no form, no input, no save button, no lock, no warning.
				$this->assertStringContainsString( 'class="rc-admin-hints"', $html );
				$this->assertStringContainsString( 'data-hint="' . SettingsHints::REQUESTS . '"', $html );
				$this->assertStringContainsString( 'Available with Review Captain Pro.', $html );
				$this->assertStringNotContainsString( '<form', $html );
				$this->assertStringNotContainsString( '<input', $html );
				$this->assertStringNotContainsString( 'Save changes', $html );
				$this->assertStringNotContainsString( 'notice-warning', $html );
			}
			if ( in_array( $tab, array( 'general', 'sources', 'display' ), true ) ) {
				$this->assertStringContainsString( 'class="rc-admin-hints"', $html, $tab );
				$this->assertGreaterThan( strpos( $html, '</form>' ), strpos( $html, 'class="rc-admin-hints"' ), "{$tab}: hints at the end of the tab" );
			}
			if ( in_array( $tab, array( 'submissions', 'privacy', 'access', 'advanced' ), true ) ) {
				$this->assertStringNotContainsString( 'rc-admin-hints', $html, $tab );
			}
			if ( 'general' === $tab ) {
				$this->assertStringContainsString( 'Show Pro hints', $html );
			}
			if ( 'sources' === $tab ) {
				$this->assertStringContainsString( '<details class="rc-admin-advanced">', $html );
				$this->assertStringContainsString( 'Show advanced', $html );
			}
			if ( 'privacy' === $tab ) {
				$this->assertStringContainsString( 'review_captain_reset_settings', $html );
				$this->assertStringContainsString( 'Remove all data when the plugin is deleted', $html );
			}
			if ( 'advanced' === $tab ) {
				$this->assertStringContainsString( 'review_captain_export_settings', $html );
				$this->assertStringContainsString( 'review_captain_import_settings', $html );
			}
		}
	}
}
