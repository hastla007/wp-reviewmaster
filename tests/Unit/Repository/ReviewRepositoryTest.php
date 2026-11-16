<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Repository;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

final class ReviewRepositoryTest extends TestCase {

	private function local( string $body = 'Sehr guter Service, gerne wieder.', float $rating = 5 ): Review {
		$r               = new Review();
		$r->origin       = Origin::LOCAL_SUBMISSION;
		$r->body         = $body;
		$r->rating_value = $rating;
		$r->author_name  = 'Anna';
		return $r;
	}

	private function external( string $id, float $rating = 4 ): Review {
		$r               = new Review();
		$r->origin       = Origin::PROVIDER_SYNC;
		$r->platform     = Platform::GOOGLE;
		$r->external_id  = $id;
		$r->body         = 'Extern ' . $id;
		$r->rating_value = $rating;
		$r->verification = Verification::EXTERNAL_PLATFORM;
		$r->source_url   = 'https://maps.google.com/?cid=1';
		return $r;
	}

	public function test_insert_and_find(): void {
		$repo = Plugin::instance()->reviews();
		$r    = $repo->insert( $this->local() );
		$this->assertNotNull( $r->id );
		$found = $repo->find( $r->id );
		$this->assertSame( $r->uuid, $found->uuid );
		$this->assertSame( ReviewStatus::PENDING, $found->status );
		$this->assertSame( 1, did_action( 'review_captain_review_created_v1' ) );
		$this->assertSame( 1, $this->rows( 'review_events' ) );
	}

	public function test_event_payload_has_no_text_or_email(): void {
		$payload = null;
		add_action( 'review_captain_review_created_v1', function ( $p ) use ( &$payload ) { $payload = $p; } );
		$r               = $this->local( 'Geheimer Text der nicht in Events gehört' );
		$r->author_email = 'anna@example.test';
		Plugin::instance()->reviews()->insert( $r );
		$json = json_encode( $payload );
		$this->assertStringNotContainsString( 'Geheimer', $json );
		$this->assertStringNotContainsString( 'anna@', $json );
		$this->assertSame( 'review_captain.review.v1', $payload['schema'] );
	}

	public function test_external_requires_source_url(): void {
		$this->expectException( \InvalidArgumentException::class );
		$r             = $this->external( 'x1' );
		$r->source_url = null;
		Plugin::instance()->reviews()->insert( $r );
	}

	public function test_testimonial_must_be_local(): void {
		$r       = $this->external( 'x2' );
		$r->kind = Kind::TESTIMONIAL;
		$this->assertContains( 'testimonial_must_be_local', $r->validate() );
	}

	public function test_dedupe_key_is_unique_and_upsert_preserves_status(): void {
		$repo  = Plugin::instance()->reviews();
		$first = $repo->upsert_external( $this->external( 'abc' ) );
		$this->assertTrue( $first['created'] );
		$repo->change_status( $first['review']->id, ReviewStatus::APPROVED );

		$second = $repo->upsert_external( $this->external( 'abc', 2 ) );
		$this->assertFalse( $second['created'] );
		$this->assertSame( 1, $repo->count() );
		$reloaded = $repo->find( $first['review']->id );
		$this->assertSame( ReviewStatus::APPROVED, $reloaded->status );
		$this->assertSame( 2.0, $reloaded->rating_value );
	}

	public function test_db_unique_constraint_blocks_duplicate_insert(): void {
		$repo = Plugin::instance()->reviews();
		$repo->insert( $this->external( 'dup' ) );
		$this->expectException( \RuntimeException::class );
		$repo->insert( $this->external( 'dup' ) );
	}

	public function test_status_transition_rules(): void {
		$repo = Plugin::instance()->reviews();
		$r    = $repo->insert( $this->local() );
		$this->assertTrue( $repo->change_status( $r->id, ReviewStatus::APPROVED, 'ok' ) );
		$this->assertFalse( $repo->change_status( $r->id, ReviewStatus::REMOVED ), 'removed is system-only' );
		$this->assertTrue( $repo->change_status( $r->id, ReviewStatus::REMOVED, 'source deleted', true ) );
		$this->assertNotNull( $repo->find( $r->id )->removed_at );
		$this->assertGreaterThanOrEqual( 3, count( $repo->history( $r->id ) ) );
		$this->assertSame( 2, $this->rows( 'audit_log' ) );
	}

	public function test_query_filters_and_pagination(): void {
		$repo = Plugin::instance()->reviews();
		for ( $i = 1; $i <= 5; $i++ ) {
			$r = $repo->insert( $this->local( 'Text Nummer ' . $i, $i ) );
			$repo->change_status( $r->id, ReviewStatus::APPROVED );
		}
		$repo->insert( $this->external( 'g1', 5 ) );

		$this->assertSame( 6, $repo->count() );
		$this->assertSame( 5, $repo->count( array( 'platform' => 'local' ) ) );
		$this->assertSame( 2, $repo->count( array( 'platform' => 'local', 'min_normalized' => 0.75 ) ) );
		$this->assertSame( 1, $repo->count( array( 'search' => 'Nummer 3' ) ) );
		$this->assertCount( 2, $repo->query( array( 'limit' => 2 ) ) );
		$page = $repo->query( array( 'status' => 'approved', 'orderby' => 'rating', 'order' => 'DESC', 'limit' => 1 ) );
		$this->assertSame( 5.0, $page[0]->rating_value );
		$this->assertSame( 0, $repo->count( array( 'ids' => array( 0 ) ) ) );
	}

	public function test_summary_is_per_platform_never_combined(): void {
		$repo = Plugin::instance()->reviews();
		foreach ( array( 4, 5 ) as $v ) {
			$r = $repo->insert( $this->local( 'Lokal Bewertung Text', $v ) );
			$repo->change_status( $r->id, ReviewStatus::APPROVED );
		}
		$g = $repo->insert( $this->external( 'g', 3 ) );
		$repo->change_status( $g->id, ReviewStatus::APPROVED );
		$summary = $repo->summary_by_platform();
		$this->assertCount( 2, $summary );
		$by = array_column( $summary, null, 'platform' );
		$this->assertSame( 4.5, $by['local']['average'] );
		$this->assertSame( 3.0, $by['google']['average'] );
	}

	public function test_sql_injection_in_filters_is_escaped(): void {
		$repo = Plugin::instance()->reviews();
		$repo->insert( $this->local() );
		$this->assertSame( 0, $repo->count( array( 'platform' => "local' OR '1'='1" ) ) );
		$this->assertSame( 0, $repo->count( array( 'search' => "%' OR 1=1 --" ) ) );
	}
}
