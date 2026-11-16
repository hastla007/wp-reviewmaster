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
	 * Constructor.
	 *
	 * @param string $scope Short scope key, e.g. "report".
	 */
	public function __construct( private string $scope ) {}

	/**
	 * Transient key for the current client.
	 */
	public function key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
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
