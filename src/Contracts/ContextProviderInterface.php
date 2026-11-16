<?php
/**
 * Capability context_provider (CON-003).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Contracts;

defined( 'ABSPATH' ) || exit;

interface ContextProviderInterface {

	/**
	 * @return array{type:string,id:string,label?:string}|null
	 */
	public function current_context(): ?array;
}
