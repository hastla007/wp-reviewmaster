<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Jobs;

use ReviewCaptain\Jobs\Budget;
use ReviewCaptain\Tests\TestCase;

final class BudgetTest extends TestCase {

	public function test_consume_until_limit(): void {
		$this->assertTrue( Budget::consume( 'profile:12', 6, 10 ) );
		$this->assertSame( 4, Budget::remaining( 'profile:12', 10 ) );
		$this->assertFalse( Budget::consume( 'profile:12', 5, 10 ) );
		$this->assertSame( 6, Budget::used( 'profile:12' ) );
		$this->assertTrue( Budget::consume( 'profile:12', 4, 10 ) );
		$this->assertSame( 0, Budget::remaining( 'profile:12', 10 ) );
		$this->assertSame( 0, Budget::used( 'profile:13' ) );
	}

	public function test_warning_threshold_fires_once(): void {
		$fired = 0;
		add_action( 'review_captain_budget_warning', function () use ( &$fired ) { ++$fired; }, 10, 3 );
		Budget::consume( 'profile:1', 7, 10 );
		$this->assertFalse( Budget::is_warning( 'profile:1', 10 ) );
		Budget::consume( 'profile:1', 1, 10 );
		$this->assertTrue( Budget::is_warning( 'profile:1', 10 ) );
		Budget::consume( 'profile:1', 1, 10 );
		$this->assertSame( 1, $fired );
	}

	public function test_new_day_resets(): void {
		Budget::consume( 'profile:2', 10, 10 );
		$this->assertFalse( Budget::consume( 'profile:2', 1, 10 ) );
		foreach ( \RC_Test_State::$options as $k => $v ) {
			if ( str_starts_with( $k, Budget::OPTION_PREFIX ) ) {
				\RC_Test_State::$options[ $k ]['day'] = '20000101';
			}
		}
		$this->assertTrue( Budget::consume( 'profile:2', 1, 10 ) );
		$this->assertGreaterThan( 0, Budget::seconds_until_reset() );
	}
}
