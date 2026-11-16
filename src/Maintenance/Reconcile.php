<?php
/**
 * Upgrade/restore reconciliation (TEC-015).
 *
 * Restore detection compares the heartbeat stored in the database with a marker file in the uploads
 * directory. A database-only restore brings back an older DB heartbeat while the file keeps the newer one.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Maintenance;

use ReviewCaptain\Audit\AuditLog;
use ReviewCaptain\Jobs\Queue;

defined( 'ABSPATH' ) || exit;

final class Reconcile {

	public const HEARTBEAT_OPTION = 'review_captain_heartbeat';

	public static function marker_file(): string {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . 'review-captain/heartbeat-' . get_current_blog_id() . '.txt';
	}

	/**
	 * @return bool True if a restore was detected and reconciliation was queued.
	 */
	public static function detect_restore(): bool {
		$db_beat   = (int) get_option( self::HEARTBEAT_OPTION, 0 );
		$file      = self::marker_file();
		$file_beat = is_readable( $file ) ? (int) file_get_contents( $file ) : 0; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$restored  = $db_beat > 0 && $file_beat > 0 && $db_beat + DAY_IN_SECONDS < $file_beat;

		$now = time();
		update_option( self::HEARTBEAT_OPTION, $now, false );
		if ( wp_mkdir_p( dirname( $file ) ) ) {
			file_put_contents( $file, (string) $now ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( $restored ) {
			Queue::enqueue(
				'maintenance.reconcile',
				array( 'reason' => 'restore' ),
				array(
					'unique_key' => 'reconcile',
					'priority'   => 100,
				)
			);
		}
		return $restored;
	}

	/**
	 * @param array<string,mixed> $payload Payload with reason.
	 * @return array<string,int>
	 */
	public static function run( array $payload = array() ): array {
		$result = array( 'recovered_jobs' => Queue::recover_expired() );
		/**
		 * Add-ons expire stale requests, validate connections and reset cursors here.
		 *
		 * @param array<string,int>   $result  Counts.
		 * @param array<string,mixed> $payload Payload.
		 */
		$result = (array) apply_filters( 'review_captain_reconcile', $result, $payload );
		AuditLog::log(
			'reconcile_run',
			null,
			null,
			array(
				'reason' => (string) ( $payload['reason'] ?? 'manual' ),
				'count'  => array_sum( array_map( 'intval', $result ) ),
			)
		);
		return $result;
	}
}
