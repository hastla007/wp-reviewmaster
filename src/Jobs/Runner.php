<?php
/**
 * Job runner tick (TEC-001, TEC-003, jobs.md §3).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Jobs;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\ErrorCode;

/**
 * Claims due jobs until the time budget is used up and dispatches them to handlers.
 * Runs only in cron/CLI context, never in frontend requests.
 */
class Runner {

	public const OPTION_HEARTBEAT = 'review_captain_runner_heartbeat';
	public const OPTION_INSTANCE  = 'review_captain_instance';
	public const OPTION_LOCK      = 'review_captain_reconcile_required';

	/**
	 * Payload key holding the original due time of a job deferred by the reconcile lock.
	 */
	public const LOCK_DUE_KEY = '_rc_due_at';

	/**
	 * Job type prefixes without external side effects (allowed while the reconcile lock is active).
	 */
	public const SIDE_EFFECT_FREE_PREFIXES = array( 'retention.', 'maintenance.', 'reconcile', 'import.', 'dev.' );

	/**
	 * Default max jobs per tick.
	 */
	public const MAX_JOBS = 50;

	/**
	 * Default global concurrency (running jobs with valid lease per site).
	 */
	public const CONCURRENCY = 2;

	/**
	 * Built-in handlers.
	 *
	 * @var array<string, callable>
	 */
	private array $handlers = array();

	/**
	 * Monotonic-ish clock in seconds (float), overridable for tests.
	 *
	 * @var (callable(): float)|null
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param JobQueue                 $queue Queue.
	 * @param (callable(): float)|null $clock Clock override (seconds).
	 */
	public function __construct( private JobQueue $queue, ?callable $clock = null ) {
		$this->clock = $clock;
	}

	/**
	 * Register a built-in handler: fn( array $payload, array $job ): mixed (see interpret_result()).
	 *
	 * @param string   $type    Job type.
	 * @param callable $handler Handler.
	 */
	public function register_handler( string $type, callable $handler ): void {
		$this->handlers[ $type ] = $handler;
	}

	/**
	 * Whether a built-in handler exists.
	 *
	 * @param string $type Type.
	 */
	public function has_handler( string $type ): bool {
		return isset( $this->handlers[ $type ] );
	}

	/**
	 * Current time in seconds.
	 */
	private function clock(): float {
		return null !== $this->clock ? (float) ( $this->clock )() : microtime( true );
	}

	/**
	 * Whether jobs may run in this request (WP-Cron, Action Scheduler queue runner or CLI only).
	 */
	public static function allowed_context(): bool {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || 'cli' === PHP_SAPI ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		return (bool) did_action( 'action_scheduler_before_process_queue' );
	}

	/**
	 * New lease owner id: {8 hex host hash}-{pid}-{16 hex random}.
	 */
	public static function new_worker_id(): string {
		$host = substr( hash( 'sha256', (string) php_uname( 'n' ) ), 0, 8 );
		return substr( $host . '-' . (int) getmypid() . '-' . bin2hex( random_bytes( 8 ) ), 0, 64 );
	}

	/**
	 * Record a runner heartbeat for a channel (wp_cron, action_scheduler, cli).
	 *
	 * @param string $channel Channel.
	 */
	public static function heartbeat( string $channel ): void {
		$beats = get_option( self::OPTION_HEARTBEAT, array() );
		$beats = is_array( $beats ) ? $beats : array();

		$beats[ sanitize_key( $channel ) ] = time();
		update_option( self::OPTION_HEARTBEAT, $beats, false );

		$instance = get_option( self::OPTION_INSTANCE, array() );
		$instance = is_array( $instance ) ? $instance : array();
		$suspect  = self::instance_suspicion( $instance );
		if ( '' !== $suspect ) {
			// Restore/clone suspected (TEC-005 R-10, reconciliation.md): lock side effects at once and
			// queue the reconciliation. last_seen_at is NOT refreshed here – it is the evidence the
			// reconciler needs; a successful run refreshes it.
			if ( ! get_option( self::OPTION_LOCK, false ) ) {
				update_option( self::OPTION_LOCK, $suspect, false );
			}
			$evidence = 'clone' === $suspect ? substr( (string) ( $instance['home_url_hash'] ?? '' ), 0, 16 ) : (string) (int) ( $instance['last_seen_at'] ?? 0 );
			( new JobQueue() )->enqueue( 'reconcile', array( 'v' => 1, 'mode' => $suspect ), 'reconcile:' . $suspect . ':' . $evidence );
			return;
		}
		if ( (int) ( $instance['last_seen_at'] ?? 0 ) < time() - 300 ) {
			$instance['last_seen_at'] = time();
			update_option( self::OPTION_INSTANCE, $instance, false );
		}
	}

	/**
	 * 'clone' when the stored home URL hash differs, 'restore' when the last runner heartbeat is
	 * older than 24 h (database restored from a backup or long outage), otherwise ''.
	 *
	 * @param array<string, mixed> $instance Stored instance fingerprint.
	 */
	public static function instance_suspicion( array $instance ): string {
		if ( isset( $instance['home_url_hash'] ) && ! hash_equals( (string) $instance['home_url_hash'], hash( 'sha256', (string) home_url() ) ) ) {
			return 'clone';
		}
		$last = (int) ( $instance['last_seen_at'] ?? 0 );
		return $last > 0 && $last < time() - DAY_IN_SECONDS ? 'restore' : '';
	}

	/**
	 * Run one tick.
	 *
	 * @param float       $time_budget_seconds Budget; ≤ 0 uses setting advanced.job_time_budget.
	 * @param string|null $worker_id           Lease owner (generated if null).
	 * @return array{processed:int, succeeded:int, failed:int}
	 */
	public function run( float $time_budget_seconds, ?string $worker_id = null ): array {
		$result = array(
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
		);
		if ( ! self::allowed_context() ) {
			return $result;
		}
		if ( (int) get_option( Schema::OPTION_VERSION, 0 ) < Schema::VERSION ) {
			return $result; // Migration pending.
		}

		$settings = Plugin::instance()->settings();
		if ( $time_budget_seconds <= 0 ) {
			$time_budget_seconds = (float) $settings->get( 'advanced', 'job_time_budget', 20 );
		}
		$max_exec = (int) ini_get( 'max_execution_time' );
		if ( $max_exec > 0 ) {
			$time_budget_seconds = min( $time_budget_seconds, max( 1, $max_exec - 5 ) );
		}
		$lease     = (int) $settings->get( 'advanced', 'lease_seconds', 300 );
		$worker_id = null === $worker_id || '' === $worker_id ? self::new_worker_id() : $worker_id;
		$max_jobs  = (int) apply_filters( 'review_captain_runner_max_jobs', self::MAX_JOBS );
		$max_conc  = (int) apply_filters( 'review_captain_runner_concurrency', self::CONCURRENCY );
		$locked    = (bool) get_option( self::OPTION_LOCK, false );
		$deadline  = $this->clock() + $time_budget_seconds;
		$mem_limit = self::memory_limit_bytes();

		$this->maybe_resume_unhandled();

		while ( $this->clock() < $deadline && $result['processed'] < $max_jobs ) {
			if ( $mem_limit > 0 && memory_get_usage( true ) > 0.8 * $mem_limit ) {
				break;
			}
			if ( $max_conc > 0 && $this->running_count() >= $max_conc ) {
				break;
			}
			$jobs = $this->queue->claim( $worker_id, $lease, 1 );
			if ( array() === $jobs ) {
				break;
			}
			$job = $jobs[0];
			if ( $locked && ! self::is_side_effect_free( $job['type'] ) ) {
				// Reconcile lock: release without consuming an attempt, retry in 5 minutes. The original due
				// time is kept in the payload so the reconciler still sees the job as overdue.
				$this->queue->continue_later( $job['id'], $worker_id, isset( $job['payload'][ self::LOCK_DUE_KEY ] ) ? array() : array( self::LOCK_DUE_KEY => $job['available_at'] ), 300 );
				continue;
			}
			++$result['processed'];
			if ( $this->process( $job, $worker_id ) ) {
				++$result['succeeded'];
			} else {
				++$result['failed'];
			}
		}
		return $result;
	}

	/**
	 * Whether a type has no external side effects.
	 *
	 * @param string $type Type.
	 */
	public static function is_side_effect_free( string $type ): bool {
		$prefixes = (array) apply_filters( 'review_captain_side_effect_free_job_prefixes', self::SIDE_EFFECT_FREE_PREFIXES );
		foreach ( $prefixes as $prefix ) {
			if ( '' !== $prefix && str_starts_with( $type, (string) $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Running jobs with a valid lease.
	 */
	private function running_count(): int {
		global $wpdb;
		$table = $this->queue->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'running' AND lease_expires_at >= %s", gmdate( 'Y-m-d H:i:s', $this->queue->time() ) ) );
	}

	/**
	 * Jobs paused for lack of a handler are re-queued at most once per hour (handler may be back).
	 */
	private function maybe_resume_unhandled(): void {
		if ( false !== get_transient( 'review_captain_resume_unhandled' ) ) {
			return;
		}
		set_transient( 'review_captain_resume_unhandled', 1, HOUR_IN_SECONDS );
		$this->queue->resume_paused_with_code( ErrorCode::CONTRACT_INCOMPATIBLE );
		$this->queue->resume_paused_with_code( ErrorCode::SOURCE_UNAVAILABLE );
	}

	/**
	 * For job types named {connector_key}.{name} that belong to a known connector, return a reason
	 * string when that connector is not executable (inactive, paused, removed); null otherwise.
	 *
	 * @param string $type Job type.
	 */
	private function connector_gate( string $type ): ?string {
		if ( ! preg_match( '/^([a-z0-9][a-z0-9_-]{2,63})\./', $type, $m ) ) {
			return null;
		}
		$plugin = \ReviewCaptain\Plugin::instance();
		if ( ! $plugin->has( 'connectors' ) ) {
			return null;
		}
		$registry = $plugin->get( 'connectors' );
		if ( ! $registry instanceof \ReviewCaptain\Connectors\Registry ) {
			return null;
		}
		$key   = $m[1];
		$known = get_option( \ReviewCaptain\Connectors\Registry::OPTION_KNOWN, array() );
		if ( ! $registry->has( $key ) && ! ( is_array( $known ) && isset( $known[ $key ] ) ) ) {
			return null; // Not a connector job type (e.g. Pro).
		}
		if ( $registry->is_executable( $key ) ) {
			return null;
		}
		return sprintf(
			/* translators: 1: connector key, 2: connector state */
			__( 'Connector “%1$s” is not active (%2$s); job is waiting.', 'review-captain' ),
			$key,
			$registry->status( $key )['state']
		);
	}

	/**
	 * Process one claimed job. Returns true on success.
	 *
	 * @param array<string, mixed> $job       Hydrated job.
	 * @param string               $worker_id Lease owner.
	 */
	public function process( array $job, string $worker_id ): bool {
		$type    = (string) $job['type'];
		$payload = (array) $job['payload'];
		try {
			if ( isset( $this->handlers[ $type ] ) ) {
				$outcome = ( $this->handlers[ $type ] )( $payload, $job );
			} else {
				$blocked = $this->connector_gate( $type );
				if ( null !== $blocked ) {
					// CON-014 / CT-J-09: connector job types only run while their connector is active.
					$this->queue->pause( (int) $job['id'], $worker_id, ErrorCode::SOURCE_UNAVAILABLE, $blocked );
					return false;
				}
				/**
				 * Handle a job type not built into the core (connectors, Pro).
				 *
				 * @param array|null $result  null = not handled.
				 * @param string     $type    Job type.
				 * @param array      $payload Payload.
				 */
				$outcome = apply_filters( 'review_captain_process_job_v1', null, $type, $payload );
			}
		} catch ( \Throwable $e ) {
			$code = ErrorCode::TEMP_UNAVAILABLE;
			if ( $e instanceof \ReviewCaptain\Connectors\ConnectorException || method_exists( $e, 'error_code' ) ) {
				$candidate = (string) $e->error_code();
				$code      = ErrorCode::is_known( $candidate ) ? $candidate : ErrorCode::TEMP_UNAVAILABLE;
			}
			$retry_after = method_exists( $e, 'retry_after' ) ? $e->retry_after() : null;
			$this->queue->fail( (int) $job['id'], $worker_id, $code, $e->getMessage(), is_numeric( $retry_after ) ? (int) $retry_after : null );
			self::debug_log( $job, $code, get_class( $e ) );
			return false;
		}

		if ( null === $outcome ) {
			// No handler (e.g. connector deactivated): pause, re-queued hourly (jobs.md §3.1).
			$this->queue->pause(
				(int) $job['id'],
				$worker_id,
				ErrorCode::CONTRACT_INCOMPATIBLE,
				sprintf(
					/* translators: %s: job type */
					__( 'No handler registered for job type “%s”.', 'review-captain' ),
					$type
				)
			);
			return false;
		}
		return $this->apply_outcome( $job, $worker_id, $outcome );
	}

	/**
	 * Interpret a handler result.
	 *
	 * Accepted: true | ['status' => 'succeeded'] → complete;
	 * ['status' => 'continue', 'payload' => array, 'delay' => int] → checkpoint;
	 * ['status' => 'failed'|'retry', 'error_code' => rc.*, 'message' => string, 'retry_after' => int] → fail;
	 * WP_Error with rc.* code → fail. Anything else → fail rc.contract.invalid.
	 *
	 * @param array<string, mixed> $job       Job.
	 * @param string               $worker_id Lease owner.
	 * @param mixed                $outcome   Handler result.
	 */
	private function apply_outcome( array $job, string $worker_id, mixed $outcome ): bool {
		$id = (int) $job['id'];
		if ( true === $outcome || ( is_array( $outcome ) && 'succeeded' === ( $outcome['status'] ?? null ) ) ) {
			return $this->queue->complete( $id, $worker_id );
		}
		if ( is_array( $outcome ) && 'continue' === ( $outcome['status'] ?? null ) ) {
			$this->queue->continue_later( $id, $worker_id, (array) ( $outcome['payload'] ?? array() ), (int) ( $outcome['delay'] ?? 0 ) );
			return true;
		}
		if ( is_array( $outcome ) && in_array( $outcome['status'] ?? null, array( 'failed', 'retry' ), true ) ) {
			$code = (string) ( $outcome['error_code'] ?? ErrorCode::TEMP_UNAVAILABLE );
			$code = ErrorCode::is_known( $code ) ? $code : ErrorCode::TEMP_UNAVAILABLE;
			$ra   = $outcome['retry_after'] ?? null;
			$this->queue->fail( $id, $worker_id, $code, (string) ( $outcome['message'] ?? '' ), is_numeric( $ra ) ? (int) $ra : null );
			self::debug_log( $job, $code, 'result' );
			return false;
		}
		if ( is_object( $outcome ) && is_a( $outcome, 'WP_Error' ) ) {
			$code = (string) $outcome->get_error_code();
			$code = ErrorCode::is_known( $code ) ? $code : ErrorCode::TEMP_UNAVAILABLE;
			$this->queue->fail( $id, $worker_id, $code, (string) $outcome->get_error_message() );
			self::debug_log( $job, $code, 'WP_Error' );
			return false;
		}
		$this->queue->fail( $id, $worker_id, ErrorCode::CONTRACT_INVALID, __( 'Invalid handler result.', 'review-captain' ) );
		self::debug_log( $job, ErrorCode::CONTRACT_INVALID, 'result' );
		return false;
	}

	/**
	 * Diagnostic line in the PHP error log for a failed job, only with `advanced.debug_logging`
	 * (SET-RC-05). Contains job ID, type, attempt, error code and the kind of failure – never the
	 * error message or payload, which may carry personal data or secrets.
	 *
	 * @param array<string, mixed> $job    Job.
	 * @param string               $code   Error code (rc.*).
	 * @param string               $source Exception class, 'WP_Error' or 'result'.
	 */
	private static function debug_log( array $job, string $code, string $source ): void {
		if ( true !== \ReviewCaptain\Plugin::instance()->settings()->get( 'advanced', 'debug_logging', false ) ) {
			return;
		}
		$line = sprintf(
			'Review Captain [debug]: job %d (%s, attempt %d) failed: %s (%s)',
			(int) ( $job['id'] ?? 0 ),
			preg_replace( '/[^a-z0-9_.:-]/i', '', (string) ( $job['type'] ?? '' ) ),
			(int) ( $job['attempts'] ?? 0 ),
			preg_replace( '/[^a-z0-9_.:-]/i', '', $code ),
			preg_replace( '/[^a-z0-9_\\\\]/i', '', $source )
		);
		/**
		 * Diagnostic log line (tests, custom loggers). Return '' to suppress.
		 *
		 * @param string $line Line without personal data.
		 */
		$line = (string) apply_filters( 'review_captain_debug_log_line', $line );
		if ( '' !== $line ) {
			error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * PHP memory limit in bytes (0 = unlimited).
	 */
	private static function memory_limit_bytes(): int {
		$raw = trim( (string) ini_get( 'memory_limit' ) );
		if ( '' === $raw || '-1' === $raw ) {
			return 0;
		}
		$unit  = strtolower( substr( $raw, -1 ) );
		$value = (int) $raw;
		return match ( $unit ) {
			'g'     => $value * 1024 * 1024 * 1024,
			'm'     => $value * 1024 * 1024,
			'k'     => $value * 1024,
			default => $value,
		};
	}
}
