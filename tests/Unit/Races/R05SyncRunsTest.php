<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Plugin;
use ReviewCaptain\Providers\SyncPage;
use ReviewCaptain\Providers\SyncService;

/**
 * R-05 (core part): applying sync pages is idempotent. Run locking (`sync_runs`, rc.lock.busy)
 * and cursor CAS live in Pro (captain-pro tests/Addons/ReviewSync*Test.php).
 */
final class R05SyncRunsTest extends RaceTestCase {

	private function service(): SyncService {
		return new SyncService( Plugin::instance()->reviews(), 3600 );
	}

	public function test_page_applied_twice_after_lease_expiry_creates_no_duplicates(): void {
		$page = new SyncPage( array( $this->external( 'a' ), $this->external( 'b' ) ) );
		$s    = $this->service();
		$s->apply( $page, 3 );
		// Second worker re-runs the same page job after the lease expired.
		$again = $s->apply( $page, 3 );
		$this->assertSame( 0, $again['created'] );
		$this->assertSame( 2, $this->rows( 'reviews' ) );
	}

	public function test_same_review_on_two_pages_is_stored_once(): void {
		$s = $this->service();
		$s->apply( new SyncPage( array( $this->external( 'shifted' ) ) ), 3 );
		$r = $s->apply( new SyncPage( array( $this->external( 'shifted' ), $this->external( 'c' ) ) ), 3 );
		$this->assertSame( 1, $r['created'] );
		$this->assertSame( 2, $this->rows( 'reviews' ) );
	}

	public function test_two_workers_applying_the_same_page_concurrently_store_each_review_once(): void {
		$s    = $this->service();
		$page = new SyncPage( array( $this->external( 'p1' ) ) );
		$this->race->before(
			'/^INSERT INTO wp_review_captain_reviews /',
			function () use ( $s, $page ): void {
				$s->apply( $page, 3 );
			}
		);
		$s->apply( $page, 3 );
		$this->assertSame( 1, $this->race->fired );
		$this->assertSame( 1, $this->rows( 'reviews' ) );
		$this->assertSame( ReviewStatus::PENDING, Plugin::instance()->reviews()->query( array() )[0]->status );
	}
}
