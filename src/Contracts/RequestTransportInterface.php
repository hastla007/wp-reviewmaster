<?php
/**
 * Capability request_transport (CON-003).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Contracts;

defined( 'ABSPATH' ) || exit;

interface RequestTransportInterface {

	/**
	 * @param array{to:string, subject:string, html:string, text:string, unsubscribe_url:string, locale:string} $message Message.
	 * @return array{status:string, message_id:?string} status: sent|failed_temporary|failed_permanent.
	 */
	public function send( array $message ): array;
}
