<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Install\Migrator;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Repository\ReviewRepository;
use ReviewCaptain\Tests\TestCase;

/**
 * Schema v3 (TEC-014): indexed sort column sorted_at = COALESCE(published_at, created_at),
 * idempotent backfill, and keyset pagination via after_id.
 */
final class SchemaV3Test extends TestCase {

	public function test_definition_has_sorted_at_and_index(): void {
		$this->assertGreaterThanOrEqual( 3, Schema::VERSION );
		$def = Schema::definitions()['reviews'];
		$this->assertStringContainsString( 'sorted_at datetime NULL', $def );
		$this->assertStringContainsString( 'KEY status_sorted (status,sorted_at)', $def );
	}

	public function test_backfill_is_rerunnable_and_uses_published_then_created(): void {
		$t = Schema::table( 'reviews' );
		$this->db->insert( $t, array( 'uuid' => wp_generate_uuid4(), 'body' => 'a', 'published_at' => '2026-01-02 03:04:05', 'created_at' => '2026-05-01 00:00:00', 'updated_at' => '2026-05-01 00:00:00' ) );
		$this->db->insert( $t, array( 'uuid' => wp_generate_uuid4(), 'body' => 'b', 'created_at' => '2026-06-01 00:00:00', 'updated_at' => '2026-06-01 00:00:00' ) );
		Migrator::run( 2, 3 );
		Migrator::run( 2, 3 );
		$this->assertSame( array( '2026-01-02 03:04:05', '2026-06-01 00:00:00' ), array_map( 'strval', (array) $this->db->get_col( "SELECT sorted_at FROM {$t} ORDER BY id ASC" ) ) );
	}

	public function test_sorted_at_helper(): void {
		$this->assertSame( '2026-01-01 00:00:00', ReviewRepository::sorted_at( '2026-01-01 00:00:00', '2026-02-01 00:00:00' ) );
		$this->assertSame( '2026-02-01 00:00:00', ReviewRepository::sorted_at( null, '2026-02-01 00:00:00' ) );
		$this->assertSame( '2026-02-01 00:00:00', ReviewRepository::sorted_at( '', '2026-02-01 00:00:00' ) );
		$this->assertNull( ReviewRepository::sorted_at( null, '' ) );
	}
}
