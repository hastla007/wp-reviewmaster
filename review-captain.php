<?php
/**
 * Plugin Name:       Review Captain
 * Plugin URI:        https://github.com/hastla007/wp-reviewmaster
 * Description:       Authentische, sauber attribuierte Kundenbewertungen und Testimonials in WordPress verwalten und anzeigen – ohne Scraping und ohne Lizenzpflicht.
 * Version:           0.1.0-dev
 * Requires at least: 6.5
 * Requires PHP:      8.5
 * Author:            Captain Suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       review-captain
 * Domain Path:       /languages
 *
 * @package ReviewCaptain
 */

defined( 'ABSPATH' ) || exit;

define( 'REVIEW_CAPTAIN_VERSION', '0.1.0-dev' );
define( 'REVIEW_CAPTAIN_FILE', __FILE__ );
define( 'REVIEW_CAPTAIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REVIEW_CAPTAIN_URL', plugin_dir_url( __FILE__ ) );
define( 'REVIEW_CAPTAIN_MIN_PHP', '8.5' );
define( 'REVIEW_CAPTAIN_MIN_WP', '6.5' );

/*
 * Mindestversion prüfen, bevor neuere PHP-Syntax geladen wird.
 */
if ( version_compare( PHP_VERSION, REVIEW_CAPTAIN_MIN_PHP, '<' ) || version_compare( get_bloginfo( 'version' ), REVIEW_CAPTAIN_MIN_WP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: required WordPress version */
						__( 'Review Captain benötigt mindestens PHP %1$s und WordPress %2$s. Das Plugin ist deshalb inaktiv.', 'review-captain' ),
						REVIEW_CAPTAIN_MIN_PHP,
						REVIEW_CAPTAIN_MIN_WP
					)
				)
			);
		}
	);
	return;
}

require_once REVIEW_CAPTAIN_DIR . 'src/Autoloader.php';
\ReviewCaptain\Autoloader::register( REVIEW_CAPTAIN_DIR . 'src/' );

register_activation_hook( __FILE__, array( \ReviewCaptain\Install\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \ReviewCaptain\Install\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \ReviewCaptain\Plugin::class, 'boot' ), 5 );
