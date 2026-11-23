<?php
/**
 * Review record kind.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Domain;

/**
 * Review record kind.
 */
enum Kind: string {
	case REVIEW = 'review';
	case TESTIMONIAL = 'testimonial';

	/**
	 * Translated label.
	 */
	public function label(): string {
		return self::TESTIMONIAL === $this ? __( 'Testimonial (website)', 'review-captain' ) : __( 'Rating', 'review-captain' );
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
