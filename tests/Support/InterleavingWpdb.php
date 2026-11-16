<?php
/**
 * wpdb test double that injects a concurrent action right before a matching statement
 * ("Interleaver", docs/contracts/race-conditions.md – Testtechnik).
 *
 * A single PHP process cannot run two workers at once. Instead, a hook registered with before()
 * fires immediately before the first statement whose SQL matches a pattern and executes the
 * "other worker" there – exactly the interleaving a second connection could produce between the
 * read and the write of the code under test.
 *
 * @package ReviewCaptain\Tests
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Support;

/**
 * SQLite wpdb with interleaving hooks.
 */
class InterleavingWpdb extends SqliteWpdb {

	/**
	 * Pending hooks.
	 *
	 * @var list<array{pattern: string, callback: callable, times: int}>
	 */
	private array $hooks = array();

	/**
	 * Number of fired hooks.
	 */
	public int $fired = 0;

	/**
	 * Run $callback right before the next statement matching $pattern (regex on the SQL).
	 *
	 * @param string   $pattern  Regex.
	 * @param callable $callback Concurrent action.
	 * @param int      $times    How many matching statements trigger it.
	 */
	public function before( string $pattern, callable $callback, int $times = 1 ): void {
		$this->hooks[] = array(
			'pattern'  => $pattern,
			'callback' => $callback,
			'times'    => $times,
		);
	}

	/**
	 * Fire matching hooks (each hook is disarmed before its callback runs, so the callback's
	 * own statements do not retrigger it).
	 *
	 * @param string $sql SQL.
	 */
	private function interleave( string $sql ): void {
		foreach ( $this->hooks as $i => $hook ) {
			if ( ! preg_match( $hook['pattern'], $sql ) ) {
				continue;
			}
			--$this->hooks[ $i ]['times'];
			if ( $this->hooks[ $i ]['times'] <= 0 ) {
				unset( $this->hooks[ $i ] );
				$this->hooks = array_values( $this->hooks );
			}
			++$this->fired;
			( $hook['callback'] )();
			return;
		}
	}

	/**
	 * @return int|bool
	 */
	public function query( string $sql ) {
		$this->interleave( $sql );
		return parent::query( $sql );
	}

	/**
	 * @return array<int, mixed>|null
	 */
	public function get_results( string $sql, string $output = 'OBJECT' ) {
		$this->interleave( $sql );
		return parent::get_results( $sql, $output );
	}
}
