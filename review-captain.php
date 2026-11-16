<?php
/**
 * Plugin Name:       Review Captain
 * Plugin URI:        https://github.com/hastla007/wp-reviewmaster
 * Description:       Collect, moderate and display authentic reviews with clear source attribution – local reviews, curated platform reviews and testimonials.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Captain Suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       review-captain
 * Domain Path:       /languages
 *
 * @package ReviewCaptain
 */

defined( 'ABSPATH' ) || exit;

define( 'REVIEW_CAPTAIN_VERSION', '0.1.0' );
define( 'REVIEW_CAPTAIN_DB_VERSION', 1 );
define( 'REVIEW_CAPTAIN_MIN_PHP', '8.1' );
define( 'REVIEW_CAPTAIN_MIN_WP', '6.5' );
define( 'REVIEW_CAPTAIN_FILE', __FILE__ );
define( 'REVIEW_CAPTAIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REVIEW_CAPTAIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Requirement check without any PHP 8 syntax so that old PHP versions get a
 * readable notice instead of a parse error.
 */
if ( version_compare( PHP_VERSION, REVIEW_CAPTAIN_MIN_PHP, '<' ) || version_compare( get_bloginfo( 'version' ), REVIEW_CAPTAIN_MIN_WP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: 1: required PHP version, 2: required WordPress version */
				esc_html__( 'Review Captain requires PHP %1$s and WordPress %2$s or newer. The plugin is not running.', 'review-captain' ),
				esc_html( REVIEW_CAPTAIN_MIN_PHP ),
				esc_html( REVIEW_CAPTAIN_MIN_WP )
			);
			echo '</p></div>';
		}
	);
	return;
}

require_once REVIEW_CAPTAIN_DIR . 'src/Autoloader.php';
\ReviewCaptain\Autoloader::register();

register_activation_hook( __FILE__, array( \ReviewCaptain\Install\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \ReviewCaptain\Install\Deactivator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \ReviewCaptain\Plugin::class, 'boot' ) );
