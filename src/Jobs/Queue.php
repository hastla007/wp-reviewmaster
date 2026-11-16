<?php
/**
 * Durable job queue with atomic claim/lease and retry policy (TEC-001, TEC-003, TEC-006).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Jobs;

use ReviewCaptain\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class Queue {

	public const LEASE_SECONDS = 300;

	/** Error class => retry behaviour (TEC-006). */
	private const RETRY_CLASSES = array(
		'temporary'  => array( 'retry' => true ),
		'rate_limit' => array( 'retry' => true ),
		'auth'       => array( 'retry' => false ),
		'permanent'  => array( 'retry' => false ),
		'data'       => array( 'retry' => false ),
	);

	private static function table(): string {
		return Schema::table( 'rc_jobs' );
	}

	private static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Enqueue a job. With a unique_key the job is idempotent: an existing active job with the same key is kept.
	 *
	 * @param array<string,mixed> $payload Job payload (references only, no personal data).
	 * @param array<string,mixed> $opts    unique_key, delay (seconds), priority, max_attempts.
	 * @return int Job ID, or 0 if a duplicate was ignored.
	 */
	public static function enqueue( string $type, array $payload = array(), array $opts = array() ): int {
		global $wpdb;
		$unique   = isset( $opts['unique_key'] ) ? hash( 'sha256', $type . '|' . $opts['unique_key'] ) : null;
		$now      = self::now();
		$data     = array(
			'type'         => substr( $type, 0, 64 ),
			'unique_key'   => $unique,
			'payload'      => wp_json_encode( $payload ),
			'status'       => 'queued',
			'priority'     => (int) ( $opts['priority'] ?? 10 ),
			'max_attempts' => (int) ( $opts['max_attempts'] ?? 6 ),
			'available_at' => gmdate( 'Y-m-d H:i:s', time() + (int) ( $opts['delay'] ?? 0 ) ),
			'created_at'   => $now,
			'updated_at'   => $now,
		);
		$suppress = $wpdb->suppress_errors( true );
		$ok       = $wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->suppress_errors( $suppress );
		if ( false === $ok ) {
			if ( null !== $unique ) {
				// Re-queue a finished job with the same key (e.g. next sync of the same profile).
				$table = self::table();
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"UPDATE {$table} SET status='queued', payload=%s, attempts=0, available_at=%s, last_error_code=NULL, last_error_message=NULL, updated_at=%s WHERE unique_key=%s AND status IN ('done','cancelled')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$data['payload'],
						$data['available_at'],
						$now,
						$unique
					)
				);
			}
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Recover jobs whose lease expired (worker crashed).
	 */
	public static function recover_expired(): int {
		global $wpdb;
		$table = self::table();
		$now   = self::now();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$dead = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='dead', claimed_by=NULL, last_error_code='lease_expired', updated_at=%s WHERE status='running' AND lease_until < %s AND attempts >= max_attempts", $now, $now ) );
		$back = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='queued', claimed_by=NULL, last_error_code='lease_expired', updated_at=%s WHERE status='running' AND lease_until < %s", $now, $now ) );
		// phpcs:enable
		return $dead + $back;
	}

	/**
	 * Atomically claim due jobs for this worker.
	 *
	 * @return array<int,array<string,mixed>> Claimed job rows.
	 */
	public static function claim( string $worker_token, int $limit = 10 ): array {
		global $wpdb;
		$table = self::table();
		$now   = self::now();
		$lease = gmdate( 'Y-m-d H:i:s', time() + self::LEASE_SECONDS );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status='running', claimed_by=%s, lease_until=%s, attempts=attempts+1, updated_at=%s
				WHERE status='queued' AND available_at <= %s ORDER BY priority DESC, available_at ASC, id ASC LIMIT %d",
				$worker_token,
				$lease,
				$now,
				$now,
				$limit
			)
		);
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE claimed_by=%s AND status='running' ORDER BY priority DESC, id ASC", $worker_token ), ARRAY_A );
		// phpcs:enable
		return $rows;
	}

	public static function complete( int $id, string $worker_token ): bool {
		global $wpdb;
		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status'      => 'done',
				'claimed_by'  => null,
				'lease_until' => null,
				'updated_at'  => self::now(),
			),
			array(
				'id'         => $id,
				'claimed_by' => $worker_token,
			)
		);
	}

	/**
	 * Mark a job as failed according to its error class.
	 *
	 * @param array<string,mixed> $job Claimed job row.
	 */
	public static function fail( array $job, string $worker_token, JobException $error ): string {
		global $wpdb;
		$class    = $error->error_class();
		$attempts = (int) $job['attempts'];
		$max      = (int) $job['max_attempts'];
		$status   = 'dead';
		$next     = null;

		if ( 'paused' === $class ) {
			$status = 'paused';
		} elseif ( ! empty( self::RETRY_CLASSES[ $class ]['retry'] ) ) {
			if ( 'rate_limit' === $class ) {
				$delay  = max( 60, (int) $error->retry_after() );
				$status = ( strtotime( $job['created_at'] . ' UTC' ) + DAY_IN_SECONDS > time() ) ? 'queued' : 'dead';
			} else {
				$delay  = self::backoff_seconds( $attempts );
				$status = $attempts < $max ? 'queued' : 'dead';
			}
			$next = gmdate( 'Y-m-d H:i:s', time() + $delay );
		}

		$data = array(
			'status'             => $status,
			'claimed_by'         => null,
			'lease_until'        => null,
			'last_error_code'    => substr( $error->code_name(), 0, 64 ),
			'last_error_message' => substr( sanitize_text_field( $error->safe_message() ), 0, 255 ),
			'updated_at'         => self::now(),
		);
		if ( 'dead' === $status && 'temporary' === $class ) {
			$data['last_error_code'] = 'retry_exhausted';
		}
		if ( $next ) {
			$data['available_at'] = $next;
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			$data,
			array(
				'id'         => (int) $job['id'],
				'claimed_by' => $worker_token,
			)
		);
		return $status;
	}

	/**
	 * Exponential backoff 1, 2, 4, 8, 16, 32 minutes with ±20 % jitter.
	 */
	public static function backoff_seconds( int $attempt ): int {
		$base   = 60 * ( 2 ** max( 0, min( $attempt - 1, 5 ) ) );
		$jitter = (int) round( $base * 0.2 );
		return $base + wp_rand( -$jitter, $jitter );
	}

	/**
	 * Manual retry of a dead/failed job: keeps unique_key, resets attempts.
	 */
	public static function retry( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status'       => 'queued',
				'attempts'     => 0,
				'available_at' => self::now(),
				'updated_at'   => self::now(),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Counts for diagnostics (TEC-002).
	 *
	 * @return array<string,mixed>
	 */
	public static function stats(): array {
		global $wpdb;
		$table = self::table();
		$now   = self::now();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$by_status = (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", OBJECT_K );
		$due       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status='queued' AND available_at <= %s", $now ) );
		$oldest    = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(available_at) FROM {$table} WHERE status='queued' AND available_at <= %s", $now ) );
		// phpcs:enable
		$counts = array();
		foreach ( $by_status as $status => $row ) {
			$counts[ $status ] = (int) $row->n;
		}
		$overdue_minutes = $oldest ? (int) floor( ( time() - strtotime( $oldest . ' UTC' ) ) / 60 ) : 0;
		return array(
			'counts'          => $counts,
			'due'             => $due,
			'oldest_due'      => $oldest,
			'overdue_minutes' => $overdue_minutes,
			'delayed'         => $overdue_minutes >= 15,
		);
	}

	/**
	 * Delete finished jobs after their retention (RC-027).
	 */
	public static function purge( int $done_days = 7, int $dead_days = 30, int $batch = 500 ): int {
		global $wpdb;
		$table = self::table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$n  = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status IN ('done','cancelled') AND updated_at < %s LIMIT %d", gmdate( 'Y-m-d H:i:s', time() - $done_days * DAY_IN_SECONDS ), $batch ) );
		$n += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status='dead' AND updated_at < %s LIMIT %d", gmdate( 'Y-m-d H:i:s', time() - $dead_days * DAY_IN_SECONDS ), $batch ) );
		// phpcs:enable
		return $n;
	}
}
