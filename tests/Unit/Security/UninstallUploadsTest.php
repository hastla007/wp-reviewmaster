<?php
/**
 * Security review 2026-11, finding SR-02: uninstall must not leave CSV import uploads
 * (personal data, only .htaccess-protected) behind in the uploads directory.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Security;

use ReviewCaptain\ImportExport\UploadStore;
use ReviewCaptain\Privacy\Uninstaller;
use ReviewCaptain\Tests\TestCase;

final class UninstallUploadsTest extends TestCase {

	private function stored_upload(): string {
		$tmp = tempnam( sys_get_temp_dir(), 'rc-up' );
		file_put_contents( $tmp, "kind,platform,body,author_name,published_at\nreview,local,Text,Anna,2026-11-01\n" );
		$path = UploadStore::store(
			array(
				'tmp_name' => $tmp,
				'name'     => 'reviews.csv',
				'error'    => 0,
			),
			false
		);
		unlink( $tmp );
		$this->assertNotNull( $path );
		$this->assertFileExists( (string) $path );
		return (string) $path;
	}

	public function test_uninstall_removes_pending_import_files_even_when_data_is_kept(): void {
		$path = $this->stored_upload();
		$dir  = dirname( $path );

		Uninstaller::run(); // delete_data_on_uninstall is off by default.

		$this->assertFileDoesNotExist( $path );
		$this->assertFileDoesNotExist( $dir . '/.htaccess' );
		$this->assertDirectoryDoesNotExist( $dir );
	}

	public function test_foreign_files_and_directory_are_left_alone(): void {
		$path    = $this->stored_upload();
		$dir     = dirname( $path );
		$foreign = $dir . '/not-ours.txt';
		file_put_contents( $foreign, 'x' );

		$this->assertSame( 1, UploadStore::purge_all() );

		$this->assertFileDoesNotExist( $path );
		$this->assertFileExists( $foreign );
		$this->assertDirectoryExists( $dir );
		unlink( $foreign );
		$this->assertSame( 0, UploadStore::purge_all() );
		$this->assertDirectoryDoesNotExist( $dir );
	}

	public function test_purge_all_never_creates_the_directory(): void {
		UploadStore::purge_all();
		$dir = sys_get_temp_dir() . '/' . UploadStore::SUBDIR;
		$this->assertDirectoryDoesNotExist( $dir );
		$this->assertSame( 0, UploadStore::purge_all() );
		$this->assertDirectoryDoesNotExist( $dir );
	}
}
