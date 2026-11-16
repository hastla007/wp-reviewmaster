<?php
/**
 * Job failure with stable code and error class (RC-030, TEC-006).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Jobs;

defined( 'ABSPATH' ) || exit;

class JobException extends \RuntimeException {

	private const CLASS_BY_CODE = array(
		'timeout'               => 'temporary',
		'provider_unavailable'  => 'temporary',
		'transport_failed'      => 'temporary',
		'quota_exceeded'        => 'rate_limit',
		'auth_failed'           => 'auth',
		'auth_expired'          => 'auth',
		'auth_forbidden'        => 'auth',
		'incompatible_contract' => 'permanent',
		'not_found'             => 'permanent',
		'crypto_key_missing'    => 'permanent',
		'invalid_payload'       => 'data',
		'source_unavailable'    => 'paused',
		'license_inactive'      => 'paused',
		'entitlement_missing'   => 'paused',
		'sender_missing'        => 'paused',
	);

	private string $code_name;
	private int $retry_after;
	private string $safe_message;

	public function __construct( string $code_name, string $safe_message = '', int $retry_after = 0 ) {
		parent::__construct( $code_name );
		$this->code_name    = $code_name;
		$this->safe_message = $safe_message;
		$this->retry_after  = $retry_after;
	}

	public function code_name(): string {
		return $this->code_name;
	}

	/** Message without secrets or personal data. */
	public function safe_message(): string {
		return $this->safe_message;
	}

	public function retry_after(): int {
		return $this->retry_after;
	}

	public function error_class(): string {
		// Unknown codes are treated as temporary (CON-007 T10).
		return self::CLASS_BY_CODE[ $this->code_name ] ?? 'temporary';
	}
}
