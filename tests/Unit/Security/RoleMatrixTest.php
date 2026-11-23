<?php
/**
 * SET-RC-16: all seven capabilities are assignable in the roles tab (role × capability matrix).
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Security;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\Unit\Admin\AdminTestCase;

final class RoleMatrixTest extends AdminTestCase {

	private function page(): SettingsPage {
		return new SettingsPage( $this->plugin() );
	}

	private function save_access( array $access ): string {
		$this->post(
			'save_settings',
			array(
				'tab'               => 'access',
				SettingsPage::INPUT => array( 'access' => $access ),
			)
		);
		return $this->run_handler( array( $this->page(), 'handle_save' ) );
	}

	public function test_schema_has_one_role_list_per_capability(): void {
		$access = SettingsSchema::fields()['access'];
		$this->assertCount( 7, Capabilities::ROLE_KEYS );
		$this->assertSame( Capabilities::all(), array_values( Capabilities::ROLE_KEYS ) );
		foreach ( Capabilities::ROLE_KEYS as $key => $cap ) {
			$this->assertArrayHasKey( $key, $access, $cap );
			$this->assertSame( 'array', $access[ $key ]['type'] );
			$this->assertContains( 'administrator', $access[ $key ]['default'] );
		}
		// Defaults mirror Capabilities::defaults().
		$this->assertSame( array( 'administrator', 'editor' ), $access['moderate_roles']['default'] );
		$this->assertSame( array( 'administrator' ), $access['reports_roles']['default'] );
	}

	public function test_all_seven_capabilities_can_be_assigned_with_one_audit_entry_per_change(): void {
		Capabilities::install();
		$this->login_with( 'promote_users', ...Capabilities::all() );

		$msg = $this->save_access(
			array(
				'moderate_roles' => array( '', 'administrator', 'editor' ),
				'sources_roles'  => array( '', 'administrator' ),
				'requests_roles' => array( '', 'administrator', 'author' ),
				'reports_roles'  => array( '', 'administrator', 'editor' ),
				'trust_roles'    => array( '', 'administrator' ),
				'privacy_roles'  => array( '', 'administrator', 'editor' ),
				'settings_roles' => array( '', 'administrator' ),
			)
		);
		$this->assertStringContainsString( 'rc_notice=saved', $msg );

		$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::VIEW_REPORTS ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::MANAGE_PRIVACY ) );
		$this->assertTrue( get_role( 'author' )->has_cap( Capabilities::MANAGE_REQUESTS ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( Capabilities::MANAGE_SOURCES ) );
		foreach ( Capabilities::all() as $cap ) {
			$this->assertTrue( get_role( 'administrator' )->has_cap( $cap ), 'administrator keeps ' . $cap );
		}
		$this->assertSame( array( 'administrator', 'editor' ), ( new Settings() )->get( 'access', 'privacy_roles' ) );

		// Exactly the three changed capabilities are logged, each with before/after roles.
		$rows = $this->audit_rows( 'roles.capabilities_changed' );
		$this->assertCount( 3, $rows );
		$caps = array_column( $rows, 'object_id' );
		sort( $caps );
		$expected = array( Capabilities::MANAGE_PRIVACY, Capabilities::MANAGE_REQUESTS, Capabilities::VIEW_REPORTS );
		sort( $expected );
		$this->assertSame( $expected, $caps );
		$ctx = json_decode( $rows[0]['context'], true );
		$this->assertSame( array( 'administrator' ), $ctx['from'] );
		$this->assertContains( 'administrator', $ctx['roles'] );

		// Saving the same matrix again changes nothing and logs nothing new.
		$this->save_access(
			array(
				'reports_roles' => array( '', 'administrator', 'editor' ),
			)
		);
		$this->assertCount( 3, $this->audit_rows( 'roles.capabilities_changed' ) );
	}

	public function test_administrator_cannot_lose_a_capability(): void {
		Capabilities::install();
		$this->login_with( 'promote_users', ...Capabilities::all() );
		$this->save_access( array( 'privacy_roles' => array( '' ) ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::MANAGE_PRIVACY ) );
		$this->assertSame( array( 'administrator' ), ( new Settings() )->get( 'access', 'privacy_roles' ) );
	}

	public function test_unheld_capabilities_can_neither_be_granted_nor_revoked(): void {
		Capabilities::install();
		get_role( 'editor' )->add_cap( Capabilities::MANAGE_SOURCES );
		// Holds settings + reports, but neither privacy nor sources.
		$this->login_with( 'promote_users', Capabilities::MANAGE_SETTINGS, Capabilities::VIEW_REPORTS );
		$this->save_access(
			array(
				'privacy_roles' => array( '', 'editor' ),
				'sources_roles' => array( '' ),
				'reports_roles' => array( '', 'author' ),
			)
		);
		$this->assertFalse( get_role( 'editor' )->has_cap( Capabilities::MANAGE_PRIVACY ), 'unheld: not granted' );
		$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::MANAGE_SOURCES ), 'unheld: not revoked' );
		$this->assertTrue( get_role( 'author' )->has_cap( Capabilities::VIEW_REPORTS ), 'held: granted' );
		$this->assertSame( array( 'administrator' ), ( new Settings() )->get( 'access', 'privacy_roles' ) );
		$this->assertSame( array( 'administrator', 'editor' ), ( new Settings() )->get( 'access', 'sources_roles' ) );
	}

	public function test_legacy_option_is_mapped_from_the_current_roles(): void {
		Capabilities::install();
		// Granted outside the plugin (e.g. a role editor) before the matrix existed.
		get_role( 'editor' )->add_cap( Capabilities::VIEW_REPORTS );
		update_option(
			Settings::OPTION,
			array(
				'access'  => array(
					'moderate_roles' => array( 'administrator', 'editor' ),
					'settings_roles' => array( 'administrator' ),
				),
				'display' => array( 'show_date' => false ),
			)
		);

		$this->assertTrue( SettingsPage::migrate_access( $this->plugin()->settings() ) );
		$this->assertFalse( SettingsPage::migrate_access( $this->plugin()->settings() ), 'runs once' );
		$stored = get_option( Settings::OPTION );
		$this->assertSame( array( 'administrator', 'editor' ), $stored['access']['reports_roles'] );
		$this->assertSame( array( 'administrator' ), $stored['access']['privacy_roles'] );
		$this->assertSame( array( 'administrator', 'editor' ), $stored['access']['moderate_roles'], 'stored lists are kept' );
		$this->assertFalse( $stored['display']['show_date'], 'other tabs untouched' );

		// An import of an old file without the new lists does not revoke the capability.
		$this->login_with( 'promote_users', ...Capabilities::all() );
		$doc = array(
			'schema'         => ConfigTransfer::SCHEMA,
			'schema_version' => 1,
			'settings'       => array( 'access' => array( 'moderate_roles' => array( 'administrator', 'editor', 'author' ) ) ),
		);
		$this->post( 'import_settings', array( 'config_json' => wp_slash( (string) wp_json_encode( $doc ) ) ) );
		$this->run_handler( array( $this->page(), 'handle_import_preview' ) );
		$this->post( 'import_apply', array( 'decision' => 'apply' ) );
		$this->run_handler( array( $this->page(), 'handle_import_apply' ) );
		$this->assertTrue( get_role( 'author' )->has_cap( Capabilities::MODERATE ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::VIEW_REPORTS ) );
	}

	public function test_import_preview_of_legacy_site_uses_current_roles(): void {
		Capabilities::install();
		get_role( 'editor' )->add_cap( Capabilities::VIEW_REPORTS );
		$this->login_with( 'promote_users', ...Capabilities::all() );
		$doc = array(
			'schema'         => ConfigTransfer::SCHEMA,
			'schema_version' => 1,
			'settings'       => array( 'display' => array( 'show_avatar' => true ) ),
		);
		$this->post( 'import_settings', array( 'config_json' => wp_slash( (string) wp_json_encode( $doc ) ) ) );
		$this->run_handler( array( $this->page(), 'handle_import_preview' ) );
		$this->post( 'import_apply', array( 'decision' => 'apply' ) );
		$this->run_handler( array( $this->page(), 'handle_import_apply' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::VIEW_REPORTS ) );
	}

	public function test_render_matrix_with_locked_administrator_and_read_only_columns(): void {
		Capabilities::install();
		$this->login_with( 'promote_users', Capabilities::MANAGE_SETTINGS, Capabilities::MODERATE );
		$_GET = array(
			'page' => SettingsPage::SLUG,
			'tab'  => 'access',
		);
		ob_start();
		$this->page()->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'rc-admin-matrix', $html );
		$this->assertStringContainsString( '<th scope="col" id="rc-access-privacy_roles-col">Datenschutz verwalten', $html );
		foreach ( Capabilities::ROLE_KEYS as $key => $cap ) {
			$this->assertStringContainsString( 'name="review_captain_settings[access][' . $key . '][]"', $html );
			$this->assertStringContainsString( '<code>' . $cap . '</code>', $html );
		}
		// Administrator: always checked and locked.
		$this->assertMatchesRegularExpression( '/id="rc-access-moderate_roles-administrator"[^>]*checked="checked" disabled="disabled"/', $html );
		// Held capability: editable; unheld capability: read-only.
		$this->assertMatchesRegularExpression( '/id="rc-access-moderate_roles-editor"[^>]*checked="checked" \/>/', $html );
		$this->assertMatchesRegularExpression( '/id="rc-access-privacy_roles-editor"[^>]*disabled="disabled"/', $html );
		$this->assertStringContainsString( '(nur lesen)', $html );
		$this->assertStringContainsString( 'Editor: Reviews moderieren', $html, 'accessible name per cell' );
	}
}
