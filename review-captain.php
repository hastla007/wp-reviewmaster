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
// Public contract version (SemVer, independent of the plugin version): docs/contracts/README.md#vertragsversion.
define( 'REVIEW_CAPTAIN_CONTRACT_VERSION', '1.0.0' );
define( 'REVIEW_CAPTAIN_FILE', __FILE__ );
define( 'REVIEW_CAPTAIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REVIEW_CAPTAIN_URL', plugin_dir_url( __FILE__ ) );
define( 'REVIEW_CAPTAIN_MIN_PHP', '8.5' );
define( 'REVIEW_CAPTAIN_MIN_WP', '6.5' );

/*
 * Mindestversion prüfen, bevor neuere PHP-Syntax geladen wird.
 */
/*
 * REVIEW_CAPTAIN_DEV_IGNORE_PHP_MIN: test environments only (local QA where PHP 8.5 is not available).
 * Ignored on production sites (wp_get_environment_type() === 'production') and below PHP 8.1 (code syntax).
 */
$review_captain_php_ok = version_compare( PHP_VERSION, REVIEW_CAPTAIN_MIN_PHP, '>=' )
	|| ( defined( 'REVIEW_CAPTAIN_DEV_IGNORE_PHP_MIN' ) && REVIEW_CAPTAIN_DEV_IGNORE_PHP_MIN
		&& function_exists( 'wp_get_environment_type' ) && 'production' !== wp_get_environment_type()
		&& version_compare( PHP_VERSION, '8.1', '>=' ) );
if ( ! $review_captain_php_ok || version_compare( get_bloginfo( 'version' ), REVIEW_CAPTAIN_MIN_WP, '<' ) ) {
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

/*
 * WooCommerce compatibility (RC-006): the Core only reads products (schema ownership, review separation)
 * and never touches orders or the checkout, so it is compatible with HPOS and the block checkout.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', REVIEW_CAPTAIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', REVIEW_CAPTAIN_FILE, true );
		}
	}
);
