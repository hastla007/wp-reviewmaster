<?php
/**
 * Connector error (CON-013). See docs/connectors/contract-v1.md §5.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors;

use ReviewCaptain\Support\ErrorCode;

/**
 * Exception thrown by connectors (and providers) with a stable error code rc.<area>.<code>.
 * Unknown codes are normalised to rc.temp.unavailable by error_code().
 */
class ConnectorException extends \RuntimeException {

	/**
	 * Maximum stored message length.
	 */
	public const MAX_MESSAGE = 500;

	/**
	 * Raw code as passed by the connector.
	 *
	 * @var string
	 */
	private string $raw_code;

	/**
	 * Constructor.
	 *
	 * @param string          $error_code Error code rc.<area>.<code>.
	 * @param string          $message    Admin-facing message without secrets or personal data.
	 * @param \Throwable|null $previous   Previous exception.
	 */
	public function __construct( string $error_code, string $message = '', ?\Throwable $previous = null ) {
		$this->raw_code = $error_code;
		parent::__construct( self::sanitize_message( $message ), 0, $previous );
	}

	/**
	 * Effective error code (unknown codes → rc.temp.unavailable).
	 */
	public function error_code(): string {
		return ErrorCode::is_known( $this->raw_code ) ? $this->raw_code : $this->fallback_code();
	}

	/**
	 * Code as supplied by the thrower (for diagnostics only).
	 */
	public function raw_error_code(): string {
		return $this->raw_code;
	}

	/**
	 * Code used when the supplied code is unknown.
	 */
	protected function fallback_code(): string {
		return ErrorCode::TEMP_UNAVAILABLE;
	}

	/**
	 * Strip markup, e-mail addresses and token-like strings and cut to 500 characters.
	 *
	 * @param string $message Message.
	 */
	public static function sanitize_message( string $message ): string {
		$message = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $message ) : trim( strip_tags( $message ) );
		$message = (string) preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $message );
		$message = (string) preg_replace( '/\b(bearer\s+)?[A-Za-z0-9_\-]{32,}\b/i', '[redacted]', $message );
		return mb_substr( $message, 0, self::MAX_MESSAGE );
	}
}
