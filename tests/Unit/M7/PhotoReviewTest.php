<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Plugin;

/**
 * RC-121: optional photo, checked before publication, minimised, deletable.
 */
final class PhotoReviewTest extends M7TestCase {

	/**
	 * @var list<string>
	 */
	private array $tmp = array();

	protected function tearDown(): void {
		foreach ( $this->tmp as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		parent::tearDown();
	}

	/**
	 * Create an upload entry. JPEGs get an Exif segment with a fake GPS marker.
	 *
	 * @param string $kind jpeg|png|webp|text.
	 * @return array<string, mixed>
	 */
	private function upload( string $kind = 'jpeg', int $w = 40, int $h = 30 ): array {
		$path        = tempnam( sys_get_temp_dir(), 'rcimg' );
		$this->tmp[] = $path;
		if ( 'text' === $kind ) {
			file_put_contents( $path, "<?php echo 'x';" );
		} else {
			$img = imagecreatetruecolor( $w, $h );
			imagefill( $img, 0, 0, imagecolorallocate( $img, 200, 30, 30 ) );
			ob_start();
			'png' === $kind ? imagepng( $img ) : ( 'webp' === $kind ? imagewebp( $img ) : imagejpeg( $img ) );
			$bytes = (string) ob_get_clean();
			imagedestroy( $img );
			if ( 'jpeg' === $kind ) {
				$payload = "Exif\0\0GPS-SECRET-48.2082N-16.3738E";
				$bytes   = substr( $bytes, 0, 2 ) . "\xFF\xE1" . pack( 'n', strlen( $payload ) + 2 ) . $payload . substr( $bytes, 2 );
			}
			file_put_contents( $path, $bytes );
		}
		return array(
			'name'     => 'foto.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $path,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $path ),
		);
	}

	private function submit( array $file ): array {
		return $this->form()->process( $this->form_post(), array( 'rc_photo' => $file ) );
	}

	public function test_form_offers_upload_only_when_enabled(): void {
		$this->enable( array( 'submissions' => array( 'photos_enabled' => true ) ) );
		$html = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $html );
		$this->assertStringContainsString( 'type="file"', $html );
		$this->assertStringContainsString( 'accept="image/jpeg,image/png,image/webp"', $html );
	}

	public function test_photo_is_stored_reencoded_without_metadata_and_hidden_until_approved(): void {
		$this->enable( array( 'submissions' => array( 'photos_enabled' => true ) ) );
		$file = $this->upload();
		$this->assertStringContainsString( 'GPS-SECRET', (string) file_get_contents( $file['tmp_name'] ) );
		$res = $this->submit( $file );
		$this->assertTrue( $res['ok'], implode( ',', $res['errors'] ) );
		$review = $res['review'];
		$this->assertSame( ReviewStatus::PENDING, $review->status );

		$row = PhotoStore::for_review( (int) $review->id );
		$this->assertSame( 'image/jpeg', $row['mime'] );
		$this->assertSame( 40, (int) $row['width'] );
		$stored = PhotoStore::path( (string) $row['file'] );
		$this->assertStringNotContainsString( 'GPS-SECRET', (string) file_get_contents( $stored ), 'Exif removed by re-encoding' );
		$this->assertStringStartsWith( PhotoStore::dir(), $stored );
		$this->assertFileExists( PhotoStore::dir() . '/.htaccess' );

		$this->assertNull( PhotoStore::resolve( $review->uuid, false ), 'pending: not public' );
		$this->assertNotNull( PhotoStore::resolve( $review->uuid, true ), 'moderators see it' );

		Plugin::instance()->reviews()->change_status( (int) $review->id, ReviewStatus::APPROVED );
		$this->assertNull( PhotoStore::resolve( $review->uuid, false ), 'display.show_photos still off' );
		$this->assertStringNotContainsString( 'rc-card__photo', $this->shortcode( array( 'v' => '1' ) ) );

		$this->enable( array( 'display' => array( 'show_photos' => true ) ) );
		$public = PhotoStore::resolve( $review->uuid, false );
		$this->assertTrue( $public['public'] );
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'rc-card__photo', $html );
		$this->assertStringContainsString( 'action=review_captain_photo', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );
		$this->assertStringContainsString( 'alt="Foto zur Bewertung von Carla"', $html );

		Plugin::instance()->reviews()->change_status( (int) $review->id, ReviewStatus::FLAGGED );
		$this->assertNull( PhotoStore::resolve( $review->uuid, false ), 'hidden again when no longer approved' );
	}

	public function test_png_and_webp_keep_their_type_and_large_images_are_downscaled(): void {
		$this->enable( array( 'submissions' => array( 'photos_enabled' => true, 'photo_max_kb' => 10240 ) ) );
		$res = $this->submit( $this->upload( 'png', 2400, 1200 ) );
		$this->assertTrue( $res['ok'] );
		$row = PhotoStore::for_review( (int) $res['review']->id );
		$this->assertSame( 'image/png', $row['mime'] );
		$this->assertSame( PhotoStore::MAX_EDGE, (int) $row['width'] );
		$this->assertSame( 800, (int) $row['height'] );
		if ( function_exists( 'imagewebp' ) ) {
			$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
			$web = $this->submit( $this->upload( 'webp' ) );
			$this->assertSame( 'image/webp', PhotoStore::for_review( (int) $web['review']->id )['mime'] );
		}
	}

	public function test_wrong_type_and_size_are_rejected_before_anything_is_saved(): void {
		$this->enable( array( 'submissions' => array( 'photos_enabled' => true, 'photo_max_kb' => 100 ) ) );
		$res = $this->submit( $this->upload( 'text' ) );
		$this->assertSame( array( 'photo_type' ), $res['errors'] );
		$big          = $this->upload();
		file_put_contents( $big['tmp_name'], str_repeat( 'x', 101 * 1024 ), FILE_APPEND );
		$this->assertSame( array( 'photo_size' ), $this->submit( $big )['errors'] );
		$this->assertSame( array( 'photo_upload' ), $this->submit( array( 'error' => UPLOAD_ERR_PARTIAL, 'tmp_name' => '', 'name' => 'a.jpg' ) )['errors'] );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
		$this->assertSame( 0, $this->rows( 'review_media' ) );
	}

	public function test_photo_is_ignored_while_disabled_and_no_file_is_fine(): void {
		$res = $this->submit( $this->upload() );
		$this->assertTrue( $res['ok'] );
		$this->assertSame( 0, $this->rows( 'review_media' ) );
		$this->enable( array( 'submissions' => array( 'photos_enabled' => true ) ) );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.8';
		$none = $this->submit( array( 'error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'name' => '' ) );
		$this->assertTrue( $none['ok'] );
	}

	public function test_photo_is_deleted_with_the_review(): void {
		$this->enable( array( 'submissions' => array( 'photos_enabled' => true ) ) );
		$res  = $this->submit( $this->upload() );
		$id   = (int) $res['review']->id;
		$file = PhotoStore::path( (string) PhotoStore::for_review( $id )['file'] );
		Plugin::instance()->reviews()->delete( $id );
		$this->assertFileDoesNotExist( $file );
		$this->assertSame( 0, $this->rows( 'review_media' ) );
	}

	public function test_invalid_photo_names_never_resolve_to_paths(): void {
		$this->assertSame( '', PhotoStore::path( '../wp-config.php' ) );
		$this->assertSame( '', PhotoStore::path( 'photo-zz.jpg' ) );
		$this->assertNull( PhotoStore::resolve( '../../etc/passwd', true ) );
	}
}
