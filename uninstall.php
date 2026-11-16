<?php
/**
 * Uninstall: keeps data unless "Delete all data when the plugin is deleted" is enabled (RC-048).
 *
 * @package ReviewCaptain
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'REVIEW_CAPTAIN_DIR' ) ) {
	define( 'REVIEW_CAPTAIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'REVIEW_CAPTAIN_FILE' ) ) {
	define( 'REVIEW_CAPTAIN_FILE', REVIEW_CAPTAIN_DIR . 'review-captain.php' );
}
if ( ! defined( 'REVIEW_CAPTAIN_VERSION' ) ) {
	define( 'REVIEW_CAPTAIN_VERSION', '0.1.0' );
}
require_once REVIEW_CAPTAIN_DIR . 'src/Autoloader.php';
\ReviewCaptain\Autoloader::register();

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $review_captain_site_id ) {
		switch_to_blog( (int) $review_captain_site_id );
		\ReviewCaptain\Install\Uninstaller::uninstall_site();
		restore_current_blog();
	}
} else {
	\ReviewCaptain\Install\Uninstaller::uninstall_site();
}
