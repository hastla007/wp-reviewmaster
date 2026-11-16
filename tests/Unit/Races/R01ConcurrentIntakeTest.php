<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Plugin;

/**
 * R-01 (core part): the same record delivered twice/in parallel ends up exactly once – enforced by
 * UNIQUE keys, not by a prior SELECT (TC-R01-a, TC-R01-d). Event/experience tables are Pro.
 */
final class R01ConcurrentIntakeTest extends RaceTestCase {

	public function test_two_parallel_upsert_external_create_one_row(): void {
		$repo   = Plugin::instance()->reviews();
		$second = null;
		// Worker B inserts the same review between A's dedupe lookup and A's INSERT.
		$this->race->before(
			'/^INSERT INTO wp_review_captain_reviews /',
			function () use ( $repo, &$second ): void {
				$second = $repo->upsert_external( $this->external( 'tp-1' ) );
			}
		);
		$first = $repo->upsert_external( $this->external( 'tp-1', 'Toller Service, Version A' ) );

		$this->assertSame( 1, $this->race->fired );
		$this->assertTrue( $second['created'] );
		$this->assertFalse( $first['created'], 'A lost the INSERT race and must update instead' );
		$this->assertSame( $second['review']->uuid, $first['review']->uuid );
		$this->assertSame( 1, $this->rows( 'reviews' ) );
		$created = (int) $this->race->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'review_events' ) . " WHERE action = 'created'" );
		$this->assertSame( 1, $created, 'exactly one creation event' );
		$this->assertSame( 'Toller Service, Version A', $repo->find_by_dedupe_key( (string) $first['review']->dedupe_key() )->body );
	}

	public function test_parallel_enqueue_with_same_idempotency_key_yields_one_job(): void {
		$q     = new JobQueue();
		$other = null;
		$this->race->before(
			'/^INSERT INTO wp_review_captain_jobs /',
			static function () use ( $q, &$other ): void {
				$other = $q->enqueue( 'acme.sync', array( 'n' => 2 ), 'acme:event:1' );
			}
		);
		$id = $q->enqueue( 'acme.sync', array( 'n' => 1 ), 'acme:event:1' );
		$this->assertSame( $other, $id );
		$this->assertSame( 1, $this->rows( 'jobs' ) );
	}

	public function test_ten_deliveries_of_the_same_workflow_draft_create_one_review(): void {
		$wf       = Plugin::instance()->workflow();
		$statuses = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$statuses[] = $wf->create_draft( $this->draft( 'form-captain:1:5531' ) )['status'];
		}
		$this->assertSame( 1, $this->rows( 'reviews' ) );
		$this->assertSame( array( 'created' => 1, 'duplicate' => 9 ), array_count_values( $statuses ) );
	}

	public function test_interleaved_workflow_draft_returns_duplicate_with_same_review(): void {
		$wf    = Plugin::instance()->workflow();
		$other = null;
		$this->race->before(
			'/^INSERT INTO wp_review_captain_reviews /',
			function () use ( $wf, &$other ): void {
				$other = $wf->create_draft( $this->draft( 'wf:run:1' ) );
			}
		);
		$mine = $wf->create_draft( $this->draft( 'wf:run:1' ) );
		$this->assertSame( 'created', $other['status'] );
		$this->assertSame( 'duplicate', $mine['status'] );
		$this->assertTrue( $mine['success'] );
		$this->assertSame( $other['data']['review_uuid'], $mine['data']['review_uuid'] );
		$this->assertSame( 1, $this->rows( 'reviews' ) );
	}

	/**
	 * Draft input.
	 *
	 * @param string $key Idempotency key.
	 * @return array<string, mixed>
	 */
	private function draft( string $key ): array {
		return array(
			'contract'        => '1.0',
			'idempotency_key' => $key,
			'rating'          => 4,
			'review_text'     => 'Kurs war gut strukturiert und verständlich.',
			'display_name'    => 'Lena',
			'source_plugin'   => 'form-captain',
			'source_entry_id' => '5531',
			'verified'        => false,
		);
	}
}
