<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Providers;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Providers\FixtureProvider;
use ReviewCaptain\Providers\SyncPage;
use ReviewCaptain\Providers\SyncService;
use ReviewCaptain\Tests\TestCase;

final class SyncServiceTest extends TestCase {

	private const DIR = REVIEW_CAPTAIN_DIR . 'tests/fixtures/providers/demo';
	private const PROFILE = 7;

	private function provider(): FixtureProvider {
		return new FixtureProvider( self::DIR );
	}

	private function service(): SyncService {
		return new SyncService( Plugin::instance()->reviews(), 3600 );
	}

	private function page( ?string $cursor = null, ?FixtureProvider $p = null ): SyncPage {
		return ( $p ?? $this->provider() )->sync_page( array( 'id' => self::PROFILE ), $cursor, 20 );
	}

	private function find( string $external_id ) {
		return Plugin::instance()->reviews()->find_by_dedupe_key( sha1( 'other|' . self::PROFILE . '|' . $external_id ) );
	}

	public function test_create_then_duplicate_over_page_boundary(): void {
		$s  = $this->service();
		$r1 = $s->apply( $this->page(), self::PROFILE );
		$this->assertSame( 2, $r1['created'] );
		$r2 = $s->apply( $this->page( 'eyJwIjoyfQ' ), self::PROFILE );
		$this->assertSame( 1, $r2['created'] );
		$this->assertSame( 1, $r2['unchanged'] );
		$this->assertSame( 3, $this->rows( 'reviews' ) );

		$review = $this->find( 'demo-rev-1001' );
		$this->assertSame( Origin::PROVIDER_SYNC, $review->origin );
		$this->assertSame( Verification::EXTERNAL_PLATFORM, $review->verification );
		$this->assertSame( self::PROFILE, $review->profile_id );
		$this->assertSame( ReviewStatus::PENDING, $review->status );
		$this->assertFalse( $review->consent_publish );
		$this->assertNotNull( $review->fetched_at );
		$this->assertEqualsWithDelta( time() + 3600, strtotime( $review->refresh_due_at . ' UTC' ), 5 );
	}

	public function test_idempotent_reapply(): void {
		$s = $this->service();
		$s->apply( $this->page(), self::PROFILE );
		$again = $s->apply( $this->page(), self::PROFILE );
		$this->assertSame( array( 'created' => 0, 'updated' => 0, 'unchanged' => 2, 'removed' => 0, 'invalid' => 0 ), $again );
		$this->assertSame( 2, $this->rows( 'reviews' ) );
	}

	public function test_change_updates_content_and_keeps_moderation_status(): void {
		$s = $this->service();
		$s->apply( $this->page(), self::PROFILE );
		$id = $this->find( 'demo-rev-1001' )->id;
		Plugin::instance()->reviews()->change_status( $id, ReviewStatus::APPROVED );
		$r = $s->apply( $this->page( null, $this->provider()->with_pages( array( 'sync_page_changed' ) ) ), self::PROFILE );
		$this->assertSame( 1, $r['updated'] );
		$review = $this->find( 'demo-rev-1001' );
		$this->assertSame( 3.0, $review->rating_value );
		$this->assertStringContainsString( 'Verpackung', $review->body );
		$this->assertSame( ReviewStatus::APPROVED, $review->status );
		$this->assertSame( $id, $review->id );
	}

	public function test_tombstones_for_removed_ids(): void {
		$s = $this->service();
		$s->apply( $this->page(), self::PROFILE );
		$removed_events = 0;
		add_action( 'review_captain_review_removed_v1', static function () use ( &$removed_events ) { ++$removed_events; } );
		$page = $this->page( null, $this->provider()->with_pages( array( 'sync_page_removed' ) ) );
		$this->assertSame( array( 'demo-rev-1001', 'demo-rev-unknown' ), $page->removed_external_ids );
		$r = $s->apply( $page, self::PROFILE );
		$this->assertSame( 1, $r['removed'], 'unknown IDs are ignored' );
		$review = $this->find( 'demo-rev-1001' );
		$this->assertSame( ReviewStatus::REMOVED, $review->status );
		$this->assertNotNull( $review->removed_at );
		$this->assertSame( 1, $removed_events );
		$this->assertSame( ReviewStatus::PENDING, $this->find( 'demo-rev-1002' )->status, 'other reviews untouched' );

		$again = $s->apply( $page, self::PROFILE );
		$this->assertSame( 0, $again['removed'], 'tombstoning is idempotent' );
		$this->assertSame( 1, $removed_events );
	}

	public function test_tombstone_uses_profile_platform_when_page_has_no_reviews(): void {
		global $wpdb;
		$wpdb->insert( Schema::table( 'profiles' ), array( 'id' => self::PROFILE, 'platform' => 'other', 'label' => 'Demo', 'status' => 'connected', 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) );
		$s = $this->service();
		$s->apply( $this->page(), self::PROFILE );
		$r = $s->apply( new SyncPage( array(), null, array( 'demo-rev-1002' ) ), self::PROFILE );
		$this->assertSame( 1, $r['removed'] );
	}

	public function test_reappearing_review_is_restored(): void {
		$s = $this->service();
		$s->apply( $this->page(), self::PROFILE );
		$s->apply( $this->page( null, $this->provider()->with_pages( array( 'sync_page_removed' ) ) ), self::PROFILE );
		$r = $s->apply( $this->page(), self::PROFILE );
		$this->assertSame( 1, $r['updated'] );
		$review = $this->find( 'demo-rev-1001' );
		$this->assertSame( ReviewStatus::PENDING, $review->status );
		$this->assertNull( $review->removed_at );
	}

	public function test_invalid_items_are_counted_not_fatal(): void {
		$r = $this->service()->apply( $this->page( null, $this->provider()->with_pages( array( 'invalid_payload' ) ) ), self::PROFILE );
		$this->assertSame( 1, $r['created'] );
		$this->assertSame( 2, $r['invalid'], 'rating out of scale + missing source link' );
		$this->assertSame( 1, $this->rows( 'reviews' ) );
	}

	public function test_provider_cannot_smuggle_personal_data_or_verification(): void {
		$review                  = $this->page()->reviews[0];
		$review->author_email    = 'x@example.test';
		$review->verification    = Verification::PURCHASE_VERIFIED;
		$review->consent_publish = true;
		$review->origin          = Origin::MANUAL;
		$this->service()->apply( new SyncPage( array( $review ) ), self::PROFILE );
		$stored = $this->find( 'demo-rev-1001' );
		$this->assertNull( $stored->author_email );
		$this->assertSame( Verification::EXTERNAL_PLATFORM, $stored->verification );
		$this->assertFalse( $stored->consent_publish );
		$this->assertSame( Origin::PROVIDER_SYNC, $stored->origin );
	}

	public function test_sync_page_value_object_validation(): void {
		$this->expectException( \InvalidArgumentException::class );
		new SyncPage( array( 'not a review' ) );
	}
}
