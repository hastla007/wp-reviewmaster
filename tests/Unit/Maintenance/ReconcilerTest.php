<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Maintenance;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Maintenance\Reconciler;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

final class ReconcilerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		update_option( Schema::OPTION_VERSION, Schema::VERSION );
	}

	private function reconciler( JobQueue $q ): Reconciler {
		return new Reconciler( Plugin::instance(), $q );
	}

	public function test_reports_schema_mismatch_and_missing_tables(): void {
		update_option( Schema::OPTION_VERSION, 0 );
		$this->db->query( 'DROP TABLE ' . Schema::table( 'import_batches' ) );
		$report = $this->reconciler( new JobQueue() )->run( true );
		$this->assertFalse( $report['schema_ok'] );
		$this->assertSame( array( 'import_batches' ), $report['missing_tables'] );
		$this->assertSame( array(), $report['fixed'] );
	}

	public function test_dry_run_reports_and_fix_resets_leases(): void {
		$q       = new JobQueue();
		$expired = $q->enqueue( 'test.a', array(), 'a' );
		$live    = $q->enqueue( 'test.b', array(), 'b' );
		$orphan  = $q->enqueue( 'test.c', array(), 'c' );
		$q->claim( 'w', 300, 3 );
		$jobs = Schema::table( 'jobs' );
		$this->db->update( $jobs, array( 'lease_expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ), array( 'id' => $expired ) );
		$this->db->query( "UPDATE {$jobs} SET lease_owner = NULL, lease_expires_at = NULL WHERE id = {$orphan}" );

		$reviews = Schema::table( 'reviews' );
		$this->db->insert( $reviews, array( 'uuid' => wp_generate_uuid4(), 'platform' => 'trustpilot', 'external_id' => 'x1', 'body' => 'b', 'status' => 'approved', 'refresh_due_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 ), 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) );

		$dry = $this->reconciler( $q )->run( false );
		$this->assertTrue( $dry['schema_ok'] );
		$this->assertSame( 1, $dry['expired_leases'] );
		$this->assertSame( 1, $dry['running_without_lease'] );
		$this->assertSame( 1, $dry['refresh_overdue'] );
		$this->assertSame( 1, $dry['missing_dedupe_keys'] );
		$this->assertSame( 'running', $q->find( $expired )['status'] );

		$fix = $this->reconciler( $q )->run( true );
		$this->assertSame( 2, $fix['fixed']['leases_reset'] );
		$this->assertSame( 'queued', $q->find( $expired )['status'] );
		$this->assertSame( 'queued', $q->find( $orphan )['status'] );
		$this->assertSame( 'running', $q->find( $live )['status'] );
		$this->assertSame( 1, $q->find( $expired )['attempts'] );
		$this->assertNotEmpty( get_option( Runner::OPTION_INSTANCE )['home_url_hash'] );
	}

	public function test_restore_mode_pauses_overdue_side_effect_jobs_and_lifts_lock(): void {
		update_option( Runner::OPTION_LOCK, 'restore' );
		$q     = new JobQueue();
		$send  = $q->enqueue( 'pro.request.send', array(), 'send:1' );
		$purge = $q->enqueue( 'retention.purge', array(), 'retention.purge' );
		$jobs  = Schema::table( 'jobs' );
		$this->db->query( "UPDATE {$jobs} SET available_at = '" . gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ) . "'" );

		$report = $this->reconciler( $q )->run( true, 'restore' );
		$this->assertSame( 1, $report['side_effect_jobs_overdue'] );
		$this->assertSame( 1, $report['fixed']['side_effect_jobs_paused'] );
		$this->assertSame( 'paused', $q->find( $send )['status'] );
		$this->assertSame( 'queued', $q->find( $purge )['status'] );
		$this->assertFalse( get_option( Runner::OPTION_LOCK, false ) );
	}

	public function test_clone_detection_sets_lock_until_confirmed(): void {
		update_option( Runner::OPTION_INSTANCE, array( 'home_url_hash' => hash( 'sha256', 'https://production.example' ), 'last_seen_at' => time() ) );
		$q    = new JobQueue();
		$send = $q->enqueue( 'pro.request.send', array(), 'send:1' );
		$report = $this->reconciler( $q )->run( true );
		$this->assertTrue( $report['clone_suspected'] );
		$this->assertSame( 'clone', $report['mode'] );
		$this->assertSame( 'clone', get_option( Runner::OPTION_LOCK ) );

		$this->assertSame( 1, $this->reconciler( $q )->confirm_instance( true ) );
		$this->assertSame( 'failed', $q->find( $send )['status'] );
		$this->assertFalse( get_option( Runner::OPTION_LOCK, false ) );
		$this->assertFalse( $this->reconciler( $q )->run( false )['clone_suspected'] );
	}
}
