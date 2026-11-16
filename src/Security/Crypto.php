<?php
/**
 * Secret encryption with libsodium (TEC-008, docs/m1/auth-und-secrets.md).
 *
 * Format: rc1:{key_id}:{base64(nonce|ciphertext)}
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Security;

defined( 'ABSPATH' ) || exit;

final class Crypto {

	public const PREFIX = 'rc1';

	public static function is_available(): bool {
		return function_exists( 'sodium_crypto_secretbox' );
	}

	/**
	 * True when an explicit key constant is configured (recommended).
	 */
	public static function has_explicit_key(): bool {
		return defined( 'REVIEW_CAPTAIN_ENCRYPTION_KEY' ) && null !== self::decode_key( (string) constant( 'REVIEW_CAPTAIN_ENCRYPTION_KEY' ) );
	}

	public static function encrypt( string $plaintext ): string {
		$key   = self::current_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $plaintext, $nonce, $key );
		return self::PREFIX . ':' . self::key_id( $key ) . ':' . base64_encode( $nonce . $box ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * @throws CryptoException When the key is missing or the payload is invalid.
	 */
	public static function decrypt( string $payload ): string {
		$parts = explode( ':', $payload, 3 );
		if ( 3 !== count( $parts ) || self::PREFIX !== $parts[0] ) {
			throw new CryptoException( 'invalid_payload' );
		}
		$key = self::key_for_id( $parts[1] );
		if ( null === $key ) {
			throw new CryptoException( 'crypto_key_missing' );
		}
		$raw = base64_decode( $parts[2], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			throw new CryptoException( 'invalid_payload' );
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
		if ( false === $plain ) {
			throw new CryptoException( 'crypto_key_missing' );
		}
		return $plain;
	}

	/**
	 * Re-encrypt a payload with the current key (rotation).
	 */
	public static function rotate( string $payload ): string {
		return self::encrypt( self::decrypt( $payload ) );
	}

	public static function needs_rotation( string $payload ): bool {
		$parts = explode( ':', $payload, 3 );
		return 3 === count( $parts ) && self::key_id( self::current_key() ) !== $parts[1];
	}

	/**
	 * Keyed, non-reversible hash for lookups (e-mail hashes for privacy export/suppression).
	 */
	public static function lookup_hash( string $value ): string {
		return hash_hmac( 'sha256', strtolower( trim( $value ) ), self::hash_key() );
	}

	private static function hash_key(): string {
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'review-captain';
		return hash_hkdf( 'sha256', $salt . get_option( 'siteurl' ), 32, 'review-captain-lookup-v1' );
	}

	private static function current_key(): string {
		if ( defined( 'REVIEW_CAPTAIN_ENCRYPTION_KEY' ) ) {
			$key = self::decode_key( (string) constant( 'REVIEW_CAPTAIN_ENCRYPTION_KEY' ) );
			if ( null !== $key ) {
				return $key;
			}
		}
		return self::derived_key();
	}

	private static function key_for_id( string $id ): ?string {
		$candidates = array( self::current_key(), self::derived_key() );
		if ( defined( 'REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS' ) ) {
			$previous = self::decode_key( (string) constant( 'REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS' ) );
			if ( null !== $previous ) {
				$candidates[] = $previous;
			}
		}
		foreach ( $candidates as $key ) {
			if ( hash_equals( self::key_id( $key ), $id ) ) {
				return $key;
			}
		}
		return null;
	}

	private static function derived_key(): string {
		$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' );
		return hash_hkdf( 'sha256', '' !== $material ? $material : 'review-captain-insecure-fallback', SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'review-captain-v1' );
	}

	private static function decode_key( string $encoded ): ?string {
		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return ( false !== $raw && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen( $raw ) ) ? $raw : null;
	}

	private static function key_id( string $key ): string {
		return substr( hash( 'sha256', $key ), 0, 8 );
	}
}
