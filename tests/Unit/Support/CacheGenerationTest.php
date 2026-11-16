<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Support;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\CacheGeneration;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Tests\TestCase;

/**
 * TEC-012/RC-136: summary_by_platform is cached per generation; every review write invalidates it.
 */
final class CacheGenerationTest extends TestCase {

	public function test_bump_increments_and_persists(): void {
		CacheGeneration::reset_local();
		$g = CacheGeneration::get();
		CacheGeneration::bump();
		$this->assertSame( $g + 1, CacheGeneration::get() );
		CacheGeneration::reset_local();
		$this->assertSame( $g + 1, CacheGeneration::get() );
	}

	public function test_remember_hits_until_bump(): void {
		$calls = 0;
		$f     = static function () use ( &$calls ) {
			return ++$calls;
		};
		$this->assertSame( 1, CacheGeneration::remember( 't', array( 'a' => 1 ), 60, $f ) );
		$this->assertSame( 1, CacheGeneration::remember( 't', array( 'a' => 1 ), 60, $f ) );
		$this->assertSame( 2, CacheGeneration::remember( 't', array( 'a' => 2 ), 60, $f ) );
		CacheGeneration::bump();
		$this->assertSame( 3, CacheGeneration::remember( 't', array( 'a' => 1 ), 60, $f ) );
	}

	public function test_summary_reflects_every_write(): void {
		$repo = Plugin::instance()->reviews();
		$this->assertSame( array(), $repo->summary_by_platform() );
		$r = $this->make_review( array( 'status' => ReviewStatus::APPROVED, 'rating_value' => 4.0 ) );
		$this->assertSame( 1, $repo->summary_by_platform()[0]['count'] );
		$r2 = $this->make_review( array( 'status' => ReviewStatus::APPROVED, 'rating_value' => 2.0 ) );
		$this->assertSame( 2, $repo->summary_by_platform()[0]['count'] );
		$this->assertSame( 3.0, $repo->summary_by_platform()[0]['average'] );
		$r2->rating_value = 4.0;
		$repo->update( $r2 );
		$this->assertSame( 4.0, $repo->summary_by_platform()[0]['average'] );
		$this->assertTrue( $repo->change_status( (int) $r->id, ReviewStatus::REJECTED, 'test' ) );
		$this->assertSame( 1, $repo->summary_by_platform()[0]['count'] );
		$repo->delete( (int) $r2->id );
		$this->assertSame( array(), $repo->summary_by_platform() );
	}

	/** @param array<string, mixed> $f */
	private function make_review( array $f ): Review {
		$r                  = new Review();
		$r->rating_value    = $f['rating_value'];
		$r->body            = 'Cache test ' . wp_rand();
		$r->author_name     = 'Test';
		$r->status          = $f['status'];
		$r->consent_publish = true;
		return Plugin::instance()->reviews()->insert( $r );
	}
}
