<?php
/**
 * Daily API budgets per scope (TEC-007).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Jobs;

/**
 * Per-scope (e.g. `profile:12`) request counter per UTC day, stored in a non-autoloaded option.
 * Consumption is refused (and nothing is counted) when the limit would be exceeded.
 */
final class Budget {

	public const OPTION_PREFIX = 'review_captain_budget_';

	/**
	 * Warning threshold (fraction of daily limit).
	 */
	public const WARN_THRESHOLD = 0.8;

	/**
	 * Option name for a scope.
	 *
	 * @param string $scope Scope.
	 */
	private static function option( string $scope ): string {
		$slug = sanitize_key( str_replace( array( ':', '/', '.' ), '_', $scope ) );
		if ( '' === $slug || strlen( $slug ) > 100 ) {
			$slug = substr( $slug, 0, 60 ) . '_' . substr( md5( $scope ), 0, 12 );
		}
		return self::OPTION_PREFIX . $slug;
	}

	/**
	 * Current UTC day key.
	 */
	private static function today(): string {
		return gmdate( 'Ymd' );
	}

	/**
	 * Units used today.
	 *
	 * @param string $scope Scope.
	 */
	public static function used( string $scope ): int {
		$state = get_option( self::option( $scope ), array() );
		if ( ! is_array( $state ) || ( $state['day'] ?? '' ) !== self::today() ) {
			return 0;
		}
		return (int) ( $state['used'] ?? 0 );
	}

	/**
	 * Consume units if the daily limit allows it.
	 *
	 * @param string $scope          Scope, e.g. `profile:12`.
	 * @param int    $units          Units (≥ 1).
	 * @param int    $limit_per_day  Limit (≤ 0 = unlimited).
	 * @return bool False when the budget is exhausted (caller defers to the next UTC day, rc.quota.budget_exhausted).
	 */
	public static function consume( string $scope, int $units, int $limit_per_day ): bool {
		$units = max( 1, $units );
		$used  = self::used( $scope );
		if ( $limit_per_day > 0 && $used + $units > $limit_per_day ) {
			return false;
		}
		$new = $used + $units;
		update_option(
			self::option( $scope ),
			array(
				'day'  => self::today(),
				'used' => $new,
			),
			false
		);
		if ( $limit_per_day > 0 && $used < self::WARN_THRESHOLD * $limit_per_day && $new >= self::WARN_THRESHOLD * $limit_per_day ) {
			/**
			 * Fires once per day and scope when budget usage crosses the warning threshold (80 %).
			 *
			 * @param string $scope Scope.
			 * @param int    $used  Used units.
			 * @param int    $limit Limit.
			 */
			do_action( 'review_captain_budget_warning', $scope, $new, $limit_per_day );
		}
		return true;
	}

	/**
	 * Remaining units today.
	 *
	 * @param string $scope         Scope.
	 * @param int    $limit_per_day Limit.
	 */
	public static function remaining( string $scope, int $limit_per_day ): int {
		return max( 0, $limit_per_day - self::used( $scope ) );
	}

	/**
	 * Whether usage reached the warning threshold.
	 *
	 * @param string $scope         Scope.
	 * @param int    $limit_per_day Limit.
	 */
	public static function is_warning( string $scope, int $limit_per_day ): bool {
		return $limit_per_day > 0 && self::used( $scope ) >= self::WARN_THRESHOLD * $limit_per_day;
	}

	/**
	 * Seconds until the next UTC day (for deferring jobs).
	 */
	public static function seconds_until_reset(): int {
		return max( 60, (int) strtotime( 'tomorrow 00:00:00 UTC' ) - time() );
	}

	/**
	 * Reset a scope (tests/admin).
	 *
	 * @param string $scope Scope.
	 */
	public static function reset( string $scope ): void {
		delete_option( self::option( $scope ) );
	}
}
