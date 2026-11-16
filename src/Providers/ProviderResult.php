<?php
/**
 * Result of a provider operation such as test_connection() (RC-082).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Providers;

use ReviewCaptain\Connectors\ConnectorException;
use ReviewCaptain\Support\ErrorCode;

/**
 * Immutable result value object.
 */
final class ProviderResult {

	/**
	 * Constructor.
	 *
	 * @param bool                 $ok         Success.
	 * @param string|null          $error_code Error code (rc.*), null on success.
	 * @param string               $message    Admin-facing message (sanitised).
	 * @param array<string, mixed> $data       Additional data (no secrets).
	 */
	private function __construct(
		public readonly bool $ok,
		public readonly ?string $error_code,
		public readonly string $message,
		public readonly array $data
	) {}

	/**
	 * Success.
	 *
	 * @param array<string, mixed> $data    Data.
	 * @param string               $message Message.
	 */
	public static function ok( array $data = array(), string $message = '' ): self {
		return new self( true, null, ConnectorException::sanitize_message( $message ), $data );
	}

	/**
	 * Failure. Unknown codes become rc.perm.failed.
	 *
	 * @param string               $error_code Code.
	 * @param string               $message    Message.
	 * @param array<string, mixed> $data       Data.
	 */
	public static function error( string $error_code, string $message = '', array $data = array() ): self {
		$code = ErrorCode::is_known( $error_code ) ? $error_code : ErrorCode::PERMANENT_FAILURE;
		return new self( false, $code, ConnectorException::sanitize_message( $message ), $data );
	}

	/**
	 * Next admin action for a failure.
	 */
	public function next_action(): string {
		return null === $this->error_code ? __( 'Keine Aktion nötig.', 'review-captain' ) : ErrorCode::admin_action( $this->error_code );
	}
}
