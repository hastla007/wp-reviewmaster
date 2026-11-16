<?php
/**
 * Ordered, idempotent data migrations between schema versions.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Install;

/**
 * Runs migration steps. dbDelta handles structure; steps here handle data.
 */
final class Migrator {

	/**
	 * Run steps for (from, to].
	 *
	 * @param int $from Installed version.
	 * @param int $to   Target version.
	 */
	public static function run( int $from, int $to ): void {
		foreach ( self::steps() as $version => $step ) {
			if ( $version > $from && $version <= $to ) {
				$step();
			}
		}
	}

	/**
	 * Steps keyed by version. Each step must be safe to re-run.
	 *
	 * @return array<int, callable():void>
	 */
	public static function steps(): array {
		return array(
			1 => static function (): void {
				// Initial schema: nothing to migrate.
			},
			2 => static function (): void {
				// Schema v2 (M7, RC-WF-03, TEC-005): dbDelta adds reviews.revision/submission_key and
				// the tables review_tags, review_reports, review_media and questions. Data step: rows
				// written by hosts whose ALTER did not apply the default get revision 0. submission_key
				// is NOT backfilled – it only applies to new multi-product submissions (RC-123).
				global $wpdb;
				$reviews = Schema::table( 'reviews' );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "UPDATE {$reviews} SET revision = 0 WHERE revision IS NULL" );
			},
			3 => static function (): void {
				// Schema v3 (TEC-014): dbDelta adds reviews.sorted_at and KEY status_sorted. Backfill in
				// batches so large tables do not hold one long lock; re-running only touches NULL rows.
				global $wpdb;
				$reviews = Schema::table( 'reviews' );
				do {
					// Portable batching (no UPDATE … LIMIT, which SQLite does not support).
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
					$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$reviews} WHERE sorted_at IS NULL ORDER BY id ASC LIMIT 5000" ) );
					if ( $ids ) {
						$in = implode( ',', $ids );
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
						$wpdb->query( "UPDATE {$reviews} SET sorted_at = COALESCE(published_at, created_at) WHERE id IN ({$in})" );
					}
				} while ( count( $ids ) === 5000 );
			},
		);
	}
}
