<?php
/**
 * Minimum versions (MINVER-1, owner decision 2026-11-16) and database version check.
 *
 * PHP below the minimum blocks loading (see review-captain.php). An older database server
 * only produces a warning in WP-CLI doctor and diagnostics – no hard block.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Support;

/**
 * Requirement constants and database server parsing.
 */
final class Requirements {

	public const MIN_PHP     = '8.5';
	public const MIN_MYSQL   = '9.7';
	public const MIN_MARIADB = '12.3';

	/**
	 * Parse a server version string such as "10.11.14-MariaDB-0ubuntu0.24.04.1",
	 * "5.5.5-10.11.14-MariaDB" (replication prefix) or "9.7.0".
	 *
	 * @param string $server_info Value of $wpdb->db_server_info() / SELECT VERSION().
	 * @return array{vendor: string, version: string}
	 */
	public static function parse_db_server( string $server_info ): array {
		$info = trim( $server_info );
		if ( false !== stripos( $info, 'mariadb' ) ) {
			$info = (string) preg_replace( '/^5\.5\.5-/', '', $info );
			preg_match( '/(\d+\.\d+(?:\.\d+)?)/', $info, $m );
			return array(
				'vendor'  => 'mariadb',
				'version' => $m[1] ?? '',
			);
		}
		preg_match( '/(\d+\.\d+(?:\.\d+)?)/', $info, $m );
		return array(
			'vendor'  => '' === $info ? 'unknown' : 'mysql',
			'version' => $m[1] ?? '',
		);
	}

	/**
	 * Check a database server against the minimum.
	 *
	 * @param string $server_info Server version string.
	 * @return array{vendor: string, version: string, minimum: string, ok: bool|null}
	 *         ok is null when the version cannot be determined (e.g. SQLite drop-in).
	 */
	public static function check_db( string $server_info ): array {
		$parsed  = self::parse_db_server( $server_info );
		$minimum = 'mariadb' === $parsed['vendor'] ? self::MIN_MARIADB : self::MIN_MYSQL;
		$ok      = '' === $parsed['version'] ? null : version_compare( $parsed['version'], $minimum, '>=' );
		return $parsed + array(
			'minimum' => $minimum,
			'ok'      => $ok,
		);
	}

	/**
	 * Check the current site's database server.
	 *
	 * @return array{vendor: string, version: string, minimum: string, ok: bool|null}
	 */
	public static function current_db(): array {
		global $wpdb;
		$info = ( is_object( $wpdb ) && method_exists( $wpdb, 'db_server_info' ) ) ? (string) $wpdb->db_server_info() : '';
		return self::check_db( $info );
	}

	/**
	 * Whether the running PHP meets the minimum.
	 *
	 * @param string $php_version PHP version (defaults to PHP_VERSION).
	 */
	public static function php_ok( string $php_version = PHP_VERSION ): bool {
		return version_compare( $php_version, self::MIN_PHP, '>=' );
	}
}
