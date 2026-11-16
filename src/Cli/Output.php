<?php
/**
 * CLI output abstraction (keeps commands testable).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Cli;

/**
 * Output sink for CLI commands.
 */
interface Output {

	/**
	 * Informational line.
	 *
	 * @param string $message Message.
	 */
	public function log( string $message ): void;

	/**
	 * Success line.
	 *
	 * @param string $message Message.
	 */
	public function success( string $message ): void;

	/**
	 * Warning line.
	 *
	 * @param string $message Message.
	 */
	public function warning( string $message ): void;

	/**
	 * Fatal error (WP-CLI exits; implementations must not return normally).
	 *
	 * @param string $message Message.
	 */
	public function error( string $message ): void;

	/**
	 * Tabular data.
	 *
	 * @param list<array<string, mixed>> $rows   Rows.
	 * @param list<string>               $fields Columns.
	 * @param string                     $format table|json|csv|yaml.
	 */
	public function table( array $rows, array $fields, string $format = 'table' ): void;
}
