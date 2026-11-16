<?php
/**
 * Daily retention job (RC-027, RC-048). Batched and time-bounded.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Maintenance;

use ReviewCaptain\Database\Schema;
use ReviewCaptain\Jobs\Queue;
use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Retention {

	public const HOOK   = 'review_captain_daily_maintenance';
	private const BATCH = 500;

	public static function register(): void {
		add_action( self::HOOK, array( self::class, 'enqueue' ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function enqueue(): void {
		Queue::enqueue(
			'maintenance.retention',
			array(),
			array(
				'unique_key' => 'daily',
				'priority'   => 1,
			)
		);
		Reconcile::detect_restore();
	}

	/**
	 * Run all retention rules once.
	 *
	 * @return array<string,int> Deleted/anonymized rows per rule.
	 */
	public static function run(): array {
		global $wpdb;
		$reviews = Schema::table( 'rc_reviews' );
		$log     = Schema::table( 'rc_review_log' );
		$audit   = Schema::table( 'rc_audit_log' );
		$batches = Schema::table( 'rc_import_batches' );
		$tests   = Schema::table( 'rc_testimonials' );
		$result  = array();

		$cut = static function ( int $days ): string {
			return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		};

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$ids                        = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$reviews} WHERE status IN ('rejected','spam') AND updated_at < %s LIMIT %d", $cut( (int) Settings::get( 'retention_rejected_reviews_days' ) ), self::BATCH ) );
		$result['rejected_reviews'] = self::delete_reviews( array_map( 'intval', $ids ) );

		$ids                          = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$reviews} WHERE tombstoned_at IS NOT NULL AND tombstoned_at < %s LIMIT %d", $cut( 7 ), self::BATCH ) );
		$result['tombstoned_reviews'] = self::delete_reviews( array_map( 'intval', $ids ) );

		$result['audit_log']      = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$audit} WHERE created_at < %s LIMIT %d", $cut( (int) Settings::get( 'retention_logs_days' ) ), self::BATCH ) );
		$result['import_batches'] = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$batches} WHERE created_at < %s LIMIT %d", $cut( 90 ), self::BATCH ) );
		$result['orphan_log']     = (int) $wpdb->query( "DELETE l FROM {$log} l LEFT JOIN {$reviews} r ON r.id = l.review_id WHERE r.id IS NULL" );

		// Testimonials: revoked → media cleanup after configured days is done by Pro (media); core hides immediately.
		$result['revoked_testimonials'] = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$tests} SET status='archived' WHERE revoked_at IS NOT NULL AND status <> 'archived' AND revoked_at < %s", $cut( 0 ) ) );
		// phpcs:enable

		$result['jobs'] = Queue::purge();

		/**
		 * Lets add-ons apply their retention rules (requests, experiences, suppressions …).
		 *
		 * @param array<string,int> $result Counts so far.
		 */
		return (array) apply_filters( 'review_captain_retention_run', $result );
	}

	/**
	 * @param int[] $ids Review IDs.
	 */
	private static function delete_reviews( array $ids ): int {
		global $wpdb;
		if ( ! $ids ) {
			return 0;
		}
		$in      = implode( ',', array_map( 'intval', $ids ) );
		$log     = Schema::table( 'rc_review_log' );
		$reviews = Schema::table( 'rc_reviews' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- IDs are cast to int above.
		$wpdb->query( "DELETE FROM {$log} WHERE review_id IN ({$in})" );
		return (int) $wpdb->query( "DELETE FROM {$reviews} WHERE id IN ({$in})" );
		// phpcs:enable
	}
}
