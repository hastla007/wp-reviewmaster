<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Jobs;

use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Support\ErrorCode;
use ReviewCaptain\Tests\TestCase;

final class JobQueueTest extends TestCase {

	private int $now;

	private function queue(): JobQueue {
		$this->now = $this->now ?? time();
		return new JobQueue( fn() => $this->now );
	}

	protected function setUp(): void {
		parent::setUp();
		$this->now = time();
	}

	public function test_duplicate_enqueue_returns_existing_id(): void {
		$q  = $this->queue();
		$a  = $q->enqueue( 'test.sync', array( 'v' => 1 ), 'test.sync:1' );
		$b  = $q->enqueue( 'test.sync', array( 'v' => 2 ), 'test.sync:1' );
		$this->assertIsInt( $a );
		$this->assertSame( $a, $b );
		$this->assertSame( 1, $this->rows( 'jobs' ) );
		$this->assertSame( array( 'v' => 1 ), $q->find( $a )['payload'] );
	}

	public function test_enqueue_rejects_invalid_type_and_long_keys_are_hashed(): void {
		$q = $this->queue();
		$this->assertFalse( $q->enqueue( 'Bad Type!', array(), 'k' ) );
		$this->assertFalse( $q->enqueue( 'ok.type', array(), '' ) );
		$long = str_repeat( 'x', 300 );
		$id   = $q->enqueue( 'ok.type', array(), $long );
		$this->assertLessThanOrEqual( 191, strlen( $q->find( $id )['idempotency_key'] ) );
		$this->assertSame( $id, $q->enqueue( 'ok.type', array(), $long ) );
	}

	public function test_two_workers_cannot_claim_same_job(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'test.a', array(), 'a' );
		$a  = $q->claim( 'worker-a', 300 );
		$b  = $q->claim( 'worker-b', 300 );
		$this->assertCount( 1, $a );
		$this->assertSame( $id, $a[0]['id'] );
		$this->assertSame( 'running', $a[0]['status'] );
		$this->assertSame( 1, $a[0]['attempts'] );
		$this->assertSame( array(), $b );
		// Worker B cannot complete a job it does not own.
		$this->assertFalse( $q->complete( $id, 'worker-b' ) );
		$this->assertTrue( $q->complete( $id, 'worker-a' ) );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
	}

	public function test_delayed_job_not_claimed_before_due(): void {
		$q = $this->queue();
		$q->enqueue( 'test.a', array(), 'a', 600 );
		$this->assertSame( array(), $q->claim( 'w', 300 ) );
		$this->now += 601;
		$this->assertCount( 1, $q->claim( 'w', 300 ) );
	}

	public function test_expired_lease_is_recovered_and_old_worker_result_discarded(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'test.a', array(), 'a' );
		$q->claim( 'worker-a', 60 );
		$this->assertSame( array(), $q->claim( 'worker-b', 60 ) );
		$this->now += 61;
		$b = $q->claim( 'worker-b', 60 );
		$this->assertCount( 1, $b );
		$this->assertSame( 2, $b[0]['attempts'] );
		$this->assertFalse( $q->complete( $id, 'worker-a' ) );
		$this->assertSame( 'running', $q->fail( $id, 'worker-a', ErrorCode::NET_TIMEOUT, 'late' ) );
		$this->assertTrue( $q->complete( $id, 'worker-b' ) );
	}

	public function test_lease_expiry_beyond_max_attempts_goes_dead(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'test.a', array(), 'a', 0, 2 );
		$q->claim( 'w1', 10 );
		$this->now += 11;
		$q->claim( 'w2', 10 );
		$this->now += 11;
		$this->assertSame( array(), $q->claim( 'w3', 10 ) );
		$job = $q->find( $id );
		$this->assertSame( 'dead', $job['status'] );
		$this->assertSame( ErrorCode::TEMP_UNAVAILABLE, $job['last_error_code'] );
	}

	public function test_transient_backoff_and_dead_after_max_attempts(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'test.a', array(), 'a', 0, 3 );
		$q->claim( 'w', 300 );
		$this->assertSame( 'queued', $q->fail( $id, 'w', ErrorCode::NET_TIMEOUT, 'timeout' ) );
		$delay = strtotime( $q->find( $id )['available_at'] . ' UTC' ) - $this->now;
		$this->assertGreaterThanOrEqual( 48, $delay );
		$this->assertLessThanOrEqual( 72, $delay );

		$this->now += 3600;
		$q->claim( 'w', 300 );
		$this->assertSame( 'queued', $q->fail( $id, 'w', ErrorCode::NET_TIMEOUT, 'timeout' ) );
		$this->now += 3600;
		$q->claim( 'w', 300 );
		$this->assertSame( 'dead', $q->fail( $id, 'w', ErrorCode::NET_TIMEOUT, 'timeout' ) );
		$this->assertSame( 3, $q->find( $id )['attempts'] );
	}

	public function test_backoff_sequence_is_capped(): void {
		$this->assertSame( 60, JobQueue::backoff( 1 ) );
		$this->assertSame( 180, JobQueue::backoff( 2 ) );
		$this->assertSame( 540, JobQueue::backoff( 3 ) );
		$this->assertSame( 21600, JobQueue::backoff( 7 ) );
		$this->assertSame( 21600, JobQueue::backoff( 50 ) );
	}

	public function test_unknown_code_is_treated_as_transient(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'test.a', array(), 'a' );
		$q->claim( 'w', 300 );
		$this->assertSame( 'queued', $q->fail( $id, 'w', 'not a code', 'x' ) );
		$this->assertSame( ErrorCode::TEMP_UNAVAILABLE, $q->find( $id )['last_error_code'] );
	}

	public function test_rate_limit_respects_retry_after_and_does_not_consume_attempt(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'test.a', array( 'v' => 1 ), 'a', 0, 2 );
		$q->claim( 'w', 300 );
		$this->assertSame( 'queued', $q->fail( $id, 'w', ErrorCode::QUOTA_EXCEEDED, '429', 120 ) );
		$job = $q->find( $id );
		$this->assertSame( 0, $job['attempts'] );
		$this->assertSame( 120, strtotime( $job['available_at'] . ' UTC' ) - $this->now );
		$this->assertSame( 1, $job['payload'][ JobQueue::RL_DEFERRALS_KEY ] );

		// Without Retry-After: 15 minutes.
		$this->now += 121;
		$q->claim( 'w', 300 );
		$q->fail( $id, 'w', ErrorCode::RATE_LIMITED, '429' );
		$this->assertSame( 900, strtotime( $q->find( $id )['available_at'] . ' UTC' ) - $this->now );
	}

	public function test_rate_limit_deferrals_are_bounded(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'test.a', array(), 'a', 0, 1 );
		$status = '';
		for ( $i = 0; $i < 60 && 'dead' !== $status; $i++ ) {
			$this->assertCount( 1, $q->claim( 'w', 300 ) );
			$status   = $q->fail( $id, 'w', ErrorCode::QUOTA_EXCEEDED, '429', 60 );
			$this->now += 61;
		}
		$this->assertSame( 'dead', $status );
		$this->assertSame( JobQueue::MAX_RATE_LIMIT_DEFERRALS + 1, $i );
	}

	public function test_auth_error_pauses_and_resume_requeues(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'pro.provider.sync', array(), 'sync:1' );
		$o  = $q->enqueue( 'other.job', array(), 'o' );
		$q->claim( 'w', 300 );
		$this->assertSame( 'paused', $q->fail( $id, 'w', ErrorCode::AUTH_INVALID, 'Token abgelehnt' ) );
		$this->assertSame( array(), array_filter( $q->claim( 'w2', 300, 5 ), fn( $j ) => $j['id'] === $id ) );
		$this->assertSame( 0, $q->resume_paused( 'nomatch.' ) );
		$this->assertSame( 1, $q->resume_paused( 'pro.provider.' ) );
		$job = $q->find( $id );
		$this->assertSame( 'queued', $job['status'] );
		$this->assertSame( 0, $job['attempts'] );
		$this->assertNotNull( $o );
	}

	public function test_permanent_and_data_errors_go_dead(): void {
		$q = $this->queue();
		$a = $q->enqueue( 'test.a', array(), 'a' );
		$b = $q->enqueue( 'test.b', array(), 'b' );
		$q->claim( 'w', 300, 2 );
		$this->assertSame( 'dead', $q->fail( $a, 'w', ErrorCode::PERMANENT_FAILURE, 'nope' ) );
		$this->assertSame( 'dead', $q->fail( $b, 'w', ErrorCode::DATA_INVALID_PAYLOAD, 'bad' ) );
	}

	public function test_error_message_is_redacted_and_truncated(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'test.a', array(), 'a' );
		$q->claim( 'w', 300 );
		$secret = 'sk_live_51HxyzABCDEF0123456789';
		$msg    = "<b>HTTP 500</b> Authorization: Bearer abc.def-ghi123 url=https://api.example.test/v1?key={$secret}&token=tok123&page=2 password=\"hunter2\" refresh_token: r3fr3sh user anna@example.test jwt eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.sig " . str_repeat( 'x ', 400 );
		$q->fail( $id, 'w', ErrorCode::TEMP_UNAVAILABLE, $msg );
		$stored = (string) $q->find( $id )['last_error_message'];
		foreach ( array( 'abc.def-ghi123', $secret, 'tok123', 'hunter2', 'r3fr3sh', 'anna@example.test', 'eyJhbGci', '<b>' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $stored );
		}
		$this->assertStringContainsString( 'HTTP 500', $stored );
		$this->assertStringContainsString( 'page=2', $stored );
		$this->assertLessThanOrEqual( 500, mb_strlen( $stored ) );
	}

	public function test_retry_cancel_and_stats(): void {
		$q  = $this->queue();
		$a  = $q->enqueue( 'test.a', array(), 'a', 0, 1 );
		$b  = $q->enqueue( 'test.b', array(), 'b' );
		$c  = $q->enqueue( 'test.c', array(), 'c' );
		$q->claim( 'w', 300 );
		$q->fail( $a, 'w', ErrorCode::NET_TIMEOUT, 'x' );
		$this->assertSame( 'dead', $q->find( $a )['status'] );
		$this->assertTrue( $q->cancel( $b ) );
		$this->assertFalse( $q->cancel( $b ) );

		$this->now += 1200;
		$stats = $q->stats();
		$this->assertSame( 1, $stats['dead'] );
		$this->assertSame( 1, $stats['failed'] );
		$this->assertSame( 1, $stats['queued'] );
		$this->assertSame( 1, $stats['overdue'] );
		$this->assertNotNull( $stats['oldest_due_at'] );
		$this->assertArrayHasKey( 'paused', $stats );

		$this->assertTrue( $q->retry( $a ) );
		$this->assertSame( 0, $q->find( $a )['attempts'] );
		$this->assertSame( 'queued', $q->find( $a )['status'] );
		$this->assertCount( 3, $q->recent( 10 ) );
		$this->assertCount( 1, $q->recent( 10, 'failed' ) );
		$this->assertNotNull( $c );
	}

	public function test_purge_finished(): void {
		$q  = $this->queue();
		$a  = $q->enqueue( 'test.a', array(), 'a' );
		$q->enqueue( 'test.b', array(), 'b' );
		$q->claim( 'w', 300 );
		$q->complete( $a, 'w' );
		$this->assertSame( 0, $q->purge_finished( 30 ) );
		$this->now += 31 * DAY_IN_SECONDS;
		$this->assertSame( 1, $q->purge_finished( 30 ) );
		$this->assertSame( 1, $this->rows( 'jobs' ) );
	}

	public function test_continue_later_keeps_attempts_and_patches_payload(): void {
		$q  = $this->queue();
		$id = $q->enqueue( 'test.a', array( 'page' => 1 ), 'a' );
		$q->claim( 'w', 300 );
		$this->assertTrue( $q->continue_later( $id, 'w', array( 'page' => 2 ) ) );
		$job = $q->find( $id );
		$this->assertSame( 'queued', $job['status'] );
		$this->assertSame( 0, $job['attempts'] );
		$this->assertSame( 2, $job['payload']['page'] );
	}
}
