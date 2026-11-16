<?php
/**
 * Activation: schema, capabilities, scheduled events. Multisite-aware without mass loops (TEC-009).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Install;

use ReviewCaptain\Database\Schema;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Maintenance\Retention;
use ReviewCaptain\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public const SETUP_REDIRECT_OPTION = 'review_captain_setup_redirect';

	public static function activate( bool $network_wide = false ): void {
		// Network activation: sites install lazily on first admin visit (Plugin::maybe_upgrade).
		if ( is_multisite() && $network_wide ) {
			return;
		}
		self::install_site();
		// Redirect to the setup wizard once, but not during bulk activation.
		if ( ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option( self::SETUP_REDIRECT_OPTION, 1, false );
		}
	}

	public static function install_site(): void {
		Schema::install();
		Capabilities::install();
		Runner::schedule();
		Retention::schedule();
		if ( false === get_option( 'review_captain_milestones', false ) ) {
			update_option( 'review_captain_milestones', array( 'activated_at' => gmdate( 'c' ) ), false );
		}
		\ReviewCaptain\Maintenance\Reconcile::detect_restore();
	}

	/**
	 * New site in a network where the plugin is network-active.
	 *
	 * @param \WP_Site $site New site.
	 */
	public static function on_new_site( $site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( REVIEW_CAPTAIN_FILE ) ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		self::install_site();
		restore_current_blog();
	}

	/**
	 * Remove tables when a site is deleted.
	 *
	 * @param array $tables Tables WordPress will drop.
	 * @return array
	 */
	public static function drop_tables_for_site( array $tables ): array {
		foreach ( Schema::TABLES as $table ) {
			$tables[] = Schema::table( $table );
		}
		return $tables;
	}
}
