<?php
define( 'DB_NAME', 'wp' );
define( 'DB_USER', 'wp' );
define( 'DB_PASSWORD', 'wp' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'DB_FILE', getenv( 'WPE2E_DB_FILE' ) ?: '.ht.sqlite' );
define( 'AUTH_KEY', 'e2e-a' ); define( 'SECURE_AUTH_KEY', 'e2e-b' ); define( 'LOGGED_IN_KEY', 'e2e-c' ); define( 'NONCE_KEY', 'e2e-d' );
define( 'AUTH_SALT', 'e2e-e' ); define( 'SECURE_AUTH_SALT', 'e2e-f' ); define( 'LOGGED_IN_SALT', 'e2e-g' ); define( 'NONCE_SALT', 'e2e-h' );
$table_prefix = 'wp_';
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', __DIR__ . '/wp-content/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'DISABLE_WP_CRON', true );
if ( file_exists( __DIR__ . '/wp-config-e2e-extra.php' ) ) { require __DIR__ . '/wp-config-e2e-extra.php'; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
