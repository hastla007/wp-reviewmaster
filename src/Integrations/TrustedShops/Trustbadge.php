<?php
/**
 * Trusted Shops trustbadge fallback (RC-077, RC-003 F15, TEC-013).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Integrations\TrustedShops;

use ReviewCaptain\Security\EmbedPolicy;

/**
 * Official Trusted Shops trustbadge as a consent-gated embed.
 *
 * The badge is the official script `https://widgets.trustedshops.com/js/{TSID}.js`. Review Captain
 * never loads it on page load: it renders a placeholder with a notice and a button; only after the
 * visitor clicks does assets/js/frontend-embed.js insert the script. Without JS a plain link to the
 * public Trusted Shops profile remains. No API access, no data from Trusted Shops is stored.
 */
final class Trustbadge {

	public const SHORTCODE = 'review_captain_trustbadge';

	public const SCRIPT_HOST = 'widgets.trustedshops.com';

	/**
	 * Normalize and validate a Trusted Shops ID ("X" + 32 hex characters).
	 *
	 * @param string $tsid Raw ID.
	 * @return string Upper-case ID, or '' if invalid.
	 */
	public static function sanitize_tsid( string $tsid ): string {
		$tsid = strtoupper( trim( $tsid ) );
		return 1 === preg_match( '/^X[0-9A-F]{32}$/', $tsid ) ? $tsid : '';
	}

	/**
	 * Official script URL for a valid TSID.
	 *
	 * @param string $tsid Valid TSID.
	 */
	public static function script_url( string $tsid ): string {
		return 'https://' . self::SCRIPT_HOST . '/js/' . $tsid . '.js';
	}

	/**
	 * Public Trusted Shops profile (fallback link, no embed).
	 *
	 * @param string $tsid Valid TSID.
	 */
	public static function profile_url( string $tsid ): string {
		return 'https://www.trustedshops.de/bewertung/info_' . $tsid . '.html';
	}

	/**
	 * Render the consent-gated placeholder.
	 *
	 * @param string $tsid Trusted Shops ID (from shortcode or settings).
	 * @return string Escaped HTML, or '' when the ID is invalid or the host is not allowed.
	 */
	public static function render( string $tsid ): string {
		$tsid = self::sanitize_tsid( $tsid );
		if ( '' === $tsid ) {
			return '';
		}
		$script = self::script_url( $tsid );
		if ( ! EmbedPolicy::is_allowed_url( $script ) ) {
			return '';
		}
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( EmbedPolicy::SCRIPT_HANDLE );
		}
		return sprintf(
			'<div class="rc-embed-placeholder rc-embed-placeholder--script rc-trustbadge" data-rc-embed-script="%1$s"><p class="rc-embed-placeholder__notice">%2$s</p><button type="button" class="rc-embed-placeholder__load">%3$s</button> <a class="rc-embed-placeholder__fallback" href="%4$s" %5$s>%6$s%7$s</a></div>',
			esc_url( $script ),
			esc_html(
				sprintf(
					/* translators: %s: third-party host name */
					__( 'Beim Laden werden Daten an %s übertragen.', 'review-captain' ),
					self::SCRIPT_HOST
				)
			),
			esc_html__( 'Trusted-Shops-Siegel laden', 'review-captain' ),
			esc_url( self::profile_url( $tsid ) ),
			EmbedPolicy::external_link_attrs( true ),
			esc_html__( 'Bewertungen bei Trusted Shops ansehen', 'review-captain' ),
			EmbedPolicy::new_tab_hint()
		);
	}
}
