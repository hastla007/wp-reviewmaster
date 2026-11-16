<?php
/**
 * Base test case.
 *
 * @package ReviewCaptain\Tests
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\Support\SqliteWpdb;

/**
 * Resets mini-WP state and gives every test a fresh SQLite database with the real schema.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	protected SqliteWpdb $db;

	protected function setUp(): void {
		parent::setUp();
		\RC_Test_State::reset();
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$GLOBALS['wpdb'] = new SqliteWpdb();
		$this->db        = $GLOBALS['wpdb'];
		foreach ( Schema::definitions() as $sql ) {
			$this->db->create_from_mysql( $sql );
		}
		Plugin::reset_for_tests();
	}

	/**
	 * Grant capabilities to the current test user.
	 *
	 * @param string ...$caps Caps.
	 */
	protected function login_with( string ...$caps ): void {
		\RC_Test_State::$user_id = 1;
		\RC_Test_State::$caps    = $caps;
	}

	/**
	 * Count rows in a plugin table.
	 *
	 * @param string $key Logical table.
	 */
	protected function rows( string $key ): int {
		return (int) $this->db->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( $key ) );
	}
}
