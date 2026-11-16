<?php
/**
 * Capability contract context_provider (CON-003). See docs/connectors/contract-v1.md §3.5.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors\Contract;

/**
 * Describes a public context object (course, appointment …) with label and URL.
 */
interface ContextProviderInterface {

	/**
	 * Describe a context. Never called during frontend requests.
	 *
	 * @param string $context_type Context type.
	 * @param string $context_id   Context ID.
	 * @return array{label: string, url: ?string}|null Null = unknown / not public.
	 */
	public function describe_context( string $context_type, string $context_id ): ?array;
}
