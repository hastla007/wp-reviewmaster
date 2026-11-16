<?php
/**
 * Temporary storage for uploaded import files (RC-045).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\ImportExport;

/**
 * Validates uploads (.csv, MIME, size) and keeps them in a non-public directory under uploads
 * with random names until the import is confirmed or cancelled.
 */
class UploadStore {

	public const SUBDIR = 'review-captain-private';

	/**
	 * MIME types accepted for CSV files (finfo detection).
	 */
	public const ALLOWED_MIME = array( 'text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'text/comma-separated-values', 'application/vnd.ms-excel' );

	/**
	 * Validate an entry of $_FILES. Returns an error code or null.
	 *
	 * @param array<string, mixed> $file          File entry.
	 * @param bool                 $require_upload Require is_uploaded_file() (false in tests/CLI).
	 */
	public static function validate( array $file, bool $require_upload = true ): ?string {
		if ( ! isset( $file['error'], $file['tmp_name'], $file['name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return 'upload_failed';
		}
		$tmp = (string) $file['tmp_name'];
		if ( $require_upload && ! is_uploaded_file( $tmp ) ) {
			return 'upload_failed';
		}
		if ( 'csv' !== strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) ) {
			return 'invalid_extension';
		}
		$size = is_file( $tmp ) ? (int) filesize( $tmp ) : 0;
		if ( $size <= 0 ) {
			return 'empty_file';
		}
		if ( $size > CsvImporter::MAX_BYTES ) {
			return 'file_too_large';
		}
		$mime = '';
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( false !== $finfo ) {
				$mime = (string) finfo_file( $finfo, $tmp );
				finfo_close( $finfo );
			}
		}
		if ( '' === $mime || ! in_array( $mime, self::ALLOWED_MIME, true ) ) {
			return 'invalid_mime';
		}
		return null;
	}

	/**
	 * Protected storage directory (created on demand).
	 */
	public static function dir(): string {
		$uploads = wp_upload_dir();
		$dir     = rtrim( (string) $uploads['basedir'], '/\\' ) . '/' . self::SUBDIR;
		if ( ! is_dir( $dir ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $dir );
			} else {
				mkdir( $dir, 0750, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			}
		}
		if ( is_dir( $dir ) && ! is_file( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}

	/**
	 * Move a validated upload into storage.
	 *
	 * @param array<string, mixed> $file          File entry.
	 * @param bool                 $require_upload Use move_uploaded_file().
	 * @return string|null Stored path.
	 */
	public static function store( array $file, bool $require_upload = true ): ?string {
		$target = self::dir() . '/import-' . bin2hex( random_bytes( 16 ) ) . '.csv';
		$ok     = $require_upload ? move_uploaded_file( (string) $file['tmp_name'], $target ) : copy( (string) $file['tmp_name'], $target );
		return $ok ? $target : null;
	}

	/**
	 * Whether a path belongs to the storage directory.
	 *
	 * @param string $path Path.
	 */
	public static function owns( string $path ): bool {
		$real = realpath( $path );
		$dir  = realpath( self::dir() );
		return false !== $real && false !== $dir && str_starts_with( $real, $dir . DIRECTORY_SEPARATOR ) && (bool) preg_match( '/^import-[0-9a-f]{32}\.csv$/', basename( $real ) );
	}

	/**
	 * Delete a stored file.
	 *
	 * @param string $path Path.
	 */
	public static function delete( string $path ): void {
		if ( self::owns( $path ) ) {
			if ( function_exists( 'wp_delete_file' ) ) {
				wp_delete_file( $path );
			} else {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
	}

	/**
	 * Delete stored import files older than the given age. `.htaccess` does not protect files on
	 * nginx, so uploads must not linger (security review / TODO RC-045).
	 *
	 * @param int $max_age_seconds Maximum age.
	 * @return int Number of deleted files.
	 */
	public static function purge_older_than( int $max_age_seconds = HOUR_IN_SECONDS ): int {
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		$deleted = 0;
		$cutoff  = time() - $max_age_seconds;
		foreach ( (array) glob( $dir . '/import-*.csv' ) as $path ) {
			if ( is_string( $path ) && is_file( $path ) && filemtime( $path ) < $cutoff && self::owns( $path ) ) {
				self::delete( $path );
				++$deleted;
			}
		}
		return $deleted;
	}

	/**
	 * Translated message for an upload error code.
	 *
	 * @param string $code Code.
	 */
	public static function message( string $code ): string {
		return match ( $code ) {
			'invalid_extension' => __( 'Nur Dateien mit der Endung .csv sind erlaubt.', 'review-captain' ),
			'invalid_mime'      => __( 'Die Datei ist keine gültige CSV-Textdatei.', 'review-captain' ),
			'file_too_large'    => __( 'Die Datei ist größer als 5 MB.', 'review-captain' ),
			'empty_file'        => __( 'Die Datei ist leer.', 'review-captain' ),
			default             => __( 'Der Upload ist fehlgeschlagen.', 'review-captain' ),
		};
	}
}
