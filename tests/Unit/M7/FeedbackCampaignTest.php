<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Feedback\CampaignStore;
use ReviewCaptain\Feedback\QrCode;
use ReviewCaptain\Install\Schema;

/**
 * RC-126: feedback link + QR (own encoder) + official wa.me click-to-chat link.
 */
final class FeedbackCampaignTest extends M7TestCase {

	/**
	 * Reference values from ISO/IEC 18004 (format information table, version information,
	 * and the "HELLO WORLD" 1-M example's ECC codewords).
	 */
	public function test_encoder_building_blocks_match_the_standard(): void {
		$qr  = QrCode::encode( 'x', 0 );
		$fmt = '';
		for ( $i = 0; $i <= 5; $i++ ) {
			$fmt .= $qr->module( 8, $i ) ? '1' : '0';
		}
		$fmt .= ( $qr->module( 8, 7 ) ? '1' : '0' ) . ( $qr->module( 8, 8 ) ? '1' : '0' ) . ( $qr->module( 7, 8 ) ? '1' : '0' );
		for ( $i = 9; $i < 15; $i++ ) {
			$fmt .= $qr->module( 14 - $i, 8 ) ? '1' : '0';
		}
		// Bits are read LSB first here; level M + mask 0 = 101010000010010 (MSB first).
		$this->assertSame( '101010000010010', strrev( $fmt ) );

		$rs  = new \ReflectionMethod( QrCode::class, 'rs_remainder' );
		$div = new \ReflectionMethod( QrCode::class, 'rs_divisor' );
		$ecc = $rs->invoke( null, array( 32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236, 17, 236, 17 ), $div->invoke( null, 10 ) );
		$this->assertSame( array( 196, 35, 39, 119, 235, 215, 231, 226, 93, 23 ), $ecc );

		$v7  = QrCode::encode( str_repeat( 'a', 115 ) );
		$this->assertSame( 7, $v7->version );
		$bits = '';
		for ( $i = 17; $i >= 0; $i-- ) {
			$bits .= $v7->module( $v7->size() - 11 + $i % 3, intdiv( $i, 3 ) ) ? '1' : '0';
		}
		$this->assertSame( '000111110010010100', $bits, 'version 7 information' );
	}

	public function test_round_trip_decodes_the_encoded_bytes(): void {
		foreach ( array( 'https://example.test/bewertung/?rc_campaign=laden', str_repeat( 'ü', 60 ) ) as $text ) {
			$qr = QrCode::encode( $text );
			$this->assertSame( $text, self::decode( $qr ), 'version ' . $qr->version );
		}
		$this->expectException( \LengthException::class );
		QrCode::encode( str_repeat( 'x', 300 ) );
	}

	/**
	 * Minimal reader: unmask, read zig-zag, de-interleave, parse byte segment.
	 */
	private static function decode( QrCode $qr ): string {
		$func = ( new \ReflectionProperty( QrCode::class, 'function' ) )->getValue( $qr );
		$size = $qr->size();
		$mask = $qr->mask;
		$bits = array();
		for ( $right = $size - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $vert = 0; $vert < $size; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x  = $right - $j;
					$y  = 0 === ( ( $right + 1 ) & 2 ) ? $size - 1 - $vert : $vert;
					if ( $func[ $y ][ $x ] ) {
						continue;
					}
					$m = match ( $mask ) {
						0 => 0 === ( $x + $y ) % 2, 1 => 0 === $y % 2, 2 => 0 === $x % 3, 3 => 0 === ( $x + $y ) % 3,
						4 => 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2, 5 => 0 === $x * $y % 2 + $x * $y % 3,
						6 => 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2, default => 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2,
					};
					$bits[] = (int) ( $qr->module( $x, $y ) xor $m );
				}
			}
		}
		$table                         = ( new \ReflectionClassConstant( QrCode::class, 'TABLE' ) )->getValue();
		[ $total, $blocks, $ecc_len ]  = $table[ $qr->version ];
		$codewords                     = array();
		for ( $i = 0; $i + 8 <= $total * 8; $i += 8 ) {
			$codewords[] = bindec( implode( '', array_slice( $bits, $i, 8 ) ) );
		}
		$short_blocks = $blocks - $total % $blocks;
		$short_len    = intdiv( $total, $blocks );
		$data_lens    = array();
		for ( $b = 0; $b < $blocks; $b++ ) {
			$data_lens[] = $short_len - $ecc_len + ( $b < $short_blocks ? 0 : 1 );
		}
		$data = array_fill( 0, $blocks, array() );
		$k    = 0;
		for ( $i = 0; $i < max( $data_lens ); $i++ ) {
			for ( $b = 0; $b < $blocks; $b++ ) {
				if ( $i < $data_lens[ $b ] ) {
					$data[ $b ][] = $codewords[ $k++ ];
				}
			}
		}
		$stream = '';
		foreach ( array_merge( ...$data ) as $byte ) {
			$stream .= str_pad( decbin( $byte ), 8, '0', STR_PAD_LEFT );
		}
		$count_bits = $qr->version < 10 ? 8 : 16;
		$len        = bindec( substr( $stream, 4, $count_bits ) );
		$out        = '';
		for ( $i = 0; $i < $len; $i++ ) {
			$out .= chr( bindec( substr( $stream, 4 + $count_bits + 8 * $i, 8 ) ) );
		}
		return $out;
	}

	public function test_campaign_link_qr_and_whatsapp(): void {
		$store = new CampaignStore();
		[ $c, $errors ] = $store->save(
			array(
				'label'           => 'Laden Wien',
				'target'          => 'page',
				'target_url'      => 'https://example.test/bewertung/',
				'whatsapp_number' => '+43 660 1234567',
				'whatsapp_text'   => 'Feedback zu unserem Laden:',
			)
		);
		$this->assertSame( array(), $errors );
		$this->assertSame( 'laden-wien', $c['id'] );
		$this->assertSame( 'https://example.test/bewertung/?rc_campaign=laden-wien', $store->link( $c ) );
		$this->assertSame( 'https://wa.me/436601234567?text=' . rawurlencode( 'Feedback zu unserem Laden: https://example.test/bewertung/?rc_campaign=laden-wien' ), $store->whatsapp_link( $c ) );

		$svg = $store->qr_svg( $c );
		$this->assertStringStartsWith( '<svg xmlns="http://www.w3.org/2000/svg"', $svg );
		$this->assertStringContainsString( 'Give feedback', $svg );
		$this->assertStringContainsString( 'No ticket – no admission code', $svg );
		$this->assertStringNotContainsStringIgnoringCase( 'ticket-id', $svg );
		$this->assertStringNotContainsString( '<script', $svg );
		$xml = simplexml_load_string( $svg );
		$this->assertNotFalse( $xml, 'well-formed SVG' );
	}

	public function test_campaign_validation(): void {
		$store = new CampaignStore();
		$this->assertSame( array( 'target_url' ), $store->save( array( 'label' => 'A', 'target_url' => 'https://evil.example/x' ) )[1] );
		$this->assertSame( array( 'target_url' ), $store->save( array( 'label' => 'A', 'target_url' => 'http://example.test/x' ) )[1] );
		$this->assertSame( array( 'whatsapp_number' ), $store->save( array( 'label' => 'A', 'target_url' => 'https://example.test/', 'whatsapp_number' => '0660123' ) )[1] );
		$this->assertSame( array( 'label', 'profile' ), $store->save( array( 'target' => 'profile', 'profile_id' => 99 ) )[1] );

		$this->db->insert( Schema::table( 'profiles' ), array( 'platform' => 'google', 'label' => 'Google', 'review_url' => 'https://g.page/r/abc/review', 'status' => 'manual', 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) );
		[ $c ] = $store->save( array( 'label' => 'Google', 'target' => 'profile', 'profile_id' => $this->db->insert_id ) );
		$this->assertSame( 'https://g.page/r/abc/review', $store->link( $c ), 'no tracking parameters on platform links' );
		$this->assertSame( '', $store->whatsapp_link( $c ) );
		$this->assertTrue( $store->delete( 'google' ) );
		$this->assertNull( $store->get( 'google' ) );
	}
}
