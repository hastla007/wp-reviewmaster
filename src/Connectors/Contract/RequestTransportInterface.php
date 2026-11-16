<?php
/**
 * Capability contract request_transport (CON-003). See docs/connectors/contract-v1.md §3.4.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors\Contract;

/**
 * Delivers a fully rendered review request message.
 */
interface RequestTransportInterface {

	/**
	 * Send a message. Must prevent duplicate delivery via $message['idempotency_key'].
	 *
	 * @param array<string, mixed> $message Message (contract 1.0).
	 * @return array{status: string, provider_message_id: ?string} status 'accepted'|'rejected'.
	 */
	public function send( array $message ): array;
}
