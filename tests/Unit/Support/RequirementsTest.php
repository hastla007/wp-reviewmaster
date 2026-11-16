<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ReviewCaptain\Support\Requirements;

final class RequirementsTest extends TestCase {

	public function test_minimums_match_owner_decision(): void {
		$this->assertSame( '8.5', Requirements::MIN_PHP );
		$this->assertSame( '9.7', Requirements::MIN_MYSQL );
		$this->assertSame( '12.3', Requirements::MIN_MARIADB );
	}

	public function test_main_file_header_and_constant_follow_class(): void {
		$main = (string) file_get_contents( dirname( __DIR__, 3 ) . '/review-captain.php' );
		$this->assertMatchesRegularExpression( '/Requires PHP:\s+' . preg_quote( Requirements::MIN_PHP, '/' ) . '\s*$/m', $main );
		$this->assertStringContainsString( "define( 'REVIEW_CAPTAIN_MIN_PHP', '" . Requirements::MIN_PHP . "' );", $main );
		$readme = (string) file_get_contents( dirname( __DIR__, 3 ) . '/readme.txt' );
		$this->assertMatchesRegularExpression( '/^Requires PHP: ' . preg_quote( Requirements::MIN_PHP, '/' ) . '$/m', $readme );
	}

	/** @return array<string, array{string, string, string, bool|null}> */
	public static function servers(): array {
		return array(
			'mariadb ubuntu'      => array( '10.11.14-MariaDB-0ubuntu0.24.04.1', 'mariadb', '10.11.14', false ),
			'mariadb repl prefix' => array( '5.5.5-10.11.14-MariaDB', 'mariadb', '10.11.14', false ),
			'mariadb lts ok'      => array( '12.3.1-MariaDB', 'mariadb', '12.3.1', true ),
			'mariadb newer'       => array( '13.0.0-MariaDB-log', 'mariadb', '13.0.0', true ),
			'mysql old'           => array( '8.0.36', 'mysql', '8.0.36', false ),
			'mysql lts ok'        => array( '9.7.0', 'mysql', '9.7.0', true ),
			'mysql suffix'        => array( '9.7.2-0ubuntu1', 'mysql', '9.7.2', true ),
			'unknown'             => array( '', 'unknown', '', null ),
		);
	}

	/** @dataProvider servers */
	public function test_check_db( string $info, string $vendor, string $version, ?bool $ok ): void {
		$r = Requirements::check_db( $info );
		$this->assertSame( $vendor, $r['vendor'] );
		$this->assertSame( $version, $r['version'] );
		$this->assertSame( $ok, $r['ok'] );
		$this->assertSame( 'mariadb' === $vendor ? '12.3' : '9.7', $r['minimum'] );
	}

	public function test_php_ok(): void {
		$this->assertFalse( Requirements::php_ok( '8.4.12' ) );
		$this->assertTrue( Requirements::php_ok( '8.5.0' ) );
		$this->assertTrue( Requirements::php_ok( '8.6.0RC1' ) );
	}
}
