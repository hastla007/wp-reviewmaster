<?php
/**
 * Security review 2026-11, finding SR-03: the roles tab may only assign capabilities the acting
 * user holds itself (docs/architecture/capabilities.md §3) – via save, import, reset and restore.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Security;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Tests\Unit\Admin\AdminTestCase;

final class RolesTabTest extends AdminTestCase {

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

	public function test_user_without_moderate_cannot_grant_moderate(): void {
		Capabilities::install();
		// Settings manager who may promote users, but is no moderator.
		$this->login_with( Capabilities::MANAGE_SETTINGS, 'promote_users' );

		$this->save_access(
			array(
				'moderate_roles' => array( '', 'author' ),
				'settings_roles' => array( '', 'editor' ),
			)
		);

		$this->assertFalse( get_role( 'author' )->has_cap( Capabilities::MODERATE ), 'unheld capability not granted' );
		$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::MODERATE ), 'unheld capability not revoked either' );
		$this->assertSame( array( 'administrator', 'editor' ), ( new Settings() )->get( 'access', 'moderate_roles' ) );

		// A held capability can still be assigned.
		$this->assertTrue( get_role( 'editor' )->has_cap( Capabilities::MANAGE_SETTINGS ) );
		$this->assertSame( array( 'administrator', 'editor' ), ( new Settings() )->get( 'access', 'settings_roles' ) );
	}

	public function test_user_holding_the_capability_can_assign_it(): void {
		Capabilities::install();
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MODERATE, 'promote_users' );
		$this->save_access( array( 'moderate_roles' => array( '', 'author' ) ) );
		$this->assertTrue( get_role( 'author' )->has_cap( Capabilities::MODERATE ) );
	}

	public function test_import_cannot_grant_unheld_capability(): void {
		Capabilities::install();
		$this->login_with( Capabilities::MANAGE_SETTINGS, 'promote_users' );
		$doc = ( new ConfigTransfer( new Settings() ) )->export();
		$doc['settings']['access']['moderate_roles'] = array( 'administrator', 'author' );

		$this->post( 'import_settings', array( 'config_json' => wp_slash( (string) wp_json_encode( $doc ) ) ) );
		$this->run_handler( array( $this->page(), 'handle_import_preview' ) );
		$this->post( 'import_apply', array( 'decision' => 'apply' ) );
		$this->run_handler( array( $this->page(), 'handle_import_apply' ) );

		$this->assertFalse( get_role( 'author' )->has_cap( Capabilities::MODERATE ) );
		$this->plugin()->settings()->flush();
		$this->assertSame( array( 'administrator', 'editor' ), $this->plugin()->settings()->get( 'access', 'moderate_roles' ) );
	}

	public function test_reset_cannot_regrant_unheld_capability(): void {
		Capabilities::install();
		// An administrator removed the moderator right from editors.
		update_option( Settings::OPTION, array( 'access' => array( 'moderate_roles' => array( 'administrator' ) ) ) );
		get_role( 'editor' )->remove_cap( Capabilities::MODERATE );

		// The default for moderate_roles is administrator + editor; a non-moderator resetting all tabs
		// must not hand the capability back to editors.
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MANAGE_PRIVACY, 'promote_users' );
		$this->assertSame( array( 'access' ), array_values( array_intersect( array( 'access' ), $this->page()->reset_tabs( array( 'access' ) ) ) ) );

		$this->assertFalse( get_role( 'editor' )->has_cap( Capabilities::MODERATE ) );
		$this->plugin()->settings()->flush();
		$this->assertSame( array( 'administrator' ), $this->plugin()->settings()->get( 'access', 'moderate_roles' ) );
	}
}
