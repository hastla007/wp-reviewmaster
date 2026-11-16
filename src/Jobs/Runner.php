<?php
/**
 * Worker and triggers (TEC-001/002): Action Scheduler if available, otherwise WP-Cron; CLI for system cron.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Jobs;

use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Runner {

	public const HOOK       = 'review_captain_run_queue';
	public const LAST_RUN   = 'review_captain_last_worker_run';
	public const SCHEDULE   = 'rc_every_minute';
	private const MAX_BATCH = 10;

	public static function register(): void {
		add_filter( 'cron_schedules', array( self::class, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( self::HOOK, array( self::class, 'run_from_trigger' ) );
		add_action( 'init', array( self::class, 'ensure_scheduled' ), 20 );
	}

	/**
	 * @param array<string,array<string,mixed>> $schedules Cron schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public static function add_schedule( array $schedules ): array {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 60,
			'display'  => __( 'Every minute (Review Captain)', 'review-captain' ),
		);
		return $schedules;
	}

	public static function mode(): string {
		$mode = (string) Settings::get( 'job_runner' );
		if ( 'auto' === $mode ) {
			return function_exists( 'as_schedule_recurring_action' ) ? 'action_scheduler' : 'wp_cron';
		}
		return $mode;
	}

	public static function schedule(): void {
		if ( 'system_cron' === self::mode() ) {
			return;
		}
		if ( 'action_scheduler' === self::mode() ) {
			if ( ! as_has_scheduled_action( self::HOOK, array(), 'review-captain' ) ) {
				as_schedule_recurring_action( time() + 60, 60, self::HOOK, array(), 'review-captain' );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// The schedule filter may not be registered yet during activation.
			add_filter( 'cron_schedules', array( self::class, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
			wp_schedule_event( time() + 60, self::SCHEDULE, self::HOOK );
		}
	}

	public static function ensure_scheduled(): void {
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}
		self::schedule();
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), 'review-captain' );
		}
	}

	public static function run_from_trigger(): void {
		self::run( (int) Settings::get( 'job_time_budget_seconds' ) );
	}

	/**
	 * Process due jobs within a time budget.
	 *
	 * @return array{processed:int, failed:int, recovered:int}
	 */
	public static function run( int $budget_seconds ): array {
		$start  = microtime( true );
		$token  = bin2hex( random_bytes( 16 ) );
		$result = array(
			'processed' => 0,
			'failed'    => 0,
			'recovered' => Queue::recover_expired(),
		);
		update_option( self::LAST_RUN, time(), false );

		$handlers = self::handlers();
		while ( ( microtime( true ) - $start ) < $budget_seconds && ! self::memory_exceeded() ) {
			$jobs = Queue::claim( $token, self::MAX_BATCH );
			if ( ! $jobs ) {
				break;
			}
			foreach ( $jobs as $job ) {
				$payload = json_decode( (string) $job['payload'], true );
				$payload = is_array( $payload ) ? $payload : array();
				try {
					if ( ! isset( $handlers[ $job['type'] ] ) ) {
						throw new JobException( 'source_unavailable', 'No handler registered for job type.' );
					}
					call_user_func( $handlers[ $job['type'] ], $payload, $job );
					Queue::complete( (int) $job['id'], $token );
					++$result['processed'];
				} catch ( JobException $e ) {
					Queue::fail( $job, $token, $e );
					++$result['failed'];
				} catch ( \Throwable $e ) {
					Queue::fail( $job, $token, new JobException( 'provider_unavailable', get_class( $e ) ) );
					++$result['failed'];
				}
				if ( ( microtime( true ) - $start ) >= $budget_seconds ) {
					// Remaining claimed jobs return to the queue when their lease expires; release them now.
					self::release_unprocessed( $token );
					break 2;
				}
			}
		}
		return $result;
	}

	private static function release_unprocessed( string $token ): void {
		global $wpdb;
		$table = \ReviewCaptain\Database\Schema::table( 'rc_jobs' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='queued', claimed_by=NULL, lease_until=NULL, attempts=GREATEST(attempts-1,0) WHERE claimed_by=%s AND status='running'", $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Registered handlers: type => callable( array $payload, array $job ).
	 *
	 * @return array<string,callable>
	 */
	public static function handlers(): array {
		$handlers = array(
			'maintenance.retention' => array( \ReviewCaptain\Maintenance\Retention::class, 'run' ),
			'maintenance.reconcile' => array( \ReviewCaptain\Maintenance\Reconcile::class, 'run' ),
		);
		/**
		 * Register job handlers (TEC-001). Add-ons and connectors add their job types here.
		 *
		 * @param array<string,callable> $handlers Job type => callable.
		 */
		return (array) apply_filters( 'wp_review_captain_job_handlers_v1', $handlers );
	}

	private static function memory_exceeded(): bool {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		return $limit > 0 && memory_get_usage( true ) > 0.8 * $limit;
	}
}
