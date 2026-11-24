<?php
/**
 * Activation, upgrade and multisite installation.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Install;

use ReviewCaptain\Security\Capabilities;

/**
 * Installer. Schema work never runs on frontend requests.
 */
final class Installer {

	/**
	 * Activation hook.
	 *
	 * @param bool $network_wide Network activation.
	 */
	public static function activate( bool $network_wide = false ): void {
		// When activated by WP-CLI or network-wide, plugins_loaded has already passed without this
		// plugin, so no module listens to review_captain_installed yet (QA F-02). Boot is idempotent.
		\ReviewCaptain\Plugin::boot();
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install_site();
				restore_current_blog();
			}
			return;
		}
		self::install_site();
	}

	/**
	 * Deactivation hook: stop scheduled events, keep all data.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'review_captain_run_jobs' );
		wp_clear_scheduled_hook( 'review_captain_daily_maintenance' );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'review_captain_run_jobs', array(), 'review-captain' );
			as_unschedule_all_actions( 'review_captain_daily_maintenance', array(), 'review-captain' );
		}
	}

	/**
	 * New site in a network where the plugin is network-active.
	 *
	 * @param \WP_Site $site Site.
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
	 * Upgrade on admin_init if schema version is behind.
	 */
	public static function maybe_upgrade(): void {
		$installed = (int) get_option( Schema::OPTION_VERSION, 0 );
		if ( $installed >= Schema::VERSION ) {
			return;
		}
		// Lock against concurrent upgrades.
		if ( ! add_option( 'review_captain_upgrade_lock', time(), '', false ) ) {
			$since = (int) get_option( 'review_captain_upgrade_lock', 0 );
			if ( $since > time() - 300 ) {
				return;
			}
			update_option( 'review_captain_upgrade_lock', time(), false );
		}
		try {
			self::install_site();
		} finally {
			delete_option( 'review_captain_upgrade_lock' );
		}
	}

	/**
	 * Install/upgrade the current site (idempotent).
	 */
	public static function install_site(): void {
		$from = (int) get_option( Schema::OPTION_VERSION, 0 );
		// Settings migration first: before the DB version is written, a new site is still recognisable (E5).
		\ReviewCaptain\Settings\SettingsMigrator::maybe_run();
		Schema::install();
		Migrator::run( $from, Schema::VERSION );
		Capabilities::install();
		update_option( Schema::OPTION_VERSION, Schema::VERSION, false );
		if ( false === get_option( 'review_captain_installed_at', false ) ) {
			add_option( 'review_captain_installed_at', gmdate( 'Y-m-d H:i:s' ), '', false );
		}
		/**
		 * Fires after the core schema is installed/upgraded for the current site.
		 *
		 * @param int $from Previous schema version.
		 * @param int $to   New schema version.
		 */
		do_action( 'review_captain_installed', $from, Schema::VERSION );
	}
}
