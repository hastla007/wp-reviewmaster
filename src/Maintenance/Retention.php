<?php
/**
 * Retention / data minimisation (RC-048, SET-015, retention-matrix.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Maintenance;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Plugin;

/**
 * Daily purge according to the privacy.* settings. Batched (max 500 rows per category and run)
 * and time-budgeted; remaining rows are handled by the next run.
 */
class Retention {

	public const BATCH = 500;

	/**
	 * Constructor.
	 *
	 * @param Plugin   $plugin Plugin.
	 * @param JobQueue $queue  Queue.
	 */
	public function __construct( private Plugin $plugin, private JobQueue $queue ) {}

	/**
	 * Run all purges.
	 *
	 * @param float $time_budget Seconds.
	 * @return array{rejected_deleted:int, external_deleted:int, logs_deleted:int, jobs_deleted:int, complete:bool}
	 */
	public function run( float $time_budget = 20.0 ): array {
		$deadline = microtime( true ) + max( 1.0, $time_budget );
		$settings = $this->plugin->settings();
		$out      = array(
			'rejected_deleted' => 0,
			'external_deleted' => 0,
			'logs_deleted'     => 0,
			'jobs_deleted'     => 0,
			'complete'         => true,
		);

		$rejected_days = (int) $settings->get( 'privacy', 'retention_rejected_days', 90 );
		$external_days = (int) $settings->get( 'privacy', 'retention_external_days', 30 );
		$logs_days     = (int) $settings->get( 'privacy', 'retention_logs_days', 90 );
		$jobs_days     = (int) $settings->get( 'privacy', 'retention_jobs_days', 30 );

		global $wpdb;
		$table = Schema::table( 'reviews' );

		// 1. Rejected local reviews (status change time = updated_at).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$ids                     = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE platform = 'local' AND status = 'rejected' AND updated_at < %s ORDER BY id ASC LIMIT %d", self::cutoff( $rejected_days ), self::BATCH ) );
		$out['rejected_deleted'] = $this->delete_reviews( $ids, $deadline, $out['complete'] );

		// 2. Removed external snapshots (tombstones) past their retention.
		if ( microtime( true ) < $deadline ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$ids                     = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE platform <> 'local' AND status = 'removed' AND removed_at IS NOT NULL AND removed_at < %s ORDER BY id ASC LIMIT %d", self::cutoff( $external_days ), self::BATCH ) );
			$out['external_deleted'] = $this->delete_reviews( $ids, $deadline, $out['complete'] );
		} else {
			$out['complete'] = false;
		}

		// 3. Audit log.
		if ( microtime( true ) < $deadline ) {
			$out['logs_deleted'] = $this->plugin->audit()->purge_older_than( $logs_days );
		} else {
			$out['complete'] = false;
		}

		// 4. Finished jobs.
		if ( microtime( true ) < $deadline ) {
			$out['jobs_deleted'] = $this->queue->purge_finished( $jobs_days, self::BATCH );
		} else {
			$out['complete'] = false;
		}

		$total = $out['rejected_deleted'] + $out['external_deleted'] + $out['logs_deleted'] + $out['jobs_deleted'];
		if ( $total > 0 ) {
			$this->plugin->audit()->record(
				'retention.purged',
				'retention',
				'',
				array(
					'count'  => $total,
					'fields' => array( $out['rejected_deleted'], $out['external_deleted'], $out['logs_deleted'], $out['jobs_deleted'] ),
				)
			);
		}
		return $out;
	}

	/**
	 * UTC cutoff N days ago.
	 *
	 * @param int $days Days.
	 */
	private static function cutoff( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', time() - max( 1, $days ) * DAY_IN_SECONDS );
	}

	/**
	 * Delete reviews via the repository (removes history, fires removal event).
	 *
	 * @param array<int, mixed> $ids      IDs.
	 * @param float             $deadline Deadline.
	 * @param bool              $complete Set to false when stopped early.
	 */
	private function delete_reviews( array $ids, float $deadline, bool &$complete ): int {
		$repo    = $this->plugin->reviews();
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( microtime( true ) >= $deadline ) {
				$complete = false;
				break;
			}
			if ( $repo->delete( (int) $id ) ) {
				++$deleted;
			}
		}
		if ( count( $ids ) >= self::BATCH ) {
			$complete = false;
		}
		return $deleted;
	}
}
