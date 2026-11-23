<?php
/**
 * Moderation status.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Domain;

/**
 * Moderation status.
 */
enum ReviewStatus: string {
	case PENDING = 'pending';
	case APPROVED = 'approved';
	case REJECTED = 'rejected';
	case ARCHIVED = 'archived';
	case FLAGGED = 'flagged';
	case REMOVED = 'removed';

	/**
	 * Allowed moderation transitions (RC-040).
	 *
	 * @return list<self>
	 */
	public function allowed_transitions(): array {
		return match ( $this ) {
			self::PENDING  => array( self::APPROVED, self::REJECTED, self::FLAGGED, self::ARCHIVED ),
			self::APPROVED => array( self::ARCHIVED, self::FLAGGED, self::REJECTED, self::PENDING ),
			self::FLAGGED  => array( self::APPROVED, self::REJECTED, self::ARCHIVED ),
			self::REJECTED => array( self::PENDING, self::APPROVED, self::ARCHIVED ),
			self::ARCHIVED => array( self::APPROVED, self::PENDING ),
			self::REMOVED  => array(),
		};
	}

	/**
	 * Whether a transition is allowed. 'removed' is a system tombstone and only set by sync/eraser.
	 *
	 * @param self $to Target.
	 */
	public function can_transition_to( self $to ): bool {
		return in_array( $to, $this->allowed_transitions(), true );
	}

	/**
	 * Whether the record may be shown publicly.
	 */
	public function is_public(): bool {
		return self::APPROVED === $this;
	}

	/**
	 * Translated label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::PENDING  => __( 'Awaiting moderation', 'review-captain' ),
			self::APPROVED => __( 'Approved', 'review-captain' ),
			self::REJECTED => __( 'Rejected', 'review-captain' ),
			self::ARCHIVED => __( 'Archived', 'review-captain' ),
			self::FLAGGED  => __( 'Reported', 'review-captain' ),
			self::REMOVED  => __( 'Removed (source deleted)', 'review-captain' ),
		};
	}

	/**
	 * All values.
	 *
	 * @return list<string>
	 */
	public static function values(): array {
		return array_map( static fn( self $c ): string => $c->value, self::cases() );
	}
}
