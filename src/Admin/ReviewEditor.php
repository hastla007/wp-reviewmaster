<?php
/**
 * Builds and validates reviews from admin form input (RC-040, RC-041, RC-043, RC-053).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;

/**
 * Pure input → Review mapping. Never sets purchase_verified/attendance_confirmed (RC-041):
 * any verification value in the input is ignored; the state is derived from the entry type.
 * Moderators set the benefit flag (SET-RC-12, every entry type) and the merchant reply
 * (SET-RC-13, own reviews only).
 */
final class ReviewEditor {

	public const TYPE_LOCAL       = 'local';
	public const TYPE_TESTIMONIAL = 'testimonial';
	public const TYPE_EXTERNAL    = 'external';

	/**
	 * Fields editable on local (website-owned) records.
	 */
	public const LOCAL_EDITABLE = array( 'title', 'body', 'author_name', 'rating_value', 'language', 'published_at', 'context_type', 'context_id', 'incentivised', 'reply_body', 'reply_at' );

	/**
	 * Fields editable on external records (no content changes, only assignment).
	 */
	public const EXTERNAL_EDITABLE = array( 'context_type', 'context_id', 'profile_id', 'incentivised' );

	/**
	 * Maximum length of a merchant reply (SET-RC-13).
	 */
	public const REPLY_MAX_LENGTH = 5000;

	/**
	 * Entry types.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		return array(
			self::TYPE_LOCAL       => __( 'Eigene Bewertung (auf dieser Website abgegeben)', 'review-captain' ),
			self::TYPE_TESTIMONIAL => __( 'Erfahrungsbericht der Website (Text-Testimonial)', 'review-captain' ),
			self::TYPE_EXTERNAL    => __( 'Externe Bewertung manuell übernehmen (Auszug mit Quelle)', 'review-captain' ),
		);
	}

	/**
	 * Entry type of an existing record.
	 *
	 * @param Review $review Review.
	 */
	public static function type_of( Review $review ): string {
		if ( $review->platform->is_external() ) {
			return self::TYPE_EXTERNAL;
		}
		return Kind::TESTIMONIAL === $review->kind ? self::TYPE_TESTIMONIAL : self::TYPE_LOCAL;
	}

	/**
	 * Build a new record from input. Returns [review|null, errors].
	 *
	 * @param array<string, mixed> $input Unslashed input.
	 * @return array{0: Review|null, 1: list<string>}
	 */
	public static function create( array $input ): array {
		$type   = (string) ( $input['type'] ?? self::TYPE_LOCAL );
		$type   = array_key_exists( $type, self::types() ) ? $type : self::TYPE_LOCAL;
		$review = new Review();
		$errors = array();

		$review->origin       = Origin::MANUAL;
		$review->verification = Verification::UNVERIFIED; // RC-041: never from input.
		$status               = ReviewStatus::tryFrom( (string) ( $input['status'] ?? '' ) );
		$review->status       = ReviewStatus::APPROVED === $status ? ReviewStatus::APPROVED : ReviewStatus::PENDING;

		if ( self::TYPE_EXTERNAL === $type ) {
			$platform = Platform::tryFrom( sanitize_key( (string) ( $input['platform'] ?? '' ) ) );
			if ( null === $platform || ! $platform->is_external() ) {
				$errors[]           = 'missing_platform';
				$review->platform   = Platform::OTHER;
			} else {
				$review->platform = $platform;
			}
			$review->kind         = Kind::REVIEW;
			$review->verification = Verification::EXTERNAL_PLATFORM;
			$min                  = self::float_or_null( $input['rating_scale_min'] ?? '1' );
			$max                  = self::float_or_null( $input['rating_scale_max'] ?? '5' );
			$review->rating_scale_min = $min ?? 1.0;
			$review->rating_scale_max = $max ?? 5.0;
			$review->rating_value     = self::float_or_null( $input['rating_value'] ?? null );
			if ( null === $review->rating_value ) {
				$errors[] = 'missing_rating';
			}
			$url = trim( (string) ( $input['source_url'] ?? '' ) );
			if ( '' === $url ) {
				$errors[] = 'missing_source_url';
			} elseif ( ! Profiles::is_https( $url ) ) {
				$errors[] = 'source_url_not_https';
			}
			$review->source_url = '' === $url ? null : $url;
			$note               = mb_substr( sanitize_text_field( (string) ( $input['source_note'] ?? '' ) ), 0, 300 );
			if ( '' === $note ) {
				$errors[] = 'missing_source_note';
			}
			$review->meta['source_note'] = $note;
			$profile_id                  = absint( $input['profile_id'] ?? 0 );
			$review->profile_id          = $profile_id > 0 ? $profile_id : null;
		} else {
			$review->platform         = Platform::LOCAL;
			$review->kind             = self::TYPE_TESTIMONIAL === $type ? Kind::TESTIMONIAL : Kind::REVIEW;
			$review->rating_scale_min = 1.0;
			$review->rating_scale_max = 5.0;
			$review->rating_value     = self::float_or_null( $input['rating_value'] ?? null );
			if ( Kind::TESTIMONIAL === $review->kind ) {
				// Clearly marked as the website's own testimonial, not a customer platform review.
				$review->meta['website_testimonial'] = true;
			}
		}

		$review->title       = mb_substr( sanitize_text_field( (string) ( $input['title'] ?? '' ) ), 0, 255 );
		$review->body        = sanitize_textarea_field( (string) ( $input['body'] ?? '' ) );
		$review->author_name = mb_substr( sanitize_text_field( (string) ( $input['author_name'] ?? '' ) ), 0, 191 );
		$review->language    = self::language( $input['language'] ?? '' );
		[ $review->context_type, $review->context_id ] = self::context( $input );

		if ( '' === trim( $review->body ) ) {
			$errors[] = 'missing_body';
		}
		if ( '' === $review->author_name ) {
			$errors[] = 'missing_author';
		}
		$date = self::date_or_null( $input['published_at'] ?? '' );
		if ( null === $date && self::TYPE_EXTERNAL === $type ) {
			$errors[] = 'missing_date';
		}
		$review->published_at = $date ?? ( self::TYPE_EXTERNAL === $type ? null : gmdate( 'Y-m-d H:i:s' ) );
		$review->incentivised = self::flag( $input['incentivised'] ?? false );
		if ( self::TYPE_EXTERNAL !== $type && array_key_exists( 'reply_body', $input ) ) {
			$errors = array_merge( $errors, self::apply_reply( $review, $input ) );
		}

		$errors = array_values( array_unique( array_merge( $errors, self::domain_errors( $review, $errors ) ) ) );
		return array( $errors ? null : $review, $errors );
	}

	/**
	 * Apply edits to an existing record. External records only accept assignment fields.
	 * Returns [changed field names, errors]. Verification is never touched.
	 *
	 * @param Review               $review Review (modified in place).
	 * @param array<string, mixed> $input  Unslashed input.
	 * @return array{0: list<string>, 1: list<string>}
	 */
	public static function apply_edit( Review $review, array $input ): array {
		$before   = clone $review;
		$external = $review->platform->is_external();
		$errors   = array();

		[ $ctx_type, $ctx_id ] = self::context( $input );
		$review->context_type  = $ctx_type;
		$review->context_id    = $ctx_id;
		// SET-RC-12: the moderator sets or removes the benefit flag (all entry types).
		if ( array_key_exists( 'incentivised', $input ) ) {
			$review->incentivised = self::flag( $input['incentivised'] );
		}

		if ( $external ) {
			$profile_id         = absint( $input['profile_id'] ?? 0 );
			$review->profile_id = $profile_id > 0 ? $profile_id : null;
			if ( null !== $review->external_id ) {
				$review->profile_id = $before->profile_id; // Dedupe key must stay stable for synced records.
			}
		} else {
			$review->title       = mb_substr( sanitize_text_field( (string) ( $input['title'] ?? $review->title ) ), 0, 255 );
			$review->body        = sanitize_textarea_field( (string) ( $input['body'] ?? $review->body ) );
			$review->author_name = mb_substr( sanitize_text_field( (string) ( $input['author_name'] ?? $review->author_name ) ), 0, 191 );
			if ( array_key_exists( 'rating_value', $input ) ) {
				$review->rating_value = self::float_or_null( $input['rating_value'] );
			}
			if ( array_key_exists( 'language', $input ) ) {
				$review->language = self::language( $input['language'] );
			}
			if ( array_key_exists( 'published_at', $input ) ) {
				$review->published_at = self::date_or_null( $input['published_at'] ) ?? $review->published_at;
			}
			if ( array_key_exists( 'reply_body', $input ) ) {
				$errors = array_merge( $errors, self::apply_reply( $review, $input ) );
			}
			if ( '' === trim( $review->body ) ) {
				$errors[] = 'missing_body';
			}
			if ( '' === $review->author_name ) {
				$errors[] = 'missing_author';
			}
		}

		$errors = array_values( array_unique( array_merge( $errors, self::domain_errors( $review, $errors ) ) ) );
		$fields = array();
		foreach ( $external ? self::EXTERNAL_EDITABLE : array_merge( self::LOCAL_EDITABLE, array( 'profile_id' ) ) as $field ) {
			if ( $before->$field !== $review->$field ) {
				$fields[] = $field;
			}
		}
		return array( $fields, $errors );
	}

	/**
	 * Merchant reply from input (SET-RC-13): empty text removes the reply. The date is the given
	 * day (reply_at, Y-m-d), else the stored date while the text is unchanged, else now.
	 *
	 * @param Review               $review Review (modified in place).
	 * @param array<string, mixed> $input  Input with reply_body and optional reply_at.
	 * @return list<string> Errors.
	 */
	private static function apply_reply( Review $review, array $input ): array {
		$text = trim( sanitize_textarea_field( (string) ( $input['reply_body'] ?? '' ) ) );
		if ( '' === $text ) {
			$review->reply_body = null;
			$review->reply_at   = null;
			return array();
		}
		if ( mb_strlen( $text ) > self::REPLY_MAX_LENGTH ) {
			return array( 'reply_too_long' );
		}
		$date = self::date_or_null( $input['reply_at'] ?? '' );
		if ( null === $date ) {
			$date = null !== $review->reply_at && $text === $review->reply_body ? $review->reply_at : gmdate( 'Y-m-d H:i:s' );
		} elseif ( null !== $review->reply_at && substr( $review->reply_at, 0, 10 ) === substr( $date, 0, 10 ) ) {
			$date = $review->reply_at; // Same day as stored: keep the exact time.
		}
		$review->reply_body = $text;
		$review->reply_at   = $date;
		return array();
	}

	/**
	 * Checkbox / flag value.
	 *
	 * @param mixed $value Value.
	 */
	private static function flag( mixed $value ): bool {
		return in_array( is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '', array( '1', 'true', 'yes', 'on' ), true ) || true === $value;
	}

	/**
	 * Domain validation errors (deduplicated against already known ones).
	 *
	 * @param Review       $review Review.
	 * @param list<string> $known  Known errors.
	 * @return list<string>
	 */
	private static function domain_errors( Review $review, array $known ): array {
		$out = array();
		foreach ( $review->validate() as $code ) {
			if ( 'empty_review' === $code && in_array( 'missing_body', $known, true ) ) {
				continue;
			}
			if ( 'missing_source_url' === $code && in_array( 'source_url_not_https', $known, true ) ) {
				continue;
			}
			$out[] = $code;
		}
		return $out;
	}

	/**
	 * Context type/id from input.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{0: string|null, 1: string|null}
	 */
	private static function context( array $input ): array {
		$type = sanitize_key( (string) ( $input['context_type'] ?? '' ) );
		$id   = mb_substr( sanitize_text_field( (string) ( $input['context_id'] ?? '' ) ), 0, 64 );
		if ( '' === $type ) {
			return array( null, null );
		}
		return array( $type, '' === $id ? null : $id );
	}

	/**
	 * Language code (e.g. de, de_DE, en-US).
	 *
	 * @param mixed $value Value.
	 */
	private static function language( mixed $value ): string {
		$value = trim( (string) $value );
		return preg_match( '/^[a-zA-Z]{2,3}([_-][a-zA-Z0-9]{2,8})?$/', $value ) ? $value : '';
	}

	/**
	 * Numeric or null.
	 *
	 * @param mixed $value Value.
	 */
	private static function float_or_null( mixed $value ): ?float {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$value = str_replace( ',', '.', trim( (string) $value ) );
		return is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * Y-m-d → UTC datetime.
	 *
	 * @param mixed $value Value.
	 */
	private static function date_or_null( mixed $value ): ?string {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		return $value . ' 00:00:00';
	}
}
