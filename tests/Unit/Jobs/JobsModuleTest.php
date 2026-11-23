<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Jobs;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\JobsModule;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

final class JobsModuleTest extends TestCase {

	private function module(): JobsModule {
		$m = new JobsModule();
		$m->register( Plugin::instance() );
		// Force WP-Cron path regardless of Action Scheduler presence.
		update_option( 'review_captain_settings', array( 'advanced' => array( 'use_action_scheduler' => false ) ) );
		Plugin::instance()->settings()->flush();
		return $m;
	}

	public function test_registers_services_and_schedule_without_writing_on_register(): void {
		$this->module();
		$this->assertInstanceOf( JobQueue::class, Plugin::instance()->get( 'jobs' ) );
		$this->assertInstanceOf( Runner::class, Plugin::instance()->get( 'jobs.runner' ) );
		$this->assertSame( array(), \RC_Test_State::$scheduled );
		$schedules = apply_filters( 'cron_schedules', array() );
		$this->assertSame( 300, $schedules['review_captain_5min']['interval'] );
	}

	public function test_admin_init_schedules_events_once(): void {
		$this->module();
		do_action( 'admin_init' );
		$this->assertSame( 'review_captain_5min', \RC_Test_State::$scheduled['review_captain_run_jobs'][2] );
		$this->assertSame( 'daily', \RC_Test_State::$scheduled['review_captain_daily_maintenance'][2] );
		$ts = \RC_Test_State::$scheduled['review_captain_run_jobs'][0];
		\RC_Test_State::$scheduled['review_captain_run_jobs'][0] = $ts - 1000;
		do_action( 'admin_init' );
		$this->assertSame( $ts - 1000, \RC_Test_State::$scheduled['review_captain_run_jobs'][0] );
	}

	public function test_upgrade_enqueues_reconcile_once(): void {
		$this->module();
		do_action( 'review_captain_installed', 1, 2 );
		do_action( 'review_captain_installed', 1, 2 );
		do_action( 'review_captain_installed', 0, 1 );
		$this->assertSame( 1, $this->rows( 'jobs' ) );
	}

	public function test_tick_runs_jobs_and_writes_heartbeat(): void {
		$this->module();
		update_option( Schema::OPTION_VERSION, Schema::VERSION );
		/** @var JobQueue $q */
		$q  = Plugin::instance()->get( 'jobs' );
		$id = $q->enqueue( 'dev.noop', array(), 'dev:1' );
		do_action( 'review_captain_run_jobs' );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
		$this->assertNotEmpty( get_option( Runner::OPTION_HEARTBEAT ) );
	}

	public function test_diagnostics_section_renders_escaped_html(): void {
		$this->module();
		/** @var JobQueue $q */
		$q = Plugin::instance()->get( 'jobs' );
		$q->enqueue( 'x.y', array(), 'k' );
		$sections = apply_filters( 'review_captain_diagnostics_sections', array( 'other' => array( 'title' => 'O', 'render' => 'strlen' ) ) );
		$this->assertArrayHasKey( 'other', $sections );
		$this->assertArrayHasKey( 'jobs', $sections );
		ob_start();
		( $sections['jobs']['render'] )();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Pending', $html );
		$this->assertStringContainsString( 'system cron', $html );
		$this->assertStringContainsString( 'at the earliest', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}
}
