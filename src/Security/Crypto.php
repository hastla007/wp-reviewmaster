<?php
/**
 * Secret encryption with libsodium secretbox (TEC-008, ADR-0005, docs/architecture/secrets.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Security;

use ReviewCaptain\Support\ErrorCode;

/**
 * Envelope: `rc1:{key_id}:{base64( nonce ‖ ciphertext )}`, key_id = first 16 hex chars of
 * generichash('review-captain|key-id|' . key). The encrypted plaintext is a JSON envelope
 * `{"v":1,"ctx":{…},"d":"…"}`; a context given on decrypt must match the stored one
 * (protects against copying ciphertexts between profiles/sites).
 *
 * Never logs or returns plaintext in exception messages.
 */
class Crypto {

	public const PREFIX = 'rc1';

	public const STATUS_EXPLICIT    = 'explicit';
	public const STATUS_FALLBACK    = 'fallback';
	public const STATUS_INVALID     = 'invalid';
	public const STATUS_UNAVAILABLE = 'unavailable';

	public const OPTION_KEY_CHECK = 'review_captain_key_check';

	private const KEY_CHECK_PLAINTEXT = 'review-captain-key-check';

	/**
	 * Primary key (raw 32 bytes) or null when invalid/unavailable.
	 */
	private ?string $primary = null;

	/**
	 * Decrypt-only keys keyed by key_id.
	 *
	 * @var array<string, string>
	 */
	private array $previous = array();

	/**
	 * Key status.
	 */
	private string $status;

	/**
	 * Constructor.
	 *
	 * @param array{key?: ?string, previous?: ?string}|null $config Base64 keys (tests); null reads the constants
	 *                                                               REVIEW_CAPTAIN_ENCRYPTION_KEY / …_KEY_PREVIOUS.
	 */
	public function __construct( ?array $config = null ) {
		if ( null === $config ) {
			$config = array(
				'key'      => defined( 'REVIEW_CAPTAIN_ENCRYPTION_KEY' ) ? (string) constant( 'REVIEW_CAPTAIN_ENCRYPTION_KEY' ) : null,
				'previous' => defined( 'REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS' ) ? (string) constant( 'REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS' ) : null,
			);
		}

		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->status = self::STATUS_UNAVAILABLE;
			return;
		}

		$fallback = self::derive_fallback();
		$explicit = $config['key'] ?? null;
		if ( null === $explicit || '' === $explicit ) {
			$this->primary = $fallback;
			$this->status  = self::STATUS_FALLBACK;
		} else {
			$raw = self::decode_key( $explicit );
			if ( null === $raw ) {
				$this->status = self::STATUS_INVALID; // No fallback for an invalid constant.
				return;
			}
			$this->primary = $raw;
			$this->status  = self::STATUS_EXPLICIT;
			// The salt-derived key counts as "previous" while switching fallback → explicit.
			$this->previous[ self::key_id_for( $fallback ) ] = $fallback;
		}

		$prev = $config['previous'] ?? null;
		if ( null !== $prev && '' !== $prev ) {
			$raw = self::decode_key( $prev );
			if ( null !== $raw ) {
				$this->previous[ self::key_id_for( $raw ) ] = $raw;
			}
		}
		unset( $this->previous[ self::key_id_for( $this->primary ) ] );
	}

	/**
	 * Generate a new random key (base64) for wp-config.php.
	 */
	public static function generate_key(): string {
		return base64_encode( random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decode a base64 key; null unless exactly 32 bytes.
	 *
	 * @param string $b64 Base64.
	 */
	private static function decode_key( string $b64 ): ?string {
		$raw = base64_decode( trim( $b64 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return ( false !== $raw && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen( $raw ) ) ? $raw : null;
	}

	/**
	 * Fallback key derived from the auth salt.
	 */
	private static function derive_fallback(): string {
		return sodium_crypto_generichash( 'review-captain|secretbox|v1|' . wp_salt( 'auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Key id for raw key material (16 hex).
	 *
	 * @param string $key Raw key.
	 */
	private static function key_id_for( string $key ): string {
		return substr( bin2hex( sodium_crypto_generichash( 'review-captain|key-id|' . $key ) ), 0, 16 );
	}

	/**
	 * Key status: explicit|fallback|invalid|unavailable.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Whether the salt-derived fallback key is in use (admin warning).
	 */
	public function is_fallback_key(): bool {
		return self::STATUS_FALLBACK === $this->status;
	}

	/**
	 * Whether encryption is possible.
	 */
	public function is_available(): bool {
		return null !== $this->primary;
	}

	/**
	 * Current key id ('' if no usable key).
	 */
	public function key_id(): string {
		return null === $this->primary ? '' : self::key_id_for( $this->primary );
	}

	/**
	 * Encrypt.
	 *
	 * @param string               $plaintext Plaintext.
	 * @param array<string, mixed> $context   Binding context, e.g. blog_id, profile_uuid, platform.
	 * @throws \RuntimeException Message rc.crypto.key_missing when no usable key.
	 */
	public function encrypt( string $plaintext, array $context = array() ): string {
		if ( null === $this->primary ) {
			throw new \RuntimeException( ErrorCode::CRYPTO_KEY_MISSING ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$envelope = wp_json_encode(
			array(
				'v'   => 1,
				'ctx' => (object) self::normalize_context( $context ),
				'd'   => $plaintext,
			)
		);
		if ( false === $envelope ) {
			throw new \RuntimeException( ErrorCode::CRYPTO_KEY_MISSING ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $envelope, $nonce, $this->primary );
		self::wipe( $envelope );
		return self::PREFIX . ':' . $this->key_id() . ':' . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt.
	 *
	 * @param string                    $cipher  Envelope.
	 * @param array<string, mixed>|null $context Expected context (checked if given).
	 * @throws \RuntimeException Message rc.crypto.decrypt_failed on any failure (wrong key, tampering, context mismatch).
	 */
	public function decrypt( string $cipher, ?array $context = null ): string {
		$parsed = self::parse( $cipher );
		if ( null === $parsed ) {
			throw self::failure();
		}
		[ $key_id, $blob ] = $parsed;
		$key               = $this->key_for( $key_id );
		if ( null === $key || strlen( $blob ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
			throw self::failure();
		}
		$nonce = substr( $blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = substr( $blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		try {
			$plain = sodium_crypto_secretbox_open( $box, $nonce, $key );
		} catch ( \Throwable $e ) {
			$plain = false;
		}
		if ( false === $plain ) {
			throw self::failure();
		}
		$data = json_decode( $plain, true );
		self::wipe( $plain );
		if ( ! is_array( $data ) || 1 !== ( $data['v'] ?? null ) || ! is_string( $data['d'] ?? null ) ) {
			throw self::failure();
		}
		if ( null !== $context ) {
			$stored = is_array( $data['ctx'] ?? null ) ? $data['ctx'] : array();
			if ( self::normalize_context( $stored ) !== self::normalize_context( $context ) ) {
				throw self::failure();
			}
		}
		return $data['d'];
	}

	/**
	 * Whether a ciphertext was made with a key other than the current one.
	 *
	 * @param string $cipher Envelope.
	 */
	public function needs_rotation( string $cipher ): bool {
		$parsed = self::parse( $cipher );
		return null !== $parsed && null !== $this->primary && $parsed[0] !== $this->key_id();
	}

	/**
	 * Re-encrypt with the current key (no-op if already current).
	 *
	 * @param string                    $cipher  Envelope.
	 * @param array<string, mixed>|null $context Context (kept from the stored envelope when null).
	 * @throws \RuntimeException On decrypt failure.
	 */
	public function rotate( string $cipher, ?array $context = null ): string {
		if ( ! $this->needs_rotation( $cipher ) ) {
			if ( null === self::parse( $cipher ) ) {
				throw self::failure();
			}
			return $cipher;
		}
		$plain      = $this->decrypt( $cipher, $context );
		$stored_ctx = $context ?? $this->stored_context( $cipher );
		$out        = $this->encrypt( $plain, $stored_ctx );
		self::wipe( $plain );
		return $out;
	}

	/**
	 * Key id embedded in a ciphertext ('' if unparsable).
	 *
	 * @param string $cipher Envelope.
	 */
	public static function key_id_of( string $cipher ): string {
		$parsed = self::parse( $cipher );
		return null === $parsed ? '' : $parsed[0];
	}

	/**
	 * Store the key check (key_id + probe ciphertext).
	 */
	public function store_key_check(): void {
		if ( null === $this->primary ) {
			return;
		}
		update_option(
			self::OPTION_KEY_CHECK,
			array(
				'key_id' => $this->key_id(),
				'probe'  => $this->encrypt( self::KEY_CHECK_PLAINTEXT ),
			),
			false
		);
	}

	/**
	 * Whether the stored key check can still be decrypted (null = no check stored yet).
	 */
	public function key_check_matches(): ?bool {
		$check = get_option( self::OPTION_KEY_CHECK, null );
		if ( ! is_array( $check ) || ! isset( $check['probe'] ) ) {
			return null;
		}
		try {
			return self::KEY_CHECK_PLAINTEXT === $this->decrypt( (string) $check['probe'] );
		} catch ( \RuntimeException $e ) {
			return false;
		}
	}

	/**
	 * Raw key for an id.
	 *
	 * @param string $key_id Key id.
	 */
	private function key_for( string $key_id ): ?string {
		if ( null !== $this->primary && hash_equals( $this->key_id(), $key_id ) ) {
			return $this->primary;
		}
		return $this->previous[ $key_id ] ?? null;
	}

	/**
	 * Context stored inside a ciphertext.
	 *
	 * @param string $cipher Envelope.
	 * @return array<string, mixed>
	 */
	private function stored_context( string $cipher ): array {
		$parsed = self::parse( $cipher );
		$key    = null === $parsed ? null : $this->key_for( $parsed[0] );
		if ( null === $key ) {
			throw self::failure();
		}
		$plain = sodium_crypto_secretbox_open( substr( $parsed[1], SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $parsed[1], 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
		if ( false === $plain ) {
			throw self::failure();
		}
		$data = json_decode( $plain, true );
		self::wipe( $plain );
		return is_array( $data['ctx'] ?? null ) ? $data['ctx'] : array();
	}

	/**
	 * Split an envelope into key id and binary blob.
	 *
	 * @param string $cipher Envelope.
	 * @return array{0: string, 1: string}|null
	 */
	private static function parse( string $cipher ): ?array {
		if ( ! preg_match( '/^rc1:([0-9a-f]{16}):([A-Za-z0-9+\/]+={0,2})$/', $cipher, $m ) ) {
			return null;
		}
		$blob = base64_decode( $m[2], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return false === $blob ? null : array( $m[1], $blob );
	}

	/**
	 * Canonical context (sorted, scalar values as strings).
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, string>
	 */
	private static function normalize_context( array $context ): array {
		$out = array();
		foreach ( $context as $k => $v ) {
			if ( is_scalar( $v ) || null === $v ) {
				$out[ (string) $k ] = (string) $v;
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Standard failure (never contains plaintext or key material).
	 */
	private static function failure(): \RuntimeException {
		return new \RuntimeException( ErrorCode::CRYPTO_DECRYPT_FAILED );
	}

	/**
	 * Best-effort memory wipe.
	 *
	 * @param string $value Value (by reference).
	 */
	private static function wipe( string &$value ): void {
		if ( function_exists( 'sodium_memzero' ) && '' !== $value ) {
			try {
				sodium_memzero( $value );
			} catch ( \Throwable $e ) {
				$value = '';
			}
		}
		$value = '';
	}

	/**
	 * Avoid leaking keys through var_dump/print_r.
	 *
	 * @return array<string, string>
	 */
	public function __debugInfo(): array {
		return array(
			'status' => $this->status,
			'key_id' => $this->key_id(),
		);
	}
}
