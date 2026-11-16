<?php
/**
 * WordPress test configuration. Values come from environment variables (CI) with local defaults.
 *
 * @package ReviewCaptain
 */

define( 'ABSPATH', ( getenv( 'WP_CORE_DIR' ) ? rtrim( getenv( 'WP_CORE_DIR' ), '/' ) : dirname( __DIR__ ) . '/vendor/johnpbloch/wordpress-core' ) . '/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );
define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ? getenv( 'WP_TESTS_DB_NAME' ) : 'wp_tests' );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ? getenv( 'WP_TESTS_DB_USER' ) : 'wp' );
define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASSWORD' ) !== false ? getenv( 'WP_TESTS_DB_PASSWORD' ) : 'wp' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ? getenv( 'WP_TESTS_DB_HOST' ) : '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'AUTH_KEY', 'test-auth-key' );
define( 'SECURE_AUTH_KEY', 'test-secure-auth-key' );
define( 'AUTH_SALT', 'test-auth-salt' );
