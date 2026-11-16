<?php
/**
 * Capability review_source (CON-003): reviews from a customer-owned system through an official export API.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Contracts;

defined( 'ABSPATH' ) || exit;

interface ReviewSourceInterface {

	/**
	 * @return array<int,array{ref:string,label:string,url?:string}>
	 */
	public function list_profiles(): array;

	/**
	 * @return array{items: array<int,array<string,mixed>>, next_cursor: ?string, complete: bool, deleted_external_ids: string[]}
	 */
	public function fetch_page( string $profile_ref, ?string $cursor ): array;
}
