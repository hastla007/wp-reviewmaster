<?php
/**
 * Connector main contract v1 (CON-002, docs/connectors/connector-vertrag.md).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Contracts;

defined( 'ABSPATH' ) || exit;

interface ConnectorInterface {

	public const CONNECTOR_CONTRACT = '1.0';

	/**
	 * Keys: key, label, version, contract, developer, capabilities (string[]), config_schema (array, optional), requires_core.
	 *
	 * @return array<string,mixed>
	 */
	public function metadata(): array;
}
