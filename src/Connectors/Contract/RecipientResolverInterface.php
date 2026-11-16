<?php
/**
 * Capability contract recipient_resolver (CON-003). See docs/connectors/contract-v1.md §3.2.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors\Contract;

/**
 * Resolves an opaque recipient reference into contact data right before sending.
 */
interface RecipientResolverInterface {

	/**
	 * Resolve a recipient.
	 *
	 * @param string $recipient_ref Site-local opaque reference (never an e-mail).
	 * @param string $purpose       'review_request' | 'review_request_reminder'.
	 * @return array{email: string, locale: ?string, permission: string}|null Null = unknown reference.
	 */
	public function resolve( string $recipient_ref, string $purpose ): ?array;
}
