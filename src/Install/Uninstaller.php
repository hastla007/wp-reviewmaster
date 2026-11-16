<?php
/**
 * Data removal for uninstall (only with explicit option) and the danger-zone action (RC-048, SET-005).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Install;

use ReviewCaptain\Database\Schema;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Maintenance\Retention;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Schema as SettingsSchema;
use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Uninstaller {

	/**
	 * Called from uninstall.php. Deletes data only if the site opted in.
	 */
	public static function uninstall_site(): void {
		Runner::unschedule();
		Retention::unschedule();
		if ( ! Settings::get( 'delete_data_on_uninstall' ) ) {
			return; // Default: keep all data.
		}
		self::erase_site_data();
		Capabilities::uninstall();
	}

	/**
	 * Remove all tables and options of the current site.
	 */
	public static function erase_site_data(): void {
		global $wpdb;
		/**
		 * Lets add-ons remove their data first.
		 */
		do_action( 'review_captain_erase_site_data' );
		Schema::drop_all();
		foreach ( SettingsSchema::GROUPS as $group ) {
			delete_option( Settings::option_name( $group ) );
		}
		foreach ( array( 'review_captain_settings_version', 'review_captain_milestones', 'review_captain_setup', 'review_captain_heartbeat', 'review_captain_cache_version', 'review_captain_last_worker_run', 'review_captain_spam_blocked', 'review_captain_event_stats', Activator::SETUP_REDIRECT_OPTION ) as $option ) {
			delete_option( $option );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_rc_' ) . '%', $wpdb->esc_like( '_transient_timeout_rc_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush_group( 'review_captain' );
	}
}
