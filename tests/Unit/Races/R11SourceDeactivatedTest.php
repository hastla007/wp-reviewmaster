<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use Acme\RcTest\ExperienceConnector;
use ReviewCaptain\Connectors\Registry;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\ErrorCode;

require_once dirname( __DIR__, 2 ) . '/fixtures/connectors/TestConnectors.php';

/**
 * R-11 (TC-R11-a/b, core part): the source plugin is deactivated between claim and processing →
 * the job is paused with rc.source.unavailable and runs after reactivation. Expiry of requests
 * (`expires_at`) is Pro.
 */
final class R11SourceDeactivatedTest extends RaceTestCase {

	public function test_deactivation_between_claim_and_process_pauses_and_reactivation_resumes(): void {
		// Connector runtime is a Pro right (QA F-03); the test grants it.
		add_filter( 'review_captain_connector_runtime_v1', '__return_true' );
		$registry = new Registry();
		$registry->register( new ExperienceConnector() );
		$registry->set_state( 'acme-booking', 'active' );
		Plugin::instance()->factory( 'connectors', static fn() => $registry );
		$runs = 0;
		add_filter(
			'review_captain_process_job_v1',
			static function ( $result, $type ) use ( &$runs ) {
				if ( 'acme-booking.sync' === $type ) {
					++$runs;
					return array( 'status' => 'succeeded' );
				}
				return $result;
			},
			10,
			2
		);
		$q   = new JobQueue();
		$id  = $q->enqueue( 'acme-booking.sync', array(), 'acme:1' );
		$job = $q->claim( 'w1', 300, 1 )[0];

		$this->assertTrue( $registry->set_state( 'acme-booking', 'inactive' ) ); // Plugin deactivated.
		$this->assertFalse( ( new Runner( $q ) )->process( $job, 'w1' ) );
		$this->assertSame( 'paused', $q->find( $id )['status'] );
		$this->assertSame( ErrorCode::SOURCE_UNAVAILABLE, $q->find( $id )['last_error_code'] );
		$this->assertSame( 0, $runs );

		$registry->set_state( 'acme-booking', 'active' );
		$this->assertSame( 1, $q->resume_paused_with_code( ErrorCode::SOURCE_UNAVAILABLE ) );
		( new Runner( $q ) )->run( 5 );
		$this->assertSame( 'succeeded', $q->find( $id )['status'] );
		$this->assertSame( 1, $runs );
	}
}
