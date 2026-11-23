<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Integrations;

use ReviewCaptain\Integrations\TrustedShops\Trustbadge;
use ReviewCaptain\Tests\TestCase;

final class TrustbadgeTest extends TestCase {

	private const TSID = 'X1234567890ABCDEF1234567890ABCDEF';

	public function test_sanitize_tsid(): void {
		$this->assertSame( self::TSID, Trustbadge::sanitize_tsid( ' x1234567890abcdef1234567890abcdef ' ) );
		foreach ( array( '', 'X123', 'Y1234567890ABCDEF1234567890ABCDEF', self::TSID . '0', 'X1234567890ABCDEF1234567890ABCDEG', 'X1234567890ABCDEF1234567890ABCDE/' ) as $bad ) {
			$this->assertSame( '', Trustbadge::sanitize_tsid( $bad ), $bad );
		}
	}

	public function test_render_is_click_to_load_without_third_party_request(): void {
		$html = Trustbadge::render( self::TSID );
		$this->assertStringContainsString( 'data-rc-embed-script="https://widgets.trustedshops.com/js/' . self::TSID . '.js"', $html );
		$this->assertStringContainsString( 'rc-embed-placeholder__load', $html );
		$this->assertStringContainsString( 'https://www.trustedshops.de/bewertung/info_' . self::TSID . '.html', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_invalid_tsid_renders_nothing(): void {
		$this->assertSame( '', Trustbadge::render( 'X"><script>alert(1)</script>' ) );
		$this->assertSame( '', Trustbadge::render( '' ) );
	}

	public function test_respects_embed_host_filter(): void {
		add_filter( 'review_captain_embed_hosts', static fn( array $h ): array => array_values( array_diff( $h, array( 'widgets.trustedshops.com' ) ) ) );
		$this->assertSame( '', Trustbadge::render( self::TSID ) );
	}
}
