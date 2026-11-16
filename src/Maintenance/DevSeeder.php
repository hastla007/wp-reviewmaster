<?php
/**
 * Test data generator for scaling tests (TEC-014).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Maintenance;

use ReviewCaptain\Install\Schema;

/**
 * Inserts synthetic reviews and jobs in multi-row batches. Seeded rows are marked
 * (`meta.rc_seed = 1`, job type `dev.noop`, key prefix `dev.seed:`).
 */
class DevSeeder {

	public const BATCH = 500;

	private const PLATFORMS = array( 'local', 'local', 'local', 'google', 'trustpilot', 'other' );
	private const STATUSES  = array( 'approved', 'approved', 'approved', 'pending', 'rejected', 'archived' );

	/**
	 * Insert N reviews.
	 *
	 * @param int           $count    Count.
	 * @param callable|null $progress fn( int $done ).
	 * @return int Inserted rows.
	 */
	public function seed_reviews( int $count, ?callable $progress = null ): int {
		global $wpdb;
		$table = Schema::table( 'reviews' );
		$done  = 0;
		$run   = substr( bin2hex( random_bytes( 4 ) ), 0, 8 );
		while ( $done < $count ) {
			$n      = min( self::BATCH, $count - $done );
			$values = array();
			$params = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$seq      = $done + $i;
				$platform = self::PLATFORMS[ $seq % count( self::PLATFORMS ) ];
				$created  = gmdate( 'Y-m-d H:i:s', time() - random_int( 0, 730 ) * DAY_IN_SECONDS - random_int( 0, 86399 ) );
				$external = 'local' === $platform ? null : 'seed-' . $run . '-' . $seq;
				// Local rows: literal NULL for the unique external columns.
				$values[] = null === $external
					? '(%s, %s, %s, %s, NULL, NULL, %f, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s)'
					: '(%s, %s, %s, %s, %s, %s, %f, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s)';
				array_push( $params, wp_generate_uuid4(), 'review', null === $external ? 'manual' : 'provider_sync', $platform );
				if ( null !== $external ) {
					array_push( $params, $external, sha1( $platform . '|0|' . $external ) );
				}
				array_push(
					$params,
					(float) random_int( 1, 5 ),
					'Testbewertung ' . $seq,
					'Automatisch erzeugte Testbewertung Nr. ' . $seq . ' für Skalierungstests.',
					'Test ' . ( $seq % 1000 ),
					'local' === $platform ? 'unverified' : 'external_platform',
					self::STATUSES[ $seq % count( self::STATUSES ) ],
					1,
					'{"rc_seed":1}',
					$created,
					$created,
					$created
				);
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$sql = $wpdb->prepare( "INSERT INTO {$table} (uuid, kind, origin, platform, external_id, dedupe_key, rating_value, title, body, author_name, verification, status, consent_publish, meta, created_at, updated_at, sorted_at) VALUES " . implode( ', ', $values ), $params );
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$done += $n;
			if ( null !== $progress ) {
				$progress( $done );
			}
		}
		\ReviewCaptain\Support\CacheGeneration::bump();
		return $done;
	}

	/**
	 * Insert N jobs (type dev.noop, mixed statuses).
	 *
	 * @param int           $count    Count.
	 * @param callable|null $progress fn( int $done ).
	 * @return int Inserted rows.
	 */
	public function seed_jobs( int $count, ?callable $progress = null ): int {
		global $wpdb;
		$table    = Schema::table( 'jobs' );
		$statuses = array( 'queued', 'queued', 'succeeded', 'succeeded', 'failed', 'dead' );
		$run      = bin2hex( random_bytes( 6 ) );
		$done     = 0;
		$now      = gmdate( 'Y-m-d H:i:s' );
		while ( $done < $count ) {
			$n      = min( self::BATCH, $count - $done );
			$values = array();
			$params = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$seq      = $done + $i;
				$values[] = '(%s, %s, %s, %s, %d, %d, %s, %s, %s)';
				array_push( $params, 'dev.noop', 'dev.seed:' . $run . ':' . $seq, '{"v":1}', $statuses[ $seq % count( $statuses ) ], 0, 8, $now, $now, $now );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (type, idempotency_key, payload, status, attempts, max_attempts, available_at, created_at, updated_at) VALUES " . implode( ', ', $values ), $params ) );
			$done += $n;
			if ( null !== $progress ) {
				$progress( $done );
			}
		}
		return $done;
	}
}
