<?php
/**
 * Ordered, idempotent data migrations between schema versions.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Install;

/**
 * Runs migration steps. dbDelta handles structure; steps here handle data.
 */
final class Migrator {

	/**
	 * Run steps for (from, to].
	 *
	 * @param int $from Installed version.
	 * @param int $to   Target version.
	 */
	public static function run( int $from, int $to ): void {
		foreach ( self::steps() as $version => $step ) {
			if ( $version > $from && $version <= $to ) {
				$step();
			}
		}
	}

	/**
	 * Steps keyed by version. Each step must be safe to re-run.
	 *
	 * @return array<int, callable():void>
	 */
	public static function steps(): array {
		return array(
			1 => static function (): void {
				// Initial schema: nothing to migrate.
			},
		);
	}
}
