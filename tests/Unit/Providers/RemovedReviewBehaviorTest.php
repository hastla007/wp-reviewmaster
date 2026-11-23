<?php
/**
 * SET-RC-05 (b): sources.removed_review_behavior and (c) advanced.debug_logging.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Providers;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Plugin;
use ReviewCaptain\Providers\FixtureProvider;
use ReviewCaptain\Providers\SyncService;
use ReviewCaptain\Tests\TestCase;

final class RemovedReviewBehaviorTest extends TestCase {

	private const DIR     = REVIEW_CAPTAIN_DIR . 'tests/fixtures/providers/demo';
	private const PROFILE = 7;

	private function apply_removal( SyncService $s ): void {
		$p = new FixtureProvider( self::DIR );
		$s->apply( $p->sync_page( array( 'id' => self::PROFILE ), null, 20 ), self::PROFILE );
		$s->apply( $p->with_pages( array( 'sync_page_removed' ) )->sync_page( array( 'id' => self::PROFILE ), null, 20 ), self::PROFILE );
	}

	private function find( string $external_id ) {
		return Plugin::instance()->reviews()->find_by_dedupe_key( sha1( 'other|' . self::PROFILE . '|' . $external_id ) );
	}

	private function settings( array $values ): void {
		update_option( 'review_captain_settings', $values );
		Plugin::instance()->settings()->flush();
	}

	public function test_default_tombstone_scrubs_content_keeps_identity(): void {
		$s = new SyncService( Plugin::instance()->reviews(), 3600 );
		$this->assertSame( 'tombstone', $s->removed_behavior() );
		$p = new FixtureProvider( self::DIR );
		$s->apply( $p->sync_page( array( 'id' => self::PROFILE ), null, 20 ), self::PROFILE );
		$before = $this->find( 'demo-rev-1001' );
		$this->assertNotSame( '', $before->body );
		// E.g. from a CSV import of the same platform review: contact data must not survive the scrub (P4-4).
		$this->db->update( Plugin::instance()->reviews()->table(), array( 'author_email' => 'kunde@example.test', 'author_user_id' => 12 ), array( 'id' => $before->id ) );

		$this->apply_removal( $s );
		$after = $this->find( 'demo-rev-1001' );
		$this->assertSame( ReviewStatus::REMOVED, $after->status );
		$this->assertSame( '', $after->body );
		$this->assertSame( '', $after->title );
		$this->assertSame( '', $after->author_name );
		$this->assertNull( $after->author_email );
		$this->assertNull( $after->author_user_id );
		$this->assertNull( $after->rating_value );
		$this->assertSame( 'remote_removed', $after->meta['tombstone']['reason'] );
		$this->assertSame( $before->uuid, $after->uuid, 'identity and dedupe key stay' );
		$this->assertSame( $before->source_url, $after->source_url );
		$this->assertContains( 'content_removed', array_column( Plugin::instance()->reviews()->history( (int) $after->id ), 'action' ) );

		// Reappearing at the source restores the content (contract: re-moderation).
		$s->apply( $p->sync_page( array( 'id' => self::PROFILE ), null, 20 ), self::PROFILE );
		$restored = $this->find( 'demo-rev-1001' );
		$this->assertSame( ReviewStatus::PENDING, $restored->status );
		$this->assertSame( $before->body, $restored->body );
	}

	public function test_hide_keeps_content_until_retention(): void {
		$this->settings( array( 'sources' => array( 'removed_review_behavior' => 'hide' ) ) );
		$s = new SyncService( Plugin::instance()->reviews(), 3600 );
		$this->assertSame( 'hide', $s->removed_behavior() );
		$this->apply_removal( $s );
		$after = $this->find( 'demo-rev-1001' );
		$this->assertSame( ReviewStatus::REMOVED, $after->status );
		$this->assertNotSame( '', $after->body );
		$this->assertNotNull( $after->rating_value );
	}

	public function test_filter_can_only_tighten(): void {
		add_filter( 'review_captain_removed_review_behavior', static fn() => 'hide' );
		$this->assertSame( 'tombstone', ( new SyncService( Plugin::instance()->reviews() ) )->removed_behavior() );
		remove_all_filters( 'review_captain_removed_review_behavior' );
		add_filter( 'review_captain_removed_review_behavior', static fn() => 'tombstone' );
		$this->assertSame( 'tombstone', ( new SyncService( Plugin::instance()->reviews(), DAY_IN_SECONDS, 'hide' ) )->removed_behavior() );
	}

	public function test_scrub_never_touches_visible_reviews(): void {
		$s = new SyncService( Plugin::instance()->reviews(), 3600 );
		$s->apply( ( new FixtureProvider( self::DIR ) )->sync_page( array( 'id' => self::PROFILE ), null, 20 ), self::PROFILE );
		$this->assertFalse( Plugin::instance()->reviews()->scrub_removed( (int) $this->find( 'demo-rev-1001' )->id, 'admin' ) );
		$this->assertNotSame( '', $this->find( 'demo-rev-1001' )->body );
	}

	public function test_debug_logging_only_when_enabled_and_without_message(): void {
		update_option( Schema::OPTION_VERSION, Schema::VERSION );
		$lines = array();
		add_filter(
			'review_captain_debug_log_line',
			static function ( string $line ) use ( &$lines ): string {
				$lines[] = $line;
				return '';
			}
		);
		$q = new JobQueue();
		$r = new Runner( $q );
		$r->register_handler(
			'core.fail',
			static function () {
				throw new \RuntimeException( 'secret token abc for max@example.org' );
			}
		);
		$q->enqueue( 'core.fail', array(), 'a' );
		$r->run( 10 );
		$this->assertSame( array(), $lines, 'off by default' );

		$this->settings( array( 'advanced' => array( 'debug_logging' => true ) ) );
		$q->enqueue( 'core.fail', array(), 'b' );
		$r->run( 10 );
		$this->assertCount( 1, $lines );
		$this->assertMatchesRegularExpression( '/^Review Captain \[debug\]: job \d+ \(core\.fail, attempt 1\) failed: rc\.[a-z_.]+ \(RuntimeException\)$/', $lines[0] );
		$this->assertStringNotContainsString( 'secret', $lines[0] );
		$this->assertStringNotContainsString( '@', $lines[0] );
	}
}
