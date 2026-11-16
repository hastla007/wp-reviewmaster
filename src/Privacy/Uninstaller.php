<?php
/**
 * Uninstall routine (RC-048). Called from uninstall.php without the plugin bootstrap.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Privacy;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Security\Capabilities;

/**
 * Data is kept by default. Only sites whose setting privacy.delete_data_on_uninstall is true lose
 * their tables, options and capabilities. Scheduled hooks are always removed (they contain no data
 * and would point to missing code).
 */
final class Uninstaller {

	public const SETTINGS_OPTION = 'review_captain_settings';

	/**
	 * Options known to the core (deleted even where the options table cannot be scanned).
	 */
	public const KNOWN_OPTIONS = array(
		'review_captain_settings',
		'review_captain_settings_version',
		'review_captain_db_version',
		'review_captain_installed_at',
		'review_captain_caps_installed',
		'review_captain_upgrade_lock',
		'review_captain_connectors_state',
		'review_captain_connectors_known',
	);

	/**
	 * Cron hooks known to the core.
	 */
	public const CRON_HOOKS = array( 'review_captain_run_jobs', 'review_captain_daily_maintenance' );

	/**
	 * Run for the current site or, on multisite, for every site.
	 */
	public static function run(): void {
		if ( is_multisite() && function_exists( 'get_sites' ) ) {
			$all_deleted = true;
			foreach ( (array) get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			) as $site_id ) {
				switch_to_blog( (int) $site_id );
				$all_deleted = self::run_site() && $all_deleted;
				restore_current_blog();
			}
			if ( $all_deleted ) {
				self::delete_network_options();
			}
			return;
		}
		self::run_site();
	}

	/**
	 * Whether the current site opted in to data deletion.
	 */
	public static function should_delete(): bool {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		$value    = is_array( $settings ) && isset( $settings['privacy'] ) && is_array( $settings['privacy'] ) ? ( $settings['privacy']['delete_data_on_uninstall'] ?? false ) : false;
		return true === $value || 1 === $value || '1' === $value;
	}

	/**
	 * Uninstall the current site. Returns true when data was deleted.
	 */
	public static function run_site(): bool {
		self::clear_cron();
		if ( ! self::should_delete() ) {
			return false;
		}
		Capabilities::uninstall();
		Schema::drop();
		self::delete_options();
		return true;
	}

	/**
	 * Delete all options and transients with the review_captain_ prefix.
	 */
	private static function delete_options(): void {
		global $wpdb;
		$names = self::KNOWN_OPTIONS;
		if ( isset( $wpdb->options ) && is_string( $wpdb->options ) ) {
			foreach ( array( 'review\_captain\_%', '\_transient\_review\_captain\_%', '\_transient\_timeout\_review\_captain\_%' ) as $like ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
				$found = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
				$names = array_merge( $names, array_map( 'strval', (array) $found ) );
			}
		}
		foreach ( array_unique( $names ) as $name ) {
			if ( str_starts_with( $name, '_transient_timeout_' ) ) {
				continue;
			}
			if ( str_starts_with( $name, '_transient_' ) ) {
				delete_transient( substr( $name, strlen( '_transient_' ) ) );
				continue;
			}
			delete_option( $name );
		}
	}

	/**
	 * Delete network options with the prefix (multisite, only if every site opted in).
	 */
	private static function delete_network_options(): void {
		global $wpdb;
		if ( ! isset( $wpdb->sitemeta ) || ! is_string( $wpdb->sitemeta ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", 'review\_captain\_%' ) );
		foreach ( (array) $names as $name ) {
			delete_site_option( (string) $name );
		}
	}

	/**
	 * Remove scheduled events of the plugin.
	 */
	private static function clear_cron(): void {
		$hooks = self::CRON_HOOKS;
		if ( function_exists( '_get_cron_array' ) ) {
			foreach ( (array) _get_cron_array() as $events ) {
				foreach ( array_keys( (array) $events ) as $hook ) {
					if ( is_string( $hook ) && str_starts_with( $hook, 'review_captain_' ) ) {
						$hooks[] = $hook;
					}
				}
			}
		}
		foreach ( array_unique( $hooks ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook, array(), 'review-captain' );
			}
		}
	}
}
