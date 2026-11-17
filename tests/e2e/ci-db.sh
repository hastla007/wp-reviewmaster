#!/usr/bin/env bash
# Integration against a real MySQL/MariaDB server (MINVER-1, TEC-005, TEC-014, RC-139).
# Installs WordPress from Packagist into a temp dir, activates Review Captain, seeds data,
# runs the schema upgrade path and the parallel race test. Used by CI (MariaDB 12.3, MySQL 9.7).
#
# Env: DB_HOST (127.0.0.1:3306), DB_NAME (wp_ci), DB_USER (wp), DB_PASSWORD (wp),
#      CI_DIR (temp dir), WP_CONSTRAINT (^7.0), RC_SEED_REVIEWS (5000), RC_RACE_WORKERS (8)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
CI_DIR="${CI_DIR:-$(mktemp -d)}"
DB_HOST="${DB_HOST:-127.0.0.1:3306}"
DB_NAME="${DB_NAME:-wp_ci}"
DB_USER="${DB_USER:-wp}"
DB_PASSWORD="${DB_PASSWORD:-wp}"
WP_CONSTRAINT="${WP_CONSTRAINT:-^7.0}"
RC_SEED_REVIEWS="${RC_SEED_REVIEWS:-5000}"
RC_RACE_WORKERS="${RC_RACE_WORKERS:-8}"
WP="$CI_DIR/wp"

echo "== WordPress ($WP_CONSTRAINT) nach $CI_DIR"
mkdir -p "$CI_DIR/composer"
( cd "$CI_DIR/composer" && composer require --quiet --no-interaction "johnpbloch/wordpress-core:$WP_CONSTRAINT" )
rm -rf "$WP" && cp -r "$CI_DIR/composer/vendor/johnpbloch/wordpress-core" "$WP"
mkdir -p "$WP/wp-content/plugins/review-captain"
( cd "$ROOT" && git archive HEAD ) | tar -x -C "$WP/wp-content/plugins/review-captain"
if [ -n "${RC_DEV_IGNORE_PHP_MIN:-}" ]; then
	# Local runs on PHP < 8.5 only: lower the header in the test copy (CI runs PHP 8.5 without this).
	sed -i "s/^ \* Requires PHP:      8\.5$/ * Requires PHP:      $(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')/" "$WP/wp-content/plugins/review-captain/review-captain.php"
fi

cat > "$WP/wp-config.php" <<PHP
<?php
define( 'DB_NAME', '$DB_NAME' ); define( 'DB_USER', '$DB_USER' ); define( 'DB_PASSWORD', '$DB_PASSWORD' ); define( 'DB_HOST', '$DB_HOST' );
define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as \$k ) { define( \$k, 'ci-' . \$k ); }
\$table_prefix = 'wp_';
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false );
if ( ! defined( 'DISABLE_WP_CRON' ) ) { define( 'DISABLE_WP_CRON', true ); }
define( 'WP_ENVIRONMENT_TYPE', 'local' );
if ( getenv( 'RC_DEV_IGNORE_PHP_MIN' ) ) { define( 'REVIEW_CAPTAIN_DEV_IGNORE_PHP_MIN', true ); }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHP

cat > "$CI_DIR/step.php" <<'PHP'
<?php
// Usage: php step.php <wp-dir> install|activate|seed|downgrade|upgrade|check
$_SERVER['HTTP_HOST'] = 'localhost';
$step = $argv[2];
if ( 'install' === $step ) { define( 'WP_INSTALLING', true ); }
require $argv[1] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
global $wpdb;
$fail = static function ( string $msg ) { fwrite( STDERR, "FAIL $msg\n" ); exit( 1 ); };
switch ( $step ) {
	case 'install':
		wp_install( 'CI', 'admin', 'admin@example.org', false, '', 'admin' );
		echo 'db server: ', $wpdb->db_server_info(), "\n";
		break;
	case 'activate':
		$r = activate_plugin( 'review-captain/review-captain.php' );
		if ( is_wp_error( $r ) ) { $fail( 'activate: ' . $r->get_error_message() ); }
		if ( ! class_exists( '\ReviewCaptain\Install\Schema' ) ) { $fail( 'plugin did not load (version requirements?)' ); }
		echo 'schema ', get_option( 'review_captain_db_version' ), "\n";
		break;
	case 'seed':
		$n = (int) ( getenv( 'RC_SEED_REVIEWS' ) ?: 5000 );
		$s = new \ReviewCaptain\Maintenance\DevSeeder();
		echo 'seeded ', $s->seed_reviews( $n ), " reviews\n";
		break;
	case 'downgrade':
		// Simulate an installation on schema v2: drop the v3 column/index, set the version back.
		$t = \ReviewCaptain\Install\Schema::table( 'reviews' );
		$wpdb->query( "ALTER TABLE {$t} DROP INDEX status_sorted" );
		$wpdb->query( "ALTER TABLE {$t} DROP COLUMN sorted_at" );
		update_option( 'review_captain_db_version', 2 );
		echo "downgraded to v2\n";
		break;
	case 'upgrade':
		$t0 = microtime( true );
		\ReviewCaptain\Install\Installer::maybe_upgrade();
		printf( "upgrade to %s in %.2fs\n", get_option( 'review_captain_db_version' ), microtime( true ) - $t0 );
		break;
	case 'check':
		$t = \ReviewCaptain\Install\Schema::table( 'reviews' );
		if ( (int) get_option( 'review_captain_db_version' ) !== \ReviewCaptain\Install\Schema::VERSION ) { $fail( 'schema version' ); }
		$nulls = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE sorted_at IS NULL" );
		if ( 0 !== $nulls ) { $fail( "$nulls rows without sorted_at" ); }
		if ( ! $wpdb->get_row( "SHOW INDEX FROM {$t} WHERE Key_name = 'status_sorted'" ) ) { $fail( 'index status_sorted missing' ); }
		$plan = $wpdb->get_row( "EXPLAIN SELECT * FROM {$t} WHERE status = 'approved' ORDER BY sorted_at DESC, id DESC LIMIT 12", ARRAY_A );
		echo 'explain key: ', $plan['key'] ?? 'NULL', "\n";
		$log = WP_CONTENT_DIR . '/debug.log';
		$bad = is_readable( $log ) ? preg_grep( '/review-captain|Review Captain|database error/i', file( $log ) ) : array();
		if ( $bad ) { $fail( "debug.log:\n" . implode( '', array_slice( $bad, 0, 10 ) ) ); }
		echo "PASS schema v", \ReviewCaptain\Install\Schema::VERSION, ", sorted_at filled, index present, clean debug.log\n";
		break;
}
PHP

php "$CI_DIR/step.php" "$WP" install
php "$CI_DIR/step.php" "$WP" activate
RC_SEED_REVIEWS="$RC_SEED_REVIEWS" php "$CI_DIR/step.php" "$WP" seed
php "$CI_DIR/step.php" "$WP" downgrade
php "$CI_DIR/step.php" "$WP" upgrade
php "$CI_DIR/step.php" "$WP" check
RC_RACE_DIR="$CI_DIR/race" bash -c "mkdir -p '$CI_DIR/race' && '$ROOT/tests/e2e/race-mariadb.sh' '$WP/wp-load.php' '$RC_RACE_WORKERS'"
