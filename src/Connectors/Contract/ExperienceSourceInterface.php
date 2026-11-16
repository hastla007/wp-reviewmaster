<?php
/**
 * Capability contract experience_source (CON-003). See docs/connectors/contract-v1.md §3.1.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors\Contract;

/**
 * Reports completed/cancelled/refunded experiences by polling.
 */
interface ExperienceSourceInterface {

	/**
	 * Poll a page of experience events.
	 *
	 * @param string $cursor Opaque cursor ('' on first call, max. 191 chars).
	 * @param int    $limit  1..100.
	 * @return array{events: list<array<string,mixed>>, next_cursor: ?string}
	 */
	public function poll_experiences( string $cursor, int $limit ): array;
}
