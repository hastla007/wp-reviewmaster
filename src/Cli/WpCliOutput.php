<?php
/**
 * WP-CLI output adapter.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Cli;

/**
 * Forwards to WP_CLI.
 */
final class WpCliOutput implements Output {

	/**
	 * {@inheritDoc}
	 *
	 * @param string $message Message.
	 */
	public function log( string $message ): void {
		\WP_CLI::log( $message );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $message Message.
	 */
	public function success( string $message ): void {
		\WP_CLI::success( $message );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $message Message.
	 */
	public function warning( string $message ): void {
		\WP_CLI::warning( $message );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $message Message.
	 */
	public function error( string $message ): void {
		\WP_CLI::error( $message );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<array<string, mixed>> $rows   Rows.
	 * @param list<string>               $fields Fields.
	 * @param string                     $format Format.
	 */
	public function table( array $rows, array $fields, string $format = 'table' ): void {
		\WP_CLI\Utils\format_items( $format, $rows, $fields );
	}
}
