<?php
/**
 * Jobs module: services, schedules and diagnostics (TEC-001, TEC-002).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Jobs;

use ReviewCaptain\Maintenance\Maintenance;
use ReviewCaptain\Maintenance\Reconciler;
use ReviewCaptain\Module;
use ReviewCaptain\Plugin;

/**
 * Registers the `jobs`, `jobs.runner`, `maintenance` and `reconciler` services, the 5-minute tick and the
 * daily maintenance. Scheduling is only (re)written on admin_init / after installation, never on frontend requests.
 */
final class JobsModule implements Module {

	public const HOOK_RUN   = 'review_captain_run_jobs';
	public const HOOK_DAILY = 'review_captain_daily_maintenance';
	public const SCHEDULE   = 'review_captain_5min';
	public const INTERVAL   = 300;
	public const AS_GROUP   = 'review-captain';

	/**
	 * Plugin.
	 */
	private ?Plugin $plugin = null;

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		$this->plugin = $plugin;

		$plugin->factory( 'jobs', static fn() => new JobQueue() );
		$plugin->factory(
			'jobs.runner',
			static function ( Plugin $p ): Runner {
				/** @var JobQueue $queue */
				$queue  = $p->get( 'jobs' );
				$runner = new Runner( $queue );
				$runner->register_handler( 'dev.noop', static fn() => array( 'status' => 'succeeded' ) );
				$runner->register_handler(
					'reconcile',
					static function ( array $payload ) use ( $p, $queue ): array {
						( new Reconciler( $p, $queue ) )->run( true, (string) ( $payload['mode'] ?? 'manual' ) );
						return array( 'status' => 'succeeded' );
					}
				);
				$runner->register_handler(
					'maintenance.daily',
					static function () use ( $p, $queue ): array {
						( new Maintenance( $p, $queue ) )->run();
						return array( 'status' => 'succeeded' );
					}
				);
				return $runner;
			}
		);
		$plugin->factory(
			'maintenance',
			static function ( Plugin $p ): Maintenance {
				/** @var JobQueue $queue */
				$queue = $p->get( 'jobs' );
				return new Maintenance( $p, $queue );
			}
		);
		$plugin->factory(
			'reconciler',
			static function ( Plugin $p ): Reconciler {
				/** @var JobQueue $queue */
				$queue = $p->get( 'jobs' );
				return new Reconciler( $p, $queue );
			}
		);

		add_filter( 'cron_schedules', array( self::class, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::HOOK_RUN, array( $this, 'run_tick' ) );
		add_action( self::HOOK_DAILY, array( $this, 'run_daily' ) );
		add_action( 'admin_init', array( $this, 'ensure_scheduled' ) );
		add_action( 'review_captain_installed', array( $this, 'on_installed' ), 10, 2 );
		add_filter( 'review_captain_diagnostics_sections', array( $this, 'diagnostics_section' ) );
	}

	/**
	 * Add the 5-minute interval.
	 *
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_schedule( $schedules ): array {
		$schedules                   = is_array( $schedules ) ? $schedules : array();
		$schedules[ self::SCHEDULE ] = array(
			'interval' => self::INTERVAL,
			'display'  => __( 'Every 5 minutes (Review Captain)', 'review-captain' ),
		);
		return $schedules;
	}

	/**
	 * Plugin instance.
	 */
	private function plugin(): Plugin {
		return $this->plugin ?? Plugin::instance();
	}

	/**
	 * Whether Action Scheduler should be the tick source.
	 */
	public function use_action_scheduler(): bool {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_has_scheduled_action' )
			&& (bool) $this->plugin()->settings()->get( 'advanced', 'use_action_scheduler', true );
	}

	/**
	 * Make sure the tick and daily events exist (admin_init / activation only). Writes only when missing.
	 */
	public function ensure_scheduled(): void {
		if ( $this->use_action_scheduler() ) {
			if ( ! as_has_scheduled_action( self::HOOK_RUN, array(), self::AS_GROUP ) ) {
				as_schedule_recurring_action( time(), self::INTERVAL, self::HOOK_RUN, array(), self::AS_GROUP, true );
			}
			if ( false !== wp_next_scheduled( self::HOOK_RUN ) ) {
				wp_clear_scheduled_hook( self::HOOK_RUN );
			}
		} else {
			if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_unschedule_all_actions' ) && as_has_scheduled_action( self::HOOK_RUN, array(), self::AS_GROUP ) ) {
				as_unschedule_all_actions( self::HOOK_RUN, array(), self::AS_GROUP );
			}
			if ( false === wp_next_scheduled( self::HOOK_RUN ) ) {
				wp_schedule_event( time(), self::SCHEDULE, self::HOOK_RUN );
			}
		}
		if ( false === wp_next_scheduled( self::HOOK_DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_DAILY );
		}
	}

	/**
	 * After install/upgrade: schedule and, on schema upgrades, queue one reconciliation (mode upgrade).
	 *
	 * @param int $from Previous schema version.
	 * @param int $to   New schema version.
	 */
	public function on_installed( $from = 0, $to = 0 ): void {
		$this->ensure_scheduled();
		if ( (int) $from > 0 && (int) $to > (int) $from ) {
			/** @var JobQueue $queue */
			$queue = $this->plugin()->get( 'jobs' );
			$queue->enqueue( 'reconcile', array( 'v' => 1, 'mode' => 'upgrade' ), 'reconcile:upgrade:' . (int) $to );
		}
	}

	/**
	 * Cron / Action Scheduler tick.
	 */
	public function run_tick(): void {
		if ( ! Runner::allowed_context() ) {
			return;
		}
		$channel = wp_doing_cron() ? 'wp_cron' : ( ( defined( 'WP_CLI' ) && WP_CLI ) ? 'cli' : 'action_scheduler' );
		if ( 'wp_cron' === $channel ) {
			ignore_user_abort( true );
		}
		Runner::heartbeat( $channel );
		/** @var Runner $runner */
		$runner = $this->plugin()->get( 'jobs.runner' );
		$runner->run( 0 );
	}

	/**
	 * Daily maintenance (cron).
	 */
	public function run_daily(): void {
		if ( ! Runner::allowed_context() ) {
			return;
		}
		/** @var Maintenance $maintenance */
		$maintenance = $this->plugin()->get( 'maintenance' );
		$maintenance->run( (float) $this->plugin()->settings()->get( 'advanced', 'job_time_budget', 20 ) );
	}

	/**
	 * Diagnostics section.
	 *
	 * @param array<string, mixed> $sections Sections.
	 * @return array<string, mixed>
	 */
	public function diagnostics_section( $sections ): array {
		$sections = is_array( $sections ) ? $sections : array();
		return $sections + array(
			'jobs' => array(
				'title'  => __( 'Background jobs', 'review-captain' ),
				'render' => function (): void {
					/** @var JobQueue $queue */
					$queue = $this->plugin()->get( 'jobs' );
					echo Diagnostics::html( $queue, $this->use_action_scheduler() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Diagnostics::html().
				},
			),
		);
	}
}
