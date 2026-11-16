<?php
/**
 * Capability model (RC-029, docs/m1/rc-029-030-rechte-fehlercodes.md).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Security;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	public const ROLE = 'rc_review_manager';

	public const ALL = array(
		'rc_view_reviews',
		'rc_moderate_reviews',
		'rc_manage_reviews',
		'rc_manage_widgets',
		'rc_manage_testimonials',
		'rc_manage_trust',
		'rc_connect_sources',
		'rc_manage_requests',
		'rc_send_requests',
		'rc_view_reports',
		'rc_manage_privacy',
		'rc_manage_settings',
	);

	/**
	 * Default role => capabilities map.
	 *
	 * @return array<string,string[]>
	 */
	public static function defaults(): array {
		return array(
			'administrator' => self::ALL,
			'editor'        => array( 'rc_view_reviews', 'rc_moderate_reviews', 'rc_manage_widgets' ),
			'shop_manager'  => array( 'rc_view_reviews', 'rc_moderate_reviews', 'rc_manage_reviews', 'rc_manage_widgets', 'rc_manage_requests', 'rc_view_reports' ),
			self::ROLE      => array( 'rc_view_reviews', 'rc_moderate_reviews', 'rc_manage_reviews', 'rc_manage_widgets', 'rc_manage_testimonials', 'rc_manage_trust', 'rc_view_reports' ),
		);
	}

	public static function install(): void {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, 'Review Manager', array( 'read' => true ) ); // Role names are translated on display.
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
	}

	/**
	 * Apply a role map from the settings page. Administrators always keep all capabilities.
	 *
	 * @param array<string,string[]> $map Role => capabilities.
	 */
	public static function apply_map( array $map ): void {
		foreach ( wp_roles()->role_objects as $role_name => $role ) {
			if ( 'administrator' === $role_name ) {
				continue;
			}
			$wanted = isset( $map[ $role_name ] ) ? array_intersect( (array) $map[ $role_name ], self::ALL ) : array();
			foreach ( self::ALL as $cap ) {
				if ( in_array( $cap, $wanted, true ) ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Current role map for display.
	 *
	 * @return array<string,string[]>
	 */
	public static function current_map(): array {
		$map = array();
		foreach ( wp_roles()->role_objects as $role_name => $role ) {
			$map[ $role_name ] = array_values( array_filter( self::ALL, array( $role, 'has_cap' ) ) );
		}
		return $map;
	}

	public static function uninstall(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::ALL as $cap ) {
				$role->remove_cap( $cap );
			}
		}
		remove_role( self::ROLE );
	}

	/**
	 * Guard for admin actions: capability + nonce. Dies with 403 on failure.
	 */
	public static function check( string $capability, string $nonce_action ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}
}
