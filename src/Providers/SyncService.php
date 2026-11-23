<?php
/**
 * Applies provider sync pages to the reviews table (RC-020, RC-027, TEC-004).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Providers;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Repository\ReviewRepository;

/**
 * Idempotent upsert via dedupe_key plus tombstones for removed external IDs.
 * Moderation status of existing records is preserved (ReviewRepository::upsert_external).
 *
 * Removed at the source (`sources.removed_review_behavior`, SET-RC-05): the review always gets
 * status `removed` (never shown again). With `tombstone` (default) its content (title, text,
 * author name, rating, avatar/reply meta, photo) is scrubbed at once, so only a placeholder with
 * the dedupe key remains; with `hide` the content stays until `privacy.retention_external_days`.
 */
class SyncService {

	public const REMOVED_BEHAVIORS = array( 'hide', 'tombstone' );

	/**
	 * Constructor.
	 *
	 * @param ReviewRepository $reviews          Repository.
	 * @param int              $ttl_seconds      Max. display time without refresh (refresh_due_at).
	 * @param string|null      $removed_behavior 'hide'|'tombstone'; null = setting sources.removed_review_behavior.
	 */
	public function __construct( private ReviewRepository $reviews, private int $ttl_seconds = DAY_IN_SECONDS, private ?string $removed_behavior = null ) {}

	/**
	 * Effective behaviour for reviews removed at the source. The filter may only tighten
	 * (`hide` → `tombstone`, e.g. a provider that demands deletion), never loosen.
	 */
	public function removed_behavior(): string {
		$value = $this->removed_behavior ?? (string) \ReviewCaptain\Plugin::instance()->settings()->get( 'sources', 'removed_review_behavior', 'tombstone' );
		$value = in_array( $value, self::REMOVED_BEHAVIORS, true ) ? $value : 'tombstone';
		/**
		 * Behaviour for reviews removed at the source. Only 'tombstone' can be forced.
		 *
		 * @param string $value 'hide'|'tombstone'.
		 */
		return 'tombstone' === apply_filters( 'review_captain_removed_review_behavior', $value ) ? 'tombstone' : $value;
	}

	/**
	 * Apply one page.
	 *
	 * @param SyncPage $page       Page.
	 * @param int      $profile_id Profile ID (profiles table).
	 * @return array{created: int, updated: int, unchanged: int, removed: int, invalid: int}
	 */
	public function apply( SyncPage $page, int $profile_id ): array {
		$now    = time();
		$result = array(
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'removed'   => 0,
			'invalid'   => 0,
		);

		$platforms = array();
		foreach ( $page->reviews as $incoming ) {
			$review = $this->prepare( clone $incoming, $profile_id, $now );
			if ( null === $review ) {
				++$result['invalid'];
				continue;
			}
			$platforms[ $review->platform->value ] = $review->platform;
			$existing                              = $this->reviews->find_by_dedupe_key( (string) $review->dedupe_key() );
			try {
				$upsert = $this->reviews->upsert_external( $review );
			} catch ( \InvalidArgumentException | \RuntimeException $e ) {
				++$result['invalid'];
				continue;
			}
			if ( $upsert['created'] ) {
				++$result['created'];
			} elseif ( null !== $existing && ( ReviewStatus::REMOVED === $existing->status || self::fingerprint( $existing ) !== self::fingerprint( $review ) ) ) {
				++$result['updated'];
			} else {
				++$result['unchanged'];
			}
		}

		if ( $page->removed_external_ids ) {
			$scrub      = 'tombstone' === $this->removed_behavior();
			$candidates = $this->platforms_for( $profile_id, $platforms );
			foreach ( $page->removed_external_ids as $external_id ) {
				foreach ( $candidates as $platform ) {
					$key      = sha1( $platform->value . '|' . $profile_id . '|' . $external_id );
					$existing = $this->reviews->find_by_dedupe_key( $key );
					if ( null === $existing || null === $existing->id ) {
						continue;
					}
					if ( ReviewStatus::REMOVED !== $existing->status && $this->reviews->change_status( $existing->id, ReviewStatus::REMOVED, 'remote_removed', true ) ) {
						++$result['removed'];
						if ( $scrub ) {
							$this->reviews->scrub_removed( $existing->id, 'remote_removed' );
						}
					} elseif ( $scrub && ReviewStatus::REMOVED === $existing->status && ! isset( $existing->meta['tombstone'] ) ) {
						// Removed but still with content: removed under "hide" or by an older version, or
						// the run stopped between status change and scrub. Reported again: scrub now (review P2).
						$this->reviews->scrub_removed( $existing->id, 'remote_removed' );
					}
					break;
				}
			}
		}
		return $result;
	}

	/**
	 * Normalise provenance fields the provider must not control.
	 *
	 * @param Review $review     Review (clone).
	 * @param int    $profile_id Profile ID.
	 * @param int    $now        Timestamp.
	 */
	private function prepare( Review $review, int $profile_id, int $now ): ?Review {
		if ( ! $review->platform->is_external() || null === $review->external_id || '' === $review->external_id ) {
			return null;
		}
		$review->id              = null;
		$review->uuid            = '';
		$review->kind            = Kind::REVIEW;
		$review->origin          = Origin::PROVIDER_SYNC;
		$review->profile_id      = $profile_id;
		$review->verification    = Verification::EXTERNAL_PLATFORM;
		$review->author_email    = null;
		$review->author_user_id  = null;
		$review->consent_publish = false;
		$review->consent_at      = null;
		$review->import_batch_id = null;
		$review->removed_at      = null;
		$review->created_at      = '';
		/**
		 * Initial moderation status of newly synced external reviews.
		 *
		 * @param ReviewStatus $status Status (pending by default).
		 */
		$status          = apply_filters( 'review_captain_sync_initial_status', ReviewStatus::PENDING );
		$review->status  = $status instanceof ReviewStatus && in_array( $status, array( ReviewStatus::PENDING, ReviewStatus::APPROVED ), true ) ? $status : ReviewStatus::PENDING;
		$review->fetched_at     = gmdate( 'Y-m-d H:i:s', $now );
		$review->refresh_due_at = gmdate( 'Y-m-d H:i:s', $now + max( 60, $this->ttl_seconds ) );
		$review->meta['last_verified_at'] = gmdate( 'c', $now );
		if ( $review->validate() ) {
			return null;
		}
		return $review;
	}

	/**
	 * Platforms to look up removed IDs for.
	 *
	 * @param int                     $profile_id Profile ID.
	 * @param array<string, Platform> $seen       Platforms seen on this page.
	 * @return list<Platform>
	 */
	private function platforms_for( int $profile_id, array $seen ): array {
		global $wpdb;
		$table = Schema::table( 'profiles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$value    = $wpdb->get_var( $wpdb->prepare( "SELECT platform FROM {$table} WHERE id = %d", $profile_id ) );
		$platform = is_string( $value ) ? Platform::tryFrom( $value ) : null;
		if ( null !== $platform ) {
			return array( $platform );
		}
		if ( $seen ) {
			return array_values( $seen );
		}
		return array_values( array_filter( Platform::cases(), static fn( Platform $p ): bool => $p->is_external() ) );
	}

	/**
	 * Content fingerprint for change detection.
	 *
	 * @param Review $r Review.
	 */
	private static function fingerprint( Review $r ): string {
		return sha1(
			(string) wp_json_encode(
				array(
					null === $r->rating_value ? null : round( $r->rating_value, 2 ),
					round( $r->rating_scale_min, 2 ),
					round( $r->rating_scale_max, 2 ),
					$r->title,
					$r->body,
					$r->language,
					$r->author_name,
					$r->source_url,
					$r->published_at,
					$r->context_type,
					$r->context_id,
				)
			)
		);
	}
}
