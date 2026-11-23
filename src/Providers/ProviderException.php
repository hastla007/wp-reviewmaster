<?php
/**
 * Provider error with retry hint (RC-082).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Providers;

use ReviewCaptain\Connectors\ConnectorException;
use ReviewCaptain\Support\ErrorCode;

/**
 * Extends ConnectorException so the job runner handles provider errors the same way.
 * Unknown codes are treated as rc.perm.failed (provider-v1.md).
 */
class ProviderException extends ConnectorException {

	/**
	 * Constructor.
	 *
	 * @param string   $error_code          Code.
	 * @param string   $message             Safe message.
	 * @param int|null $retry_after_seconds Retry-After hint.
	 */
	public function __construct( string $error_code, string $message = '', private ?int $retry_after_seconds = null ) {
		parent::__construct( $error_code, $message );
	}

	/**
	 * Retry-After hint in seconds.
	 */
	public function retry_after(): ?int {
		return $this->retry_after_seconds;
	}

	/**
	 * Fallback for unknown codes.
	 */
	protected function fallback_code(): string {
		return ErrorCode::PERMANENT_FAILURE;
	}

	/**
	 * Map an HTTP failure to a standard error (RC-082).
	 *
	 * @param int      $status        HTTP status (0 = timeout/connection error).
	 * @param int|null $retry_after   Retry-After seconds.
	 * @param bool     $token_expired Whether a 401 was caused by an expired, refreshable token.
	 */
	public static function from_http( int $status, ?int $retry_after = null, bool $token_expired = false ): self {
		$code = match ( true ) {
			0 === $status                     => ErrorCode::NET_TIMEOUT,
			401 === $status && $token_expired => ErrorCode::AUTH_EXPIRED,
			401 === $status                   => ErrorCode::AUTH_INVALID,
			403 === $status                   => ErrorCode::PERMISSION_DENIED,
			404 === $status, 410 === $status  => ErrorCode::REMOTE_REMOVED,
			429 === $status                   => ErrorCode::RATE_LIMITED,
			$status >= 500                    => ErrorCode::TEMP_UNAVAILABLE,
			default                           => ErrorCode::DATA_INVALID_PAYLOAD,
		};
		/* translators: %d: HTTP status code */
		return new self( $code, sprintf( __( 'Provider responded with HTTP %d.', 'review-captain' ), $status ), $retry_after );
	}
}
