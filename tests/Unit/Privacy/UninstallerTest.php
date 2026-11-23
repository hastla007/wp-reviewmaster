<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Privacy;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Privacy\Uninstaller;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Tests\TestCase;

final class UninstallerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		update_option( 'review_captain_db_version', 1 );
		update_option( 'review_captain_connectors_known', array( 'x' => array() ) );
		Capabilities::install();
		wp_schedule_event( time(), 'hourly', 'review_captain_run_jobs' );
		wp_schedule_event( time(), 'daily', 'review_captain_daily_maintenance' );
		update_option( 'unrelated_option', 'keep' );
	}

	private function table_exists( string $key ): bool {
		return null !== $this->db->get_var( "SELECT name FROM sqlite_master WHERE type='table' AND name='" . Schema::table( $key ) . "'" );
	}

	public function test_data_is_kept_by_default(): void {
		Uninstaller::run();
		foreach ( Schema::TABLES as $key ) {
			$this->assertTrue( $this->table_exists( $key ), $key );
		}
		$this->assertSame( 1, get_option( 'review_captain_db_version' ) );
		$this->assertTrue( \RC_Test_State::$roles['administrator'][ Capabilities::MANAGE_PRIVACY ] ?? false );
		$this->assertArrayNotHasKey( 'review_captain_run_jobs', \RC_Test_State::$scheduled, 'cron hooks are always removed' );
	}

	public function test_explicit_option_deletes_everything(): void {
		update_option( 'review_captain_settings', array( 'privacy' => array( 'delete_data_on_uninstall' => true, 'delete_data_confirmed_at' => '2026-11-23 10:00:00' ) ) );
		Uninstaller::run();
		foreach ( Schema::TABLES as $key ) {
			$this->assertFalse( $this->table_exists( $key ), $key );
		}
		foreach ( Uninstaller::KNOWN_OPTIONS as $name ) {
			$this->assertFalse( get_option( $name ), $name );
		}
		$this->assertSame( 'keep', get_option( 'unrelated_option' ) );
		$this->assertArrayNotHasKey( Capabilities::MANAGE_PRIVACY, \RC_Test_State::$roles['administrator'] );
		$this->assertArrayNotHasKey( Capabilities::MODERATE, \RC_Test_State::$roles['editor'] );
		$this->assertSame( array(), \RC_Test_State::$scheduled );
	}

	public function test_false_like_values_keep_data(): void {
		foreach ( array( false, 0, '0', '', 'false' ) as $value ) {
			update_option( 'review_captain_settings', array( 'privacy' => array( 'delete_data_on_uninstall' => $value ) ) );
			$this->assertFalse( Uninstaller::should_delete(), var_export( $value, true ) );
		}
		update_option( 'review_captain_settings', 'garbage' );
		$this->assertFalse( Uninstaller::should_delete() );
	}

	public function test_unconfirmed_switch_keeps_data(): void {
		// SET-RC-20: switched on (e.g. before the confirmation existed) but never confirmed.
		update_option( 'review_captain_settings', array( 'privacy' => array( 'delete_data_on_uninstall' => true ) ) );
		$this->assertFalse( Uninstaller::should_delete() );
		update_option( 'review_captain_settings', array( 'privacy' => array( 'delete_data_on_uninstall' => true, 'delete_data_confirmed_at' => '  ' ) ) );
		$this->assertFalse( Uninstaller::should_delete() );
		Uninstaller::run();
		$this->assertTrue( $this->table_exists( 'reviews' ) );
		$this->assertSame( 1, get_option( 'review_captain_db_version' ) );
	}

	public function test_uninstall_php_runs_uninstaller(): void {
		update_option( 'review_captain_settings', array( 'privacy' => array( 'delete_data_on_uninstall' => true, 'delete_data_confirmed_at' => '2026-11-23 10:00:00' ) ) );
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'review-captain/review-captain.php' );
		}
		include REVIEW_CAPTAIN_DIR . 'uninstall.php';
		$this->assertFalse( $this->table_exists( 'reviews' ) );
		$this->assertFalse( get_option( 'review_captain_settings' ) );
	}
}
