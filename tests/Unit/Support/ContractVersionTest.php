<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ReviewCaptain\Events\Events;
use ReviewCaptain\Workflow\WorkflowService;

/**
 * REVIEW_CAPTAIN_CONTRACT_VERSION (RC-2, suite spec R-3.1): SemVer, independent of the plugin version,
 * same MAJOR as the workflow service contract and the review event schema.
 */
final class ContractVersionTest extends TestCase {

	private static function contract_version(): string {
		$main = (string) file_get_contents( dirname( __DIR__, 3 ) . '/review-captain.php' );
		preg_match( "/define\( 'REVIEW_CAPTAIN_CONTRACT_VERSION', '([^']+)' \);/", $main, $m );
		return $m[1] ?? '';
	}

	public function test_main_file_defines_semver_contract_version(): void {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', self::contract_version() );
	}

	public function test_major_matches_workflow_service_and_event_schema(): void {
		$major = (int) strtok( self::contract_version(), '.' );
		$this->assertSame( 1, $major );
		$this->assertSame( $major, (int) strtok( WorkflowService::CONTRACT, '.' ) );
		$this->assertTrue( WorkflowService::compatible( $major . '.0' ) );
		$this->assertStringEndsWith( '.v' . $major, Events::SCHEMA );
	}

	public function test_contract_version_is_documented(): void {
		$readme = (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/contracts/README.md' );
		$this->assertStringContainsString( 'REVIEW_CAPTAIN_CONTRACT_VERSION', $readme );
		$this->assertStringContainsString( 'Stand: `' . self::contract_version() . '`', $readme );
	}
}
