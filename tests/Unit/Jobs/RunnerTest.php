<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Jobs;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Support\ErrorCode;
use ReviewCaptain\Tests\TestCase;

final class RunnerTest extends TestCase {

	private float $clock = 1000.0;

	protected function setUp(): void {
		parent::setUp();
		update_option( Schema::OPTION_VERSION, Schema::VERSION );
	}

	private function runner( JobQueue $q ): Runner {
		return new Runner( $q, fn() => $this->clock );
	}

	public function test_builtin_handler_success(): void {
		$q  = new JobQueue();
		$r  = $this->runner( $q );
		$id = $q->enqueue( 'core.thing', array( 'v' => 1 ), 'k' );
		$seen = null;
		$r->register_handler(
			'core.thing',
			function ( array $payload, array $job ) use ( &$seen ) {
				$seen = $payload;
				return array( 'status' => 'succeeded' );
			}
		);
		$res = $r->run( 10 );
		$this->assertSame( array( 'processed' => 1, 'succeeded' => 1, 'failed' => 0 ), $res );
		$this->assertSame( array( 'v' => 1 ), $seen );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
	}

	public function test_filter_handler(): void {
		$q  = new JobQueue();
		$id = $q->enqueue( 'acme-booking.reconcile', array( 'v' => 1 ), 'k' );
		add_filter(
			'review_captain_process_job_v1',
			static function ( $result, string $type, array $payload ) {
				if ( null !== $result || 'acme-booking.reconcile' !== $type ) {
					return $result;
				}
				return array( 'status' => 'succeeded' );
			},
			10,
			3
		);
		$res = $this->runner( $q )->run( 10 );
		$this->assertSame( 1, $res['succeeded'] );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
	}

	public function test_unhandled_type_is_paused_with_contract_code(): void {
		$q   = new JobQueue();
		$id  = $q->enqueue( 'gone.plugin', array(), 'k' );
		$res = $this->runner( $q )->run( 10 );
		$this->assertSame( 1, $res['failed'] );
		$job = $q->find( $id );
		$this->assertSame( 'paused', $job['status'] );
		$this->assertSame( ErrorCode::CONTRACT_INCOMPATIBLE, $job['last_error_code'] );
		$this->assertSame( 0, $job['attempts'] );
		// Hourly re-check re-queues it once the transient expired.
		delete_transient( 'review_captain_resume_unhandled' );
		add_filter( 'review_captain_process_job_v1', static fn( $r, $t ) => 'gone.plugin' === $t ? array( 'status' => 'succeeded' ) : $r, 10, 2 );
		$this->runner( $q )->run( 10 );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
	}

	public function test_connector_exception_code_is_used(): void {
		$q  = new JobQueue();
		$id = $q->enqueue( 'acme.x', array(), 'k' );
		$r  = $this->runner( $q );
		$r->register_handler(
			'acme.x',
			static function () {
				throw new class( 'Key abgelehnt key=supersecret' ) extends \RuntimeException {
					public function error_code(): string {
						return 'rc.auth.invalid';
					}
				};
			}
		);
		$r->run( 10 );
		$job = $q->find( $id );
		$this->assertSame( 'paused', $job['status'] );
		$this->assertSame( 'rc.auth.invalid', $job['last_error_code'] );
		$this->assertStringNotContainsString( 'supersecret', (string) $job['last_error_message'] );
	}

	public function test_generic_exception_is_transient(): void {
		$q  = new JobQueue();
		$id = $q->enqueue( 'acme.y', array(), 'k' );
		$r  = $this->runner( $q );
		$r->register_handler( 'acme.y', static fn() => throw new \LogicException( 'boom' ) );
		$r->run( 10 );
		$job = $q->find( $id );
		$this->assertSame( 'queued', $job['status'] );
		$this->assertSame( ErrorCode::TEMP_UNAVAILABLE, $job['last_error_code'] );
	}

	public function test_invalid_result_and_explicit_failure(): void {
		$q = new JobQueue();
		$a = $q->enqueue( 'acme.a', array(), 'a' );
		$b = $q->enqueue( 'acme.b', array(), 'b' );
		$r = $this->runner( $q );
		$r->register_handler( 'acme.a', static fn() => 'weird' );
		$r->register_handler( 'acme.b', static fn() => array( 'status' => 'failed', 'error_code' => ErrorCode::DATA_INVALID_PAYLOAD, 'message' => 'bad' ) );
		$res = $r->run( 10 );
		$this->assertSame( 2, $res['failed'] );
		$this->assertSame( ErrorCode::CONTRACT_INVALID, $q->find( $a )['last_error_code'] );
		$this->assertSame( 'dead', $q->find( $b )['status'] );
	}

	public function test_time_budget_stops_claiming(): void {
		$q = new JobQueue();
		for ( $i = 0; $i < 5; $i++ ) {
			$q->enqueue( 'core.slow', array(), 'slow:' . $i );
		}
		$r = $this->runner( $q );
		$r->register_handler(
			'core.slow',
			function () {
				$this->clock += 4.0; // Each job "takes" 4 seconds.
				return true;
			}
		);
		$res = $r->run( 10 );
		$this->assertSame( 3, $res['processed'] );
		$this->assertSame( 2, $q->stats()['queued'] );
	}

	public function test_skips_when_schema_outdated(): void {
		update_option( Schema::OPTION_VERSION, 0 );
		$q = new JobQueue();
		$q->enqueue( 'core.x', array(), 'k' );
		$this->assertSame( 0, $this->runner( $q )->run( 10 )['processed'] );
	}

	public function test_reconcile_lock_defers_side_effect_jobs(): void {
		update_option( Runner::OPTION_LOCK, 'restore' );
		$q    = new JobQueue();
		$send = $q->enqueue( 'pro.request.send', array(), 'send:1' );
		$ret  = $q->enqueue( 'retention.purge', array(), 'retention.purge' );
		$r    = $this->runner( $q );
		$called = array();
		$r->register_handler( 'pro.request.send', function () use ( &$called ) { $called[] = 'send'; return true; } );
		$r->register_handler( 'retention.purge', function () use ( &$called ) { $called[] = 'retention'; return true; } );
		$r->run( 10 );
		$this->assertSame( array( 'retention' ), $called );
		$this->assertSame( 'queued', $q->find( $send )['status'] );
		$this->assertSame( 0, $q->find( $send )['attempts'] );
		$this->assertSame( 'succeeded', $q->find( $ret )['status'] );
	}

	public function test_continue_result_requeues(): void {
		$q  = new JobQueue();
		$id = $q->enqueue( 'core.pages', array( 'page' => 1 ), 'k' );
		$r  = $this->runner( $q );
		$r->register_handler( 'core.pages', static fn( array $p ) => $p['page'] < 3 ? array( 'status' => 'continue', 'payload' => array( 'page' => $p['page'] + 1 ) ) : true );
		$res = $r->run( 10 );
		$this->assertSame( 3, $res['processed'] );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
		$this->assertSame( 3, $q->find( $id )['payload']['page'] );
	}

	public function test_worker_id_format(): void {
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}-\d+-[0-9a-f]{16}$/', Runner::new_worker_id() );
	}
}
