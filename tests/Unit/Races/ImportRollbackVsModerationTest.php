<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\ImportExport\CsvImporter;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Maintenance\Reconciler;
use ReviewCaptain\Moderation\TagStore;
use ReviewCaptain\Plugin;

/**
 * Import rollback while a moderator works on the same batch: the rollback is an explicit
 * operator decision and wins; nothing of the deleted reviews may survive (history, tags,
 * reports). A moderation write that slips in after the delete leaves at most orphan history rows,
 * which the reconciliation removes.
 */
final class ImportRollbackVsModerationTest extends RaceTestCase {

	private int $batch = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->race->insert(
			Schema::table( 'import_batches' ),
			array(
				'filename'   => 'test.csv',
				'status'     => 'completed',
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$this->batch = (int) $this->race->insert_id;
	}

	private function imported( string $external_id ): int {
		$r                  = $this->external( $external_id );
		$r->origin          = Origin::CSV_IMPORT;
		$r->import_batch_id = $this->batch;
		return (int) Plugin::instance()->reviews()->insert( $r )->id;
	}

	public function test_approval_during_rollback_does_not_keep_the_review(): void {
		$repo = Plugin::instance()->reviews();
		$a    = $this->imported( 'i1' );
		$b    = $this->imported( 'i2' );
		( new TagStore() )->set( $b, 'pruefen' );
		$approved = null;
		// Moderator approves review B after the rollback selected the batch IDs.
		$this->race->before(
			'/^DELETE FROM wp_review_captain_reviews WHERE "id" = ' . $a . '/',
			static function () use ( $repo, $b, &$approved ): void {
				$approved = $repo->change_status( $b, ReviewStatus::APPROVED );
			}
		);
		$deleted = ( new CsvImporter( $repo ) )->rollback( $this->batch );
		$this->assertTrue( $approved );
		$this->assertSame( 2, $deleted );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
		$this->assertSame( 0, $this->orphans() );
		$this->assertFalse( $repo->change_status( $b, ReviewStatus::REJECTED, 'zu spät' ), 'moderating a deleted review fails cleanly' );
		$this->assertSame( 0, ( new CsvImporter( $repo ) )->rollback( $this->batch ), 'rollback is idempotent' );
	}

	public function test_history_written_after_the_delete_is_cleaned_by_reconciliation(): void {
		$repo = Plugin::instance()->reviews();
		$id   = $this->imported( 'i3' );
		// The status UPDATE succeeded; before its history INSERT the rollback deletes the review.
		$this->race->before(
			"/^INSERT INTO wp_review_captain_review_events .*'status_changed'/",
			function () use ( $repo ): void {
				( new CsvImporter( $repo ) )->rollback( $this->batch );
			}
		);
		$repo->change_status( $id, ReviewStatus::APPROVED );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
		$this->assertSame( 1, $this->orphans(), 'one orphan history row' );

		$report = ( new Reconciler( Plugin::instance(), new JobQueue() ) )->run( false );
		$this->assertSame( 1, $report['orphan_rows'] );
		$fixed = ( new Reconciler( Plugin::instance(), new JobQueue() ) )->run( true );
		$this->assertSame( 1, $fixed['fixed']['orphans_removed'] );
		$this->assertSame( 0, $this->orphans() );
	}
}
