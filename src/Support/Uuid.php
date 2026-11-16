<?php
/**
 * UUID helper.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Support;

/**
 * RFC 4122 v4 UUIDs.
 */
final class Uuid {

	/**
	 * Generate a v4 UUID.
	 */
	public static function v4(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		$b    = random_bytes( 16 );
		$b[6] = chr( ( ord( $b[6] ) & 0x0f ) | 0x40 );
		$b[8] = chr( ( ord( $b[8] ) & 0x3f ) | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) );
	}

	/**
	 * Validate a UUID string.
	 *
	 * @param string $value Value.
	 */
	public static function is_valid( string $value ): bool {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
