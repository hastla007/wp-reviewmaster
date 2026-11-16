<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

/**
 * Helpers for admin handler tests.
 */
abstract class AdminTestCase extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\RC_Test_State::$is_admin = true;
	}

	protected function plugin(): Plugin {
		return Plugin::instance();
	}

	/**
	 * Simulate a POST with a valid nonce for the given short action.
	 *
	 * @param string               $action Short action.
	 * @param array<string, mixed> $post   POST data.
	 * @param bool                 $nonce  Include a valid nonce.
	 */
	protected function post( string $action, array $post, bool $nonce = true ): void {
		if ( $nonce ) {
			$post['_rc_nonce'] = wp_create_nonce( 'review_captain_' . $action );
		}
		$_POST    = $post;
		$_REQUEST = $post;
	}

	/**
	 * Run a handler and return the RC_Test_Die message (redirect:… or error).
	 *
	 * @param callable $handler Handler.
	 */
	protected function run_handler( callable $handler ): string {
		try {
			$handler();
		} catch ( \RC_Test_Die $e ) {
			return $e->getMessage();
		}
		$this->fail( 'Handler did not stop with redirect/wp_die.' );
	}

	/**
	 * Audit rows for an event.
	 *
	 * @param string $event Event.
	 * @return list<array<string, mixed>>
	 */
	protected function audit_rows( string $event ): array {
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT * FROM ' . Schema::table( 'audit_log' ) . ' WHERE event = %s ORDER BY id ASC', $event ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
