<?php
/**
 * One page returned by a provider (RC-020).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Providers;

use ReviewCaptain\Domain\Review;

/**
 * Immutable sync page.
 */
final class SyncPage {

	/**
	 * Normalised reviews.
	 *
	 * @var list<Review>
	 */
	public readonly array $reviews;

	/**
	 * Cursor for the next page, null = done.
	 */
	public readonly ?string $next_cursor;

	/**
	 * External IDs deleted at the source (tombstones).
	 *
	 * @var list<string>
	 */
	public readonly array $removed_external_ids;

	/**
	 * Constructor.
	 *
	 * @param list<Review> $reviews              Reviews.
	 * @param string|null  $next_cursor          Next cursor.
	 * @param list<string> $removed_external_ids Removed IDs.
	 * @throws \InvalidArgumentException On invalid input.
	 */
	public function __construct( array $reviews, ?string $next_cursor = null, array $removed_external_ids = array() ) {
		foreach ( $reviews as $review ) {
			if ( ! $review instanceof Review ) {
				throw new \InvalidArgumentException( 'SyncPage::reviews must contain Review objects' );
			}
		}
		foreach ( $removed_external_ids as $id ) {
			if ( ! is_string( $id ) || '' === $id || strlen( $id ) > 191 ) {
				throw new \InvalidArgumentException( 'SyncPage::removed_external_ids must contain non-empty strings' );
			}
		}
		if ( null !== $next_cursor && ( '' === $next_cursor || strlen( $next_cursor ) > 1024 ) ) {
			throw new \InvalidArgumentException( 'SyncPage::next_cursor must be null or 1..1024 bytes' );
		}
		$this->reviews              = array_values( $reviews );
		$this->next_cursor          = $next_cursor;
		$this->removed_external_ids = array_values( array_unique( $removed_external_ids ) );
	}

	/**
	 * Whether more pages follow.
	 */
	public function has_more(): bool {
		return null !== $this->next_cursor;
	}
}
