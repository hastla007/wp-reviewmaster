<?php
/**
 * SDK contract tests (CON-007 §E). Run from the Review Captain repository:
 *   vendor/bin/phpunit --bootstrap tests/bootstrap.php examples/connector-sdk/tests
 *
 * @package MyVendor\MyConnector
 */

declare(strict_types=1);

namespace MyVendor\MyConnector\Tests;

use MyVendor\MyConnector\Connector;
use MyVendor\MyConnector\JobHandler;
use ReviewCaptain\Connectors\ConnectorException;
use ReviewCaptain\Connectors\ContractValidator;
use ReviewCaptain\Connectors\ExperienceEvent;

require_once dirname( __DIR__ ) . '/src/Connector.php';
require_once dirname( __DIR__ ) . '/src/JobHandler.php';

final class ConnectorTest extends \PHPUnit\Framework\TestCase {

	private function connector(): Connector {
		$fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/experience-completed.json' ), true );
		$rows    = array(
			array(
				'id'          => '1001',
				'status'      => 'attended',
				'occurred_at' => $fixture['occurred_at'],
				'object_id'   => 'C-1',
				'customer_id' => '42',
				'locale'      => 'de_DE',
				'opt_in'      => true,
				'opt_in_at'   => '2026-11-01T09:00:00Z',
			),
		);
		return new Connector( array( 'object_type' => 'course' ), static fn( string $cursor, int $limit ): array => '' === $cursor ? $rows : array() );
	}

	public function test_ct_s_01_02_contract_valid(): void {
		$result = ( new ContractValidator() )->validate( $this->connector() );
		self::assertSame( ContractValidator::OUTCOME_OK, $result['outcome'], implode( ' ', $result['messages'] ) );
		self::assertMatchesRegularExpression( ContractValidator::KEY_PATTERN, $this->connector()->key() );
	}

	public function test_ct_s_04_fixture_passes_payload_validator(): void {
		$fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/experience-completed.json' ), true );
		self::assertSame( array(), ExperienceEvent::validate( $fixture ) );
	}

	public function test_ct_s_05_poll_is_repeatable_and_valid(): void {
		$c     = $this->connector();
		$first = $c->poll_experiences( '', 10 );
		self::assertSame( $first, $c->poll_experiences( '', 10 ) );
		foreach ( $first['events'] as $event ) {
			self::assertSame( array(), ExperienceEvent::validate( $event ) );
			self::assertStringNotContainsString( '@', $event['recipient_ref'] );
		}
	}

	public function test_ct_s_07_errors_are_connector_exceptions(): void {
		$this->expectException( ConnectorException::class );
		( new JobHandler() )->handle( 'my-connector.reconcile', array( 'v' => 99 ) );
	}
}
