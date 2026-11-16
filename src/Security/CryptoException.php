<?php
/**
 * Crypto failure with a stable error code (RC-030).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Security;

defined( 'ABSPATH' ) || exit;

final class CryptoException extends \RuntimeException {

	public function code_name(): string {
		return $this->getMessage();
	}
}
