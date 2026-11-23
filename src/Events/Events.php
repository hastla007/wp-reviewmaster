<?php
/**
 * Post-commit public events (RC-WF-02 core part).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Events;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Support\Uuid;

/**
 * Dispatches versioned, data-minimised events after a write was committed.
 * Payloads never contain review text, e-mail addresses or tokens.
 */
class Events {

	public const SCHEMA = 'review_captain.review.v1';

	/**
	 * Build the public payload for a review.
	 *
	 * @param Review      $review          Review.
	 * @param string|null $previous_status Previous status.
	 * @return array<string, mixed>
	 */
	public static function payload( Review $review, ?string $previous_status = null ): array {
		return array(
			'schema'           => self::SCHEMA,
			'event_id'         => Uuid::v4(),
			'review_uuid'      => $review->uuid,
			'kind'             => $review->kind->value,
			'platform'         => $review->platform->value,
			'origin'           => $review->origin->value,
			'status'           => $review->status->value,
			'previous_status'  => $previous_status,
			'verification'     => $review->verification->value,
			// Since contract 1.1.0 (RC-4): rating on the review's own scale, null without rating.
			'rating_value'     => $review->rating_value,
			'rating_scale_min' => $review->rating_scale_min,
			'rating_scale_max' => $review->rating_scale_max,
			'context_type'     => $review->context_type,
			'context_id'       => $review->context_id,
			'experience_id'    => $review->experience_id,
			'occurred_at'      => gmdate( 'c' ),
		);
	}

	/**
	 * Review created.
	 *
	 * @param Review $review Review.
	 */
	public function review_created( Review $review ): void {
		do_action( 'review_captain_review_created_v1', self::payload( $review ) );
	}

	/**
	 * Status changed.
	 *
	 * @param Review $review   Review (new state).
	 * @param string $previous Previous status.
	 */
	public function review_status_changed( Review $review, string $previous ): void {
		do_action( 'review_captain_review_status_changed_v1', self::payload( $review, $previous ) );
	}

	/**
	 * Review removed (tombstone or erase).
	 *
	 * @param Review $review Review.
	 */
	public function review_removed( Review $review ): void {
		do_action( 'review_captain_review_removed_v1', self::payload( $review ) );
	}
}
