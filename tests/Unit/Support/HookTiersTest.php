<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

/**
 * ADR-0007 / SET-RC-P6: every hook the core fires (docs/hooks.md, kept current by
 * `bin/list-hooks.php --check`) has exactly one row with a stability tier in conventions.md.
 */
final class HookTiersTest extends TestCase {

	/**
	 * Hook names of docs/hooks.md.
	 *
	 * @return list<string>
	 */
	private static function fired(): array {
		$doc = (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/hooks.md' );
		preg_match_all( '/^\| `(review_captain_[a-z0-9_]+)` \|/m', $doc, $m );
		return array_values( array_unique( $m[1] ) );
	}

	/**
	 * Tier per hook from the hook table of conventions.md (name => list of tiers found).
	 *
	 * @return array<string, list<string>>
	 */
	private static function tiers(): array {
		$doc = (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/architecture/conventions.md' );
		preg_match_all( '/^\| `(review_captain_[a-z0-9_]+)` \| [^|]+ \| ([A-Z]+) \|/m', $doc, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $row ) {
			$out[ $row[1] ][] = $row[2];
		}
		return $out;
	}

	public function test_reference_lists_hooks(): void {
		$this->assertGreaterThan( 40, count( self::fired() ) );
	}

	public function test_every_fired_hook_has_exactly_one_tier(): void {
		$tiers   = self::tiers();
		$missing = array();
		foreach ( self::fired() as $hook ) {
			if ( ! isset( $tiers[ $hook ] ) ) {
				$missing[] = $hook;
				continue;
			}
			$this->assertCount( 1, $tiers[ $hook ], $hook . ' is listed more than once' );
			$this->assertContains( $tiers[ $hook ][0], array( 'V', 'S', 'B' ), $hook );
		}
		$this->assertSame( array(), $missing, 'hooks without stability tier in docs/architecture/conventions.md' );
	}

	public function test_versioned_names_are_tier_v(): void {
		foreach ( self::tiers() as $hook => $found ) {
			if ( str_ends_with( $hook, '_v1' ) ) {
				$this->assertSame( array( 'V' ), $found, $hook );
			}
		}
	}
}
