<?php
/**
 * Contract test suite every review provider must pass (RC-020, RC-083).
 *
 * Usage: extend this class, implement provider(), profile() and valid_credentials().
 *
 * @package ReviewCaptain\Tests
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Providers;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Providers\Contract\ProviderInterface;
use ReviewCaptain\Providers\ProviderException;
use ReviewCaptain\Providers\ProviderRegistry;
use ReviewCaptain\Providers\ProviderResult;
use ReviewCaptain\Providers\SyncPage;
use ReviewCaptain\Providers\SyncService;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\ErrorCode;
use ReviewCaptain\Tests\TestCase;

abstract class ProviderContractTestCase extends TestCase {

	/**
	 * Provider under test.
	 */
	abstract protected function provider(): ProviderInterface;

	/**
	 * Profile passed to sync_page().
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function profile(): array;

	/**
	 * Credentials that test_connection() accepts.
	 *
	 * @return array<string, string>
	 */
	abstract protected function valid_credentials(): array;

	/**
	 * Maximum pages followed in pagination tests.
	 */
	protected function max_pages(): int {
		return 50;
	}

	public function test_contract_metadata(): void {
		$p = $this->provider();
		$this->assertMatchesRegularExpression( ProviderRegistry::KEY_PATTERN, $p->key() );
		$this->assertSame( $p->key(), $this->provider()->key(), 'key is stable' );
		$this->assertNotSame( '', trim( $p->label() ) );
		$this->assertNotSame( '', trim( $p->version() ) );
		$this->assertMatchesRegularExpression( ProviderRegistry::CONTRACT_PATTERN, $p->contract_version() );
		$this->assertInstanceOf( Platform::class, $p->platform() );
		$this->assertTrue( $p->platform()->is_external(), 'providers deliver external platform reviews' );
		$caps = $p->capabilities();
		$this->assertContains( 'read_reviews', $caps );
		$this->assertSame( array(), array_diff( $caps, ProviderInterface::CAPABILITIES ) );
		$this->assertSame( $caps, array_values( array_unique( $caps ) ) );
	}

	public function test_registers_as_compatible(): void {
		$registry = new ProviderRegistry();
		$this->assertTrue( $registry->register( $this->provider() ) );
		$this->assertSame( 'active', $registry->status( $this->provider()->key() )['state'] );
	}

	public function test_connection_with_invalid_credentials_returns_error_result(): void {
		$result = $this->provider()->test_connection( array() );
		$this->assertInstanceOf( ProviderResult::class, $result );
		$this->assertFalse( $result->ok );
		$this->assertTrue( ErrorCode::is_known( (string) $result->error_code ) );
		$ok = $this->provider()->test_connection( $this->valid_credentials() );
		$this->assertTrue( $ok->ok );
		$this->assertNull( $ok->error_code );
		$this->assertStringNotContainsString( implode( '', $this->valid_credentials() ), (string) wp_json_encode( $ok->data ), 'no credentials echoed' );
	}

	public function test_list_profiles_shape(): void {
		if ( ! in_array( 'list_profiles', $this->provider()->capabilities(), true ) ) {
			$this->markTestSkipped( 'list_profiles not supported' );
		}
		$out = $this->provider()->list_profiles( $this->valid_credentials() );
		$this->assertArrayHasKey( 'profiles', $out );
		$this->assertArrayHasKey( 'next_cursor', $out );
		foreach ( $out['profiles'] as $profile ) {
			$this->assertIsString( $profile['external_ref'] );
			$this->assertIsString( $profile['label'] );
			$this->assertIsBool( $profile['selectable'] );
			if ( ! $profile['selectable'] ) {
				$this->assertNotNull( $profile['reason'] );
			}
		}
	}

	public function test_sync_pages_terminate_respect_limit_and_are_valid(): void {
		$provider = $this->provider();
		$cursor   = null;
		$pages    = 0;
		do {
			$page = $provider->sync_page( $this->profile(), $cursor, 2 );
			$this->assertInstanceOf( SyncPage::class, $page );
			$this->assertLessThanOrEqual( 2, count( $page->reviews ) );
			foreach ( $page->reviews as $review ) {
				$this->assertInstanceOf( Review::class, $review );
				$this->assertNotEmpty( $review->external_id );
				$this->assertSame( $provider->platform(), $review->platform );
				$this->assertNull( $review->author_email, 'providers never deliver e-mail addresses' );
				$this->assertSame( array(), $review->validate(), 'normalised reviews pass review validation' );
			}
			$cursor = $page->next_cursor;
			++$pages;
		} while ( null !== $cursor && $pages < $this->max_pages() );
		$this->assertNull( $cursor, 'pagination terminates' );
	}

	public function test_sync_page_is_repeatable(): void {
		$a = $this->provider()->sync_page( $this->profile(), null, 2 );
		$b = $this->provider()->sync_page( $this->profile(), null, 2 );
		$this->assertSame(
			array_map( static fn( Review $r ) => $r->external_id, $a->reviews ),
			array_map( static fn( Review $r ) => $r->external_id, $b->reviews )
		);
		$this->assertSame( $a->next_cursor, $b->next_cursor );
	}

	public function test_full_sync_is_idempotent(): void {
		$service = new SyncService( Plugin::instance()->reviews() );
		for ( $run = 0; $run < 2; $run++ ) {
			$cursor = null;
			$pages  = 0;
			do {
				$page   = $this->provider()->sync_page( $this->profile(), $cursor, 2 );
				$result = $service->apply( $page, (int) ( $this->profile()['id'] ?? 1 ) );
				if ( 1 === $run ) {
					$this->assertSame( 0, $result['created'], 'second run creates nothing' );
				}
				$cursor = $page->next_cursor;
				++$pages;
			} while ( null !== $cursor && $pages < $this->max_pages() );
			$count = $count ?? $this->rows( 'reviews' );
		}
		$this->assertSame( $count, $this->rows( 'reviews' ) );
	}

	public function test_quota_status_shape(): void {
		$p = $this->provider();
		$p->sync_page( $this->profile(), null, 2 );
		$quota = $p->quota_status();
		$this->assertContains( $quota['state'], array( 'ok', 'warning', 'exhausted', 'unknown' ) );
		foreach ( array( 'remaining', 'reset_at', 'retry_after_seconds' ) as $key ) {
			$this->assertArrayHasKey( $key, $quota );
		}
	}

	public function test_errors_are_provider_exceptions_with_known_codes(): void {
		$this->assertTrue( is_subclass_of( ProviderException::class, \ReviewCaptain\Connectors\ConnectorException::class ) );
		try {
			$this->provider()->sync_page( $this->profile(), 'definitely-not-a-cursor-' . bin2hex( random_bytes( 4 ) ), 2 );
			$this->addToAssertionCount( 1 ); // Provider may treat unknown cursors as restart.
		} catch ( ProviderException $e ) {
			$this->assertTrue( ErrorCode::is_known( $e->error_code() ) );
		}
	}
}
