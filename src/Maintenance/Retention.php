<?php
/**
 * Retention / data minimisation (RC-048, SET-015, retention-matrix.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Maintenance;

use ReviewCaptain\Audit\AuditLog;
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
	 * @return array{rejected_deleted:int, external_deleted:int, logs_deleted:int, jobs_deleted:int, questions_deleted:int, revisions_deleted:int, contacts_removed:int, approved_deleted:int, complete:bool}
	 */
	public function run( float $time_budget = 20.0 ): array {
		$deadline = microtime( true ) + max( 1.0, $time_budget );
		$settings = $this->plugin->settings();
		$out      = array(
			'rejected_deleted'  => 0,
			'external_deleted'  => 0,
			'logs_deleted'      => 0,
			'jobs_deleted'      => 0,
			'questions_deleted' => 0,
			'revisions_deleted' => 0,
			'contacts_removed'  => 0,
			'approved_deleted'  => 0,
			'complete'          => true,
		);

		$rejected_days = (int) $settings->get( 'privacy', 'retention_rejected_days', 90 );
		$external_days = (int) $settings->get( 'privacy', 'retention_external_days', 30 );
		$logs_days     = (int) $settings->get( 'privacy', 'retention_logs_days', 90 );
		$jobs_days     = (int) $settings->get( 'privacy', 'retention_jobs_days', 30 );
		/**
		 * Filter the retention of settings and role change entries in the audit log (SET-RC-17).
		 *
		 * @param int $days Days (setting privacy.retention_audit_days, default 365).
		 */
		$audit_days = max( 1, (int) apply_filters( 'review_captain_retention_audit_days', (int) $settings->get( 'privacy', 'retention_audit_days', 365 ) ) );
		// SET-RC-08 / SET-RC-21: 0 = unlimited for contact address and approved reviews.
		$contact_days     = (int) $settings->get( 'privacy', 'retention_contact_days', 0 );
		$approved_days    = (int) $settings->get( 'privacy', 'retention_approved_days', 0 );
		$corrections_days = (int) $settings->get( 'privacy', 'retention_corrections_days', \ReviewCaptain\Moderation\CorrectionService::RETENTION_DAYS );

		global $wpdb;
		$table = Schema::table( 'reviews' );

		// 1. Rejected local reviews (status change time = updated_at).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$ids                     = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE platform = 'local' AND status = 'rejected' AND updated_at < %s ORDER BY id ASC LIMIT %d", self::cutoff( $rejected_days ), self::BATCH ) );
		$out['rejected_deleted'] = $this->delete_reviews( $ids, $deadline, $out['complete'] );

		// 1b. Rejected product questions (RC-122) – same retention as rejected reviews.
		$questions = Schema::table( 'questions' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$out['questions_deleted'] = max( 0, (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$questions} WHERE status = 'rejected' AND updated_at < %s", self::cutoff( $rejected_days ) ) ) );

		// 2. Removed external snapshots (tombstones) past their retention.
		if ( microtime( true ) < $deadline ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$ids                     = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE platform <> 'local' AND status = 'removed' AND removed_at IS NOT NULL AND removed_at < %s ORDER BY id ASC LIMIT %d", self::cutoff( $external_days ), self::BATCH ) );
			$out['external_deleted'] = $this->delete_reviews( $ids, $deadline, $out['complete'] );
		} else {
			$out['complete'] = false;
		}

		// 3. Audit log; settings and role changes have their own retention (SET-RC-17).
		if ( microtime( true ) < $deadline ) {
			$audit               = $this->plugin->audit();
			$out['logs_deleted'] = $audit->purge_older_than( $logs_days, AuditLog::SETTINGS_OBJECT_TYPES )
				+ $audit->purge_types_older_than( AuditLog::SETTINGS_OBJECT_TYPES, $audit_days );
		} else {
			$out['complete'] = false;
		}

		// 4. Finished jobs.
		if ( microtime( true ) < $deadline ) {
			$out['jobs_deleted'] = $this->queue->purge_finished( $jobs_days, self::BATCH );
		} else {
			$out['complete'] = false;
		}

		// 5. Decided author-correction versions (RC-125A), privacy.retention_corrections_days after the decision.
		$out['revisions_deleted'] = microtime( true ) < $deadline ? \ReviewCaptain\Moderation\CorrectionService::purge_old( $corrections_days ) : 0;

		// 6. Contact address of reviews (SET-RC-08): the e-mail is removed, the review stays.
		if ( $contact_days > 0 ) {
			if ( microtime( true ) < $deadline ) {
				$ids                     = self::contact_ids( $contact_days, self::BATCH );
				$out['contacts_removed'] = $this->plugin->reviews()->remove_contact( array_map( 'intval', $ids ), 'retention_contact' );
				if ( count( $ids ) >= self::BATCH ) {
					$out['complete'] = false;
				}
				if ( $out['contacts_removed'] > 0 ) {
					$this->plugin->audit()->record(
						'retention.contacts_removed',
						'retention',
						'',
						array(
							'count'  => $out['contacts_removed'],
							'reason' => 'retention_contact_days:' . $contact_days,
						)
					);
				}
			} else {
				$out['complete'] = false;
			}
		}

		// 7. Approved local reviews (SET-RC-08, optional): deleted N days after receipt.
		if ( $approved_days > 0 ) {
			if ( microtime( true ) < $deadline ) {
				$ids                     = self::approved_ids( $approved_days, self::BATCH );
				$out['approved_deleted'] = $this->delete_reviews( $ids, $deadline, $out['complete'] );
			} else {
				$out['complete'] = false;
			}
		}

		$total = $out['rejected_deleted'] + $out['external_deleted'] + $out['logs_deleted'] + $out['jobs_deleted'] + $out['questions_deleted'] + $out['revisions_deleted'] + $out['contacts_removed'] + $out['approved_deleted'];
		if ( $total > 0 ) {
			$this->plugin->audit()->record(
				'retention.purged',
				'retention',
				'',
				array(
					'count'  => $total,
					'fields' => array( $out['rejected_deleted'], $out['external_deleted'], $out['logs_deleted'], $out['jobs_deleted'], $out['contacts_removed'], $out['approved_deleted'] ),
				)
			);
		}
		return $out;
	}

	/**
	 * Preview for the privacy tab (SET-RC-08): how many reviews the next runs would touch with the
	 * given periods (0 = unlimited → 0). Two COUNT queries, no changes.
	 *
	 * @param int $contact_days  privacy.retention_contact_days.
	 * @param int $approved_days privacy.retention_approved_days.
	 * @return array{contacts: int, approved: int}
	 */
	public static function preview( int $contact_days, int $approved_days ): array {
		global $wpdb;
		$table = Schema::table( 'reviews' );
		$out   = array(
			'contacts' => 0,
			'approved' => 0,
		);
		if ( $contact_days > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$out['contacts'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE author_email IS NOT NULL AND author_email <> '' AND created_at < %s", self::cutoff( $contact_days ) ) );
		}
		if ( $approved_days > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$out['approved'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE platform = 'local' AND kind = 'review' AND status = 'approved' AND created_at < %s", self::cutoff( $approved_days ) ) );
		}
		return $out;
	}

	/**
	 * Reviews whose contact address is past its retention.
	 *
	 * @param int $days  Days.
	 * @param int $limit Batch size.
	 * @return array<int, mixed>
	 */
	private static function contact_ids( int $days, int $limit ): array {
		global $wpdb;
		$table = Schema::table( 'reviews' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE author_email IS NOT NULL AND author_email <> '' AND created_at < %s ORDER BY id ASC LIMIT %d", self::cutoff( $days ), $limit ) );
		return is_array( $ids ) ? $ids : array();
	}

	/**
	 * Approved local reviews past their retention (testimonials and platform reviews excluded).
	 *
	 * @param int $days  Days.
	 * @param int $limit Batch size.
	 * @return array<int, mixed>
	 */
	private static function approved_ids( int $days, int $limit ): array {
		global $wpdb;
		$table = Schema::table( 'reviews' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE platform = 'local' AND kind = 'review' AND status = 'approved' AND created_at < %s ORDER BY id ASC LIMIT %d", self::cutoff( $days ), $limit ) );
		return is_array( $ids ) ? $ids : array();
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
