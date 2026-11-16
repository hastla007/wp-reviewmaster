<?php
/**
 * Background job queue with lease, idempotency and retry policy (TEC-003, TEC-004, TEC-006).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Jobs;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Support\ErrorCode;

/**
 * Public job API. All state lives in the `jobs` table; every status change out of
 * `running` is guarded by `lease_owner` so a worker that lost its lease cannot
 * overwrite a newer result.
 */
class JobQueue {

	public const STATUS_QUEUED    = 'queued';
	public const STATUS_RUNNING   = 'running';
	public const STATUS_SUCCEEDED = 'succeeded';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_DEAD      = 'dead';
	public const STATUS_PAUSED    = 'paused';

	public const STATUSES = array( 'queued', 'running', 'succeeded', 'failed', 'dead', 'paused' );

	/**
	 * Backoff base in seconds (transient errors): 60 s × 3^(n−1), ±20 % jitter (jobs.md §6.3).
	 */
	public const BACKOFF_BASE = 60;

	/**
	 * Backoff cap (6 h).
	 */
	public const BACKOFF_MAX = 21600;

	/**
	 * Default delay for rate limits without Retry-After (15 min).
	 */
	public const RATE_LIMIT_DEFAULT = 900;

	/**
	 * A queued job is overdue when available_at is older than this (two missed ticks).
	 */
	public const OVERDUE_SECONDS = 600;

	/**
	 * Max payload size (bytes JSON).
	 */
	public const MAX_PAYLOAD_BYTES = 65536;

	/**
	 * Protective limit of rate-limit deferrals before a job goes dead (jobs.md §6.2).
	 */
	public const MAX_RATE_LIMIT_DEFERRALS = 48;

	/**
	 * Payload key counting rate-limit deferrals.
	 */
	public const RL_DEFERRALS_KEY = '_rl_deferrals';

	/**
	 * Optional clock returning a Unix timestamp (tests).
	 *
	 * @var (callable(): int)|null
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param (callable(): int)|null $clock Clock override.
	 */
	public function __construct( ?callable $clock = null ) {
		$this->clock = $clock;
	}

	/**
	 * Table name.
	 */
	public function table(): string {
		return Schema::table( 'jobs' );
	}

	/**
	 * Current Unix time.
	 */
	public function time(): int {
		return null !== $this->clock ? (int) ( $this->clock )() : time();
	}

	/**
	 * Current UTC datetime (optionally shifted).
	 *
	 * @param int $offset Seconds.
	 */
	private function now( int $offset = 0 ): string {
		return gmdate( 'Y-m-d H:i:s', $this->time() + $offset );
	}

	/**
	 * Enqueue a job. A duplicate idempotency key returns the existing job ID and never creates a second row.
	 *
	 * @param string               $type            Job type, e.g. `retention.purge` or `{connector}.{name}`.
	 * @param array<string, mixed> $payload         JSON-serialisable payload (no secrets, e-mails, review texts).
	 * @param string               $idempotency_key Unique key.
	 * @param int                  $delay_seconds   Delay before first run.
	 * @param int                  $max_attempts    Max attempts.
	 * @return int|false Job ID or false on invalid input / DB error.
	 */
	public function enqueue( string $type, array $payload, string $idempotency_key, int $delay_seconds = 0, int $max_attempts = 8 ): int|false {
		global $wpdb;
		$type = trim( $type );
		if ( '' === $type || strlen( $type ) > 100 || ! preg_match( '/^[a-z0-9_.\-]+$/', $type ) ) {
			return false;
		}
		$key = self::normalize_key( $idempotency_key );
		if ( '' === $key ) {
			return false;
		}
		$json = wp_json_encode( (object) $payload );
		if ( false === $json || strlen( $json ) > self::MAX_PAYLOAD_BYTES ) {
			return false;
		}

		$existing = $this->id_by_key( $key );
		if ( null !== $existing ) {
			return $existing;
		}

		$now = $this->now();
		// A lost race on the unique key is expected under concurrency (TEC-005): keep it out of the PHP
		// error log; genuine DB errors are still logged below.
		$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			$this->table(),
			array(
				'type'            => $type,
				'idempotency_key' => $key,
				'payload'         => $json,
				'status'          => self::STATUS_QUEUED,
				'attempts'        => 0,
				'max_attempts'    => max( 1, min( 100, $max_attempts ) ),
				'available_at'    => $this->now( max( 0, $delay_seconds ) ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		if ( null !== $suppress ) {
			$wpdb->suppress_errors( $suppress );
		}
		if ( false === $ok || ! $wpdb->insert_id ) {
			// Lost an insert race on the unique key: return the winner. Under heavy contention the
			// winner's row can become visible a moment after our duplicate-key error (TEC-005, seen on
			// MariaDB with 8 parallel workers), so look it up a few times before giving up.
			$existing = $this->id_by_key( $key );
			for ( $i = 0; null === $existing && $i < 5; $i++ ) {
				usleep( 10000 * ( $i + 1 ) );
				$existing = $this->id_by_key( $key );
			}
			if ( null === $existing ) {
				self::log_db_error( 'job_enqueue_failed', (string) $wpdb->last_error );
			}
			return null !== $existing ? $existing : false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Log an unexpected database error without data values (only the first part of the message).
	 *
	 * @param string $code  Short code.
	 * @param string $error $wpdb->last_error.
	 */
	public static function log_db_error( string $code, string $error ): void {
		if ( '' === $error || false !== stripos( $error, 'duplicate' ) ) {
			return;
		}
		error_log( 'Review Captain: ' . $code . ': ' . substr( preg_replace( "/'[^']*'/", "'…'", $error ), 0, 200 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Normalise an idempotency key to ≤ 191 chars (long keys are hashed).
	 *
	 * @param string $key Key.
	 */
	public static function normalize_key( string $key ): string {
		$key = trim( $key );
		if ( strlen( $key ) > 191 ) {
			$key = substr( $key, 0, 140 ) . ':sha1:' . sha1( $key );
		}
		return $key;
	}

	/**
	 * Job ID by idempotency key.
	 *
	 * @param string $key Normalised key.
	 */
	private function id_by_key( string $key ): ?int {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE idempotency_key = %s", $key ) );
		return null === $id ? null : (int) $id;
	}

	/**
	 * Atomically claim due jobs (queued and available, or running with expired lease).
	 *
	 * @param string $worker_id     Lease owner (≤ 64 chars).
	 * @param int    $lease_seconds Lease length.
	 * @param int    $limit         Max jobs.
	 * @return list<array<string, mixed>> Claimed jobs (hydrated).
	 */
	public function claim( string $worker_id, int $lease_seconds, int $limit = 1 ): array {
		global $wpdb;
		$worker_id     = substr( $worker_id, 0, 64 );
		$lease_seconds = max( 1, $lease_seconds );
		$limit         = max( 1, min( 100, $limit ) );
		if ( '' === $worker_id ) {
			return array();
		}
		$table = $this->table();
		$now   = $this->now();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$candidates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE (status = 'queued' AND available_at <= %s)
				   OR (status = 'running' AND lease_expires_at < %s)
				ORDER BY available_at ASC, id ASC LIMIT %d",
				$now,
				$now,
				$limit * 3 + 5
			)
		);

		$claimed = array();
		foreach ( $candidates as $id ) {
			if ( count( $claimed ) >= $limit ) {
				break;
			}
			$affected = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					SET status = 'running', lease_owner = %s, lease_expires_at = %s, attempts = attempts + 1, updated_at = %s
					WHERE id = %d
					  AND ((status = 'queued' AND available_at <= %s) OR (status = 'running' AND lease_expires_at < %s))",
					$worker_id,
					$this->now( $lease_seconds ),
					$now,
					(int) $id,
					$now,
					$now
				)
			);
			if ( 1 !== (int) $affected ) {
				continue; // Another worker won.
			}
			$job = $this->find( (int) $id );
			if ( null === $job || $job['lease_owner'] !== $worker_id ) {
				continue;
			}
			if ( $job['attempts'] > $job['max_attempts'] ) {
				// Lease expired repeatedly: process presumably crashed.
				$this->transition(
					(int) $id,
					$worker_id,
					array(
						'status'             => self::STATUS_DEAD,
						'last_error_code'    => ErrorCode::TEMP_UNAVAILABLE,
						'last_error_message' => __( 'Lease abgelaufen, Prozessabbruch vermutet.', 'review-captain' ),
					)
				);
				continue;
			}
			$claimed[] = $job;
		}
		// phpcs:enable
		return $claimed;
	}

	/**
	 * Extend the lease of a running job.
	 *
	 * @param int    $id            Job ID.
	 * @param string $worker_id     Lease owner.
	 * @param int    $lease_seconds Seconds from now.
	 * @return bool False when the lease was lost (worker must stop without further writes).
	 */
	public function extend_lease( int $id, string $worker_id, int $lease_seconds ): bool {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$affected = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET lease_expires_at = %s, updated_at = %s WHERE id = %d AND lease_owner = %s AND status = 'running'", $this->now( max( 1, $lease_seconds ) ), $this->now(), $id, $worker_id ) );
		return 1 === (int) $affected;
	}

	/**
	 * Mark a running job as succeeded.
	 *
	 * @param int    $id        Job ID.
	 * @param string $worker_id Lease owner.
	 */
	public function complete( int $id, string $worker_id ): bool {
		return $this->transition(
			$id,
			$worker_id,
			array(
				'status'             => self::STATUS_SUCCEEDED,
				'last_error_code'    => null,
				'last_error_message' => null,
			)
		);
	}

	/**
	 * Checkpoint: re-queue a running job with a payload patch without consuming an attempt.
	 *
	 * @param int                  $id            Job ID.
	 * @param string               $worker_id     Lease owner.
	 * @param array<string, mixed> $payload_patch Keys merged into the payload.
	 * @param int                  $delay_seconds Delay.
	 */
	public function continue_later( int $id, string $worker_id, array $payload_patch = array(), int $delay_seconds = 0 ): bool {
		$job = $this->find( $id );
		if ( null === $job || self::STATUS_RUNNING !== $job['status'] || $job['lease_owner'] !== $worker_id ) {
			return false;
		}
		$payload = array_merge( $job['payload'], $payload_patch );
		$json    = wp_json_encode( (object) $payload );
		if ( false === $json || strlen( $json ) > self::MAX_PAYLOAD_BYTES ) {
			return false;
		}
		return $this->transition(
			$id,
			$worker_id,
			array(
				'status'       => self::STATUS_QUEUED,
				'attempts'     => max( 0, $job['attempts'] - 1 ),
				'payload'      => $json,
				'available_at' => $this->now( max( 0, $delay_seconds ) ),
			)
		);
	}

	/**
	 * Record a failure and apply the retry policy of the error class.
	 *
	 * - transient: queued with backoff 60 s × 3^(n−1) (max 6 h, ±20 % jitter); dead after max_attempts.
	 * - rate_limit: queued after retry_after (60 s … 24 h, default 15 min); does not consume an attempt;
	 *   dead after max(48, 3 × max_attempts) deferrals.
	 * - auth: paused (resume via resume_paused() after reconnect).
	 * - permanent / data: dead.
	 *
	 * @param int      $id          Job ID.
	 * @param string   $worker_id   Lease owner.
	 * @param string   $error_code  rc.* error code (unknown codes count as rc.temp.unavailable).
	 * @param string   $message     Admin message (sanitised, ≤ 500 chars, secrets removed).
	 * @param int|null $retry_after Seconds (rate limit).
	 * @return string New status; the unchanged current status if the lease was lost; '' if the job does not exist.
	 */
	public function fail( int $id, string $worker_id, string $error_code, string $message, ?int $retry_after = null ): string {
		$job = $this->find( $id );
		if ( null === $job ) {
			return '';
		}
		if ( self::STATUS_RUNNING !== $job['status'] || $job['lease_owner'] !== $worker_id ) {
			return (string) $job['status'];
		}
		if ( ! preg_match( '/^rc\.[a-z0-9_]+\.[a-z0-9_]+$/', $error_code ) ) {
			$error_code = ErrorCode::TEMP_UNAVAILABLE;
		}
		$fields = array(
			'last_error_code'    => substr( $error_code, 0, 64 ),
			'last_error_message' => self::sanitize_message( $message ),
		);

		switch ( ErrorCode::retry_class( $error_code ) ) {
			case ErrorCode::CLASS_RATE_LIMIT:
				$payload                           = $job['payload'];
				$deferrals                         = (int) ( $payload[ self::RL_DEFERRALS_KEY ] ?? 0 ) + 1;
				$payload[ self::RL_DEFERRALS_KEY ] = $deferrals;
				$fields['payload']                 = (string) wp_json_encode( (object) $payload );
				$fields['attempts']                = max( 0, $job['attempts'] - 1 );
				if ( $deferrals > max( self::MAX_RATE_LIMIT_DEFERRALS, 3 * $job['max_attempts'] ) ) {
					$fields['status'] = self::STATUS_DEAD;
					break;
				}
				$delay                  = null === $retry_after ? self::RATE_LIMIT_DEFAULT : max( 60, min( DAY_IN_SECONDS, $retry_after ) );
				$fields['status']       = self::STATUS_QUEUED;
				$fields['available_at'] = $this->now( $delay );
				break;
			case ErrorCode::CLASS_AUTH:
			case ErrorCode::CLASS_CONFIG:
				$fields['status'] = self::STATUS_PAUSED;
				break;
			case ErrorCode::CLASS_PERMANENT:
			case ErrorCode::CLASS_DATA:
				$fields['status'] = self::STATUS_DEAD;
				break;
			case ErrorCode::CLASS_TRANSIENT:
			default:
				if ( $job['attempts'] >= $job['max_attempts'] ) {
					$fields['status'] = self::STATUS_DEAD;
					break;
				}
				$fields['status']       = self::STATUS_QUEUED;
				$fields['available_at'] = $this->now( self::backoff( $job['attempts'], true ) );
				break;
		}

		if ( ! $this->transition( $id, $worker_id, $fields ) ) {
			$current = $this->find( $id );
			return null === $current ? '' : (string) $current['status'];
		}
		return (string) $fields['status'];
	}

	/**
	 * Transient backoff for the n-th attempt (n ≥ 1): min(6 h, 60 s × 3^(n−1)), optionally × U(0.8, 1.2).
	 *
	 * @param int  $attempts Attempts so far.
	 * @param bool $jitter   Apply ±20 % jitter.
	 */
	public static function backoff( int $attempts, bool $jitter = false ): int {
		$n     = min( 20, max( 1, $attempts ) );
		$delay = (int) min( self::BACKOFF_MAX, self::BACKOFF_BASE * ( 3 ** ( $n - 1 ) ) );
		if ( $jitter ) {
			$delay = (int) round( $delay * ( random_int( 800, 1200 ) / 1000 ) );
		}
		return max( 1, $delay );
	}

	/**
	 * Pause a running job (configuration problem, e.g. no handler / reconcile lock), keeping its attempt budget.
	 *
	 * @param int    $id         Job ID.
	 * @param string $worker_id  Lease owner.
	 * @param string $error_code rc.* code.
	 * @param string $message    Admin message (sanitised).
	 */
	public function pause( int $id, string $worker_id, string $error_code, string $message ): bool {
		$job = $this->find( $id );
		if ( null === $job || self::STATUS_RUNNING !== $job['status'] || $job['lease_owner'] !== $worker_id ) {
			return false;
		}
		return $this->transition(
			$id,
			$worker_id,
			array(
				'status'             => self::STATUS_PAUSED,
				'attempts'           => max( 0, $job['attempts'] - 1 ),
				'last_error_code'    => substr( $error_code, 0, 64 ),
				'last_error_message' => self::sanitize_message( $message ),
			)
		);
	}

	/**
	 * Resume paused jobs paused with a given error code (e.g. missing handler), regardless of type.
	 *
	 * @param string $error_code Code.
	 * @return int Resumed jobs.
	 */
	public function resume_paused_with_code( string $error_code ): int {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$affected = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'queued', available_at = %s, updated_at = %s WHERE status = 'paused' AND last_error_code = %s", $this->now(), $this->now(), $error_code ) );
		return max( 0, (int) $affected );
	}

	/**
	 * Status change out of `running`, guarded by lease owner. Always clears the lease.
	 *
	 * @param int                  $id        Job ID.
	 * @param string               $worker_id Lease owner.
	 * @param array<string, mixed> $fields    Columns to set.
	 */
	private function transition( int $id, string $worker_id, array $fields ): bool {
		global $wpdb;
		$fields['lease_owner']      = null;
		$fields['lease_expires_at'] = null;
		$fields['updated_at']       = $this->now();

		[ $set, $params ] = self::set_clause( $fields );
		$params[]         = $id;
		$params[]         = $worker_id;
		$table            = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$affected = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET {$set} WHERE id = %d AND lease_owner = %s AND status = 'running'", $params ) );
		return 1 === (int) $affected;
	}

	/**
	 * Build a SET clause with placeholders. NULL values are written literally.
	 *
	 * @param array<string, mixed> $fields Fields (keys are trusted column names).
	 * @return array{0: string, 1: list<mixed>}
	 */
	private static function set_clause( array $fields ): array {
		$parts  = array();
		$params = array();
		foreach ( $fields as $col => $value ) {
			if ( null === $value ) {
				$parts[] = "{$col} = NULL";
			} elseif ( is_int( $value ) ) {
				$parts[]  = "{$col} = %d";
				$params[] = $value;
			} else {
				$parts[]  = "{$col} = %s";
				$params[] = (string) $value;
			}
		}
		return array( implode( ', ', $parts ), $params );
	}

	/**
	 * Admin retry: dead/failed/paused → queued with attempts = 0; queued → due now.
	 *
	 * @param int $id Job ID.
	 */
	public function retry( int $id ): bool {
		global $wpdb;
		$job = $this->find( $id );
		if ( null === $job || ! in_array( $job['status'], array( self::STATUS_DEAD, self::STATUS_FAILED, self::STATUS_PAUSED, self::STATUS_QUEUED ), true ) ) {
			return false;
		}
		$payload = $job['payload'];
		unset( $payload[ self::RL_DEFERRALS_KEY ] );
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$affected = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'queued', attempts = 0, available_at = %s, payload = %s, lease_owner = NULL, lease_expires_at = NULL, updated_at = %s WHERE id = %d AND status = %s", $this->now(), (string) wp_json_encode( (object) $payload ), $this->now(), $id, $job['status'] ) );
		return 1 === (int) $affected;
	}

	/**
	 * Cancel/discard a job (queued, paused, running or dead → failed). A running worker loses its lease.
	 *
	 * @param int $id Job ID.
	 */
	public function cancel( int $id ): bool {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$affected = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'failed', lease_owner = NULL, lease_expires_at = NULL, last_error_code = %s, last_error_message = %s, updated_at = %s WHERE id = %d AND status IN ('queued','paused','running','dead')", ErrorCode::SOURCE_UNAVAILABLE, __( 'Abgebrochen.', 'review-captain' ), $this->now(), $id ) );
		return 1 === (int) $affected;
	}

	/**
	 * Resume paused jobs whose type starts with a prefix (e.g. after reconnect).
	 *
	 * @param string $type_prefix Type prefix ('' = all).
	 * @return int Number of resumed jobs.
	 */
	public function resume_paused( string $type_prefix ): int {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$affected = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'queued', attempts = 0, available_at = %s, updated_at = %s WHERE status = 'paused' AND type LIKE %s", $this->now(), $this->now(), $wpdb->esc_like( $type_prefix ) . '%' ) );
		return max( 0, (int) $affected );
	}

	/**
	 * Single job.
	 *
	 * @param int $id Job ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * Counters for diagnostics.
	 *
	 * @return array{queued:int,running:int,failed:int,dead:int,paused:int,succeeded:int,overdue:int,oldest_due_at:?string}
	 */
	public function stats(): array {
		global $wpdb;
		$table = $this->table();
		$out   = array_fill_keys( array( 'queued', 'running', 'failed', 'dead', 'paused', 'succeeded' ), 0 );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['status'] ] ) ) {
				$out[ $row['status'] ] = (int) $row['c'];
			}
		}
		$out['overdue']       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'queued' AND available_at < %s", $this->now( -self::OVERDUE_SECONDS ) ) );
		$oldest               = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(available_at) FROM {$table} WHERE status = 'queued' AND available_at <= %s", $this->now() ) );
		$out['oldest_due_at'] = null === $oldest ? null : (string) $oldest;
		// phpcs:enable
		return $out;
	}

	/**
	 * Recent jobs, newest first.
	 *
	 * @param int         $limit  Limit (1–200).
	 * @param string|null $status Status filter.
	 * @return list<array<string, mixed>>
	 */
	public function recent( int $limit = 20, ?string $status = null ): array {
		global $wpdb;
		$table = $this->table();
		$limit = max( 1, min( 200, $limit ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( null !== $status && '' !== $status ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at DESC, id DESC LIMIT %d", $status, $limit ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY updated_at DESC, id DESC LIMIT %d", $limit ), ARRAY_A );
		}
		// phpcs:enable
		return array_values( array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? $rows : array() ) );
	}

	/**
	 * Delete finished jobs (succeeded, failed, dead) last updated more than N days ago. Batched.
	 *
	 * @param int $days       Days.
	 * @param int $max_delete Upper bound per call.
	 * @return int Deleted rows.
	 */
	public function purge_finished( int $days, int $max_delete = 5000 ): int {
		global $wpdb;
		$table   = $this->table();
		$cutoff  = $this->now( -max( 0, $days ) * DAY_IN_SECONDS );
		$deleted = 0;
		while ( $deleted < $max_delete ) {
			$batch = min( 500, $max_delete - $deleted );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status IN ('succeeded','failed','dead') AND updated_at < %s ORDER BY id ASC LIMIT %d", $cutoff, $batch ) ) );
			if ( array() === $ids ) {
				break;
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$deleted += max( 0, (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) ) );
			if ( count( $ids ) < $batch ) {
				break;
			}
		}
		return $deleted;
	}

	/**
	 * Normalise a DB row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	public static function hydrate( array $row ): array {
		$payload = json_decode( (string) ( $row['payload'] ?? '' ), true );
		return array(
			'id'                 => (int) $row['id'],
			'type'               => (string) $row['type'],
			'idempotency_key'    => (string) $row['idempotency_key'],
			'payload'            => is_array( $payload ) ? $payload : array(),
			'status'             => (string) $row['status'],
			'attempts'           => (int) $row['attempts'],
			'max_attempts'       => (int) $row['max_attempts'],
			'available_at'       => (string) $row['available_at'],
			'lease_owner'        => null === $row['lease_owner'] ? null : (string) $row['lease_owner'],
			'lease_expires_at'   => null === $row['lease_expires_at'] ? null : (string) $row['lease_expires_at'],
			'last_error_code'    => null === $row['last_error_code'] ? null : (string) $row['last_error_code'],
			'last_error_message' => null === $row['last_error_message'] ? null : (string) $row['last_error_message'],
			'created_at'         => (string) $row['created_at'],
			'updated_at'         => (string) $row['updated_at'],
		);
	}

	/**
	 * Sanitise an error message for storage: no tags/control chars, secrets and e-mails removed, ≤ 500 chars.
	 *
	 * @param string $message Raw message.
	 */
	public static function sanitize_message( string $message ): string {
		$m = wp_strip_all_tags( $message, true );
		$m = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $m );

		$patterns = array(
			// Authorization headers / schemes.
			'/\b(Bearer|Basic|Token|Digest)\s+[A-Za-z0-9\-._~+\/=]+/i' => '$1 [redacted]',
			'/\b(authorization|proxy-authorization|x-api-key|apikey|api-key)\s*[:=]\s*("[^"]*"|\'[^\']*\'|\S+)/i' => '$1=[redacted]',
			// JWTs.
			'/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]*)?/' => '[redacted]',
			// key=value / key: value pairs with secret-looking names.
			'/(?<![\w-])([a-z0-9_\-]*(?:key|token|secret|password|passwd|pwd|credential|assertion|signature|code_verifier)|code|auth|sig)\s*([=:])\s*("[^"]*"|\'[^\']*\'|[^\s&"\',;]+)/i' => '$1$2[redacted]',
			// E-mail addresses.
			'/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/' => '[redacted-email]',
			// Long opaque strings (keys, tokens).
			'/(?<![A-Za-z0-9+\/_\-])[A-Za-z0-9+\/_\-]{40,}={0,2}/' => '[redacted]',
		);
		foreach ( $patterns as $pattern => $replacement ) {
			$m = (string) preg_replace( $pattern, $replacement, $m );
		}
		$m = trim( (string) preg_replace( '/\s+/u', ' ', $m ) );
		return mb_substr( $m, 0, 500 );
	}
}
