<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\ImportExport;

use ReviewCaptain\ImportExport\UploadStore;
use ReviewCaptain\Tests\TestCase;

final class UploadPurgeTest extends TestCase {

	public function test_purges_only_old_owned_import_files(): void {
		$dir   = UploadStore::dir();
		$old   = $dir . '/import-' . str_repeat( 'a', 32 ) . '.csv';
		$fresh = $dir . '/import-' . str_repeat( 'b', 32 ) . '.csv';
		$other = $dir . '/keep-me.csv';
		foreach ( array( $old, $fresh, $other ) as $f ) {
			file_put_contents( $f, "x\n" );
		}
		touch( $old, time() - 7200 );
		touch( $other, time() - 7200 );

		$this->assertSame( 1, UploadStore::purge_older_than( 3600 ) );
		$this->assertFileDoesNotExist( $old );
		$this->assertFileExists( $fresh );
		$this->assertFileExists( $other, 'files not matching the import pattern are never touched' );
		@unlink( $fresh );
		@unlink( $other );
	}
}
