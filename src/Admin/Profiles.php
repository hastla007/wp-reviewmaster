<?php
/**
 * Data access for the profiles table (Core part: manual profiles / CTA links).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Install\Schema;

/**
 * Minimal CRUD for `{prefix}review_captain_profiles`. All SQL is prepared.
 *
 * Row shape: id, platform, label, profile_url, review_url, external_ref, status, settings,
 * last_sync_at, last_error_code, created_at, updated_at (see Schema::definitions()).
 * The Core only creates profiles with status 'manual'; Pro may manage other statuses.
 */
class Profiles {

	public const STATUS_MANUAL = 'manual';

	/**
	 * Table name.
	 */
	public static function table(): string {
		return Schema::table( 'profiles' );
	}

	/**
	 * Find one profile.
	 *
	 * @param int $id ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * All profiles, optionally filtered by platform.
	 *
	 * @param string|null $platform Platform.
	 * @return list<array<string, mixed>>
	 */
	public function all( ?string $platform = null ): array {
		global $wpdb;
		$table = self::table();
		if ( null !== $platform && '' !== $platform ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE platform = %s ORDER BY platform ASC, label ASC, id ASC LIMIT 500", $platform ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY platform ASC, label ASC, id ASC LIMIT 500", ARRAY_A );
		}
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Number of profiles.
	 */
	public function count(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Validate and normalise input. Returns [data, errors].
	 *
	 * @param array<string, mixed> $input Raw input (unslashed).
	 * @return array{0: array<string, string>, 1: list<string>}
	 */
	public static function validate( array $input ): array {
		$errors   = array();
		$platform = sanitize_key( (string) ( $input['platform'] ?? '' ) );
		$label    = mb_substr( sanitize_text_field( (string) ( $input['label'] ?? '' ) ), 0, 191 );
		$profile  = trim( (string) ( $input['profile_url'] ?? '' ) );
		$review   = trim( (string) ( $input['review_url'] ?? '' ) );

		$enum = Platform::tryFrom( $platform );
		if ( null === $enum || ! $enum->is_external() ) {
			$errors[] = 'invalid_platform';
		}
		if ( '' === $label ) {
			$errors[] = 'missing_label';
		}
		if ( '' !== $profile && ! self::is_https( $profile ) ) {
			$errors[] = 'profile_url_not_https';
		}
		if ( '' !== $review && ! self::is_https( $review ) ) {
			$errors[] = 'review_url_not_https';
		}
		if ( '' === $profile && '' === $review ) {
			$errors[] = 'missing_url';
		}
		return array(
			array(
				'platform'    => $platform,
				'label'       => $label,
				'profile_url' => '' === $profile ? '' : esc_url_raw( $profile, array( 'https' ) ),
				'review_url'  => '' === $review ? '' : esc_url_raw( $review, array( 'https' ) ),
			),
			$errors,
		);
	}

	/**
	 * Create or update a profile. Status of new profiles is always 'manual'.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @param int|null             $id    Existing ID.
	 * @return array{id: int|null, errors: list<string>}
	 */
	public function save( array $input, ?int $id = null ): array {
		global $wpdb;
		[ $data, $errors ] = self::validate( $input );
		if ( $errors ) {
			return array(
				'id'     => $id,
				'errors' => $errors,
			);
		}
		$now                = gmdate( 'Y-m-d H:i:s' );
		$data['updated_at'] = $now;
		$data['profile_url'] = '' === $data['profile_url'] ? null : $data['profile_url'];
		$data['review_url']  = '' === $data['review_url'] ? null : $data['review_url'];

		if ( null !== $id && null !== $this->find( $id ) ) {
			$ok = $wpdb->update( self::table(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return array(
				'id'     => $id,
				'errors' => false === $ok ? array( 'save_failed' ) : array(),
			);
		}
		$data['status']     = self::STATUS_MANUAL;
		$data['created_at'] = $now;
		$ok                 = $wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $ok ) {
			return array(
				'id'     => null,
				'errors' => array( 'save_failed' ),
			);
		}
		return array(
			'id'     => (int) $wpdb->insert_id,
			'errors' => array(),
		);
	}

	/**
	 * Delete a profile. Reviews referencing it are kept untouched.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Number of reviews that reference a profile.
	 *
	 * @param int $id Profile ID.
	 */
	public function review_count( int $id ): int {
		global $wpdb;
		$table = Schema::table( 'reviews' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE profile_id = %d", $id ) );
	}

	/**
	 * Whether a URL is an absolute https URL with host.
	 *
	 * @param string $url URL.
	 */
	public static function is_https( string $url ): bool {
		if ( ! preg_match( '#^https://#i', $url ) ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) && '' !== $host && ! preg_match( '/[\s<>"\']/', $url );
	}
}
