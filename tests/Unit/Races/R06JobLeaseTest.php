<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\Runner;

/**
 * R-06: double claim and lease expiry while a job runs (TC-R06-b). The send-specific part
 * (TC-R06-a, S3 send_key) is Pro: captain-pro tests/Addons/ReviewRequestSendTest.php.
 */
final class R06JobLeaseTest extends RaceTestCase {

	private int $now = 1_800_000_000;

	private function queue(): JobQueue {
		return new JobQueue( fn(): int => $this->now );
	}

	public function test_two_workers_claiming_the_same_job_only_one_wins(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'acme.sync', array(), 'job-1' );
		$b  = array();
		// Worker B claims between A's candidate SELECT and A's UPDATE.
		$this->race->before(
			"/^UPDATE wp_review_captain_jobs\s+SET status = 'running'/",
			static function () use ( $q, &$b ): void {
				$b = $q->claim( 'worker-b', 300, 1 );
			}
		);
		$a = $q->claim( 'worker-a', 300, 1 );
		$this->assertSame( array(), $a );
		$this->assertCount( 1, $b );
		$this->assertSame( 'worker-b', $q->find( $id )['lease_owner'] );
		$this->assertSame( 1, $q->find( $id )['attempts'] );
	}

	public function test_expired_lease_is_reclaimed_and_the_first_worker_can_no_longer_write(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'acme.sync', array(), 'job-2' );
		$this->assertCount( 1, $q->claim( 'worker-a', 60, 1 ) );
		$this->assertSame( array(), $q->claim( 'worker-b', 60, 1 ), 'lease still valid' );

		$this->now += 61; // A is slow; its lease expires.
		$this->assertCount( 1, $q->claim( 'worker-b', 60, 1 ) );

		$this->assertFalse( $q->extend_lease( $id, 'worker-a', 60 ) );
		$this->assertFalse( $q->complete( $id, 'worker-a' ), 'rows_affected = 0 for the stale worker' );
		$this->assertSame( 'running', $q->fail( $id, 'worker-a', 'rc.net.timeout', 'x' ) );
		$this->assertFalse( $q->continue_later( $id, 'worker-a', array( 'cursor' => 'stale' ) ) );
		$this->assertSame( 'worker-b', $q->find( $id )['lease_owner'] );
		$this->assertArrayNotHasKey( 'cursor', $q->find( $id )['payload'] );

		$this->assertTrue( $q->complete( $id, 'worker-b' ) );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
		$this->assertSame( 2, $q->find( $id )['attempts'] );
	}

	public function test_handler_running_past_its_lease_does_not_overwrite_the_new_owner(): void {
		$q      = $this->queue();
		$id     = $q->enqueue( 'acme.slow', array(), 'job-3' );
		$runner = new Runner( $q );
		$calls  = 0;
		$runner->register_handler(
			'acme.slow',
			function () use ( $q, &$calls ): array {
				++$calls;
				if ( 1 === $calls ) {
					// While the first run is busy, its lease expires and a second worker takes over.
					$this->now += 400;
					$second = $q->claim( 'worker-b', 300, 1 );
					$this->assertCount( 1, $second );
				}
				return array( 'status' => 'succeeded' );
			}
		);
		$job = $q->claim( 'worker-a', 300, 1 )[0];
		$this->assertFalse( $runner->process( $job, 'worker-a' ), 'stale completion is rejected' );
		$this->assertSame( 'running', $q->find( $id )['status'] );
		$this->assertSame( 'worker-b', $q->find( $id )['lease_owner'] );
	}
}
