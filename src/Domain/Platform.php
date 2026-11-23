<?php
/**
 * Review platform. Platform 'local' means collected on this website.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Domain;

/**
 * Review platform. Platform 'local' means collected on this website.
 */
enum Platform: string {
	case LOCAL = 'local';
	case GOOGLE = 'google';
	case TRUSTPILOT = 'trustpilot';
	case TRUSTED_SHOPS = 'trusted_shops';
	case APP_STORE = 'app_store';
	case GOOGLE_PLAY = 'google_play';
	case OTHER = 'other';

	/**
	 * Human readable label (platform names are proper nouns, not translated).
	 */
	public function label(): string {
		return match ( $this ) {
			self::LOCAL         => __( 'This website', 'review-captain' ),
			self::GOOGLE        => 'Google',
			self::TRUSTPILOT    => 'Trustpilot',
			self::TRUSTED_SHOPS => 'Trusted Shops',
			self::APP_STORE     => 'App Store',
			self::GOOGLE_PLAY   => 'Google Play',
			self::OTHER         => __( 'Other platform', 'review-captain' ),
		};
	}

	/**
	 * Whether reviews from this platform were collected on a third-party site.
	 */
	public function is_external(): bool {
		return self::LOCAL !== $this;
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
