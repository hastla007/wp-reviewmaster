<?php
/**
 * Robots rule for Review Captain query parameters (SEO-014, docs/seo/indexing-rules.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Seo;

/**
 * Adds `noindex, follow` only when an rc_* parameter is present. Never removes directives,
 * never sets nofollow, otherwise leaves robots untouched.
 */
final class Robots {

	/**
	 * Parameters that trigger noindex (pagination + submission status).
	 * Neue öffentliche Parameter müssen hier ergänzt werden, bevor sie ausgeliefert werden.
	 */
	public const PARAMS = array( 'rc_page', 'rc_submitted', 'rc_error', 'rc_form', 'rc_draft' );

	/**
	 * Whether the current request carries one of our parameters.
	 */
	public static function has_rc_params(): bool {
		foreach ( self::PARAMS as $param ) {
			if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
				return true;
			}
		}
		return false;
	}

	/**
	 * `wp_robots` filter.
	 *
	 * @param array<string, mixed> $robots Directives.
	 * @return array<string, mixed>
	 */
	public static function filter_wp_robots( $robots ): array {
		$robots = is_array( $robots ) ? $robots : array();
		if ( ! self::has_rc_params() ) {
			return $robots;
		}
		$robots['noindex'] = true;
		if ( empty( $robots['nofollow'] ) ) {
			$robots['follow'] = true;
		}
		unset( $robots['index'] );
		return $robots;
	}

	/**
	 * Yoast adapter (`wpseo_robots_array`) – only adds noindex, never removes anything.
	 *
	 * @param array<string, mixed> $robots Yoast robots array (index => index|noindex, follow => …).
	 * @return array<string, mixed>
	 */
	public static function filter_yoast_robots( $robots ): array {
		$robots = is_array( $robots ) ? $robots : array();
		if ( self::has_rc_params() ) {
			$robots['index'] = 'noindex';
			if ( ! isset( $robots['follow'] ) ) {
				$robots['follow'] = 'follow';
			}
		}
		return $robots;
	}
}
