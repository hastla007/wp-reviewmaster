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
		);
	}
}
