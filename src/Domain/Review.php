<?php
/**
 * Normalised review / testimonial record (review-schema v1).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Domain;

/**
 * Mutable data object mapped 1:1 to the reviews table.
 */
final class Review {

	public ?int $id                  = null;
	public string $uuid              = '';
	public Kind $kind                = Kind::REVIEW;
	public Origin $origin            = Origin::MANUAL;
	public Platform $platform        = Platform::LOCAL;
	public ?int $profile_id          = null;
	public ?string $external_id      = null;
	public ?float $rating_value      = null;
	public float $rating_scale_min   = 1.0;
	public float $rating_scale_max   = 5.0;
	public string $title             = '';
	public string $body              = '';
	public string $language          = '';
	public string $author_name       = '';
	public ?string $author_email     = null;
	public ?int $author_user_id      = null;
	public Verification $verification = Verification::UNVERIFIED;
	public ReviewStatus $status      = ReviewStatus::PENDING;
	public ?string $context_type     = null;
	public ?string $context_id       = null;
	public ?string $source_url       = null;
	public ?string $published_at     = null;
	public ?string $fetched_at       = null;
	public ?string $refresh_due_at   = null;
	public bool $consent_publish     = false;
	public ?string $consent_at       = null;
	public ?int $import_batch_id     = null;
	public ?string $experience_id    = null;

	/**
	 * Free-form JSON metadata (never secrets).
	 *
	 * @var array<string, mixed>
	 */
	public array $meta = array();

	public string $created_at   = '';
	public string $updated_at   = '';
	public ?string $removed_at  = null;

	/**
	 * Context types accepted by the core.
	 */
	public const CONTEXT_TYPES = array( 'post', 'product', 'event', 'voucher', 'app', 'form', 'course', 'organization' );

	/**
	 * Build from a database row.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	public static function from_row( array $row ): self {
		$r                   = new self();
		$r->id               = isset( $row['id'] ) ? (int) $row['id'] : null;
		$r->uuid             = (string) ( $row['uuid'] ?? '' );
		$r->kind             = Kind::tryFrom( (string) ( $row['kind'] ?? '' ) ) ?? Kind::REVIEW;
		$r->origin           = Origin::tryFrom( (string) ( $row['origin'] ?? '' ) ) ?? Origin::MANUAL;
		$r->platform         = Platform::tryFrom( (string) ( $row['platform'] ?? '' ) ) ?? Platform::OTHER;
		$r->profile_id       = self::nullable_int( $row['profile_id'] ?? null );
		$r->external_id      = self::nullable_string( $row['external_id'] ?? null );
		$r->rating_value     = isset( $row['rating_value'] ) && '' !== $row['rating_value'] ? (float) $row['rating_value'] : null;
		$r->rating_scale_min = isset( $row['rating_scale_min'] ) ? (float) $row['rating_scale_min'] : 1.0;
		$r->rating_scale_max = isset( $row['rating_scale_max'] ) ? (float) $row['rating_scale_max'] : 5.0;
		$r->title            = (string) ( $row['title'] ?? '' );
		$r->body             = (string) ( $row['body'] ?? '' );
		$r->language         = (string) ( $row['language'] ?? '' );
		$r->author_name      = (string) ( $row['author_name'] ?? '' );
		$r->author_email     = self::nullable_string( $row['author_email'] ?? null );
		$r->author_user_id   = self::nullable_int( $row['author_user_id'] ?? null );
		$r->verification     = Verification::tryFrom( (string) ( $row['verification'] ?? '' ) ) ?? Verification::UNVERIFIED;
		$r->status           = ReviewStatus::tryFrom( (string) ( $row['status'] ?? '' ) ) ?? ReviewStatus::PENDING;
		$r->context_type     = self::nullable_string( $row['context_type'] ?? null );
		$r->context_id       = self::nullable_string( $row['context_id'] ?? null );
		$r->source_url       = self::nullable_string( $row['source_url'] ?? null );
		$r->published_at     = self::nullable_string( $row['published_at'] ?? null );
		$r->fetched_at       = self::nullable_string( $row['fetched_at'] ?? null );
		$r->refresh_due_at   = self::nullable_string( $row['refresh_due_at'] ?? null );
		$r->consent_publish  = ! empty( $row['consent_publish'] );
		$r->consent_at       = self::nullable_string( $row['consent_at'] ?? null );
		$r->import_batch_id  = self::nullable_int( $row['import_batch_id'] ?? null );
		$r->experience_id    = self::nullable_string( $row['experience_id'] ?? null );
		$meta                = isset( $row['meta'] ) && is_string( $row['meta'] ) ? json_decode( $row['meta'], true ) : array();
		$r->meta             = is_array( $meta ) ? $meta : array();
		$r->created_at       = (string) ( $row['created_at'] ?? '' );
		$r->updated_at       = (string) ( $row['updated_at'] ?? '' );
		$r->removed_at       = self::nullable_string( $row['removed_at'] ?? null );
		return $r;
	}

	/**
	 * Convert to a database row (without id).
	 *
	 * @return array<string, mixed>
	 */
	public function to_row(): array {
		return array(
			'uuid'             => $this->uuid,
			'kind'             => $this->kind->value,
			'origin'           => $this->origin->value,
			'platform'         => $this->platform->value,
			'profile_id'       => $this->profile_id,
			'external_id'      => $this->external_id,
			'dedupe_key'       => $this->dedupe_key(),
			'rating_value'     => $this->rating_value,
			'rating_scale_min' => $this->rating_scale_min,
			'rating_scale_max' => $this->rating_scale_max,
			'title'            => $this->title,
			'body'             => $this->body,
			'language'         => $this->language,
			'author_name'      => $this->author_name,
			'author_email'     => $this->author_email,
			'author_user_id'   => $this->author_user_id,
			'verification'     => $this->verification->value,
			'status'           => $this->status->value,
			'context_type'     => $this->context_type,
			'context_id'       => $this->context_id,
			'source_url'       => $this->source_url,
			'published_at'     => $this->published_at,
			'fetched_at'       => $this->fetched_at,
			'refresh_due_at'   => $this->refresh_due_at,
			'consent_publish'  => $this->consent_publish ? 1 : 0,
			'consent_at'       => $this->consent_at,
			'import_batch_id'  => $this->import_batch_id,
			'experience_id'    => $this->experience_id,
			'meta'             => wp_json_encode( (object) $this->meta ),
			'created_at'       => $this->created_at,
			'updated_at'       => $this->updated_at,
			'removed_at'       => $this->removed_at,
		);
	}

	/**
	 * Deduplication key for external records (TEC-004). Null for records without external ID.
	 */
	public function dedupe_key(): ?string {
		if ( null === $this->external_id || '' === $this->external_id ) {
			return null;
		}
		return sha1( $this->platform->value . '|' . (string) ( $this->profile_id ?? 0 ) . '|' . $this->external_id );
	}

	/**
	 * Rating normalised to 0..1 for sorting/filtering only – never displayed as cross-platform score.
	 */
	public function normalized_rating(): ?float {
		if ( null === $this->rating_value || $this->rating_scale_max <= $this->rating_scale_min ) {
			return null;
		}
		return max( 0.0, min( 1.0, ( $this->rating_value - $this->rating_scale_min ) / ( $this->rating_scale_max - $this->rating_scale_min ) ) );
	}

	/**
	 * Validate the record. Returns list of error codes (empty = valid).
	 *
	 * @return list<string>
	 */
	public function validate(): array {
		$errors = array();
		if ( null !== $this->rating_value && ( $this->rating_value < $this->rating_scale_min || $this->rating_value > $this->rating_scale_max ) ) {
			$errors[] = 'rating_out_of_scale';
		}
		if ( $this->rating_scale_max <= $this->rating_scale_min ) {
			$errors[] = 'invalid_scale';
		}
		if ( '' === trim( $this->body ) && null === $this->rating_value ) {
			$errors[] = 'empty_review';
		}
		if ( $this->platform->is_external() ) {
			// RC-043: external records need provenance.
			if ( null === $this->source_url || '' === $this->source_url ) {
				$errors[] = 'missing_source_url';
			}
			if ( Kind::TESTIMONIAL === $this->kind ) {
				$errors[] = 'testimonial_must_be_local';
			}
		}
		if ( null !== $this->source_url && '' !== $this->source_url && ! preg_match( '#^https://#i', $this->source_url ) ) {
			$errors[] = 'source_url_not_https';
		}
		if ( null !== $this->context_type && ! in_array( $this->context_type, self::CONTEXT_TYPES, true ) ) {
			$errors[] = 'invalid_context_type';
		}
		if ( Verification::EXTERNAL_PLATFORM === $this->verification && ! $this->platform->is_external() ) {
			$errors[] = 'external_verification_on_local';
		}
		return $errors;
	}

	/**
	 * Helper.
	 *
	 * @param mixed $v Value.
	 */
	private static function nullable_int( mixed $v ): ?int {
		return null === $v || '' === $v ? null : (int) $v;
	}

	/**
	 * Helper.
	 *
	 * @param mixed $v Value.
	 */
	private static function nullable_string( mixed $v ): ?string {
		return null === $v || '' === $v ? null : (string) $v;
	}
}
