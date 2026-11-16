<?php
/**
 * Recipient resolver contract v1 (RC-023, CON-003). Called only right before sending; results are never stored.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Contracts;

defined( 'ABSPATH' ) || exit;

interface RecipientResolverInterface {

	public const RESOLVER_CONTRACT = '1.0';

	/**
	 * @return array{status:string, email:?string, first_name:?string, locale:?string, basis:string}
	 *         status: ok|unknown|revoked|erased; basis: consent|existing_customer|unknown|denied.
	 */
	public function resolve( string $recipient_ref, string $experience_id ): array;
}
