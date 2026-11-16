<?php
/**
 * Cache generation counter (TEC-012, docs/architecture/caching.md §2).
 *
 * Every write to reviews bumps the generation; cache keys contain it, so old entries are never read
 * again and no key iteration is needed. Persisted as a non-autoloaded option when no persistent
 * object cache is available.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Support;

/**
 * Generation counter plus a small read-through cache helper.
 */
final class CacheGeneration {

	public const GROUP  = 'review_captain';
	public const OPTION = 'review_captain_cache_gen';

	/** Request-local copy. */
	private static ?int $gen = null;

	/** Current generation. */
	public static function get(): int {
		if ( null !== self::$gen ) {
			return self::$gen;
		}
		$gen = wp_cache_get( 'gen', self::GROUP );
		if ( false === $gen ) {
			$gen = (int) get_option( self::OPTION, 1 );
			wp_cache_set( 'gen', $gen, self::GROUP );
		}
		return self::$gen = max( 1, (int) $gen );
	}

	/** Invalidate all generation-keyed caches. */
	public static function bump(): void {
		$gen = self::get() + 1;
		update_option( self::OPTION, $gen, false );
		wp_cache_set( 'gen', $gen, self::GROUP );
		self::$gen = $gen;
	}

	/**
	 * Read-through cache: object cache when persistent, else transient.
	 *
	 * @param string   $prefix  Short key prefix (e.g. "s" for summaries).
	 * @param mixed    $args    Key material (serialised and hashed).
	 * @param int      $ttl     Seconds.
	 * @param callable $compute Producer.
	 * @return mixed
	 */
	public static function remember( string $prefix, $args, int $ttl, callable $compute ) {
		$key = 'rc:' . $prefix . ':' . self::get() . ':' . sha1( (string) wp_json_encode( $args ) );
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			$hit = wp_cache_get( $key, self::GROUP );
			if ( false !== $hit ) {
				return $hit;
			}
			$value = $compute();
			wp_cache_set( $key, $value, self::GROUP, $ttl );
			return $value;
		}
		$tkey = 'rc_' . substr( sha1( $key ), 0, 32 );
		$hit  = get_transient( $tkey );
		if ( is_array( $hit ) && ( $hit['k'] ?? '' ) === $key ) {
			return $hit['v'];
		}
		$value = $compute();
		set_transient( $tkey, array( 'k' => $key, 'v' => $value ), $ttl );
		return $value;
	}

	/** Test helper: forget the request-local copy. */
	public static function reset_local(): void {
		self::$gen = null;
	}
}
