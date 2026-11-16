<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\Support\InterleavingWpdb;
use ReviewCaptain\Tests\TestCase;

/**
 * Base for TEC-005 race tests: real schema on an interleaving wpdb.
 */
abstract class RaceTestCase extends TestCase {

	protected InterleavingWpdb $race;

	protected function setUp(): void {
		parent::setUp();
		$this->race      = new InterleavingWpdb();
		$GLOBALS['wpdb'] = $this->race;
		$this->db        = $this->race;
		foreach ( Schema::definitions() as $sql ) {
			$this->race->create_from_mysql( $sql );
		}
		update_option( Schema::OPTION_VERSION, Schema::VERSION );
		Plugin::reset_for_tests();
	}

	/**
	 * External review fixture (provider sync shape).
	 *
	 * @param string $external_id External ID.
	 * @param string $body        Body.
	 */
	protected function external( string $external_id, string $body = 'Toller Service' ): Review {
		$r               = new Review();
		$r->platform     = Platform::TRUSTPILOT;
		$r->profile_id   = 3;
		$r->external_id  = $external_id;
		$r->source_url   = 'https://www.trustpilot.com/reviews/' . $external_id;
		$r->rating_value = 4.0;
		$r->body         = $body;
		$r->author_name  = 'Kim';
		$r->status       = ReviewStatus::PENDING;
		return $r;
	}

	/**
	 * Local review fixture.
	 *
	 * @param ReviewStatus $status Status.
	 * @param string       $email  E-mail.
	 */
	protected function local( ReviewStatus $status = ReviewStatus::PENDING, string $email = 'anna@example.test' ): Review {
		$r                  = new Review();
		$r->rating_value    = 5.0;
		$r->body            = 'Sehr freundliche Beratung und schnelle Lieferung.';
		$r->author_name     = 'Anna';
		$r->author_email    = $email;
		$r->status          = $status;
		$r->consent_publish = true;
		return Plugin::instance()->reviews()->insert( $r );
	}

	/**
	 * Orphan rows (children without review).
	 */
	protected function orphans(): int {
		$reviews = Schema::table( 'reviews' );
		$n       = 0;
		foreach ( array( 'review_events', 'review_tags', 'review_reports', 'review_media' ) as $key ) {
			$n += (int) $this->race->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( $key ) . " c LEFT JOIN {$reviews} r ON r.id = c.review_id WHERE r.id IS NULL" );
		}
		return $n;
	}
}
