<?php
/**
 * Photo reviews: validated, re-encoded and access-controlled storage (RC-121).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Media;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * One optional photo per local review.
 *
 * - Accepted: JPEG, PNG, WebP (detected from the file content, not the name), size limit from
 *   `submissions.photo_max_kb`, at most 40 megapixels.
 * - Every photo is decoded and re-encoded with GD (max. 1600 px edge). This removes EXIF/XMP
 *   metadata such as GPS position and camera serial numbers (data minimisation). Without GD,
 *   uploads are rejected instead of stored unprocessed.
 * - Files live in a protected uploads sub-directory under random names and are only delivered
 *   through {@see self::serve()}: publicly only while the review is approved and
 *   `display.show_photos` is on; moderators always.
 * - Deleted together with the review (ReviewRepository::purge_related) and by the privacy eraser.
 */
final class PhotoStore {

	public const SUBDIR = 'review-captain-photos';

	/**
	 * admin-post action delivering a photo.
	 */
	public const ACTION = 'review_captain_photo';

	/**
	 * Detected MIME type → file extension.
	 */
	public const ALLOWED = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	);

	public const MAX_PIXELS = 40000000;
	public const MAX_EDGE   = 1600;

	/**
	 * Per-request cache review_id => row|null.
	 *
	 * @var array<int, array<string, mixed>|null>
	 */
	private static array $cache = array();

	/**
	 * Validate an uploaded file. Returns an error code or null.
	 *
	 * @param array<string, mixed> $file           Entry of $_FILES.
	 * @param int                  $max_bytes      Maximum size.
	 * @param bool                 $require_upload Require is_uploaded_file() (false in tests).
	 */
	public static function validate( array $file, int $max_bytes, bool $require_upload = true ): ?string {
		if ( ! isset( $file['error'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return isset( $file['error'] ) && in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ? 'photo_size' : 'photo_upload';
		}
		$tmp = (string) $file['tmp_name'];
		if ( $require_upload && ! is_uploaded_file( $tmp ) ) {
			return 'photo_upload';
		}
		clearstatcache( true, $tmp );
		$size = is_file( $tmp ) ? (int) filesize( $tmp ) : 0;
		if ( $size <= 0 ) {
			return 'photo_upload';
		}
		if ( $size > $max_bytes ) {
			return 'photo_size';
		}
		$mime = self::detect_mime( $tmp );
		if ( ! isset( self::ALLOWED[ $mime ] ) ) {
			return 'photo_type';
		}
		if ( ! self::can_process() ) {
			return 'photo_unsupported';
		}
		$info = getimagesize( $tmp );
		if ( false === $info || $info[0] < 1 || $info[1] < 1 ) {
			return 'photo_invalid';
		}
		if ( $info[0] * $info[1] > self::MAX_PIXELS ) {
			return 'photo_size';
		}
		return null;
	}

	/**
	 * Whether image processing (GD) is available.
	 */
	public static function can_process(): bool {
		return function_exists( 'imagecreatefromstring' ) && function_exists( 'imagejpeg' ) && function_exists( 'imagepng' );
	}

	/**
	 * Decode, downscale and re-encode a validated upload (strips all metadata).
	 *
	 * @param string $tmp Path of the validated upload.
	 * @return array{bytes: string, mime: string, width: int, height: int}|null
	 */
	public static function process( string $tmp ): ?array {
		$mime = self::detect_mime( $tmp );
		$data = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $data || ! isset( self::ALLOWED[ $mime ] ) || ! self::can_process() ) {
			return null;
		}
		$image = @imagecreatefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- corrupt uploads must not emit warnings.
		if ( false === $image ) {
			return null;
		}
		$w = imagesx( $image );
		$h = imagesy( $image );
		if ( max( $w, $h ) > self::MAX_EDGE ) {
			$scale  = self::MAX_EDGE / max( $w, $h );
			$scaled = imagescale( $image, max( 1, (int) round( $w * $scale ) ), max( 1, (int) round( $h * $scale ) ) );
			if ( false !== $scaled ) {
				imagedestroy( $image );
				$image = $scaled;
				$w     = imagesx( $image );
				$h     = imagesy( $image );
			}
		}
		if ( 'image/webp' === $mime && ! function_exists( 'imagewebp' ) ) {
			$mime = 'image/jpeg';
		}
		ob_start();
		if ( 'image/png' === $mime ) {
			imagesavealpha( $image, true );
			$ok = imagepng( $image, null, 6 );
		} elseif ( 'image/webp' === $mime ) {
			$ok = imagewebp( $image, null, 82 );
		} else {
			$ok = imagejpeg( $image, null, 85 );
		}
		$bytes = (string) ob_get_clean();
		imagedestroy( $image );
		if ( ! $ok || '' === $bytes ) {
			return null;
		}
		return array(
			'bytes'  => $bytes,
			'mime'   => $mime,
			'width'  => $w,
			'height' => $h,
		);
	}

	/**
	 * Store processed image bytes for a review (one photo per review; a second call replaces it).
	 *
	 * @param int                                                       $review_id Review ID.
	 * @param array{bytes: string, mime: string, width: int, height: int} $image     Processed image.
	 */
	public static function store( int $review_id, array $image ): bool {
		global $wpdb;
		$ext = self::ALLOWED[ $image['mime'] ] ?? '';
		if ( '' === $ext ) {
			return false;
		}
		$dir  = self::dir();
		$name = 'photo-' . bin2hex( random_bytes( 16 ) ) . '.' . $ext;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( '' === $dir || false === file_put_contents( $dir . '/' . $name, $image['bytes'] ) ) {
			return false;
		}
		self::delete_for_review( $review_id );
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Schema::table( 'review_media' ),
			array(
				'review_id'  => $review_id,
				'file'       => $name,
				'mime'       => $image['mime'],
				'bytes'      => strlen( $image['bytes'] ),
				'width'      => (int) $image['width'],
				'height'     => (int) $image['height'],
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $ok ) {
			self::unlink( $name );
			return false;
		}
		unset( self::$cache[ $review_id ] );
		return true;
	}

	/**
	 * Photo row of a review.
	 *
	 * @param int $review_id Review ID.
	 * @return array<string, mixed>|null
	 */
	public static function for_review( int $review_id ): ?array {
		if ( array_key_exists( $review_id, self::$cache ) ) {
			return self::$cache[ $review_id ];
		}
		self::prime( array( $review_id ) );
		return self::$cache[ $review_id ] ?? null;
	}

	/**
	 * Load the photo rows of many reviews with one query (widget rendering).
	 *
	 * @param list<int> $review_ids Review IDs.
	 */
	public static function prime( array $review_ids ): void {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $review_ids ) ) ) );
		if ( ! $ids ) {
			return;
		}
		foreach ( $ids as $id ) {
			self::$cache[ $id ] = null;
		}
		$table = Schema::table( 'review_media' );
		$sql   = "SELECT * FROM {$table} WHERE review_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $ids ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			self::$cache[ (int) $row['review_id'] ] = $row;
		}
	}

	/**
	 * Delete the photo(s) of a review (rows and files). Safe to call repeatedly.
	 *
	 * @param int $review_id Review ID.
	 * @return int Deleted photos.
	 */
	public static function delete_for_review( int $review_id ): int {
		global $wpdb;
		$table = Schema::table( 'review_media' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$files = $wpdb->get_col( $wpdb->prepare( "SELECT file FROM {$table} WHERE review_id = %d", $review_id ) );
		foreach ( (array) $files as $file ) {
			self::unlink( (string) $file );
		}
		$wpdb->delete( $table, array( 'review_id' => $review_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		unset( self::$cache[ $review_id ] );
		return count( (array) $files );
	}

	/**
	 * Reset the request cache (tests).
	 */
	public static function flush(): void {
		self::$cache = array();
	}

	/**
	 * Public URL of a photo (delivered through admin-post.php, never a direct file URL).
	 *
	 * @param string $review_uuid Review UUID.
	 */
	public static function url( string $review_uuid ): string {
		return add_query_arg(
			array(
				'action'    => self::ACTION,
				'rc_review' => $review_uuid,
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Resolve the file a request may see. Public: only approved, not removed reviews with
	 * `display.show_photos` on; moderators: every review.
	 *
	 * @param string $review_uuid Review UUID.
	 * @param bool   $moderator   Whether the requester may moderate.
	 * @return array{path: string, mime: string, public: bool}|null
	 */
	public static function resolve( string $review_uuid, bool $moderator ): ?array {
		if ( ! preg_match( '/^[0-9a-f-]{36}$/', $review_uuid ) ) {
			return null;
		}
		$plugin = Plugin::instance();
		$review = $plugin->reviews()->find_by_uuid( $review_uuid );
		if ( null === $review || null === $review->id ) {
			return null;
		}
		$public = ReviewStatus::APPROVED === $review->status && null === $review->removed_at && (bool) $plugin->settings()->get( 'display', 'show_photos', false );
		if ( ! $public && ! $moderator ) {
			return null;
		}
		$row = self::for_review( $review->id );
		if ( null === $row ) {
			return null;
		}
		$path = self::path( (string) $row['file'] );
		if ( '' === $path || ! is_file( $path ) ) {
			return null;
		}
		return array(
			'path'   => $path,
			'mime'   => (string) $row['mime'],
			'public' => $public,
		);
	}

	/**
	 * admin-post handler: stream a photo or answer 404.
	 */
	public static function serve(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, access checked in resolve().
		$uuid = isset( $_GET['rc_review'] ) ? sanitize_key( wp_unslash( (string) $_GET['rc_review'] ) ) : '';
		$file = self::resolve( $uuid, current_user_can( Capabilities::MODERATE ) );
		if ( null === $file ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		header( 'Content-Type: ' . $file['mime'] );
		header( 'Content-Length: ' . (string) filesize( $file['path'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: default-src \'none\'' );
		header( $file['public'] ? 'Cache-Control: public, max-age=3600' : 'Cache-Control: private, no-store' );
		readfile( $file['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Absolute path of a stored file name (validated name only).
	 *
	 * @param string $file File name.
	 */
	public static function path( string $file ): string {
		if ( ! preg_match( '/^photo-[0-9a-f]{32}\.(jpg|png|webp)$/', $file ) ) {
			return '';
		}
		return self::dir() . '/' . $file;
	}

	/**
	 * Protected storage directory (created on demand).
	 */
	public static function dir(): string {
		$uploads = wp_upload_dir();
		$base    = is_array( $uploads ) ? (string) ( $uploads['basedir'] ?? '' ) : '';
		if ( '' === $base ) {
			return '';
		}
		$dir = rtrim( $base, '/\\' ) . '/' . self::SUBDIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( is_dir( $dir ) && ! is_file( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}

	/**
	 * Remove all stored photos and the directory (uninstall with data deletion).
	 *
	 * @return int Deleted files.
	 */
	public static function purge_all(): int {
		$uploads = wp_upload_dir();
		$base    = is_array( $uploads ) ? (string) ( $uploads['basedir'] ?? '' ) : '';
		$dir     = rtrim( $base, '/\\' ) . '/' . self::SUBDIR;
		if ( '' === $base || ! is_dir( $dir ) || is_link( $dir ) ) {
			return 0;
		}
		$deleted = 0;
		foreach ( (array) glob( $dir . '/photo-*' ) as $path ) {
			if ( is_string( $path ) && '' !== self::path( basename( $path ) ) && is_file( $path ) ) {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				++$deleted;
			}
		}
		foreach ( array( '.htaccess', 'index.php' ) as $protect ) {
			if ( is_file( $dir . '/' . $protect ) ) {
				unlink( $dir . '/' . $protect ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		$rest = scandir( $dir );
		if ( is_array( $rest ) && array() === array_diff( $rest, array( '.', '..' ) ) ) {
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		return $deleted;
	}

	/**
	 * Translated message for an error code.
	 *
	 * @param string $code Code.
	 */
	public static function message( string $code ): string {
		return match ( $code ) {
			'photo_size'        => __( 'Das Foto ist zu groß.', 'review-captain' ),
			'photo_type'        => __( 'Bitte laden Sie ein Foto im Format JPG, PNG oder WebP hoch.', 'review-captain' ),
			'photo_invalid'     => __( 'Das Foto konnte nicht gelesen werden.', 'review-captain' ),
			'photo_unsupported' => __( 'Fotos können auf dieser Website derzeit nicht verarbeitet werden.', 'review-captain' ),
			default             => __( 'Das Foto konnte nicht hochgeladen werden.', 'review-captain' ),
		};
	}

	/**
	 * MIME type from content.
	 *
	 * @param string $path Path.
	 */
	private static function detect_mime( string $path ): string {
		if ( ! function_exists( 'finfo_open' ) || ! is_file( $path ) ) {
			return '';
		}
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( false === $finfo ) {
			return '';
		}
		$mime = (string) finfo_file( $finfo, $path );
		finfo_close( $finfo );
		return $mime;
	}

	/**
	 * Delete a stored file by name.
	 *
	 * @param string $file File name.
	 */
	private static function unlink( string $file ): void {
		$path = self::path( $file );
		if ( '' !== $path && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
