<?php
/**
 * WP-CLI commands (RC-036, TEC-002, TEC-014, TEC-015).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Cli;

use ReviewCaptain\Admin\DiagnosticsPage;
use ReviewCaptain\Database\Schema;
use ReviewCaptain\Install\Activator;
use ReviewCaptain\Jobs\Queue;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Maintenance\Reconcile;
use ReviewCaptain\Maintenance\Retention;
use ReviewCaptain\Reviews\ReviewRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Review Captain.
 */
final class Command {

	/**
	 * Process due background tasks.
	 *
	 * ## OPTIONS
	 *
	 * <queue> : Must be "queue".
	 * <run>   : Must be "run".
	 *
	 * [--time=<seconds>]
	 * : Time budget in seconds. Default 50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp review-captain queue run --time=50
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string,string> $assoc_args Assoc args.
	 */
	public function queue( array $args, array $assoc_args ): void {
		$sub = $args[0] ?? 'run';
		if ( 'run' === $sub ) {
			$result = Runner::run( max( 1, (int) ( $assoc_args['time'] ?? 50 ) ) );
			\WP_CLI::success( sprintf( 'Processed %d, failed %d, recovered %d.', $result['processed'], $result['failed'], $result['recovered'] ) );
			return;
		}
		if ( 'stats' === $sub ) {
			\WP_CLI::print_value( Queue::stats(), array( 'format' => 'json' ) );
			return;
		}
		\WP_CLI::error( 'Unknown subcommand. Use: queue run | queue stats' );
	}

	/**
	 * Database schema maintenance.
	 *
	 * ## EXAMPLES
	 *
	 *     wp review-captain db upgrade
	 *     wp review-captain db status
	 *
	 * @param string[] $args Positional args.
	 */
	public function db( array $args ): void {
		$sub = $args[0] ?? 'status';
		if ( 'upgrade' === $sub ) {
			Activator::install_site();
			\WP_CLI::success( 'Schema version ' . Schema::installed_version() . '.' );
			return;
		}
		\WP_CLI::log( sprintf( 'Installed schema %d, code expects %d.', Schema::installed_version(), REVIEW_CAPTAIN_DB_VERSION ) );
	}

	/**
	 * Maintenance tasks: retention, reconcile.
	 *
	 * ## EXAMPLES
	 *
	 *     wp review-captain maintenance retention
	 *     wp review-captain maintenance reconcile
	 *
	 * @param string[] $args Positional args.
	 */
	public function maintenance( array $args ): void {
		$sub = $args[0] ?? '';
		if ( 'retention' === $sub ) {
			\WP_CLI::print_value( Retention::run(), array( 'format' => 'json' ) );
			return;
		}
		if ( 'reconcile' === $sub ) {
			\WP_CLI::print_value( Reconcile::run( array( 'reason' => 'cli' ) ), array( 'format' => 'json' ) );
			return;
		}
		\WP_CLI::error( 'Use: maintenance retention | maintenance reconcile' );
	}

	/**
	 * Print diagnostics without secrets.
	 */
	public function diagnostics(): void {
		\WP_CLI::print_value( DiagnosticsPage::data(), array( 'format' => 'json' ) );
	}

	/**
	 * Re-encrypt stored secrets with the current key (TEC-008). Add-ons register their secrets via filter.
	 */
	public function crypto( array $args ): void {
		if ( 'rotate' !== ( $args[0] ?? '' ) ) {
			\WP_CLI::error( 'Use: crypto rotate' );
		}
		$count = (int) apply_filters( 'review_captain_rotate_secrets', 0 );
		\WP_CLI::success( sprintf( 'Re-encrypted %d secrets.', $count ) );
	}

	/**
	 * Generate test data for scale measurements (TEC-014). Only with WP_DEBUG or --force.
	 *
	 * ## OPTIONS
	 *
	 * [--reviews=<n>]
	 * [--jobs=<n>]
	 * [--force]
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string,string> $assoc_args Assoc args.
	 */
	public function seed( array $args, array $assoc_args ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && empty( $assoc_args['force'] ) ) {
			\WP_CLI::error( 'Refusing to seed without WP_DEBUG or --force.' );
		}
		global $wpdb;
		$reviews   = (int) ( $assoc_args['reviews'] ?? 1000 );
		$jobs      = (int) ( $assoc_args['jobs'] ?? 0 );
		$providers = array( 'site', 'google', 'trustpilot', 'apple_app_store', 'google_play' );
		$table     = Schema::table( 'rc_reviews' );
		$now       = gmdate( 'Y-m-d H:i:s' );
		for ( $i = 0; $i < $reviews; $i += 500 ) {
			$values = array();
			$end    = min( $reviews, $i + 500 );
			for ( $j = $i; $j < $end; $j++ ) {
				$p        = $providers[ $j % 5 ];
				$values[] = $wpdb->prepare(
					'(%s,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s)',
					wp_generate_uuid4(),
					'site' === $p ? 'local' : 'manual',
					$p,
					'general',
					'Seed review ' . $j,
					wp_rand( 1, 5 ),
					'Lorem ipsum seed text ' . $j,
					'Seed User',
					0 === $j % 7 ? 'pending' : 'approved',
					'site' === $p ? 'unverified' : 'external',
					gmdate( 'Y-m-d H:i:s', time() - wp_rand( 0, 3 * YEAR_IN_SECONDS ) ),
					$now,
					$now
				);
			}
			$wpdb->query( "INSERT INTO {$table} (uuid, origin, provider, type, title, rating_value, content, author_name, status, verification, published_at, created_at, updated_at) VALUES " . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		}
		for ( $k = 0; $k < $jobs; $k++ ) {
			Queue::enqueue( 'seed.noop', array( 'n' => $k ), array( 'delay' => wp_rand( 0, 3600 ) ) );
		}
		\WP_CLI::success( sprintf( 'Seeded %d reviews and %d jobs. Total reviews: %d.', $reviews, $jobs, ReviewRepository::count() ) );
	}
}
