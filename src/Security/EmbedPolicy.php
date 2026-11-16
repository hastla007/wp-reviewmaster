<?php
/**
 * Frontend embed security (TEC-013): fixed https host allowlist, no server-side fetching of user URLs,
 * click-to-load placeholders for third-party content.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Security;

use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class EmbedPolicy {

	private const DEFAULT_HOSTS = array(
		'www.youtube-nocookie.com',
		'player.vimeo.com',
		'widget.trustpilot.com',
		'widgets.trustedshops.com',
		'integrations.etrusted.com',
	);

	/**
	 * @return string[]
	 */
	public static function hosts(): array {
		/**
		 * Extend the embed host allowlist (code only, not via settings UI).
		 *
		 * @param string[] $hosts Exact host names.
		 */
		return array_values( array_unique( array_map( 'strtolower', (array) apply_filters( 'review_captain_embed_hosts', self::DEFAULT_HOSTS ) ) ) );
	}

	public static function is_allowed( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return false;
		}
		return in_array( strtolower( $parts['host'] ), self::hosts(), true );
	}

	/**
	 * Placeholder that loads an iframe only after a click (no request to the third party before interaction).
	 */
	public static function click_to_load( string $url, string $title, string $poster_url = '' ): string {
		if ( ! self::is_allowed( $url ) ) {
			return '';
		}
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$mode = (string) Settings::get( 'external_services_consent_mode' );
		if ( 'consent_hook' === $mode && apply_filters( 'review_captain_external_consent_given', false, $host ) ) {
			return '<iframe class="rc-embed" src="' . esc_url( $url ) . '" title="' . esc_attr( $title ) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';
		}
		wp_enqueue_script( 'review-captain-embed' );
		/* translators: %s: host name */
		$button = sprintf( __( 'Load content from %s', 'review-captain' ), $host );
		$html   = '<div class="rc-embed-placeholder" data-rc-embed="' . esc_url( $url ) . '" data-rc-title="' . esc_attr( $title ) . '">';
		if ( $poster_url ) {
			$html .= '<img src="' . esc_url( $poster_url ) . '" alt="" loading="lazy">';
		}
		$html .= '<p>' . esc_html( $title ) . '</p><button type="button" class="rc-btn rc-embed-load">' . esc_html( $button ) . '</button>';
		/* translators: %s: host name */
		$html .= '<p class="rc-embed-note">' . esc_html( sprintf( __( 'When you load this content, data is transferred to %s.', 'review-captain' ), $host ) ) . '</p></div>';
		return $html;
	}
}
