<?php
/**
 * Connector main contract v1 (CON-002). See docs/connectors/contract-v1.md §2.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors\Contract;

/**
 * Every connector implements this interface plus one capability interface per declared capability.
 * Metadata methods must be fast and free of side effects (no DB writes, no HTTP).
 */
interface ConnectorInterface {

	/**
	 * Stable, unique key. Regex ^[a-z0-9][a-z0-9_-]{2,63}$. Must never change.
	 */
	public function key(): string;

	/**
	 * Translatable display name.
	 */
	public function label(): string;

	/**
	 * Version of the connector plugin (SemVer recommended, e.g. "1.2.0").
	 */
	public function version(): string;

	/**
	 * Implemented contract version, major 1: "1.0", "1.1" … or "1.x".
	 */
	public function contract_version(): string;

	/**
	 * Declared capabilities.
	 *
	 * @return list<string> Values of 'experience_source'|'recipient_resolver'|'review_source'|'request_transport'|'context_provider'.
	 */
	public function capabilities(): array;

	/**
	 * Developer/vendor (company or person), shown in the admin.
	 */
	public function developer(): string;
}
