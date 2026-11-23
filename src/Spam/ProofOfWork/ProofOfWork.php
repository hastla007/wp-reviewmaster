<?php
/**
 * Built-in proof-of-work spam check (SET-RC-11), dependency-free core.
 *
 * Port of Form Captain's `FormCaptain\Spam\ProofOfWork\ProofOfWork` (wp-formmaster, commit
 * ca81401, branch claude/youthful-babbage-wkrqyv; spec docs/spam-proof-of-work.md there). Both
 * plugins use the same data format and verification rules so they behave identically; only the
 * prefix, the namespace and the WordPress glue differ (see docs/spam-proof-of-work.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Spam\ProofOfWork;

/**
 * Creates signed challenges and verifies solutions. No WordPress functions, no storage: the
 * caller passes the secret, the clock and a "consume" callback for single use.
 *
 * Procedure (SHA-256, hash-reversal over a bounded number range):
 * - Challenge: salt = 24 random hex chars + "?expires=<unix time>"; secret number n in
 *   [0, max_number]; challenge = hex(SHA-256(salt . n)); signature = hex(HMAC-SHA-256(challenge,
 *   secret)). The browser receives algorithm, challenge, max_number, salt, signature (never n).
 * - Solution: the browser tries 0..max_number until SHA-256(salt . number) equals the challenge
 *   and posts base64(JSON{algorithm, challenge, number, salt, signature}).
 * - Verification: well-formed payload, algorithm "SHA-256", signature matches the challenge
 *   (constant-time), salt not expired, SHA-256(salt . number) equals the challenge, number within
 *   [0, MAX_NUMBER_LIMIT], and the challenge was not used before (single use).
 */
final class ProofOfWork {

	public const ALGORITHM = 'SHA-256';

	/**
	 * Default upper bound of the secret number (on average half of it is tried; a few hundred
	 * milliseconds on a current phone with the bundled pure-JS SHA-256).
	 */
	public const DEFAULT_MAX_NUMBER = 100000;

	/**
	 * Lower and upper bound for the difficulty filter.
	 */
	public const MIN_MAX_NUMBER   = 1000;
	public const MAX_NUMBER_LIMIT = 10000000;

	/**
	 * Default challenge lifetime in seconds.
	 */
	public const DEFAULT_TTL = 3600;

	/**
	 * Result codes of verify() (also the spam reasons recorded by the forms).
	 */
	public const OK       = 'ok';
	public const MISSING  = 'pow_missing';
	public const INVALID  = 'pow_invalid';
	public const REPLAYED = 'pow_replayed';

	/**
	 * Upper bound of an accepted payload (bytes) – rejects oversized input before decoding.
	 */
	private const MAX_PAYLOAD = 2048;

	/**
	 * Constructor.
	 *
	 * @param string        $secret     HMAC secret (site specific, never sent to the browser).
	 * @param int           $max_number Upper bound of the secret number (difficulty).
	 * @param int           $ttl        Lifetime of a challenge in seconds.
	 * @param callable|null $clock      Returns the current Unix time (tests); default time().
	 */
	public function __construct(
		private string $secret,
		private int $max_number = self::DEFAULT_MAX_NUMBER,
		private int $ttl = self::DEFAULT_TTL,
		private $clock = null
	) {
		$this->max_number = self::clamp_max_number( $max_number );
		$this->ttl        = max( 60, $ttl );
	}

	/**
	 * Keep a difficulty within the supported bounds.
	 *
	 * @param int $value Requested upper bound.
	 */
	public static function clamp_max_number( int $value ): int {
		return max( self::MIN_MAX_NUMBER, min( self::MAX_NUMBER_LIMIT, $value ) );
	}

	/**
	 * Current time.
	 */
	private function now(): int {
		return null !== $this->clock ? (int) ( $this->clock )() : time();
	}

	/**
	 * Create a challenge.
	 *
	 * @param int|null $number Secret number (tests only; default random).
	 * @return array{algorithm: string, challenge: string, maxnumber: int, salt: string, signature: string, expires: int}
	 */
	public function create( ?int $number = null ): array {
		$expires   = $this->now() + $this->ttl;
		$salt      = bin2hex( random_bytes( 12 ) ) . '?expires=' . $expires;
		$number    = null !== $number ? max( 0, min( $this->max_number, $number ) ) : random_int( 0, $this->max_number );
		$challenge = self::hash( $salt, $number );
		return array(
			'algorithm' => self::ALGORITHM,
			'challenge' => $challenge,
			'maxnumber' => $this->max_number,
			'salt'      => $salt,
			'signature' => $this->sign( $challenge ),
			'expires'   => $expires,
		);
	}

	/**
	 * Solve a challenge by brute force (tests and CLI checks; the browser uses the JS port).
	 *
	 * @param array{challenge: string, salt: string, maxnumber?: int} $challenge Challenge.
	 */
	public static function solve( array $challenge ): ?int {
		$max = (int) ( $challenge['maxnumber'] ?? self::MAX_NUMBER_LIMIT );
		for ( $n = 0; $n <= $max; $n++ ) {
			if ( hash_equals( (string) $challenge['challenge'], self::hash( (string) $challenge['salt'], $n ) ) ) {
				return $n;
			}
		}
		return null;
	}

	/**
	 * Encode a solution the way the browser does (base64 of the JSON object).
	 *
	 * @param array{challenge: string, salt: string, signature: string} $challenge Challenge.
	 * @param int                                                       $number    Found number.
	 */
	public static function encode_solution( array $challenge, int $number ): string {
		$data = array(
			'algorithm' => self::ALGORITHM,
			'challenge' => (string) $challenge['challenge'],
			'number'    => $number,
			'salt'      => (string) $challenge['salt'],
			'signature' => (string) $challenge['signature'],
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.json_encode_json_encode -- wire format of the solution; the core is WordPress-free.
		return base64_encode( (string) json_encode( $data ) );
	}

	/**
	 * Verify a posted solution.
	 *
	 * @param string        $payload Posted value (base64 JSON).
	 * @param callable|null $consume fn( string $id, int $expires ): bool – marks the challenge as
	 *                               used; returns false when it was used before. Null = no single
	 *                               use check (tests of the pure verification only).
	 * @return string One of OK, MISSING, INVALID, REPLAYED.
	 */
	public function verify( string $payload, ?callable $consume = null ): string {
		$payload = trim( $payload );
		if ( '' === $payload ) {
			return self::MISSING;
		}
		if ( strlen( $payload ) > self::MAX_PAYLOAD ) {
			return self::INVALID;
		}
		$json = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- wire format of the solution.
		$data = false !== $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $data ) ) {
			return self::INVALID;
		}
		$algorithm = $data['algorithm'] ?? null;
		$challenge = $data['challenge'] ?? null;
		$number    = $data['number'] ?? null;
		$salt      = $data['salt'] ?? null;
		$signature = $data['signature'] ?? null;
		if ( self::ALGORITHM !== $algorithm || ! is_string( $challenge ) || ! is_string( $salt ) || ! is_string( $signature ) || ! is_int( $number ) ) {
			return self::INVALID;
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $challenge ) || ! preg_match( '/^[a-f0-9]{64}$/', $signature ) || $number < 0 || $number > self::MAX_NUMBER_LIMIT ) {
			return self::INVALID;
		}
		if ( ! hash_equals( $this->sign( $challenge ), $signature ) ) {
			return self::INVALID;
		}
		$expires = self::expires( $salt );
		if ( null === $expires || $expires < $this->now() ) {
			return self::INVALID;
		}
		if ( ! hash_equals( $challenge, self::hash( $salt, $number ) ) ) {
			return self::INVALID;
		}
		if ( null !== $consume && ! $consume( hash( 'sha256', 'pow|' . $challenge ), $expires ) ) {
			return self::REPLAYED;
		}
		return self::OK;
	}

	/**
	 * Expiry encoded in the salt ("…?expires=<unix time>"), or null.
	 *
	 * @param string $salt Salt.
	 */
	public static function expires( string $salt ): ?int {
		return preg_match( '/^[a-f0-9]{8,64}\?expires=(\d{9,11})$/', $salt, $m ) ? (int) $m[1] : null;
	}

	/**
	 * SHA-256 of salt and number (hex).
	 *
	 * @param string $salt   Salt.
	 * @param int    $number Number.
	 */
	public static function hash( string $salt, int $number ): string {
		return hash( 'sha256', $salt . $number );
	}

	/**
	 * HMAC signature of a challenge.
	 *
	 * @param string $challenge Challenge hash.
	 */
	private function sign( string $challenge ): string {
		return hash_hmac( 'sha256', $challenge, $this->secret );
	}
}
