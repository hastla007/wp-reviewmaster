<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Providers;

use ReviewCaptain\Providers\Contract\ProviderInterface;
use ReviewCaptain\Providers\FixtureProvider;
use ReviewCaptain\Providers\ProviderException;
use ReviewCaptain\Providers\ProvidersModule;
use ReviewCaptain\Providers\ProviderRegistry;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\ErrorCode;

require_once __DIR__ . '/ProviderContractTestCase.php';

final class FixtureProviderContractTest extends ProviderContractTestCase {

	public const DIR = REVIEW_CAPTAIN_DIR . 'tests/fixtures/providers/demo';

	protected function provider(): ProviderInterface {
		return new FixtureProvider( self::DIR );
	}

	protected function profile(): array {
		return array(
			'id'           => 7,
			'external_ref' => 'loc-100',
			'credentials'  => array( 'api_key' => 'demo-key' ),
		);
	}

	protected function valid_credentials(): array {
		return array( 'api_key' => 'demo-key' );
	}

	public function test_pagination_and_duplicate_over_page_boundary(): void {
		$p     = $this->provider();
		$first = $p->sync_page( $this->profile(), null, 20 );
		$this->assertSame( array( 'demo-rev-1001', 'demo-rev-1002' ), array_map( static fn( $r ) => $r->external_id, $first->reviews ) );
		$this->assertSame( 'eyJwIjoyfQ', $first->next_cursor );
		$second = $p->sync_page( $this->profile(), $first->next_cursor, 20 );
		$this->assertSame( array( 'demo-rev-1002', 'demo-rev-1003' ), array_map( static fn( $r ) => $r->external_id, $second->reviews ) );
		$this->assertFalse( $second->has_more() );
		$this->assertSame( '2026-11-10 09:12:00', $first->reviews[0]->published_at );
		$this->assertSame( 'Danke für den Hinweis.', $first->reviews[1]->meta['reply']['body'] );
	}

	/** @return array<string, array{0: string, 1: string, 2: ?int}> */
	public static function error_fixtures(): array {
		return array(
			'401'         => array( 'error_401', ErrorCode::AUTH_INVALID, null ),
			'401 expired' => array( 'error_401_expired', ErrorCode::AUTH_EXPIRED, null ),
			'403'         => array( 'error_403', ErrorCode::PERMISSION_DENIED, null ),
			'429'         => array( 'error_429', ErrorCode::RATE_LIMITED, 120 ),
			'5xx'         => array( 'error_5xx', ErrorCode::TEMP_UNAVAILABLE, null ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'error_fixtures' )]
	public function test_rc_082_error_mapping( string $fixture, string $code, ?int $retry_after ): void {
		$expected = json_decode( (string) file_get_contents( self::DIR . '/' . $fixture . '.json' ), true )['expected_exception'];
		$this->assertSame( $expected['code'], $code );
		try {
			( new FixtureProvider( self::DIR ) )->with_error( $fixture )->sync_page( $this->profile(), null, 20 );
			$this->fail( 'exception expected' );
		} catch ( ProviderException $e ) {
			$this->assertSame( $code, $e->error_code() );
			$this->assertSame( $retry_after, $e->retry_after() );
			$this->assertNotSame( '', ErrorCode::admin_action( $e->error_code() ) );
		}
	}

	public function test_http_mapping_extra_cases(): void {
		$this->assertSame( ErrorCode::NET_TIMEOUT, ProviderException::from_http( 0 )->error_code() );
		$this->assertSame( ErrorCode::REMOTE_REMOVED, ProviderException::from_http( 404 )->error_code() );
		$this->assertSame( ErrorCode::PERMANENT_FAILURE, ( new ProviderException( 'rc.vendor.weird' ) )->error_code() );
	}

	public function test_quota_from_last_response(): void {
		$p = $this->provider()->with_pages( array( 'sync_page_changed' ) );
		$this->assertSame( 'unknown', $p->quota_status()['state'] );
		$p->sync_page( $this->profile(), null, 20 );
		$this->assertSame( 'warning', $p->quota_status()['state'] );
		$this->assertSame( 4, $p->quota_status()['remaining'] );
	}

	public function test_registry_rules(): void {
		$registry = new ProviderRegistry();
		$this->assertTrue( $registry->register( $this->provider() ) );
		$this->assertFalse( $registry->register( $this->provider() ), 'duplicate key' );
		$this->assertFalse( $registry->register( new FixtureProvider( self::DIR, 'Bad Key' ) ) );
		$this->assertFalse( $registry->register( new \stdClass() ) );
		$this->assertCount( 3, $registry->rejected() );
		$this->assertSame( ErrorCode::DATA_DUPLICATE, $registry->rejected()[0]['error_code'] );
		$this->assertNotNull( $registry->get( 'demo' ) );
		$this->assertSame( 'source_unavailable', $registry->status( 'missing' )['state'] );
	}

	public function test_incompatible_provider_is_paused_not_fatal(): void {
		$bad = new class( self::DIR, 'demo2' ) implements ProviderInterface {
			private FixtureProvider $inner;
			public function __construct( string $dir, string $key ) { $this->inner = new FixtureProvider( $dir, $key ); }
			public function key(): string { return 'demo2'; }
			public function label(): string { return 'Bad'; }
			public function version(): string { return '1.0.0'; }
			public function platform(): \ReviewCaptain\Domain\Platform { return \ReviewCaptain\Domain\Platform::OTHER; }
			public function contract_version(): string { return '2.0'; }
			public function capabilities(): array { return array( 'read_reviews', 'teleport' ); }
			public function test_connection( array $credentials ): \ReviewCaptain\Providers\ProviderResult { return $this->inner->test_connection( $credentials ); }
			public function list_profiles( array $credentials, ?string $cursor = null ): array { return $this->inner->list_profiles( $credentials, $cursor ); }
			public function sync_page( array $profile, ?string $cursor, int $limit ): \ReviewCaptain\Providers\SyncPage { throw new \LogicException( 'must not be called' ); }
			public function quota_status(): array { return $this->inner->quota_status(); }
		};
		$registry = new ProviderRegistry();
		$this->assertTrue( $registry->register( $bad ) );
		$status = $registry->status( 'demo2' );
		$this->assertSame( 'paused', $status['state'] );
		$this->assertSame( ErrorCode::CONTRACT_INCOMPATIBLE, $status['error_code'] );
		$this->assertNull( $registry->get( 'demo2' ), 'incompatible providers are not handed out' );
	}

	public function test_module_registers_services_and_no_fixture_in_production(): void {
		$plugin = Plugin::instance();
		$module = new ProvidersModule();
		$module->register( $plugin );
		$this->assertInstanceOf( ProviderRegistry::class, $plugin->get( 'providers' ) );
		$this->assertInstanceOf( \ReviewCaptain\Providers\SyncService::class, $plugin->get( 'provider_sync' ) );
		$this->assertSame( 20, has_action( 'init', array( $module, 'fire' ) ) );
		$this->assertFalse( ProvidersModule::dev_fixtures_enabled(), 'requires REVIEW_CAPTAIN_DEV_FIXTURES' );
		do_action( 'init' );
		$this->assertSame( array(), $module->registry()->keys() );
		$this->assertSame( 1, did_action( 'review_captain_providers_v1' ) );
		$this->assertArrayHasKey( 'providers', apply_filters( 'review_captain_diagnostics_sections', array() ) );
	}
}
