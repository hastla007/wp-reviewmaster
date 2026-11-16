<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Jobs;

use Acme\RcTest\ExperienceConnector;
use ReviewCaptain\Connectors\Registry;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\ErrorCode;
use ReviewCaptain\Tests\TestCase;

require_once dirname( __DIR__, 2 ) . '/fixtures/connectors/TestConnectors.php';

/**
 * CON-014 / CT-J-09: connector job types only run while their connector is active.
 */
final class RunnerConnectorGateTest extends TestCase {

	private Registry $registry;

	protected function setUp(): void {
		parent::setUp();
		update_option( Schema::OPTION_VERSION, Schema::VERSION );
		$this->registry = new Registry();
		$this->registry->register( new ExperienceConnector() );
		$registry = $this->registry;
		Plugin::instance()->factory( 'connectors', static fn() => $registry );
		add_filter(
			'review_captain_process_job_v1',
			static fn( $result, $type ) => 'acme-booking.sync' === $type ? array( 'status' => 'succeeded' ) : $result,
			10,
			2
		);
	}

	public function test_inactive_connector_job_is_paused_with_source_unavailable(): void {
		$q  = new JobQueue();
		$id = $q->enqueue( 'acme-booking.sync', array( 'v' => 1 ), 'acme-1' );
		( new Runner( $q ) )->run( 5 );
		$job = $q->find( $id );
		$this->assertSame( 'paused', $job['status'] );
		$this->assertSame( ErrorCode::SOURCE_UNAVAILABLE, $job['last_error_code'] );
	}

	public function test_active_connector_job_runs(): void {
		$this->assertTrue( $this->registry->set_state( 'acme-booking', 'active' ) );
		$q  = new JobQueue();
		$id = $q->enqueue( 'acme-booking.sync', array( 'v' => 1 ), 'acme-2' );
		( new Runner( $q ) )->run( 5 );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
	}

	public function test_non_connector_types_are_not_gated(): void {
		add_filter( 'review_captain_process_job_v1', static fn( $r, $t ) => 'pro.sync' === $t ? true : $r, 10, 2 );
		$q  = new JobQueue();
		$id = $q->enqueue( 'pro.sync', array(), 'pro-1' );
		( new Runner( $q ) )->run( 5 );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
	}
}
