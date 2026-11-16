<?php
/**
 * Minimal QR code encoder (byte mode, error correction level M, versions 1–10) – RC-126.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Feedback;

/**
 * Own implementation following ISO/IEC 18004 (no external library is loaded). Enough for
 * feedback URLs up to 213 bytes (version 10-M). Larger input throws; callers then show the link
 * only.
 *
 * Steps: byte-mode segment → terminator/padding → Reed–Solomon ECC per block (GF(256), 0x11D)
 * → interleaving → function patterns (finder, timing, alignment, format, version) → zig-zag
 * placement → best of the 8 masks by the standard penalty rules.
 */
final class QrCode {

	/**
	 * Per version (1–10) for level M: [total codewords, blocks, ECC codewords per block].
	 */
	private const TABLE = array(
		1  => array( 26, 1, 10 ),
		2  => array( 44, 1, 16 ),
		3  => array( 70, 1, 26 ),
		4  => array( 100, 2, 18 ),
		5  => array( 134, 2, 24 ),
		6  => array( 172, 4, 16 ),
		7  => array( 196, 4, 18 ),
		8  => array( 242, 4, 22 ),
		9  => array( 292, 5, 22 ),
		10 => array( 346, 5, 26 ),
	);

	/**
	 * Alignment pattern centres per version.
	 */
	private const ALIGN = array(
		1  => array(),
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 50 ),
	);

	public const MAX_VERSION = 10;

	/**
	 * Module matrix [row][col] (true = dark).
	 *
	 * @var list<list<bool>>
	 */
	private array $modules = array();

	/**
	 * Function module flags [row][col].
	 *
	 * @var list<list<bool>>
	 */
	private array $function = array();

	private int $size = 21;

	public int $version = 1;

	public int $mask = 0;

	/**
	 * Encode a string.
	 *
	 * @param string   $text       UTF-8 text (byte mode).
	 * @param int|null $force_mask Mask 0–7 (tests); null = choose by penalty.
	 * @throws \LengthException When the text does not fit into version 10-M.
	 */
	public static function encode( string $text, ?int $force_mask = null ): self {
		$qr    = new self();
		$bytes = array_values( unpack( 'C*', $text ) ?: array() );
		$len   = count( $bytes );
		$ver   = 0;
		for ( $v = 1; $v <= self::MAX_VERSION; $v++ ) {
			$capacity = self::data_codewords( $v ) * 8;
			$needed   = 4 + ( $v < 10 ? 8 : 16 ) + 8 * $len;
			if ( $needed <= $capacity ) {
				$ver = $v;
				break;
			}
		}
		if ( 0 === $ver ) {
			throw new \LengthException( 'qr_too_long' );
		}
		$qr->version = $ver;
		$qr->size    = 4 * $ver + 17;

		// Bit stream.
		$bits = array();
		self::append_bits( $bits, 0x4, 4 );
		self::append_bits( $bits, $len, $ver < 10 ? 8 : 16 );
		foreach ( $bytes as $b ) {
			self::append_bits( $bits, $b, 8 );
		}
		$capacity = self::data_codewords( $ver ) * 8;
		self::append_bits( $bits, 0, min( 4, $capacity - count( $bits ) ) );
		self::append_bits( $bits, 0, ( 8 - count( $bits ) % 8 ) % 8 );
		for ( $pad = 0xEC; count( $bits ) < $capacity; $pad ^= 0xEC ^ 0x11 ) {
			self::append_bits( $bits, $pad, 8 );
		}
		$data = array();
		foreach ( array_chunk( $bits, 8 ) as $chunk ) {
			$byte = 0;
			foreach ( $chunk as $bit ) {
				$byte = ( $byte << 1 ) | $bit;
			}
			$data[] = $byte;
		}

		$codewords = self::add_ecc_and_interleave( $data, $ver );

		$qr->modules  = array_fill( 0, $qr->size, array_fill( 0, $qr->size, false ) );
		$qr->function = array_fill( 0, $qr->size, array_fill( 0, $qr->size, false ) );
		$qr->draw_function_patterns();
		$qr->draw_codewords( $codewords );

		if ( null !== $force_mask ) {
			$best = max( 0, min( 7, $force_mask ) );
		} else {
			$best    = 0;
			$penalty = PHP_INT_MAX;
			for ( $m = 0; $m < 8; $m++ ) {
				$qr->apply_mask( $m );
				$qr->draw_format_bits( $m );
				$p = $qr->penalty();
				if ( $p < $penalty ) {
					$penalty = $p;
					$best    = $m;
				}
				$qr->apply_mask( $m ); // XOR again = undo.
			}
		}
		$qr->mask = $best;
		$qr->apply_mask( $best );
		$qr->draw_format_bits( $best );
		return $qr;
	}

	/**
	 * Matrix size in modules.
	 */
	public function size(): int {
		return $this->size;
	}

	/**
	 * Module at column x, row y.
	 *
	 * @param int $x Column.
	 * @param int $y Row.
	 */
	public function module( int $x, int $y ): bool {
		return $x >= 0 && $y >= 0 && $x < $this->size && $y < $this->size && $this->modules[ $y ][ $x ];
	}

	/**
	 * Rows as strings of 0/1 (tests).
	 *
	 * @return list<string>
	 */
	public function rows(): array {
		return array_map( static fn( array $row ): string => implode( '', array_map( 'intval', $row ) ), $this->modules );
	}

	/**
	 * SVG path data of the dark modules (one sub-path per horizontal run), offset by the quiet
	 * zone.
	 *
	 * @param int $quiet Quiet zone in modules.
	 */
	public function path( int $quiet = 4 ): string {
		$d = '';
		for ( $y = 0; $y < $this->size; $y++ ) {
			$x = 0;
			while ( $x < $this->size ) {
				if ( ! $this->modules[ $y ][ $x ] ) {
					++$x;
					continue;
				}
				$start = $x;
				while ( $x < $this->size && $this->modules[ $y ][ $x ] ) {
					++$x;
				}
				$d .= 'M' . ( $start + $quiet ) . ' ' . ( $y + $quiet ) . 'h' . ( $x - $start ) . 'v1h-' . ( $x - $start ) . 'z';
			}
		}
		return $d;
	}

	/**
	 * Data codewords of a version (level M).
	 *
	 * @param int $ver Version.
	 */
	private static function data_codewords( int $ver ): int {
		[ $total, $blocks, $ecc ] = self::TABLE[ $ver ];
		return $total - $blocks * $ecc;
	}

	/**
	 * Append bits (MSB first).
	 *
	 * @param list<int> $bits  Bits.
	 * @param int       $value Value.
	 * @param int       $count Bit count.
	 */
	private static function append_bits( array &$bits, int $value, int $count ): void {
		for ( $i = $count - 1; $i >= 0; $i-- ) {
			$bits[] = ( $value >> $i ) & 1;
		}
	}

	/**
	 * Split into blocks, add Reed–Solomon ECC and interleave.
	 *
	 * @param list<int> $data Data codewords.
	 * @param int       $ver  Version.
	 * @return list<int>
	 */
	private static function add_ecc_and_interleave( array $data, int $ver ): array {
		[ $total, $num_blocks, $ecc_len ] = self::TABLE[ $ver ];
		$short_blocks                     = $num_blocks - $total % $num_blocks;
		$short_len                        = intdiv( $total, $num_blocks );
		$divisor                          = self::rs_divisor( $ecc_len );
		$blocks                           = array();
		$k                                = 0;
		for ( $i = 0; $i < $num_blocks; $i++ ) {
			$dat = array_slice( $data, $k, $short_len - $ecc_len + ( $i < $short_blocks ? 0 : 1 ) );
			$k  += count( $dat );
			$ecc = self::rs_remainder( $dat, $divisor );
			if ( $i < $short_blocks ) {
				$dat[] = 0;
			}
			$blocks[] = array_merge( $dat, $ecc );
		}
		$out = array();
		$len = count( $blocks[0] );
		for ( $i = 0; $i < $len; $i++ ) {
			foreach ( $blocks as $j => $block ) {
				if ( $i !== $short_len - $ecc_len || $j >= $short_blocks ) {
					$out[] = $block[ $i ];
				}
			}
		}
		return $out;
	}

	/**
	 * Reed–Solomon generator polynomial (without leading term).
	 *
	 * @param int $degree Degree.
	 * @return list<int>
	 */
	private static function rs_divisor( int $degree ): array {
		$result                = array_fill( 0, $degree, 0 );
		$result[ $degree - 1 ] = 1;
		$root                  = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$result[ $j ] = self::gf_mul( $result[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$result[ $j ] ^= $result[ $j + 1 ];
				}
			}
			$root = self::gf_mul( $root, 0x02 );
		}
		return $result;
	}

	/**
	 * Polynomial remainder.
	 *
	 * @param list<int> $data    Data.
	 * @param list<int> $divisor Divisor.
	 * @return list<int>
	 */
	private static function rs_remainder( array $data, array $divisor ): array {
		$result = array_fill( 0, count( $divisor ), 0 );
		foreach ( $data as $b ) {
			$factor   = $b ^ array_shift( $result );
			$result[] = 0;
			foreach ( $divisor as $i => $coef ) {
				$result[ $i ] ^= self::gf_mul( $coef, $factor );
			}
		}
		return $result;
	}

	/**
	 * GF(2^8) multiplication modulo x^8 + x^4 + x^3 + x^2 + 1.
	 *
	 * @param int $x X.
	 * @param int $y Y.
	 */
	private static function gf_mul( int $x, int $y ): int {
		$z = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$z  = ( ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D ) ) & 0xFF;
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}
		return $z;
	}

	/**
	 * Set a function module.
	 *
	 * @param int  $x    Column.
	 * @param int  $y    Row.
	 * @param bool $dark Dark.
	 */
	private function set_function( int $x, int $y, bool $dark ): void {
		$this->modules[ $y ][ $x ]  = $dark;
		$this->function[ $y ][ $x ] = true;
	}

	/**
	 * Finder, timing, alignment, reserved format area, version information.
	 */
	private function draw_function_patterns(): void {
		for ( $i = 0; $i < $this->size; $i++ ) {
			$this->set_function( 6, $i, 0 === $i % 2 );
			$this->set_function( $i, 6, 0 === $i % 2 );
		}
		foreach ( array( array( 3, 3 ), array( $this->size - 4, 3 ), array( 3, $this->size - 4 ) ) as [ $cx, $cy ] ) {
			for ( $dy = -4; $dy <= 4; $dy++ ) {
				for ( $dx = -4; $dx <= 4; $dx++ ) {
					$x = $cx + $dx;
					$y = $cy + $dy;
					if ( $x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size ) {
						$dist = max( abs( $dx ), abs( $dy ) );
						$this->set_function( $x, $y, 2 !== $dist && 4 !== $dist );
					}
				}
			}
		}
		$pos = self::ALIGN[ $this->version ];
		$n   = count( $pos );
		for ( $i = 0; $i < $n; $i++ ) {
			for ( $j = 0; $j < $n; $j++ ) {
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $n - 1 === $j ) || ( $n - 1 === $i && 0 === $j ) ) {
					continue;
				}
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$this->set_function( $pos[ $i ] + $dx, $pos[ $j ] + $dy, 1 !== max( abs( $dx ), abs( $dy ) ) );
					}
				}
			}
		}
		$this->draw_format_bits( 0 ); // Reserve; overwritten after masking.
		if ( $this->version >= 7 ) {
			$rem = $this->version;
			for ( $i = 0; $i < 12; $i++ ) {
				$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
			}
			$bits = ( $this->version << 12 ) | $rem;
			for ( $i = 0; $i < 18; $i++ ) {
				$bit = 1 === ( ( $bits >> $i ) & 1 );
				$a   = $this->size - 11 + $i % 3;
				$b   = intdiv( $i, 3 );
				$this->set_function( $a, $b, $bit );
				$this->set_function( $b, $a, $bit );
			}
		}
	}

	/**
	 * Format information (level M = 00) for a mask.
	 *
	 * @param int $mask Mask.
	 */
	private function draw_format_bits( int $mask ): void {
		$data = ( 0 << 3 ) | $mask;
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		$get  = static fn( int $i ): bool => 1 === ( ( $bits >> $i ) & 1 );
		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set_function( 8, $i, $get( $i ) );
		}
		$this->set_function( 8, 7, $get( 6 ) );
		$this->set_function( 8, 8, $get( 7 ) );
		$this->set_function( 7, 8, $get( 8 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			$this->set_function( 14 - $i, 8, $get( $i ) );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$this->set_function( $this->size - 1 - $i, 8, $get( $i ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$this->set_function( 8, $this->size - 15 + $i, $get( $i ) );
		}
		$this->set_function( 8, $this->size - 8, true );
	}

	/**
	 * Zig-zag placement of the codewords.
	 *
	 * @param list<int> $codewords Codewords.
	 */
	private function draw_codewords( array $codewords ): void {
		$total = count( $codewords ) * 8;
		$i     = 0;
		for ( $right = $this->size - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $vert = 0; $vert < $this->size; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x      = $right - $j;
					$upward = 0 === ( ( $right + 1 ) & 2 );
					$y      = $upward ? $this->size - 1 - $vert : $vert;
					if ( ! $this->function[ $y ][ $x ] && $i < $total ) {
						$this->modules[ $y ][ $x ] = 1 === ( ( $codewords[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1 );
						++$i;
					}
				}
			}
		}
	}

	/**
	 * XOR a mask over all non-function modules.
	 *
	 * @param int $mask Mask 0–7.
	 */
	private function apply_mask( int $mask ): void {
		for ( $y = 0; $y < $this->size; $y++ ) {
			for ( $x = 0; $x < $this->size; $x++ ) {
				if ( $this->function[ $y ][ $x ] ) {
					continue;
				}
				$invert = match ( $mask ) {
					0 => 0 === ( $x + $y ) % 2,
					1 => 0 === $y % 2,
					2 => 0 === $x % 3,
					3 => 0 === ( $x + $y ) % 3,
					4 => 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2,
					5 => 0 === $x * $y % 2 + $x * $y % 3,
					6 => 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2,
					default => 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2,
				};
				if ( $invert ) {
					$this->modules[ $y ][ $x ] = ! $this->modules[ $y ][ $x ];
				}
			}
		}
	}

	/**
	 * Penalty score (rules N1–N4 of ISO/IEC 18004).
	 */
	private function penalty(): int {
		$n      = $this->size;
		$result = 0;
		$lines  = array();
		for ( $y = 0; $y < $n; $y++ ) {
			$lines[] = $this->modules[ $y ];
		}
		for ( $x = 0; $x < $n; $x++ ) {
			$lines[] = array_column( $this->modules, $x );
		}
		$pattern_a = '10111010000';
		$pattern_b = '00001011101';
		foreach ( $lines as $line ) {
			$run   = 1;
			$count = count( $line );
			for ( $i = 1; $i <= $count; $i++ ) {
				if ( $i < $count && $line[ $i ] === $line[ $i - 1 ] ) {
					++$run;
					continue;
				}
				if ( $run >= 5 ) {
					$result += 3 + ( $run - 5 );
				}
				$run = 1;
			}
			$str     = '0000' . implode( '', array_map( 'intval', $line ) ) . '0000';
			$result += 40 * ( substr_count( $str, $pattern_a ) + substr_count( $str, $pattern_b ) );
		}
		$dark = 0;
		for ( $y = 0; $y < $n; $y++ ) {
			for ( $x = 0; $x < $n; $x++ ) {
				if ( $this->modules[ $y ][ $x ] ) {
					++$dark;
				}
				if ( $x < $n - 1 && $y < $n - 1 ) {
					$c = $this->modules[ $y ][ $x ];
					if ( $c === $this->modules[ $y ][ $x + 1 ] && $c === $this->modules[ $y + 1 ][ $x ] && $c === $this->modules[ $y + 1 ][ $x + 1 ] ) {
						$result += 3;
					}
				}
			}
		}
		$total   = $n * $n;
		$k       = (int) ceil( abs( $dark * 20 - $total * 10 ) / $total ) - 1;
		$result += max( 0, $k ) * 10;
		return $result;
	}
}
