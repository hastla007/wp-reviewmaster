<?php
/**
 * Frontend embed policy (TEC-013, docs/architecture/embed-security.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Security;

/**
 * Fixed host allowlist for third-party embeds, link attributes for external links and a
 * click-to-load placeholder that causes no third-party request until the visitor clicks.
 *
 * The core only provides this policy; Pro uses it for media testimonials and trust widgets.
 */
final class EmbedPolicy {

	public const FILTER_HOSTS = 'review_captain_embed_hosts';

	public const SCRIPT_HANDLE = 'review-captain-embed';

	/**
	 * Allowed embed hosts. Extension only via code + ADR, never via admin field.
	 */
	public const HOSTS = array(
		'youtube-nocookie.com',
		'www.youtube-nocookie.com',
		'player.vimeo.com',
		'widget.trustpilot.com',
		'widgets.trustedshops.com',
	);

	/**
	 * Effective allowlist. The filter may only remove hosts, never add.
	 *
	 * @return list<string>
	 */
	public static function hosts(): array {
		/**
		 * Restrict the embed host allowlist. Hosts not in the core list are ignored.
		 *
		 * @param list<string> $hosts Hosts.
		 */
		$filtered = apply_filters( self::FILTER_HOSTS, self::HOSTS );
		if ( ! is_array( $filtered ) ) {
			return self::HOSTS;
		}
		$filtered = array_map( static fn( $h ): string => strtolower( (string) $h ), array_filter( $filtered, 'is_scalar' ) );
		return array_values( array_intersect( self::HOSTS, $filtered ) );
	}

	/**
	 * Whether a URL may be embedded: https only, allowlisted host, no IP host,
	 * no credentials, no port other than 443.
	 *
	 * @param string $url URL.
	 */
	public static function is_allowed_url( string $url ): bool {
		if ( '' === $url || strlen( $url ) > 2048 || preg_match( '/[\s\x00-\x1F\x7F\\\\]/', $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return false;
		}
		if ( 'https' !== strtolower( $parts['scheme'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
			return false;
		}
		$host = strtolower( rtrim( (string) $parts['host'], '.' ) );
		if ( '' === $host || str_starts_with( $host, '[' ) || false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		return in_array( $host, self::hosts(), true );
	}

	/**
	 * Attributes for external links related to review content (source, platform profile).
	 *
	 * @param bool $new_tab Whether the link opens a new tab (caller must add a visible/SR hint).
	 */
	public static function external_link_attrs( bool $new_tab = false ): string {
		return 'rel="nofollow noopener noreferrer ugc"' . ( $new_tab ? ' target="_blank"' : '' );
	}

	/**
	 * Screen reader hint for links opening a new tab.
	 */
	public static function new_tab_hint(): string {
		return '<span class="rc-visually-hidden"> ' . esc_html__( '(öffnet in neuem Tab)', 'review-captain' ) . '</span>';
	}

	/**
	 * Click-to-load placeholder. Contains no iframe/script/image from the third party; an
	 * optional script swaps in the iframe after a click. Without JS the fallback link works.
	 *
	 * @param string $url   Embed URL (must pass is_allowed_url()).
	 * @param string $title Accessible title of the embedded content.
	 * @return string Escaped HTML, or '' if the URL is not allowed.
	 */
	public static function click_to_load( string $url, string $title ): string {
		if ( ! self::is_allowed_url( $url ) ) {
			return '';
		}
		$host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$title = '' !== trim( $title ) ? $title : __( 'Eingebetteter Inhalt', 'review-captain' );
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}
		return sprintf(
			'<div class="rc-embed-placeholder" data-rc-embed-src="%1$s" data-rc-embed-title="%2$s"><p class="rc-embed-placeholder__notice">%3$s</p><button type="button" class="rc-embed-placeholder__load">%4$s</button> <a class="rc-embed-placeholder__fallback" href="%1$s" %5$s>%6$s%7$s</a></div>',
			esc_url( $url ),
			esc_attr( $title ),
			esc_html(
				sprintf(
					/* translators: %s: third-party host name */
					__( 'Beim Laden werden Daten an %s übertragen.', 'review-captain' ),
					$host
				)
			),
			esc_html(
				sprintf(
					/* translators: %s: content title */
					__( 'Inhalt laden: %s', 'review-captain' ),
					$title
				)
			),
			self::external_link_attrs( true ),
			esc_html__( 'Beim Anbieter öffnen', 'review-captain' ),
			self::new_tab_hint()
		);
	}
}
