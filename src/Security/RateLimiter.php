<?php
/**
 * Fixed-window rate limit per hashed client address (reports, questions).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Security;

use ReviewCaptain\Frontend\FormHandler;

/**
 * One-hour window stored in a transient keyed by wp_hash(scope + IPv4 / IPv6-/64). The plain
 * address is never stored. Same approach as the review form (FormHandler).
 */
final class RateLimiter {

	/**
	 * Supported proxy headers (setting value => $_SERVER key), SET-RC-29.
	 */
	public const PROXY_HEADERS = array(
		'x-forwarded-for'  => 'HTTP_X_FORWARDED_FOR',
		'x-real-ip'        => 'HTTP_X_REAL_IP',
		'cf-connecting-ip' => 'HTTP_CF_CONNECTING_IP',
	);

	/**
	 * Client address used for rate limiting. Default: REMOTE_ADDR. Only when the operator names a
	 * trusted proxy header (`submissions.trusted_proxy_header`, off by default; the filter
	 * `review_captain_trusted_proxy_header` wins) is that header read. From a list (X-Forwarded-For)
	 * the last valid address is used – the one the operator's own proxy appended – so a client
	 * cannot choose its address by sending the header itself. Invalid values fall back to REMOTE_ADDR.
	 */
	public static function client_ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$header = (string) \ReviewCaptain\Plugin::instance()->settings()->get( 'submissions', 'trusted_proxy_header', 'none' );
		/**
		 * Trusted proxy header for the client address ('none', 'x-forwarded-for', 'x-real-ip',
		 * 'cf-connecting-ip').
		 *
		 * @param string $header Header from the setting.
		 */
		$header = strtolower( (string) apply_filters( 'review_captain_trusted_proxy_header', $header ) );
		$key    = self::PROXY_HEADERS[ $header ] ?? '';
		if ( '' === $key || ! isset( $_SERVER[ $key ] ) ) {
			return $remote;
		}
		/**
		 * Addresses of the operator's own reverse proxies / CDN edge (exact IPs). When the list is not
		 * empty, the proxy header is only read for requests that come from one of them, so a client
		 * that reaches the origin directly cannot choose its address (P4-7). Empty (default) = the
		 * header is read for every request, as configured.
		 *
		 * @param list<string> $proxies Proxy addresses.
		 */
		$proxies = array_values( array_filter( array_map( 'trim', array_map( 'strval', (array) apply_filters( 'review_captain_trusted_proxies', array() ) ) ), 'strlen' ) );
		if ( $proxies && ! in_array( $remote, $proxies, true ) ) {
			return $remote;
		}
		$parts = array_reverse( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) ) ) ) );
		foreach ( $parts as $candidate ) {
			if ( false !== filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}
		return $remote;
	}

	/**
	 * Constructor.
	 *
	 * @param string $scope Short scope key, e.g. "report".
	 */
	public function __construct( private string $scope ) {}

	/**
	 * Transient key for the current client.
	 */
	public function key(): string {
		$ip = self::client_ip();
		return 'review_captain_rl' . substr( sanitize_key( $this->scope ), 0, 8 ) . '_' . substr( wp_hash( 'rc_' . $this->scope . '|' . FormHandler::rate_subject( $ip ), 'nonce' ), 0, 24 );
	}

	/**
	 * Current window data.
	 *
	 * @return array{count: int, start: int, seen: list<string>}
	 */
	private function window(): array {
		$v = get_transient( $this->key() );
		if ( ! is_array( $v ) || ! isset( $v['start'] ) || (int) $v['start'] <= time() - HOUR_IN_SECONDS ) {
			return array(
				'count' => 0,
				'start' => time(),
				'seen'  => array(),
			);
		}
		return array(
			'count' => (int) ( $v['count'] ?? 0 ),
			'start' => (int) $v['start'],
			'seen'  => array_values( array_map( 'strval', (array) ( $v['seen'] ?? array() ) ) ),
		);
	}

	/**
	 * Hits in the current window.
	 */
	public function count(): int {
		return $this->window()['count'];
	}

	/**
	 * Whether an object key was already counted in this window (e.g. same review reported twice).
	 *
	 * @param string $object Object key.
	 */
	public function seen( string $object ): bool {
		return in_array( substr( wp_hash( $object, 'nonce' ), 0, 12 ), $this->window()['seen'], true );
	}

	/**
	 * Count one hit (optionally remembering an object key).
	 *
	 * @param string $object Object key.
	 */
	public function hit( string $object = '' ): void {
		$v = $this->window();
		++$v['count'];
		if ( '' !== $object ) {
			$v['seen'][] = substr( wp_hash( $object, 'nonce' ), 0, 12 );
			$v['seen']   = array_slice( array_values( array_unique( $v['seen'] ) ), -50 );
		}
		set_transient( $this->key(), $v, max( 60, HOUR_IN_SECONDS - ( time() - $v['start'] ) ) );
	}
}
