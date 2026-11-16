<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use ReviewCaptain\Admin\ReviewActions;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Providers\SyncPage;
use ReviewCaptain\Providers\SyncService;
use ReviewCaptain\Security\Capabilities;

/**
 * R-12: parallel moderation.
 *
 * - status race: CAS on the status read (`UPDATE … WHERE status = <read>`), the loser gets
 *   "status_failed";
 * - TC-R12-a: two moderators edit the same content → the second save is rejected (optimistic lock
 *   on `reviews.revision`, new in schema v2) instead of silently overwriting the first;
 * - TC-R12-b: a sync content update and a moderation decision at the same time → both survive.
 *   Fixed with this test: upsert_external used to write back the status it had read, which
 *   reverted a moderation decision taken in between.
 */
final class R12ParallelModerationTest extends RaceTestCase {

	public function test_status_change_race_has_exactly_one_winner(): void {
		$repo = Plugin::instance()->reviews();
		$id   = (int) $this->local()->id;
		$b    = null;
		$this->race->before(
			"/^UPDATE wp_review_captain_reviews SET .*\"status\" = 'approved'/",
			static function () use ( $repo, $id, &$b ): void {
				$b = $repo->change_status( $id, ReviewStatus::REJECTED, 'Spam' );
			}
		);
		$a = $repo->change_status( $id, ReviewStatus::APPROVED );
		$this->assertTrue( $b );
		$this->assertFalse( $a, 'A read "pending", the CAS fails because B changed it' );
		$this->assertSame( ReviewStatus::REJECTED, $repo->find( $id )->status );
		$changes = (int) $this->race->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'review_events' ) . " WHERE action = 'status_changed'" );
		$this->assertSame( 1, $changes );
	}

	public function test_admin_handler_reports_failed_status_change_after_concurrent_decision(): void {
		$repo = Plugin::instance()->reviews();
		$id   = (int) $this->local()->id;
		$this->race->before(
			"/^UPDATE wp_review_captain_reviews SET .*\"status\" = 'approved'/",
			static function () use ( $repo, $id ): void {
				$repo->change_status( $id, ReviewStatus::FLAGGED );
			}
		);
		$this->assertSame( 'status_failed', ( new ReviewActions( Plugin::instance() ) )->change_status( $id, ReviewStatus::APPROVED, '' ) );
		$this->assertSame( ReviewStatus::FLAGGED, $repo->find( $id )->status );
	}

	public function test_second_editor_with_stale_revision_is_rejected(): void {
		$repo  = Plugin::instance()->reviews();
		$id    = (int) $this->local()->id;
		$first = $repo->find( $id );
		$late  = $repo->find( $id );

		$first->body = 'Fassung von Moderatorin A, inhaltlich korrigiert.';
		$this->assertTrue( $repo->update( $first, false, true ) );
		$this->assertSame( 1, $first->revision );

		$late->body = 'Fassung von Moderator B auf altem Stand.';
		$this->assertFalse( $repo->update( $late, false, true ) );
		$this->assertSame( 'Fassung von Moderatorin A, inhaltlich korrigiert.', $repo->find( $id )->body );
	}

	public function test_edit_form_with_stale_revision_shows_conflict(): void {
		\RC_Test_State::$is_admin = true;
		$this->login_with( Capabilities::MODERATE );
		$repo = Plugin::instance()->reviews();
		$id   = (int) $this->local()->id;
		$sync = $repo->find( $id );
		$sync->body = 'Zwischenzeitlich von jemand anderem geändert.';
		$repo->update( $sync );

		$post = array(
			'action'   => 'review_captain_save_review',
			'id'       => (string) $id,
			'revision' => '0',
			'review'   => array(
				'type'         => 'local',
				'rating_value' => '3',
				'body'         => 'Mein veralteter Entwurf mit genug Text.',
				'author_name'  => 'Anna',
			),
		);
		$post['_rc_nonce'] = wp_create_nonce( 'review_captain_save_review' );
		$_POST             = $post;
		$_REQUEST          = $post;
		try {
			( new ReviewActions( Plugin::instance() ) )->handle_save();
			$this->fail( 'redirect expected' );
		} catch ( \RC_Test_Die $e ) {
			$this->assertStringContainsString( 'rc_notice=review_conflict', $e->getMessage() );
		}
		$this->assertSame( 'Zwischenzeitlich von jemand anderem geändert.', $repo->find( $id )->body );
	}

	public function test_sync_content_update_and_concurrent_approval_both_survive(): void {
		$repo = Plugin::instance()->reviews();
		$s    = new SyncService( $repo, 3600 );
		$s->apply( new SyncPage( array( $this->external( 'x1', 'Erste Fassung' ) ) ), 3 );
		$id = (int) $repo->query( array() )[0]->id;

		// Moderator approves between the sync's read and its content UPDATE.
		$this->race->before(
			'/^UPDATE wp_review_captain_reviews SET .*revision = revision \+ 1/',
			static function () use ( $repo, $id ): void {
				$repo->change_status( $id, ReviewStatus::APPROVED, 'ok' );
			}
		);
		$s->apply( new SyncPage( array( $this->external( 'x1', 'Vom Autor geänderte Fassung' ) ) ), 3 );

		$this->assertSame( 1, $this->race->fired );
		$review = $repo->find( $id );
		$this->assertSame( ReviewStatus::APPROVED, $review->status, 'moderation decision is kept' );
		$this->assertSame( 'Vom Autor geänderte Fassung', $review->body, 'content update is kept' );
		$actions = $this->race->get_col( 'SELECT action FROM ' . Schema::table( 'review_events' ) . " WHERE review_id = {$id} ORDER BY id" );
		$this->assertSame( array( 'created', 'status_changed' ), $actions );
	}

	public function test_restore_of_removed_review_uses_status_cas(): void {
		$repo = Plugin::instance()->reviews();
		$s    = new SyncService( $repo, 3600 );
		$s->apply( new SyncPage( array( $this->external( 'gone' ) ) ), 3 );
		$id = (int) $repo->query( array() )[0]->id;
		$s->apply( new SyncPage( array(), null, array( 'gone' ) ), 3 );
		$this->assertSame( ReviewStatus::REMOVED, $repo->find( $id )->status );

		$s->apply( new SyncPage( array( $this->external( 'gone', 'Wieder da' ) ) ), 3 );
		$review = $repo->find( $id );
		$this->assertSame( ReviewStatus::PENDING, $review->status );
		$this->assertNull( $review->removed_at );
		$this->assertSame( 'Wieder da', $review->body );
		$last = $repo->history( $id )[0];
		$this->assertSame( 'remote_restored', $last['reason'] );
	}
}
