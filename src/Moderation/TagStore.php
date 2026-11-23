<?php
/**
 * Internal review tags (RC-124).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Moderation;

use ReviewCaptain\Install\Schema;

/**
 * Tags are internal moderation labels (e.g. "lieferung", "rueckfrage"). They are never rendered
 * publicly, never part of event payloads, schema markup or CSV exports. Stored in
 * `review_tags` (UNIQUE review_id+tag) so a provider sync, which rewrites `reviews.meta`, can
 * never drop them.
 */
final class TagStore {

	/**
	 * Default maximum of tags per review; raisable with `review_captain_max_tags` up to
	 * MAX_TAGS_LIMIT (SET-RC-31).
	 */
	public const MAX_TAGS       = 20;
	public const MAX_TAGS_LIMIT = 50;
	public const MAX_LENGTH     = 40;

	/**
	 * Effective maximum of tags per review.
	 */
	public static function max_tags(): int {
		/**
		 * Maximum number of internal tags per review (1–50, default 20).
		 *
		 * @param int $max Maximum.
		 */
		return max( 1, min( self::MAX_TAGS_LIMIT, (int) apply_filters( 'review_captain_max_tags', self::MAX_TAGS ) ) );
	}

	/**
	 * Normalise user input ("Lieferung, Rückfrage") into tag keys.
	 *
	 * @param string|list<string> $input Comma separated string or list.
	 * @return list<string>
	 */
	public static function normalize( string|array $input ): array {
		$parts = is_array( $input ) ? $input : explode( ',', $input );
		$tags  = array();
		foreach ( $parts as $part ) {
			$tag = strtolower( trim( (string) $part ) );
			$tag = strtr( $tag, array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', ' ' => '-' ) );
			$tag = substr( sanitize_key( $tag ), 0, self::MAX_LENGTH );
			if ( '' !== $tag ) {
				$tags[ $tag ] = $tag;
			}
		}
		return array_slice( array_values( $tags ), 0, self::max_tags() );
	}

	/**
	 * Tags of a review (sorted).
	 *
	 * @param int $review_id Review ID.
	 * @return list<string>
	 */
	public function for_review( int $review_id ): array {
		global $wpdb;
		$table = Schema::table( 'review_tags' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$tags = $wpdb->get_col( $wpdb->prepare( "SELECT tag FROM {$table} WHERE review_id = %d ORDER BY tag ASC", $review_id ) );
		return array_values( array_map( 'strval', (array) $tags ) );
	}

	/**
	 * Replace the tags of a review. Returns the stored tags.
	 *
	 * @param int                 $review_id Review ID.
	 * @param string|list<string> $input     Input.
	 * @return list<string>
	 */
	public function set( int $review_id, string|array $input ): array {
		global $wpdb;
		$table   = Schema::table( 'review_tags' );
		$wanted  = self::normalize( $input );
		$current = $this->for_review( $review_id );
		foreach ( array_diff( $current, $wanted ) as $tag ) {
			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'review_id' => $review_id,
					'tag'       => $tag,
				)
			);
		}
		foreach ( array_diff( $wanted, $current ) as $tag ) {
			// A parallel insert of the same tag is absorbed by the UNIQUE key (review_id, tag).
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (review_id, tag, created_at) VALUES (%d, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$review_id,
					$tag,
					gmdate( 'Y-m-d H:i:s' )
				)
			);
		}
		return $this->for_review( $review_id );
	}

	/**
	 * All tags in use with counts (admin filter).
	 *
	 * @return array<string, int>
	 */
	public function all(): array {
		global $wpdb;
		$table = Schema::table( 'review_tags' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT tag, COUNT(*) AS cnt FROM {$table} GROUP BY tag ORDER BY tag ASC LIMIT 500", ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['tag'] ] = (int) $row['cnt'];
		}
		return $out;
	}
}
