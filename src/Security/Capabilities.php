<?php
/**
 * Capability model (RC-029, RC-037).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Security;

/**
 * Plugin capabilities and helpers for capability + nonce checks.
 */
final class Capabilities {

	public const MODERATE        = 'review_captain_moderate';
	public const MANAGE_SOURCES  = 'review_captain_manage_sources';
	public const MANAGE_REQUESTS = 'review_captain_manage_requests';
	public const VIEW_REPORTS    = 'review_captain_view_reports';
	public const MANAGE_TRUST    = 'review_captain_manage_trust';
	public const MANAGE_PRIVACY  = 'review_captain_manage_privacy';
	public const MANAGE_SETTINGS = 'review_captain_manage_settings';

	/**
	 * Default role grants.
	 *
	 * @return array<string, list<string>>
	 */
	public static function defaults(): array {
		$all = self::all();
		return array(
			'administrator' => $all,
			'editor'        => array( self::MODERATE ),
		);
	}

	/**
	 * All capabilities.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::MODERATE,
			self::MANAGE_SOURCES,
			self::MANAGE_REQUESTS,
			self::VIEW_REPORTS,
			self::MANAGE_TRUST,
			self::MANAGE_PRIVACY,
			self::MANAGE_SETTINGS,
		);
	}

	/**
	 * Grant default capabilities (only once per site, so admins can later revoke them).
	 */
	public static function install(): void {
		if ( get_option( 'review_captain_caps_installed' ) ) {
			return;
		}
		foreach ( self::defaults() as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
		update_option( 'review_captain_caps_installed', 1, false );
	}

	/**
	 * Sync role grants for one capability to the given roles (used by Settings › Zugriff & Rollen).
	 * The administrator role always keeps every capability to prevent lock-out.
	 *
	 * @param string       $cap   Capability.
	 * @param list<string> $roles Role names.
	 */
	public static function sync_roles( string $cap, array $roles ): void {
		if ( ! in_array( $cap, self::all(), true ) ) {
			return;
		}
		$roles[] = 'administrator';
		foreach ( wp_roles()->role_objects as $name => $role ) {
			if ( in_array( $name, $roles, true ) ) {
				$role->add_cap( $cap );
			} else {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Remove all capabilities (explicit data deletion on uninstall).
	 */
	public static function uninstall(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
		delete_option( 'review_captain_caps_installed' );
	}

	/**
	 * Nonce action name.
	 *
	 * @param string $action Short action.
	 */
	public static function nonce_action( string $action ): string {
		return 'review_captain_' . $action;
	}

	/**
	 * Abort unless the user has the capability and a valid nonce was sent (admin forms).
	 *
	 * @param string $cap    Capability.
	 * @param string $action Short action.
	 * @param string $field  Request field holding the nonce.
	 */
	public static function verify_request( string $cap, string $action, string $field = '_rc_nonce' ): void {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'Dafür fehlt dir die Berechtigung.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::nonce_action( $action ), $field );
	}
}
