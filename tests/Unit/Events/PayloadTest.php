<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Events\Events;

/**
 * RC-4: the public review payload carries the rating (contract 1.1.0) and still no personal data or text.
 */
final class PayloadTest extends TestCase {

	private static function review( ?float $rating ): Review {
		return Review::from_row(
			array(
				'id'               => 7,
				'uuid'             => '0b0f3c1e-2a4b-4c5d-8e9f-0123456789ab',
				'kind'             => 'review',
				'platform'         => 'local',
				'origin'           => 'local_submission',
				'status'           => 'approved',
				'verification'     => 'unverified',
				'rating_value'     => null === $rating ? '' : (string) $rating,
				'rating_scale_min' => '1',
				'rating_scale_max' => '5',
				'author_name'      => 'Erika Muster',
				'author_email'     => 'erika@example.com',
				'body'             => 'Geheimer Text',
				'context_type'     => 'product',
				'context_id'       => '42',
			)
		);
	}

	public function test_payload_contains_rating_fields(): void {
		$p = Events::payload( self::review( 4.0 ), 'pending' );
		$this->assertSame( 4.0, $p['rating_value'] );
		$this->assertSame( 1.0, $p['rating_scale_min'] );
		$this->assertSame( 5.0, $p['rating_scale_max'] );
		$this->assertSame( 'pending', $p['previous_status'] );
	}

	public function test_rating_is_null_without_stars(): void {
		$p = Events::payload( self::review( null ) );
		$this->assertArrayHasKey( 'rating_value', $p );
		$this->assertNull( $p['rating_value'] );
	}

	public function test_payload_has_no_personal_data_or_text(): void {
		$encoded = (string) wp_json_encode( Events::payload( self::review( 5.0 ) ) );
		foreach ( array( 'Erika', 'erika@example.com', 'Geheimer Text' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $encoded );
		}
		$this->assertSame(
			array( 'schema', 'event_id', 'review_uuid', 'kind', 'platform', 'origin', 'status', 'previous_status', 'verification', 'rating_value', 'rating_scale_min', 'rating_scale_max', 'context_type', 'context_id', 'experience_id', 'removal', 'occurred_at' ),
			array_keys( Events::payload( self::review( 5.0 ) ) )
		);
	}

	public function test_removal_field(): void {
		$this->assertNull( Events::payload( self::review( 5.0 ) )['removal'] );
		$this->assertSame( 'tombstoned', Events::payload( self::review( 5.0 ), null, 'tombstoned' )['removal'] );

		$seen = array();
		add_action(
			'review_captain_review_removed_v1',
			static function ( $payload ) use ( &$seen ) {
				$seen[] = $payload['removal'];
			}
		);
		$events = new Events();
		$events->review_removed( self::review( 5.0 ), 'tombstoned' );
		$events->review_removed( self::review( 5.0 ) );
		$events->review_removed( self::review( 5.0 ), 'anything' );
		$this->assertSame( array( 'tombstoned', 'deleted', 'deleted' ), $seen );
	}
}
