<?php
/**
 * Business rules for reviews: creation paths, moderation, verification (RC-039–RC-043).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Reviews;

use ReviewCaptain\Audit\AuditLog;
use ReviewCaptain\Security\Crypto;
use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class ReviewService {

	/** Providers that can be curated manually (RC-043). */
	public const EXTERNAL_PROVIDERS = array( 'google', 'trustpilot', 'etrusted', 'apple_app_store', 'google_play', 'other' );

	/** Allowed moderation transitions (RC-040). */
	private const TRANSITIONS = array(
		'pending'  => array( 'approved', 'rejected', 'spam', 'archived' ),
		'approved' => array( 'archived', 'rejected', 'pending' ),
		'rejected' => array( 'approved', 'pending', 'archived' ),
		'archived' => array( 'approved', 'pending' ),
		'spam'     => array( 'pending', 'rejected' ),
	);

	/**
	 * Verification may only be set by a trusted source contract (RC-041).
	 * Source key => allowed verification value.
	 */
	private const VERIFYING_SOURCES = array(
		'woocommerce:order'      => 'purchase',
		'voucher-captain:redeem' => 'purchase',
		'pass-captain:checkin'   => 'attendance',
	);

	public static function provider_label( string $provider ): string {
		$labels = array(
			'site'            => __( 'This website', 'review-captain' ),
			'google'          => 'Google',
			'trustpilot'      => 'Trustpilot',
			'etrusted'        => 'Trusted Shops',
			'apple_app_store' => 'App Store',
			'google_play'     => 'Google Play',
			'other'           => __( 'Other platform', 'review-captain' ),
		);
		return $labels[ $provider ] ?? ucfirst( $provider );
	}

	/**
	 * Local review submitted through the frontend form (RC-039). Always pending unless settings allow otherwise.
	 *
	 * @param array<string,mixed> $input rating, title, content, author_name, email, context_type, context_id, language.
	 * @return int|\WP_Error
	 */
	public static function create_local( array $input ) {
		$errors = self::validate_common( $input, true );
		if ( $errors->has_errors() ) {
			return $errors;
		}
		$status = 'approved' === Settings::get( 'default_status' ) ? 'approved' : 'pending';
		$email  = isset( $input['email'] ) ? sanitize_email( (string) $input['email'] ) : '';
		$id     = ReviewRepository::insert(
			array(
				'origin'            => 'local',
				'provider'          => 'site',
				'type'              => in_array( $input['type'] ?? '', ReviewRepository::TYPES, true ) ? $input['type'] : 'general',
				'rating_value'      => (int) $input['rating'],
				'rating_min'        => 1,
				'rating_max'        => 5,
				'title'             => self::clean_line( $input['title'] ?? '', 255 ),
				'content'           => self::clean_text( $input['content'] ?? '' ),
				'language'          => self::clean_line( $input['language'] ?? get_locale(), 20 ),
				'author_name'       => self::clean_line( $input['author_name'] ?? '', 191 ),
				'author_user_id'    => get_current_user_id() ? get_current_user_id() : null,
				'author_email_hash' => $email ? Crypto::lookup_hash( $email ) : null,
				'context_type'      => self::clean_key( $input['context_type'] ?? '' ),
				'context_id'        => self::clean_line( $input['context_id'] ?? '', 64 ),
				'status'            => $status,
				'verification'      => 'unverified',
				'published_at'      => current_time( 'mysql', true ),
			)
		);
		if ( is_int( $id ) ) {
			ReviewRepository::log( $id, 'created', 'local_form' );
			self::milestone( 'first_review_at', 'approved' === $status );
			do_action( 'review_captain_review_created', $id, 'local' );
		}
		return $id;
	}

	/**
	 * Manually curated external review (RC-043): platform, original rating, permitted text, date, profile link and source note are required.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return int|\WP_Error
	 */
	public static function create_manual_external( array $input ) {
		$errors = self::validate_external( $input );
		if ( $errors->has_errors() ) {
			return $errors;
		}
		$id = ReviewRepository::insert( self::external_row( $input, 'manual' ) );
		if ( is_int( $id ) ) {
			ReviewRepository::log( $id, 'created', 'manual_external' );
			AuditLog::log( 'review_manual_created', 'review', $id, array( 'provider' => $input['provider'] ) );
			self::milestone( 'first_review_at', 'approved' === ( $input['status'] ?? 'approved' ) );
		}
		return $id;
	}

	/**
	 * Update a manually curated external review. Text of external reviews must stay unchanged in meaning; edits are logged.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return true|\WP_Error
	 */
	public static function update_manual_external( int $id, array $input ) {
		$review = ReviewRepository::find( $id );
		if ( ! $review || 'manual' !== $review['origin'] ) {
			return new \WP_Error( 'not_editable', __( 'Only manually added external reviews can be edited.', 'review-captain' ) );
		}
		$errors = self::validate_external( $input );
		if ( $errors->has_errors() ) {
			return $errors;
		}
		$row = self::external_row( $input, 'manual' );
		unset( $row['status'] );
		ReviewRepository::update( $id, $row );
		ReviewRepository::log( $id, 'edited', isset( $input['edit_reason'] ) ? (string) $input['edit_reason'] : null );
		return true;
	}

	/**
	 * Local review from a connector / Form Captain (public API wp_review_captain_create_local_review_v1).
	 * Always pending and unverified; idempotent via idempotency_key.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return array{success:bool, code:string, review_id:int|null}
	 */
	public static function create_from_connector( array $payload ): array {
		$fail = static function ( string $code ) {
			return array(
				'success'   => false,
				'code'      => $code,
				'review_id' => null,
			);
		};
		$key    = isset( $payload['idempotency_key'] ) ? (string) $payload['idempotency_key'] : '';
		$source = isset( $payload['source_plugin'] ) ? sanitize_key( (string) $payload['source_plugin'] ) : '';
		if ( '' === $key || '' === $source || strlen( $key ) > 191 ) {
			return $fail( 'invalid_payload' );
		}
		if ( ! empty( $payload['verified'] ) ) {
			return $fail( 'invalid_payload' ); // Connectors can never set verification.
		}
		$rating = $payload['rating'] ?? null;
		if ( ! is_numeric( $rating ) || (int) $rating < 1 || (int) $rating > 5 || (string) (int) $rating !== (string) $rating ) {
			return $fail( 'invalid_payload' );
		}
		$id = ReviewRepository::insert(
			array(
				'origin'           => 'connector',
				'provider'         => 'site',
				'source_entry_key' => hash( 'sha256', $key ),
				'type'             => in_array( $payload['review_type'] ?? '', ReviewRepository::TYPES, true ) ? $payload['review_type'] : 'general',
				'rating_value'     => (int) $rating,
				'title'            => self::clean_line( $payload['title'] ?? '', 255 ),
				'content'          => self::clean_text( $payload['review_text'] ?? '' ),
				'author_name'      => self::clean_line( $payload['display_name'] ?? '', 191 ),
				'profile_id'       => isset( $payload['profile_id'] ) ? absint( $payload['profile_id'] ) : null,
				'status'           => 'pending',
				'verification'     => 'unverified',
				'published_at'     => current_time( 'mysql', true ),
				'meta'             => array(
					'source_plugin'   => $source,
					'source_entry_id' => isset( $payload['source_entry_id'] ) ? substr( (string) $payload['source_entry_id'], 0, 64 ) : null,
				),
			)
		);
		if ( is_wp_error( $id ) ) {
			return $fail( 'duplicate' === $id->get_error_code() ? 'duplicate' : 'db_error' );
		}
		ReviewRepository::log( $id, 'created', 'connector:' . $source );
		do_action( 'review_captain_review_created', $id, 'connector' );
		return array(
			'success'   => true,
			'code'      => 'ok',
			'review_id' => $id,
		);
	}

	/**
	 * Remove a pending connector review when the source entry was deleted (Konzept §7.3 E7).
	 */
	public static function delete_pending_by_idempotency_key( string $key ): bool {
		global $wpdb;
		$table = \ReviewCaptain\Database\Schema::table( 'rc_reviews' );
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE source_entry_key = %s AND status = 'pending'", hash( 'sha256', $key ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return $id ? ReviewRepository::delete( $id ) : false;
	}

	/**
	 * Change moderation status with logged reason (RC-040).
	 *
	 * @return true|\WP_Error
	 */
	public static function moderate( int $id, string $to, ?string $reason = null ) {
		$review = ReviewRepository::find( $id );
		if ( ! $review ) {
			return new \WP_Error( 'not_found', __( 'Review not found.', 'review-captain' ) );
		}
		$from = $review['status'];
		if ( $from === $to ) {
			return true;
		}
		if ( ! in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true ) ) {
			return new \WP_Error( 'invalid_transition', __( 'This status change is not allowed.', 'review-captain' ) );
		}
		if ( 'approved' === $to && ! empty( $review['tombstoned_at'] ) ) {
			return new \WP_Error( 'invalid_transition', __( 'This review was removed at its source and cannot be published.', 'review-captain' ) );
		}
		ReviewRepository::update( $id, array( 'status' => $to ) );
		ReviewRepository::log( $id, $to, $reason );
		AuditLog::log(
			'review_moderated',
			'review',
			$id,
			array(
				'status_from' => $from,
				'status_to'   => $to,
			)
		);
		if ( 'approved' === $to ) {
			self::milestone( 'first_review_at', true );
		}
		self::bump_cache();
		do_action( 'review_captain_review_moderated', $id, $from, $to );
		return true;
	}

	/**
	 * Public report by a visitor (stored as counter + log, moderation decides).
	 */
	public static function report( int $id ): bool {
		$review = ReviewRepository::find( $id );
		if ( ! $review || 'approved' !== $review['status'] ) {
			return false;
		}
		ReviewRepository::update( $id, array( 'reported_count' => (int) $review['reported_count'] + 1 ) );
		ReviewRepository::log( $id, 'reported' );
		return true;
	}

	/**
	 * Set verification. Only trusted source contracts can verify (RC-041).
	 *
	 * @return true|\WP_Error
	 */
	public static function set_verification( int $id, string $source_contract, string $reference ) {
		$value = self::VERIFYING_SOURCES[ $source_contract ] ?? null;
		/**
		 * Allow registered source contracts to verify (only official adapters should use this).
		 *
		 * @param string|null $value           Verification value or null.
		 * @param string      $source_contract Contract key.
		 */
		$value = apply_filters( 'review_captain_verification_for_source', $value, $source_contract );
		if ( ! in_array( $value, array( 'purchase', 'attendance' ), true ) ) {
			return new \WP_Error( 'not_allowed', 'This source cannot verify reviews.' );
		}
		$review = ReviewRepository::find( $id );
		if ( ! $review || 'site' !== $review['provider'] ) {
			return new \WP_Error( 'not_allowed', 'Only reviews collected on this site can be verified.' );
		}
		ReviewRepository::update(
			$id,
			array(
				'verification'     => $value,
				'verification_ref' => substr( $source_contract . ':' . $reference, 0, 191 ),
			)
		);
		ReviewRepository::log( $id, 'edited', 'verified:' . $value );
		return true;
	}

	/**
	 * Mark an external review as removed at the source (never output again).
	 */
	public static function tombstone( int $id ): void {
		ReviewRepository::update( $id, array( 'tombstoned_at' => current_time( 'mysql', true ) ) );
		ReviewRepository::log( $id, 'tombstoned' );
		self::bump_cache();
	}

	public static function delete( int $id ): bool {
		$ok = ReviewRepository::delete( $id );
		if ( $ok ) {
			AuditLog::log( 'review_deleted', 'review', $id );
			self::bump_cache();
		}
		return $ok;
	}

	/**
	 * Invalidate rendered widget caches (TEC-012).
	 */
	public static function bump_cache(): void {
		update_option( 'review_captain_cache_version', (int) get_option( 'review_captain_cache_version', 1 ) + 1, true );
		do_action( 'review_captain_content_changed' );
	}

	/**
	 * Common validation for local reviews.
	 *
	 * @param array<string,mixed> $input Input.
	 */
	private static function validate_common( array $input, bool $require_rating ): \WP_Error {
		$errors = new \WP_Error();
		$rating = $input['rating'] ?? null;
		if ( $require_rating && ( ! is_numeric( $rating ) || (int) $rating < 1 || (int) $rating > 5 ) ) {
			$errors->add( 'rating', __( 'Please choose a rating between 1 and 5 stars.', 'review-captain' ) );
		}
		$content = (string) ( $input['content'] ?? '' );
		$len     = function_exists( 'mb_strlen' ) ? mb_strlen( $content ) : strlen( $content );
		$min     = (int) Settings::get( 'min_text_length' );
		$max     = (int) Settings::get( 'max_text_length' );
		if ( $len < $min ) {
			/* translators: %d: minimum number of characters */
			$errors->add( 'content', sprintf( __( 'Please write at least %d characters.', 'review-captain' ), $min ) );
		}
		if ( $len > $max ) {
			/* translators: %d: maximum number of characters */
			$errors->add( 'content', sprintf( __( 'Please write at most %d characters.', 'review-captain' ), $max ) );
		}
		if ( '' === trim( (string) ( $input['author_name'] ?? '' ) ) ) {
			$errors->add( 'author_name', __( 'Please enter a name.', 'review-captain' ) );
		}
		$email = (string) ( $input['email'] ?? '' );
		if ( Settings::get( 'require_email' ) && ! is_email( $email ) ) {
			$errors->add( 'email', __( 'Please enter a valid email address.', 'review-captain' ) );
		} elseif ( '' !== $email && ! is_email( $email ) ) {
			$errors->add( 'email', __( 'Please enter a valid email address.', 'review-captain' ) );
		}
		return $errors;
	}

	/**
	 * @param array<string,mixed> $input Input.
	 */
	private static function validate_external( array $input ): \WP_Error {
		$errors = new \WP_Error();
		if ( ! in_array( $input['provider'] ?? '', self::EXTERNAL_PROVIDERS, true ) ) {
			$errors->add( 'provider', __( 'Please choose the platform.', 'review-captain' ) );
		}
		$min = isset( $input['rating_min'] ) ? (int) $input['rating_min'] : 1;
		$max = isset( $input['rating_max'] ) ? (int) $input['rating_max'] : 5;
		$val = $input['rating_value'] ?? null;
		if ( $max <= $min || $max > 100 ) {
			$errors->add( 'rating_max', __( 'Please enter a valid rating scale.', 'review-captain' ) );
		} elseif ( ! is_numeric( $val ) || (float) $val < $min || (float) $val > $max ) {
			$errors->add( 'rating_value', __( 'Please enter the original rating within the platform scale.', 'review-captain' ) );
		}
		if ( empty( $input['published_at'] ) || false === strtotime( (string) $input['published_at'] ) ) {
			$errors->add( 'published_at', __( 'Please enter the date of the review.', 'review-captain' ) );
		}
		$url = (string) ( $input['source_url'] ?? '' );
		if ( ! self::valid_https_url( $url ) ) {
			$errors->add( 'source_url', __( 'Please enter the https link to the review or profile.', 'review-captain' ) );
		}
		if ( '' === trim( (string) ( $input['attribution'] ?? '' ) ) ) {
			$errors->add( 'attribution', __( 'Please enter a source note (e.g. “Review on Google”).', 'review-captain' ) );
		}
		if ( '' === trim( (string) ( $input['content'] ?? '' ) ) && '' === trim( (string) ( $input['title'] ?? '' ) ) ) {
			$errors->add( 'content', __( 'Please enter the permitted review text or title.', 'review-captain' ) );
		}
		return $errors;
	}

	/**
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	private static function external_row( array $input, string $origin ): array {
		$status = in_array( $input['status'] ?? 'approved', array( 'approved', 'pending' ), true ) ? ( $input['status'] ?? 'approved' ) : 'approved';
		return array(
			'origin'       => $origin,
			'provider'     => $input['provider'],
			'type'         => in_array( $input['type'] ?? '', ReviewRepository::TYPES, true ) ? $input['type'] : (string) Settings::get( 'new_review_type_default' ),
			'rating_value' => round( (float) $input['rating_value'], 2 ),
			'rating_min'   => isset( $input['rating_min'] ) ? (int) $input['rating_min'] : 1,
			'rating_max'   => isset( $input['rating_max'] ) ? (int) $input['rating_max'] : 5,
			'title'        => self::clean_line( $input['title'] ?? '', 255 ),
			'content'      => self::clean_text( $input['content'] ?? '' ),
			'language'     => self::clean_line( $input['language'] ?? '', 20 ),
			'author_name'  => self::clean_line( $input['author_name'] ?? '', 191 ),
			'context_type' => self::clean_key( $input['context_type'] ?? '' ),
			'context_id'   => self::clean_line( $input['context_id'] ?? '', 64 ),
			'source_url'   => esc_url_raw( (string) $input['source_url'] ),
			'attribution'  => self::clean_line( $input['attribution'], 255 ),
			'status'       => $status,
			'verification' => 'external',
			'published_at' => gmdate( 'Y-m-d H:i:s', strtotime( (string) $input['published_at'] ) ),
		);
	}

	/**
	 * Validate a public https link for display. No DNS lookup: these URLs are only linked, never fetched by the server.
	 */
	public static function valid_https_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		return (bool) preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/i', (string) $parts['host'] ) && strlen( $url ) <= 2048;
	}

	public static function clean_line( $value, int $max ): ?string {
		$value = trim( sanitize_text_field( wp_unslash( (string) $value ) ) );
		if ( '' === $value ) {
			return null;
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	public static function clean_text( $value ): ?string {
		$value = trim( sanitize_textarea_field( wp_unslash( (string) $value ) ) );
		return '' === $value ? null : $value;
	}

	private static function clean_key( $value ): ?string {
		$value = substr( sanitize_key( (string) $value ), 0, 32 );
		return '' === $value ? null : $value;
	}

	public static function milestone( string $key, bool $condition = true ): void {
		if ( ! $condition ) {
			return;
		}
		$m = (array) get_option( 'review_captain_milestones', array() );
		if ( empty( $m[ $key ] ) ) {
			$m[ $key ] = gmdate( 'c' );
			update_option( 'review_captain_milestones', $m, false );
		}
	}
}
