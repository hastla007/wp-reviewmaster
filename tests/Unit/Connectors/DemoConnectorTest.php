<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Connectors;

use CaptainDemo\BookingConnector\Connector;
use CaptainDemo\BookingConnector\ReconcileJob;
use ReviewCaptain\Connectors\ConnectorException;
use ReviewCaptain\Connectors\ContractValidator;
use ReviewCaptain\Connectors\ExperienceEvent;
use ReviewCaptain\Connectors\Registry;
use ReviewCaptain\Tests\TestCase;

final class DemoConnectorTest extends TestCase {

	private const DIR = REVIEW_CAPTAIN_DIR . 'examples/review-captain-demo-connector';

	protected function setUp(): void {
		parent::setUp();
		require_once self::DIR . '/src/Connector.php';
		require_once self::DIR . '/src/ReconcileJob.php';
	}

	private function connector(): Connector {
		return new Connector( self::DIR . '/fixtures/enrollments.json' );
	}

	public function test_demo_connector_passes_contract_validator(): void {
		$result = ( new ContractValidator() )->validate( $this->connector() );
		$this->assertSame( ContractValidator::OUTCOME_OK, $result['outcome'], implode( ' ', $result['messages'] ) );
		$this->assertSame( array(), $result['notices'] );
	}

	public function test_emits_exactly_one_valid_completed_course_event(): void {
		$page = $this->connector()->poll_experiences( '', 10 );
		$this->assertCount( 1, $page['events'] );
		$this->assertNull( $page['next_cursor'] );
		$event = $page['events'][0];
		$this->assertSame( array(), ExperienceEvent::validate( $event ) );
		$this->assertSame( 'experience_completed', $event['event_type'] );
		$this->assertSame( 'course', $event['object_type'] );
		$this->assertStringNotContainsString( '@', (string) wp_json_encode( $event ) );
		$this->assertSame( $page, $this->connector()->poll_experiences( '', 10 ), 'repeatable' );
		$this->assertSame( array(), $this->connector()->poll_experiences( '1', 10 )['events'] );
	}

	public function test_context_provider(): void {
		$this->assertSame( 'Einführungskurs Segeln', $this->connector()->describe_context( 'course', 'C-101' )['label'] );
		$this->assertNull( $this->connector()->describe_context( 'course', 'nope' ) );
		$this->assertNull( $this->connector()->describe_context( 'product', 'C-101' ) );
	}

	public function test_plugin_file_registers_via_public_hook_and_handles_its_jobs(): void {
		require_once self::DIR . '/review-captain-demo-connector.php';
		$registry = new Registry();
		$registry->fire();
		$this->assertTrue( $registry->has( 'demo-booking' ) );
		$this->assertSame( 'inactive', $registry->status( 'demo-booking' )['state'] );
		$this->assertSame( array( 'status' => 'succeeded' ), apply_filters( 'review_captain_process_job_v1', null, 'demo-booking.reconcile', array( 'v' => 1, 'course_id' => 'C-101' ) ) );
		$this->assertNull( apply_filters( 'review_captain_process_job_v1', null, 'other.job', array() ), 'foreign job types are not handled' );
		$this->assertFalse( rc_demo_connector_enqueue_reconcile( 'C-101' ), 'without job service nothing is enqueued' );
	}

	public function test_job_errors_are_connector_exceptions(): void {
		$this->expectException( ConnectorException::class );
		( new ReconcileJob( self::DIR . '/fixtures/enrollments.json' ) )->handle( 'demo-booking.reconcile', array( 'v' => 2 ) );
	}

	public function test_sdk_skeleton_passes_validator_and_fixture(): void {
		require_once REVIEW_CAPTAIN_DIR . 'examples/connector-sdk/src/Connector.php';
		$sdk    = new \MyVendor\MyConnector\Connector( array(), static fn() => array() );
		$result = ( new ContractValidator() )->validate( $sdk );
		$this->assertSame( ContractValidator::OUTCOME_OK, $result['outcome'], implode( ' ', $result['messages'] ) );
		$fixture = json_decode( (string) file_get_contents( REVIEW_CAPTAIN_DIR . 'examples/connector-sdk/tests/fixtures/experience-completed.json' ), true );
		$this->assertSame( array(), ExperienceEvent::validate( $fixture ) );
	}
}
