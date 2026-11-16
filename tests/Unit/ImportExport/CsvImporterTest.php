<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\ImportExport;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\ImportExport\CsvExporter;
use ReviewCaptain\ImportExport\CsvImporter;
use ReviewCaptain\ImportExport\UploadStore;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

final class CsvImporterTest extends TestCase {

	private const HEADER = 'kind,platform,external_id,rating_value,rating_scale_max,title,body,language,author_name,published_at,source_url,verification,status,context_type,context_id';

	/** @var list<string> */
	private array $files = array();

	protected function tearDown(): void {
		foreach ( $this->files as $f ) {
			if ( is_file( $f ) ) {
				unlink( $f );
			}
		}
		parent::tearDown();
	}

	private function importer(): CsvImporter {
		return new CsvImporter( Plugin::instance()->reviews() );
	}

	/** @param list<string> $lines */
	private function csv( array $lines, string $header = self::HEADER ): string {
		$path          = tempnam( sys_get_temp_dir(), 'rc-csv' );
		$this->files[] = $path;
		file_put_contents( $path, $header . "\n" . implode( "\n", $lines ) . "\n" );
		return $path;
	}

	private function fixture( string $name ): string {
		return REVIEW_CAPTAIN_DIR . 'tests/fixtures/import/' . $name;
	}

	/** @param list<array{line:int,code:string,message:string}> $errors */
	private function codes_for( array $errors, int $line ): array {
		return array_values( array_map( static fn( $e ) => $e['code'], array_filter( $errors, static fn( $e ) => $e['line'] === $line ) ) );
	}

	public function test_dry_run_valid_fixture_with_bom_and_multiline(): void {
		$r = $this->importer()->dry_run( $this->fixture( 'valid.csv' ) );
		$this->assertSame( 3, $r['rows'] );
		$this->assertSame( 3, $r['valid'] );
		$this->assertSame( array(), $r['errors'] );
		$this->assertSame( array(), $r['duplicates'] );
		$this->assertSame( 0, $this->rows( 'reviews' ), 'dry run writes nothing' );
		$this->assertSame( 0, $this->rows( 'import_batches' ) );
	}

	public function test_dry_run_reports_line_errors(): void {
		$future = gmdate( 'Y-m-d', time() + 3 * DAY_IN_SECONDS );
		$path   = $this->csv(
			array(
				'review,local,,5,5,,ok,de,Anna,2026-11-01,,,,,',             // 2 ok
				'story,local,,5,5,,Text,de,Anna,2026-11-01,,,,,',            // 3 invalid kind
				'review,local,,5,5,,,de,Anna,2026-11-01,,,,,',               // 4 missing body
				'review,local,,7,5,,Text,de,Anna,2026-11-01,,,,,',           // 5 out of scale
				'review,google,g-9,4,5,,Text,de,Anna,2026-11-01,,,,,',       // 6 missing source url
				'review,local,,4,5,,Text,de,Anna,' . $future . ',,,,,',      // 7 future
				'review,local,,4,5,,Text,de,Anna,01.11.2026,,,,,',           // 8 bad date
				'review,local,,,5,,Text,de,Anna,2026-11-01,,,,,',            // 9 missing rating
				'testimonial,google,g-10,,5,,Text,de,Anna,2026-11-01,https://g.test/1,,,,', // 10 testimonial external
				'review,mars,,4,5,,Text,de,Anna,2026-11-01,,,,,',            // 11 platform
				'review,local,,4,5,,Text,de,,2026-11-01,,,,,',               // 12 author
				'review,local,,4,5,,Text,de,Anna,2026-11-01,,,,galaxy,1',    // 13 context type
				'review,local,,4,5,,Text,de,Anna,2026-11-01,,fake_state,,,', // 14 verification
				'review,google,g-11,4,5,,Text,de,Anna,2026-11-01,http://g.test/1,,,,', // 15 not https
			)
		);
		$r = $this->importer()->dry_run( $path );
		$this->assertSame( 14, $r['rows'] );
		$this->assertSame( 1, $r['valid'] );
		$this->assertSame( array(), $this->codes_for( $r['errors'], 2 ) );
		$this->assertContains( 'invalid_kind', $this->codes_for( $r['errors'], 3 ) );
		$this->assertContains( 'missing_value', $this->codes_for( $r['errors'], 4 ) );
		$this->assertContains( 'rating_out_of_scale', $this->codes_for( $r['errors'], 5 ) );
		$this->assertContains( 'missing_source_url', $this->codes_for( $r['errors'], 6 ) );
		$this->assertContains( 'future_date', $this->codes_for( $r['errors'], 7 ) );
		$this->assertContains( 'invalid_date', $this->codes_for( $r['errors'], 8 ) );
		$this->assertContains( 'missing_rating', $this->codes_for( $r['errors'], 9 ) );
		$this->assertContains( 'testimonial_must_be_local', $this->codes_for( $r['errors'], 10 ) );
		$this->assertContains( 'invalid_platform', $this->codes_for( $r['errors'], 11 ) );
		$this->assertContains( 'missing_value', $this->codes_for( $r['errors'], 12 ) );
		$this->assertContains( 'invalid_context_type', $this->codes_for( $r['errors'], 13 ) );
		$this->assertContains( 'invalid_verification', $this->codes_for( $r['errors'], 14 ) );
		$this->assertContains( 'source_url_not_https', $this->codes_for( $r['errors'], 15 ) );
		foreach ( $r['errors'] as $e ) {
			$this->assertNotSame( '', $e['message'] );
		}
	}

	public function test_missing_required_column_and_unknown_column(): void {
		$r = $this->importer()->dry_run( $this->csv( array( 'review,local,5' ), 'kind,platform,rating_value' ) );
		$this->assertSame( array( 'missing_column' ), array_values( array_unique( $this->codes_for( $r['errors'], 1 ) ) ) );
		$this->assertSame( 0, $r['valid'] );
		$r2 = $this->importer()->dry_run( $this->fixture( 'semicolon.csv' ) );
		$this->assertSame( 1, $r2['valid'], 'semicolon delimiter detected' );
		$this->assertSame( 'unknown_column', $r2['warnings'][0]['code'] );
	}

	public function test_duplicates_in_file_and_db_and_suspected(): void {
		$existing               = new Review();
		$existing->origin       = Origin::PROVIDER_SYNC;
		$existing->platform     = Platform::GOOGLE;
		$existing->external_id  = 'g-db';
		$existing->rating_value = 4;
		$existing->body         = 'x';
		$existing->source_url   = 'https://g.test/db';
		$existing->verification = Verification::EXTERNAL_PLATFORM;
		Plugin::instance()->reviews()->insert( $existing );

		$local               = new Review();
		$local->body         = 'Schon vorhanden im System';
		$local->rating_value = 5;
		$local->author_name  = 'Erika';
		$local->published_at = '2026-10-05 08:00:00';
		Plugin::instance()->reviews()->insert( $local );

		$r = $this->importer()->dry_run(
			$this->csv(
				array(
					'review,google,g-1,4,5,,A,de,X,2026-11-01,https://g.test/1,,,,',   // 2
					'review,google,g-1,4,5,,A,de,X,2026-11-01,https://g.test/1,,,,',   // 3 dup in file
					'review,google,g-db,4,5,,A,de,X,2026-11-01,https://g.test/1,,,,',  // 4 dup in db
					'review,local,,5,5,,Gleicher Text,de,Max,2026-11-01,,,,,',          // 5
					'review,local,,5,5,,Gleicher Text,de,max,2026-11-01T18:00:00Z,,,,,', // 6 suspected (file)
					'review,local,,5,5,,Schon vorhanden im System,de,Erika,2026-10-05,,,,,', // 7 suspected (db)
					'review,trustpilot,g-1,4,5,,A,de,X,2026-11-01,https://t.test/1,,,,', // 8 other platform → not dup
				)
			)
		);
		$this->assertSame( array( 3, 4, 6, 7 ), $r['duplicates'] );
		$this->assertSame( array( 6, 7 ), $r['suspected'] );
		$this->assertSame( 3, $r['valid'] );
	}

	public function test_import_creates_batch_downgrades_verification_and_maps_status(): void {
		$path = $this->csv(
			array(
				'review,local,,5,5,,Gekauft,de,Anna,2026-11-01,,purchase_verified,approved,,',
				'review,google,g-1,4,5,,Extern,de,Bernd,2026-11-01,https://g.test/1,attendance_confirmed,flagged,,',
				'review,local,,3,5,,Lokal,de,Carla,2026-11-01,,external_platform,removed,,',
				'review,local,,3,5,,Archiv,de,Dora,2026-11-01,,unverified,archived,,',
				'review,local,,9,5,,Fehler,de,Emil,2026-11-01,,,,,',
			)
		);
		$dry = $this->importer()->dry_run( $path );
		$this->assertContains( 'verification_downgraded', array_column( $dry['warnings'], 'code' ) );
		$this->assertContains( 'status_reset', array_column( $dry['warnings'], 'code' ) );

		$result = $this->importer()->import( $path, 3, 'meine-datei.csv' );
		$this->assertGreaterThan( 0, $result['batch_id'] );
		$this->assertSame( 4, $result['imported'] );
		$this->assertSame( 1, $result['skipped'] );

		$reviews = Plugin::instance()->reviews()->query( array( 'import_batch_id' => $result['batch_id'], 'orderby' => 'id', 'order' => 'ASC' ) );
		$this->assertCount( 4, $reviews );
		foreach ( $reviews as $review ) {
			$this->assertSame( Origin::CSV_IMPORT, $review->origin );
			$this->assertSame( $result['batch_id'], $review->import_batch_id );
			$this->assertFalse( $review->consent_publish );
			$this->assertFalse( $review->verification->requires_source_contract(), 'RC-041: CSV never verifies' );
		}
		$this->assertSame( Verification::UNVERIFIED, $reviews[0]->verification );
		$this->assertSame( ReviewStatus::APPROVED, $reviews[0]->status );
		$this->assertSame( Verification::EXTERNAL_PLATFORM, $reviews[1]->verification );
		$this->assertSame( ReviewStatus::PENDING, $reviews[1]->status, 'flagged is not importable' );
		$this->assertSame( Verification::UNVERIFIED, $reviews[2]->verification, 'external_platform not allowed for local' );
		$this->assertSame( ReviewStatus::PENDING, $reviews[2]->status, 'removed is not importable' );
		$this->assertSame( ReviewStatus::ARCHIVED, $reviews[3]->status );

		$batch = $this->importer()->batch( $result['batch_id'] );
		$this->assertSame( 'completed', $batch['status'] );
		$this->assertSame( 'meine-datei.csv', $batch['filename'] );
		$this->assertSame( 5, (int) $batch['rows_total'] );
		$this->assertSame( 4, (int) $batch['rows_imported'] );
		$this->assertSame( 3, (int) $batch['created_by'] );

		$again = $this->importer()->import( $path, 3 );
		$this->assertSame( 0, $again['imported'], 'second import: external row is a duplicate, local rows are suspected duplicates' );
		$this->assertSame( 5, $again['skipped'] );
	}

	public function test_rollback_deletes_only_its_batch(): void {
		$path1 = $this->csv( array( 'review,local,,5,5,,Eins,de,Anna,2026-11-01,,,,,', 'review,local,,4,5,,Zwei,de,Bert,2026-11-01,,,,,' ) );
		$path2 = $this->csv( array( 'review,local,,5,5,,Drei,de,Cleo,2026-11-01,,,,,' ) );
		$b1    = $this->importer()->import( $path1, 1 )['batch_id'];
		$b2    = $this->importer()->import( $path2, 1 )['batch_id'];

		$manual               = new Review();
		$manual->body         = 'Manuell';
		$manual->rating_value = 5;
		$manual->import_batch_id = $b1; // Same batch ID but not origin csv_import → must survive.
		Plugin::instance()->reviews()->insert( $manual );
		$this->assertSame( 4, $this->rows( 'reviews' ) );

		$this->assertSame( 2, $this->importer()->rollback( $b1 ) );
		$this->assertSame( 2, $this->rows( 'reviews' ) );
		$this->assertCount( 1, Plugin::instance()->reviews()->query( array( 'import_batch_id' => $b2 ) ) );
		$this->assertNotNull( Plugin::instance()->reviews()->find( (int) $manual->id ) );

		$batch = $this->importer()->batch( $b1 );
		$this->assertSame( 'rolled_back', $batch['status'] );
		$this->assertNotEmpty( $batch['rolled_back_at'] );
		$this->assertSame( 0, $this->importer()->rollback( $b1 ), 'idempotent' );
		$this->assertSame( 0, $this->importer()->rollback( 999 ) );
		$this->assertSame( 1, (int) $this->db->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'audit_log' ) . " WHERE event = 'import.rolled_back'" ) );
	}

	public function test_modified_since_import(): void {
		$b  = $this->importer()->import( $this->csv( array( 'review,local,,5,5,,Eins,de,Anna,2026-11-01,,,,,' ) ), 1 )['batch_id'];
		$this->assertSame( 0, $this->importer()->modified_since_import( $b ) );
		$id = Plugin::instance()->reviews()->query( array( 'import_batch_id' => $b ) )[0]->id;
		Plugin::instance()->reviews()->change_status( (int) $id, ReviewStatus::APPROVED );
		$this->assertSame( 1, $this->importer()->modified_since_import( $b ) );
	}

	public function test_export_import_roundtrip(): void {
		$r               = new Review();
		$r->body         = '=Formel im Text';
		$r->title        = 'Zeile 1';
		$r->rating_value = 4.5;
		$r->author_name  = '@Anna';
		$r->published_at = '2026-11-01 10:00:00';
		Plugin::instance()->reviews()->insert( $r );
		$path          = tempnam( sys_get_temp_dir(), 'rc-rt' );
		$this->files[] = $path;
		$h             = fopen( $path, 'wb' );
		( new CsvExporter( Plugin::instance()->reviews() ) )->write( $h, array() );
		fclose( $h );
		Plugin::instance()->reviews()->delete( (int) $r->id );

		$result = $this->importer()->import( $path, 1 );
		$this->assertSame( 1, $result['imported'] );
		$back = Plugin::instance()->reviews()->query( array() )[0];
		$this->assertSame( '=Formel im Text', $back->body );
		$this->assertSame( '@Anna', $back->author_name );
		$this->assertSame( 4.5, $back->rating_value );
		$this->assertSame( '2026-11-01 10:00:00', $back->published_at );
	}

	public function test_limits(): void {
		$lines = array_fill( 0, CsvImporter::MAX_ROWS + 1, 'review,local,,5,5,,Text,de,Anna,2026-11-01,,,,,' );
		$r     = $this->importer()->dry_run( $this->csv( $lines ) );
		$this->assertSame( 'too_many_rows', $r['errors'][0]['code'] );
		$this->assertSame( 0, $r['valid'] );
		$this->assertSame( 0, $this->importer()->import( $this->csv( $lines ), 1 )['batch_id'] );

		$big           = tempnam( sys_get_temp_dir(), 'rc-big' );
		$this->files[] = $big;
		file_put_contents( $big, self::HEADER . "\n" . str_repeat( 'x', CsvImporter::MAX_BYTES ) );
		$this->assertSame( 'file_too_large', $this->importer()->dry_run( $big )['errors'][0]['code'] );
		$this->assertSame( 'file_unreadable', $this->importer()->dry_run( '/nonexistent/file.csv' )['errors'][0]['code'] );
	}

	public function test_date_parsing(): void {
		$this->assertSame( '2026-11-13 23:00:00', CsvImporter::parse_date( '2026-11-14' ), 'site zone Europe/Vienna' );
		$this->assertSame( '2026-11-14 09:00:00', CsvImporter::parse_date( '2026-11-14T10:00:00+01:00' ) );
		$this->assertSame( '2026-11-14 10:00:00', CsvImporter::parse_date( '2026-11-14T10:00:00Z' ) );
		$this->assertNull( CsvImporter::parse_date( '2026-02-30' ) );
		$this->assertNull( CsvImporter::parse_date( 'gestern' ) );
	}

	public function test_upload_validation(): void {
		$csv           = tempnam( sys_get_temp_dir(), 'rc-up' );
		$this->files[] = $csv;
		file_put_contents( $csv, self::HEADER . "\n" );
		$file = array( 'name' => 'reviews.csv', 'tmp_name' => $csv, 'error' => UPLOAD_ERR_OK, 'size' => filesize( $csv ) );
		$this->assertNull( UploadStore::validate( $file, false ) );
		$this->assertSame( 'upload_failed', UploadStore::validate( $file ), 'must be an HTTP upload' );
		$this->assertSame( 'invalid_extension', UploadStore::validate( array( 'name' => 'reviews.php' ) + $file, false ) );
		$this->assertSame( 'invalid_extension', UploadStore::validate( array( 'name' => 'reviews.csv.php' ) + $file, false ) );
		$this->assertSame( 'upload_failed', UploadStore::validate( array( 'error' => UPLOAD_ERR_INI_SIZE ) + $file, false ) );

		$png           = tempnam( sys_get_temp_dir(), 'rc-png' );
		$this->files[] = $png;
		file_put_contents( $png, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) );
		$this->assertSame( 'invalid_mime', UploadStore::validate( array( 'tmp_name' => $png ) + $file, false ) );

		$stored = UploadStore::store( $file, false );
		$this->assertNotNull( $stored );
		$this->assertTrue( UploadStore::owns( $stored ) );
		$this->assertFileExists( dirname( $stored ) . '/.htaccess' );
		$this->assertFalse( UploadStore::owns( $csv ) );
		UploadStore::delete( $csv );
		$this->assertFileExists( $csv, 'foreign files are never deleted' );
		UploadStore::delete( $stored );
		$this->assertFileDoesNotExist( $stored );
	}
}
