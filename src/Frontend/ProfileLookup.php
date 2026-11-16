<?php
/**
 * Read-only access to source profiles for the CTA (RC-054).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Install\Schema;

/**
 * Minimal read helper until a ProfileRepository exists in the core.
 */
class ProfileLookup {

	/**
	 * Profile statuses that must not be promoted publicly.
	 */
	private const HIDDEN_STATUSES = array( 'disabled', 'archived', 'removed', 'disconnected' );

	/**
	 * Find a usable profile by ID.
	 *
	 * @param int $id Profile ID.
	 * @return array{id: int, platform: string, label: string, url: string}|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		if ( $id <= 0 ) {
			return null;
		}
		$table = Schema::table( 'profiles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, platform, label, profile_url, review_url, status FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! is_array( $row ) || in_array( (string) $row['status'], self::HIDDEN_STATUSES, true ) ) {
			return null;
		}
		$url = '';
		foreach ( array( 'review_url', 'profile_url' ) as $col ) {
			$candidate = trim( (string) ( $row[ $col ] ?? '' ) );
			if ( '' !== $candidate && self::is_https_url( $candidate ) ) {
				$url = $candidate;
				break;
			}
		}
		if ( '' === $url ) {
			return null;
		}
		return array(
			'id'       => (int) $row['id'],
			'platform' => (string) $row['platform'],
			'label'    => (string) $row['label'],
			'url'      => $url,
		);
	}

	/**
	 * Basic https URL check (links only – never fetched server-side).
	 *
	 * @param string $url URL.
	 */
	public static function is_https_url( string $url ): bool {
		if ( preg_match( '/[\s\x00-\x1F\x7F]/', $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && 'https' === strtolower( $parts['scheme'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] );
	}
}
