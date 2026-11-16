<?php
/**
 * PHPUnit bootstrap using the official WordPress test library (wp-phpunit).
 *
 * Environment: WP_TESTS_DB_NAME, WP_TESTS_DB_USER, WP_TESTS_DB_PASSWORD, WP_TESTS_DB_HOST.
 *
 * @package ReviewCaptain
 */

$root = dirname( __DIR__ );
require_once $root . '/vendor/autoload.php';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : $root . '/vendor/wp-phpunit/wp-phpunit';

require_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $root ) {
		require $root . '/review-captain.php';
	}
);

// Install schema and capabilities for the test site.
tests_add_filter(
	'plugins_loaded',
	static function () {
		\ReviewCaptain\Install\Activator::install_site();
	},
	20
);

require $wp_tests_dir . '/includes/bootstrap.php';
