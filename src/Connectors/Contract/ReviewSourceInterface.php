<?php
/**
 * Capability contract review_source (CON-003). See docs/connectors/contract-v1.md §3.3.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors\Contract;

/**
 * Delivers reviews collected by another local plugin.
 */
interface ReviewSourceInterface {

	/**
	 * Fetch a page of reviews.
	 *
	 * @param string $cursor Opaque cursor ('' on first call).
	 * @param int    $limit  1..100.
	 * @return array{reviews: list<array<string,mixed>>, next_cursor: ?string}
	 */
	public function fetch_reviews( string $cursor, int $limit ): array;
}
