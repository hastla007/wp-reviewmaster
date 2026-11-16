<?php
/**
 * Security review 2026-11, finding SR-01: rate limit must not be bypassable by rotating
 * IPv6 addresses inside the same /64.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Security;

use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\Unit\Frontend\FrontendTestCase;

final class RateLimitIpv6Test extends FrontendTestCase {

	protected function tearDown(): void {
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		parent::tearDown();
	}

	/**
	 * Valid submission payload.
	 *
	 * @param int $n Distinguishes bodies (no duplicate side effects).
	 * @return array<string, mixed>
	 */
	private function post( int $n ): array {
		return array(
			'_rc_nonce'       => wp_create_nonce( FormHandler::NONCE_ACTION ),
			'rc_form'         => '1',
			'rc_ts'           => FormHandler::sign_timestamp( time() - 10 ),
			'rc_context_type' => '',
			'rc_context_id'   => '',
			'rc_context_sig'  => FormHandler::sign_context( '', '' ),
			'rc_return'       => 'https://example.test/kontakt/',
			'rc_rating'       => '5',
			'rc_body'         => 'Sehr gute Erfahrung mit dem Team, Nummer ' . $n . '.',
			'rc_name'         => 'Dana',
			'rc_email'        => 'dana@example.test',
			'rc_consent'      => '1',
			'rc_website'      => '',
		);
	}

	public function test_ipv6_addresses_are_grouped_by_64_prefix(): void {
		$this->assertSame( '2001:db8:1:2::/64', FormHandler::rate_subject( '2001:db8:1:2::1' ) );
		$this->assertSame( '2001:db8:1:2::/64', FormHandler::rate_subject( '2001:0db8:0001:0002:ffff:eeee:dddd:cccc' ) );
		$this->assertNotSame( FormHandler::rate_subject( '2001:db8:1:2::1' ), FormHandler::rate_subject( '2001:db8:1:3::1' ) );
		$this->assertSame( '203.0.113.7', FormHandler::rate_subject( '203.0.113.7' ) );
		$this->assertSame( '203.0.113.7', FormHandler::rate_subject( '::ffff:203.0.113.7' ), 'IPv4-mapped counts as IPv4' );
		$this->assertSame( 'garbage', FormHandler::rate_subject( 'garbage' ) );

		$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2::1';
		$a                      = FormHandler::rate_key();
		$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:abcd::99';
		$this->assertSame( $a, FormHandler::rate_key() );
		$this->assertStringNotContainsString( '2001', $a, 'plain address never stored' );
	}

	public function test_rotating_addresses_in_one_prefix_hit_the_limit(): void {
		$this->settings( array( 'submissions' => array( 'rate_limit_per_hour' => 1 ) ) );
		/** @var FormHandler $handler */
		$handler = Plugin::instance()->get( 'form_handler' );

		$_SERVER['REMOTE_ADDR'] = '2001:db8:aa:bb::1';
		$this->assertTrue( $handler->process( $this->post( 1 ) )['ok'] );

		$_SERVER['REMOTE_ADDR'] = '2001:db8:aa:bb::2';
		$second                 = $handler->process( $this->post( 2 ) );
		$this->assertFalse( $second['ok'] );
		$this->assertSame( array( 'rate_limited' ), $second['errors'] );

		$_SERVER['REMOTE_ADDR'] = '2001:db8:aa:cc::1';
		$this->assertTrue( $handler->process( $this->post( 3 ) )['ok'], 'other /64 has its own budget' );
	}
}
