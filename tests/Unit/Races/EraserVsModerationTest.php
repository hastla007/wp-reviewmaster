<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Plugin;
use ReviewCaptain\Privacy\PersonalData;

/**
 * Privacy eraser vs. moderation: the erasure must win and a moderator holding an old copy of the
 * review must not be able to write the personal data back (optimistic lock on `revision`).
 */
final class EraserVsModerationTest extends RaceTestCase {

	private function eraser(): PersonalData {
		return new PersonalData( Plugin::instance()->reviews(), Plugin::instance()->settings() );
	}

	public function test_stale_editor_cannot_restore_anonymised_name(): void {
		$repo    = Plugin::instance()->reviews();
		$id      = (int) $this->local( ReviewStatus::APPROVED )->id;
		$opened  = $repo->find( $id ); // Moderator opened the edit screen before the erasure.
		$result  = $this->eraser()->erase( 'anna@example.test' );
		$this->assertTrue( $result['items_removed'] );

		$opened->body = 'Kleine Korrektur durch die Moderation, alter Stand.';
		$this->assertFalse( $repo->update( $opened, false, true ), 'stale revision is rejected' );
		$stored = $repo->find( $id );
		$this->assertSame( 'Anonymous', $stored->author_name );
		$this->assertNull( $stored->author_email );
		$this->assertSame( ReviewStatus::APPROVED, $stored->status, 'eraser never changes the moderation status' );
	}

	public function test_moderation_during_delete_mode_erasure_leaves_nothing_behind(): void {
		$settings = Plugin::instance()->settings();
		$all      = $settings->all();
		$all['privacy']['erase_mode'] = 'delete';
		$settings->save( $all );
		$repo = Plugin::instance()->reviews();
		$id   = (int) $this->local()->id;
		$this->race->before(
			'/^DELETE FROM wp_review_captain_reviews /',
			static function () use ( $repo, $id ): void {
				$repo->change_status( $id, ReviewStatus::APPROVED );
			}
		);
		$this->eraser()->erase( 'anna@example.test' );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
		$this->assertSame( 0, $this->orphans() );
	}

	public function test_anonymisation_removes_the_photo(): void {
		$repo = Plugin::instance()->reviews();
		$id   = (int) $this->local()->id;
		$this->assertTrue( PhotoStore::store( $id, array( 'bytes' => 'fake-jpeg', 'mime' => 'image/jpeg', 'width' => 1, 'height' => 1 ) ) );
		$file = PhotoStore::path( (string) PhotoStore::for_review( $id )['file'] );
		$this->assertFileExists( $file );
		$this->eraser()->erase( 'anna@example.test' );
		PhotoStore::flush();
		$this->assertNull( PhotoStore::for_review( $id ) );
		$this->assertFileDoesNotExist( $file );
		$this->assertNotNull( $repo->find( $id ) );
	}
}
