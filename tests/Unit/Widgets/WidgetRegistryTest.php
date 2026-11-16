<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Widgets;

use ReviewCaptain\Tests\Unit\Frontend\FrontendTestCase;
use ReviewCaptain\Widgets\WidgetDefinition;
use ReviewCaptain\Widgets\WidgetRegistry;

final class WidgetRegistryTest extends FrontendTestCase {

	public function test_core_types_registered_and_extensions_via_action(): void {
		add_action(
			WidgetRegistry::ACTION,
			static function ( WidgetRegistry $registry ): void {
				$registry->register( 'carousel', static fn( WidgetDefinition $d ): string => '<div class="pro">' . esc_html( $d->raw['speed'] ?? '' ) . '</div>', array( 'label' => 'Karussell' ) );
				// Core types cannot be replaced.
				$registry->register( 'reviews', static fn(): string => 'hijack' );
			}
		);
		$registry = $this->module->registry();
		$this->assertTrue( $registry->has( 'reviews' ) );
		$this->assertTrue( $registry->has( 'form' ) );
		$this->assertTrue( $registry->has( 'cta' ) );
		$this->assertTrue( $registry->has( 'carousel' ) );
		$this->assertSame( 1, did_action( WidgetRegistry::ACTION ) );

		$this->assertSame( '<div class="pro">3</div>', $this->shortcode( array( 'type' => 'carousel', 'speed' => '3' ) ) );
		$this->assertStringNotContainsString( 'hijack', $this->shortcode() );
	}

	public function test_invalid_and_unknown_types(): void {
		$registry = new WidgetRegistry();
		$this->assertFalse( $registry->register( 'Bad Key', static fn() => '' ) );
		$this->assertFalse( $registry->register( 'form', static fn() => '' ) );
		$this->assertTrue( $registry->register( 'badge', static fn() => 'b' ) );
		$this->assertFalse( $registry->register( 'badge', static fn() => 'c' ) );

		$out = $this->shortcode( array( 'type' => 'does-not-exist' ) );
		$this->assertStringStartsWith( '<!--', $out );
		$this->assertStringNotContainsString( '--><', $out );
	}

	public function test_comment_cannot_break_out(): void {
		$c = WidgetRegistry::comment( 'x --> <script>alert(1)</script>' );
		$this->assertSame( 1, substr_count( $c, '-->' ) );
		$this->assertStringNotContainsString( '<script>', $c );
	}
}
