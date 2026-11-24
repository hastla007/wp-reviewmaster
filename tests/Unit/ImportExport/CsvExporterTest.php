<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\ImportExport;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\ImportExport\Csv;
use ReviewCaptain\ImportExport\CsvExporter;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

final class CsvExporterTest extends TestCase {

	private function review( array $props = array() ): Review {
		$r               = new Review();
		$r->origin       = Origin::LOCAL_SUBMISSION;
		$r->body         = 'Guter Service';
		$r->rating_value = 5;
		$r->author_name  = 'Anna';
		$r->author_email = 'anna@example.test';
		$r->published_at = '2026-11-01 10:00:00';
		foreach ( $props as $k => $v ) {
			$r->$k = $v;
		}
		return Plugin::instance()->reviews()->insert( $r );
	}

	/** @return array{0: string, 1: list<list<string>>} */
	private function export( array $filters = array(), bool $personal = false, ?int &$count = null ): array {
		$h     = fopen( 'php://memory', 'w+b' );
		$count = ( new CsvExporter( Plugin::instance()->reviews() ) )->write( $h, $filters, $personal );
		rewind( $h );
		$raw = (string) stream_get_contents( $h );
		fclose( $h );
		$h = fopen( 'php://memory', 'w+b' );
		fwrite( $h, substr( $raw, 3 ) );
		rewind( $h );
		$rows = array();
		while ( false !== ( $row = fgetcsv( $h, 0, ',', '"', '' ) ) ) {
			$rows[] = $row;
		}
		fclose( $h );
		return array( $raw, $rows );
	}

	public function test_bom_header_and_columns(): void {
		$this->review();
		[ $raw, $rows ] = $this->export();
		$this->assertStringStartsWith( Csv::BOM, $raw );
		$this->assertSame( array_merge( Csv::IMPORT_COLUMNS, array( 'uuid', 'origin', 'created_at', 'reply_body', 'reply_at' ) ), $rows[0] );
		$this->assertCount( 2, $rows );
		$row = array_combine( $rows[0], $rows[1] );
		$this->assertSame( 'review', $row['kind'] );
		$this->assertSame( '5', $row['rating_value'] );
		$this->assertSame( '2026-11-01T10:00:00Z', $row['published_at'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $row['created_at'] );
	}

	public function test_no_email_by_default_only_on_explicit_request(): void {
		$this->review();
		[ $raw ] = $this->export();
		$this->assertStringNotContainsString( 'anna@example.test', $raw );
		$this->assertStringNotContainsString( 'author_email', $raw );
		[ $raw2, $rows ] = $this->export( array(), true );
		$this->assertStringContainsString( 'anna@example.test', $raw2 );
		$this->assertSame( 'author_email', end( $rows[0] ) );
	}

	public function test_formula_injection_is_neutralised(): void {
		$this->review(
			array(
				'title'       => '=HYPERLINK("https://evil.test","x")',
				'body'        => '+SUM(A1:A2)',
				'author_name' => '@admin',
				'language'    => '-de',
			)
		);
		$this->review( array( 'body' => "\tTab", 'author_name' => "\rCR" ) );
		[ , $rows ] = $this->export();
		$a = array_combine( $rows[0], $rows[1] );
		$this->assertSame( '\'=HYPERLINK("https://evil.test","x")', $a['title'] );
		$this->assertSame( "'+SUM(A1:A2)", $a['body'] );
		$this->assertSame( "'@admin", $a['author_name'] );
		$this->assertSame( "'-de", $a['language'] );
		$b = array_combine( $rows[0], $rows[2] );
		$this->assertSame( "'\tTab", $b['body'] );
		$this->assertSame( "'\rCR", $b['author_name'] );
		$this->assertSame( 'Normal', Csv::protect( 'Normal' ) );
		$this->assertSame( '=x', Csv::unprotect( Csv::protect( '=x' ) ) );
		$this->assertSame( "'quote", Csv::unprotect( "'quote" ) );
	}

	public function test_pagination_exports_all_rows_in_chunks(): void {
		for ( $i = 0; $i < 450; $i++ ) {
			$this->review( array( 'body' => 'Text ' . $i ) );
		}
		$this->db->queries = array();
		$count             = 0;
		[ , $rows ]        = $this->export( array(), false, $count );
		$this->assertSame( 450, $count );
		$this->assertCount( 451, $rows );
		$this->assertSame( 'Text 0', array_combine( $rows[0], $rows[1] )['body'] );
		$this->assertSame( 'Text 449', array_combine( $rows[0], $rows[450] )['body'] );
		$selects = array_filter( $this->db->queries, static fn( $q ) => str_starts_with( $q, 'SELECT * FROM' ) );
		$this->assertGreaterThanOrEqual( 3, count( $selects ), 'paginated queries' );
		foreach ( $selects as $sql ) {
			$this->assertMatchesRegularExpression( '/LIMIT (200|500) OFFSET \d+/', $sql );
		}
	}

	public function test_filters(): void {
		$this->review();
		$this->review(
			array(
				'platform'     => Platform::GOOGLE,
				'origin'       => Origin::PROVIDER_SYNC,
				'external_id'  => 'g1',
				'source_url'   => 'https://maps.google.com/?cid=1',
				'verification' => Verification::EXTERNAL_PLATFORM,
				'author_email' => null,
			)
		);
		$this->review( array( 'kind' => Kind::TESTIMONIAL, 'rating_value' => null ) );
		$count = 0;
		$this->export( array( 'platform' => 'google' ), false, $count );
		$this->assertSame( 1, $count );
		$this->export( array( 'kind' => 'testimonial' ), false, $count );
		$this->assertSame( 1, $count );
		$this->export( array( 'status' => 'approved' ), false, $count );
		$this->assertSame( 0, $count );
		$this->export( array( 'date_from' => '2999-01-01' ), false, $count );
		$this->assertSame( 0, $count );
		$this->export( array( 'author_email' => 'anna@example.test', 'search' => 'x' ), false, $count );
		$this->assertSame( 3, $count, 'only whitelisted filters are applied' );
	}
}
