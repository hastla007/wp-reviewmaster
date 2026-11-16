<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\JobsModule;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Plugin;

/**
 * R-10 / TEC-015: after a backup restore the side-effect jobs are held and the reconciliation runs
 * first (TC-R10-a); a job that was due before the restore is not executed automatically
 * (TC-R10-b, core part – the request-specific hold is Pro).
 *
 * Fixed with this test: the runner heartbeat used to refresh `last_seen_at` on the first tick
 * after a restore, erasing the evidence before the daily reconciliation could see it, and a job
 * deferred by the lock lost its original due time.
 */
final class R10RestoreReconcileTest extends RaceTestCase {

	private int $sends = 0;

	protected function setUp(): void {
		parent::setUp();
		( new JobsModule() )->register( Plugin::instance() );
		add_filter(
			'review_captain_process_job_v1',
			function ( $result, $type ) {
				if ( 'acme.request.send' === $type ) {
					++$this->sends;
					return array( 'status' => 'succeeded' );
				}
				return $result;
			},
			10,
			2
		);
	}

	private function restore_state( int $days_ago ): void {
		update_option(
			Runner::OPTION_INSTANCE,
			array(
				'home_url_hash' => hash( 'sha256', (string) home_url() ),
				'last_seen_at'  => time() - $days_ago * DAY_IN_SECONDS,
			)
		);
	}

	private function backdate( int $id, int $seconds ): void {
		$this->race->update( Schema::table( 'jobs' ), array( 'available_at' => gmdate( 'Y-m-d H:i:s', time() - $seconds ) ), array( 'id' => $id ) );
	}

	public function test_restore_marker_locks_side_effects_and_reconciliation_runs_first(): void {
		$this->restore_state( 3 );
		/** @var JobQueue $q */
		$q    = Plugin::instance()->get( 'jobs' );
		$send = $q->enqueue( 'acme.request.send', array( 'request' => 'r-1' ), 'send:r-1' );
		$this->backdate( (int) $send, 2 * DAY_IN_SECONDS ); // Was due before the backup was taken.

		do_action( 'review_captain_run_jobs' ); // One tick: heartbeat + runner.

		$this->assertSame( 0, $this->sends, 'no side effect before/while reconciling' );
		$this->assertSame( 'paused', $q->find( (int) $send )['status'] );
		$this->assertSame( 'rc.source.unavailable', $q->find( (int) $send )['last_error_code'] );
		$reconcile = (int) $this->race->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'jobs' ) . " WHERE type = 'reconcile' AND status = 'succeeded'" );
		$this->assertSame( 1, $reconcile, 'reconciliation ran exactly once' );
		$this->assertFalse( get_option( Runner::OPTION_LOCK, false ), 'restore lock lifted after reconciliation' );
		$this->assertGreaterThan( time() - 60, get_option( Runner::OPTION_INSTANCE )['last_seen_at'] );

		do_action( 'review_captain_run_jobs' );
		$this->assertSame( 0, $this->sends, 'held job is not sent automatically (admin releases it)' );
	}

	public function test_new_jobs_run_normally_after_reconciliation(): void {
		$this->restore_state( 2 );
		do_action( 'review_captain_run_jobs' );
		/** @var JobQueue $q */
		$q   = Plugin::instance()->get( 'jobs' );
		$new = $q->enqueue( 'acme.request.send', array( 'request' => 'r-2' ), 'send:r-2' );
		do_action( 'review_captain_run_jobs' );
		$this->assertSame( 'succeeded', $q->find( (int) $new )['status'] );
		$this->assertSame( 1, $this->sends );
	}

	public function test_without_restore_evidence_heartbeat_just_refreshes(): void {
		$this->restore_state( 0 );
		Runner::heartbeat( 'cli' );
		$this->assertFalse( get_option( Runner::OPTION_LOCK, false ) );
		$this->assertSame( 0, $this->rows( 'jobs' ) );
	}

	public function test_clone_suspicion_keeps_lock_until_confirmed(): void {
		update_option( Runner::OPTION_INSTANCE, array( 'home_url_hash' => hash( 'sha256', 'https://prod.example' ), 'last_seen_at' => time() ) );
		/** @var JobQueue $q */
		$q    = Plugin::instance()->get( 'jobs' );
		$send = $q->enqueue( 'acme.request.send', array(), 'send:c' );
		do_action( 'review_captain_run_jobs' );
		do_action( 'review_captain_run_jobs' );
		$this->assertSame( 'clone', get_option( Runner::OPTION_LOCK ) );
		$this->assertSame( 0, $this->sends );
		$this->assertSame( 'queued', $q->find( (int) $send )['status'] );
		$this->assertSame( 1, (int) $this->race->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'jobs' ) . " WHERE type = 'reconcile'" ) );
	}
}
