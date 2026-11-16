<?php
/**
 * PSR-4 autoloader for the ReviewCaptain namespace (no Composer required at runtime).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain;

/**
 * Minimal PSR-4 autoloader.
 */
final class Autoloader {

	/**
	 * Base directory with trailing slash.
	 *
	 * @var string
	 */
	private static string $base_dir = '';

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Directory containing the namespace root.
	 */
	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . '/';
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function load( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = self::$base_dir . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
