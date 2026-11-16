<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\ImportExport\CsvExporter;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

/**
 * TEC-014: the repository maintains sorted_at on insert/update; ordering by published_at uses it;
 * the CSV export pages with after_id and returns every row exactly once.
 */
final class SortedAtTest extends TestCase {

	private function make( ?string $published, string $body ): Review {
		$r                  = new Review();
		$r->rating_value    = 4.0;
		$r->body            = $body;
		$r->author_name     = 'Test';
		$r->status          = ReviewStatus::APPROVED;
		$r->consent_publish = true;
		$r->published_at    = $published;
		return Plugin::instance()->reviews()->insert( $r );
	}

	public function test_insert_and_update_maintain_sorted_at_and_order(): void {
		$old   = $this->make( '2020-01-01 00:00:00', 'alt' );
		$fresh = $this->make( null, 'neu' );
		$t     = Schema::table( 'reviews' );
		$this->assertSame( '2020-01-01 00:00:00', (string) $this->db->get_var( "SELECT sorted_at FROM {$t} WHERE id = " . (int) $old->id ) );
		$this->assertSame( $fresh->created_at, (string) $this->db->get_var( "SELECT sorted_at FROM {$t} WHERE id = " . (int) $fresh->id ) );

		$list = Plugin::instance()->reviews()->query( array( 'status' => 'approved', 'orderby' => 'published_at', 'order' => 'DESC' ) );
		$this->assertSame( array( $fresh->id, $old->id ), array_map( static fn( $r ) => $r->id, $list ) );

		$old->published_at = '2030-01-01 00:00:00';
		Plugin::instance()->reviews()->update( $old );
		$this->assertSame( '2030-01-01 00:00:00', (string) $this->db->get_var( "SELECT sorted_at FROM {$t} WHERE id = " . (int) $old->id ) );
		$list = Plugin::instance()->reviews()->query( array( 'status' => 'approved', 'orderby' => 'published_at', 'order' => 'DESC' ) );
		$this->assertSame( $old->id, $list[0]->id );
	}

	public function test_export_keyset_returns_each_row_once(): void {
		$n = CsvExporter::CHUNK + 7;
		for ( $i = 0; $i < $n; $i++ ) {
			$this->make( null, 'Zeile ' . $i );
		}
		$h     = fopen( 'php://memory', 'w+' ); // phpcs:ignore
		$count = ( new CsvExporter( Plugin::instance()->reviews() ) )->write( $h, array() );
		$this->assertSame( $n, $count );
		rewind( $h );
		$raw = (string) stream_get_contents( $h );
		for ( $i = 0; $i < $n; $i += 97 ) {
			$this->assertSame( 1, substr_count( $raw, 'Zeile ' . $i . '"' ) + substr_count( $raw, 'Zeile ' . $i . "\r" ) + substr_count( $raw, 'Zeile ' . $i . "\n" ) + substr_count( $raw, 'Zeile ' . $i . ',' ), 'Zeile ' . $i );
		}
	}
}
