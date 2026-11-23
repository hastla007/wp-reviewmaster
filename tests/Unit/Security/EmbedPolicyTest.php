<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Security;

use ReviewCaptain\Security\EmbedPolicy;
use ReviewCaptain\Tests\TestCase;

final class EmbedPolicyTest extends TestCase {

	public function test_allowed_urls(): void {
		foreach (
			array(
				'https://www.youtube-nocookie.com/embed/abcdefghijk',
				'https://youtube-nocookie.com/embed/abcdefghijk',
				'https://player.vimeo.com/video/123?dnt=1',
				'https://widget.trustpilot.com/trustboxes/x/index.html',
				'https://widgets.trustedshops.com/js/X.js',
				'https://integrations.etrusted.com/applications/widget.js/v2',
				'https://player.vimeo.com:443/video/1',
				'https://PLAYER.VIMEO.COM/video/1',
			) as $url
		) {
			$this->assertTrue( EmbedPolicy::is_allowed_url( $url ), $url );
		}
	}

	public function test_rejected_urls(): void {
		foreach (
			array(
				'http://player.vimeo.com/video/1',
				'https://player.vimeo.com:8443/video/1',
				'https://user:pass@player.vimeo.com/video/1',
				'https://user@player.vimeo.com/video/1',
				'https://127.0.0.1/embed',
				'https://169.254.169.254/latest',
				'https://[::1]/embed',
				'https://www.youtube.com/embed/x',
				'https://evil.example/?u=https://player.vimeo.com',
				'https://player.vimeo.com.evil.example/video/1',
				'https://etrusted.com/applications/widget.js/v2',
				'https://api.etrusted.com/x',
				'https://integrations.etrusted.com.evil.example/x',
				'http://integrations.etrusted.com/applications/widget.js/v2',
				'javascript:alert(1)',
				'//player.vimeo.com/video/1',
				"https://player.vimeo.com/video/1\n",
				'https://player.vimeo.com\\@evil.example/',
				'',
			) as $url
		) {
			$this->assertFalse( EmbedPolicy::is_allowed_url( $url ), $url );
		}
	}

	public function test_filter_can_only_restrict(): void {
		add_filter( EmbedPolicy::FILTER_HOSTS, static fn( array $hosts ): array => array( 'player.vimeo.com', 'evil.example' ) );
		$this->assertSame( array( 'player.vimeo.com' ), EmbedPolicy::hosts() );
		$this->assertFalse( EmbedPolicy::is_allowed_url( 'https://evil.example/x' ) );
		$this->assertFalse( EmbedPolicy::is_allowed_url( 'https://www.youtube-nocookie.com/embed/abcdefghijk' ) );
		$this->assertTrue( EmbedPolicy::is_allowed_url( 'https://player.vimeo.com/video/1' ) );
	}

	public function test_etrusted_widget_host_is_allowed_and_can_be_removed(): void {
		$this->assertContains( 'integrations.etrusted.com', EmbedPolicy::hosts() );
		add_filter( EmbedPolicy::FILTER_HOSTS, static fn( array $hosts ): array => array_values( array_diff( $hosts, array( 'integrations.etrusted.com' ) ) ) );
		$this->assertNotContains( 'integrations.etrusted.com', EmbedPolicy::hosts() );
		$this->assertFalse( EmbedPolicy::is_allowed_url( 'https://integrations.etrusted.com/applications/widget.js/v2' ) );
		$this->assertTrue( EmbedPolicy::is_allowed_url( 'https://widgets.trustedshops.com/js/X.js' ) );
	}

	public function test_click_to_load_script_uses_the_same_host_list(): void {
		$js = (string) file_get_contents( dirname( __DIR__, 3 ) . '/assets/js/frontend-embed.js' );
		$this->assertSame( 1, preg_match( '/var ALLOWED = \[(.*?)\];/s', $js, $m ) );
		preg_match_all( "/'([^']+)'/", $m[1], $hosts );
		$this->assertSame( EmbedPolicy::HOSTS, $hosts[1] );
	}

	public function test_external_link_attrs(): void {
		$this->assertSame( 'rel="nofollow noopener noreferrer ugc"', EmbedPolicy::external_link_attrs() );
		$this->assertSame( 'rel="nofollow noopener noreferrer ugc" target="_blank"', EmbedPolicy::external_link_attrs( true ) );
	}

	public function test_click_to_load_has_no_third_party_request(): void {
		$GLOBALS['rc_test_scripts'] = array();
		$html                       = EmbedPolicy::click_to_load( 'https://www.youtube-nocookie.com/embed/abcdefghijk', 'Video "Anna" <b>' );
		$this->assertStringContainsString( 'rc-embed-placeholder', $html );
		$this->assertStringContainsString( '<button type="button"', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( 'www.youtube-nocookie.com', $html );
		$this->assertStringContainsString( 'öffnet in neuem Tab', $html );
		$this->assertContains( EmbedPolicy::SCRIPT_HANDLE, $GLOBALS['rc_test_scripts'] );

		$this->assertSame( '', EmbedPolicy::click_to_load( 'https://evil.example/embed', 'x' ) );
	}
}
