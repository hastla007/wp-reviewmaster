<?php
/**
 * Self-hosted proof-of-work captcha – core logic without WordPress or plugin dependencies
 * (SET-FC-05 a, owner decisions E1/S7). Format and rules: docs/spam-proof-of-work.md.
 *
 * Principle (as ALTCHA / Friendly Captcha): the server picks a random number n ≤ max_number and
 * publishes challenge = SHA-256( salt . n ) together with an HMAC signature. The browser finds n
 * by trying 0…max_number. No external request, no cookie, no personal data, no puzzle.
 *
 * salt = <24 hex random> "." <expires unix> "." <16 hex scope tag>
 * scope tag = first 16 hex of HMAC-SHA-256( "scope|" . scope, key ), binds the challenge to one form.
 *
 * Other plugins copy this file with their own namespace; the key, the clock and the single-use
 * store are injected, so nothing here knows about the host plugin.
 *
 * Copied from Form Captain (wp-formmaster ca81401); only the namespace differs.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Spam\ProofOfWork;

final class ProofOfWork {

	public const FORMAT_VERSION = 1;
	public const ALGORITHM      = 'SHA-256';

	/** Default difficulty: about 25 000 hashes on average, well under a second on a phone. */
	public const DEFAULT_MAX_NUMBER = 50000;
	public const MIN_MAX_NUMBER     = 1000;
	public const MAX_MAX_NUMBER     = 1000000;
	public const DEFAULT_TTL        = 1800;

	public const OK             = 'ok';
	public const MISSING        = 'missing';
	public const MALFORMED      = 'malformed';
	public const BAD_SIGNATURE  = 'bad_signature';
	public const EXPIRED        = 'expired';
	public const WRONG_SCOPE    = 'wrong_scope';
	public const WRONG_SOLUTION = 'wrong_solution';
	public const REPLAYED       = 'replayed';

	private int $max_number;
	private int $ttl;

	/** @var callable(string,int):bool */
	private $consume;

	/** @var callable():int */
	private $clock;

	/**
	 * @param string                   $key        secret HMAC key (at least 32 bytes)
	 * @param callable(string,int):bool $consume   single-use store: (challenge, expires) → true only the first time
	 * @param callable():int|null      $clock      current unix time
	 */
	public function __construct( private string $key, callable $consume, int $max_number = self::DEFAULT_MAX_NUMBER, int $ttl = self::DEFAULT_TTL, ?callable $clock = null ) {
		if ( strlen( $key ) < 32 ) {
			throw new \InvalidArgumentException( 'Key too short' );
		}
		$this->max_number = max( self::MIN_MAX_NUMBER, min( self::MAX_MAX_NUMBER, $max_number ) );
		$this->ttl        = max( 60, $ttl );
		$this->consume    = $consume;
		$this->clock      = $clock ?? static fn(): int => time();
	}

	/**
	 * New challenge for a scope (e.g. a form ID). Safe to send to the browser.
	 *
	 * @return array{algorithm:string,challenge:string,maxnumber:int,salt:string,signature:string,expires:int}
	 */
	public function create( string $scope ): array {
		$expires   = ( $this->clock )() + $this->ttl;
		$salt      = bin2hex( random_bytes( 12 ) ) . '.' . $expires . '.' . $this->scope_tag( $scope );
		$challenge = hash( 'sha256', $salt . random_int( 0, $this->max_number ) );
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
	 * Checks a solution and marks it as used. Order: format, signature, expiry, scope, solution,
	 * single use – so the store is only touched for a correct, signed solution.
	 *
	 * @param mixed $payload base64 JSON string (as sent by the browser) or decoded array
	 * @return string one of the result constants
	 */
	public function verify( mixed $payload, string $scope ): string {
		if ( null === $payload || '' === $payload || array() === $payload ) {
			return self::MISSING;
		}
		$data = is_array( $payload ) ? $payload : self::decode( $payload );
		if ( null === $data ) {
			return self::MALFORMED;
		}
		$challenge = $data['challenge'] ?? null;
		$salt      = $data['salt'] ?? null;
		$signature = $data['signature'] ?? null;
		$number    = $data['number'] ?? null;
		if ( self::ALGORITHM !== ( $data['algorithm'] ?? null )
			|| ! is_string( $challenge ) || ! preg_match( '/^[0-9a-f]{64}$/', $challenge )
			|| ! is_string( $signature ) || ! preg_match( '/^[0-9a-f]{64}$/', $signature )
			|| ! is_string( $salt ) || ! preg_match( '/^([0-9a-f]{24})\.(\d{1,12})\.([0-9a-f]{16})$/', $salt, $parts )
			|| ! is_int( $number ) || $number < 0 || $number > self::MAX_MAX_NUMBER ) {
			return self::MALFORMED;
		}
		if ( ! hash_equals( $this->sign( $challenge ), $signature ) ) {
			return self::BAD_SIGNATURE;
		}
		$expires = (int) $parts[2];
		if ( $expires < ( $this->clock )() ) {
			return self::EXPIRED;
		}
		if ( ! hash_equals( $this->scope_tag( $scope ), $parts[3] ) ) {
			return self::WRONG_SCOPE;
		}
		if ( ! hash_equals( $challenge, hash( 'sha256', $salt . $number ) ) ) {
			return self::WRONG_SOLUTION;
		}
		return ( $this->consume )( $challenge, $expires ) ? self::OK : self::REPLAYED;
	}

	/**
	 * Reference solver (tests, server-side tools). The browser uses proof-of-work.js.
	 *
	 * @param array{challenge:string,salt:string,maxnumber:int} $challenge
	 */
	public static function solve( array $challenge ): ?int {
		for ( $n = 0; $n <= (int) $challenge['maxnumber']; $n++ ) {
			if ( hash( 'sha256', $challenge['salt'] . $n ) === $challenge['challenge'] ) {
				return $n;
			}
		}
		return null;
	}

	/**
	 * Browser payload: base64( JSON {algorithm, challenge, number, salt, signature} ).
	 *
	 * @param array<string,mixed> $challenge
	 */
	public static function encode( array $challenge, int $number ): string {
		return base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			(string) json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Dependency-free core.
				array(
					'algorithm' => $challenge['algorithm'],
					'challenge' => $challenge['challenge'],
					'number'    => $number,
					'salt'      => $challenge['salt'],
					'signature' => $challenge['signature'],
				)
			)
		);
	}

	/** @return array<string,mixed>|null */
	private static function decode( mixed $payload ): ?array {
		if ( ! is_string( $payload ) || strlen( $payload ) > 1024 ) {
			return null;
		}
		$json = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$data = false !== $json ? json_decode( $json, true ) : null;
		return is_array( $data ) ? $data : null;
	}

	private function sign( string $challenge ): string {
		return hash_hmac( 'sha256', $challenge, $this->key );
	}

	private function scope_tag( string $scope ): string {
		return substr( hash_hmac( 'sha256', 'scope|' . $scope, $this->key ), 0, 16 );
	}
}
