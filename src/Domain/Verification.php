<?php
/**
 * Verification state. Only a source contract may set purchase_verified/attendance_confirmed.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Domain;

/**
 * Verification state. Only a source contract may set purchase_verified/attendance_confirmed.
 */
enum Verification: string {
	case UNVERIFIED = 'unverified';
	case PURCHASE_VERIFIED = 'purchase_verified';
	case ATTENDANCE_CONFIRMED = 'attendance_confirmed';
	case EXTERNAL_PLATFORM = 'external_platform';

	/**
	 * Translated label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::UNVERIFIED           => __( 'Ungeprüft', 'review-captain' ),
			self::PURCHASE_VERIFIED    => __( 'Kauf verifiziert', 'review-captain' ),
			self::ATTENDANCE_CONFIRMED => __( 'Teilnahme bestätigt', 'review-captain' ),
			self::EXTERNAL_PLATFORM    => __( 'Externe Plattformbewertung', 'review-captain' ),
		};
	}

	/**
	 * Whether this state can only be set by a trusted source contract (RC-041).
	 */
	public function requires_source_contract(): bool {
		return self::PURCHASE_VERIFIED === $this || self::ATTENDANCE_CONFIRMED === $this;
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
