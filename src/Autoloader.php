<?php
/**
 * PSR-4 autoloader for the ReviewCaptain namespace (no Composer needed at runtime).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	public static function load( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
