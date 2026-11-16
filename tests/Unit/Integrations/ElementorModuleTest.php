<?php
declare(strict_types=1);

namespace Elementor {
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
		/** Minimal stand-in for Elementor's base class (test only). */
		abstract class Widget_Base {
			/** @var array<string, mixed> */
			public array $controls = array();
			/** @var array<string, mixed> */
			public array $settings = array();
			protected function start_controls_section( string $id, array $args ): void {}
			protected function end_controls_section(): void {}
			protected function add_control( string $id, array $args ): void {
				$this->controls[ $id ] = $args;
			}
			public function get_settings_for_display(): array {
				return $this->settings;
			}
			public function boot(): void {
				$this->register_controls();
			}
			public function output(): string {
				ob_start();
				$this->render();
				return (string) ob_get_clean();
			}
			abstract protected function register_controls(): void;
			abstract protected function render(): void;
		}
		/** Control type constants (test only). */
		class Controls_Manager {
			const SELECT = 'select';
			const NUMBER = 'number';
			const TEXT = 'text';
			const SWITCHER = 'switcher';
		}
	}
}

namespace ReviewCaptain\Tests\Unit\Integrations {

	use ReviewCaptain\Domain\ReviewStatus;
	use ReviewCaptain\Domain\Review;
	use ReviewCaptain\Integrations\Elementor\ElementorModule;
	use ReviewCaptain\Integrations\Elementor\ReviewsWidget;
	use ReviewCaptain\Plugin;
	use ReviewCaptain\Tests\TestCase;

	/**
	 * RC-119A: native Elementor widget maps to widget-v1 and renders like the shortcode.
	 */
	final class ElementorModuleTest extends TestCase {

		public function test_settings_map_to_shortcode_atts(): void {
			$atts = ElementorModule::settings_to_atts(
				array(
					'rc_type' => 'reviews', 'rc_layout' => 'list', 'rc_columns' => 2, 'rc_limit' => '4',
					'rc_platform' => array( 'local', 'google' ), 'rc_min_rating' => '', 'rc_paginate' => 'yes',
					'rc_heading_level' => '2', 'unknown' => 'x',
				)
			);
			$this->assertSame(
				array( 'v' => '1', 'type' => 'reviews', 'layout' => 'list', 'columns' => '2', 'limit' => '4', 'platform' => 'local,google', 'heading_level' => '2', 'paginate' => '1' ),
				$atts
			);
		}

		public function test_unknown_type_falls_back_to_reviews(): void {
			$this->assertSame( 'reviews', ElementorModule::settings_to_atts( array( 'rc_type' => 'evil' ) )['type'] );
			$this->assertSame( 'form', ElementorModule::settings_to_atts( array( 'rc_type' => 'form' ) )['type'] );
		}

		public function test_register_widget_only_with_elementor_manager(): void {
			$module  = new ElementorModule();
			$manager = new class() {
				/** @var list<object> */
				public array $widgets = array();
				public function register( object $w ): void {
					$this->widgets[] = $w;
				}
			};
			$module->register_widget( $manager );
			$this->assertCount( 1, $manager->widgets );
			$this->assertInstanceOf( ReviewsWidget::class, $manager->widgets[0] );
			$module->register_widget( new \stdClass() ); // No register() method: ignored, no fatal.
			$this->addToAssertionCount( 1 );
		}

		public function test_widget_controls_and_output_match_shortcode(): void {
			$r                  = new Review();
			$r->rating_value    = 5.0;
			$r->body            = 'Elementor-Testbewertung';
			$r->author_name     = 'Test';
			$r->status          = ReviewStatus::APPROVED;
			$r->consent_publish = true;
			Plugin::instance()->reviews()->insert( $r );

			$w = new ReviewsWidget();
			$w->boot();
			$this->assertArrayHasKey( 'rc_type', $w->controls );
			$this->assertArrayHasKey( 'rc_heading_level', $w->controls );
			$w->settings = array( 'rc_type' => 'reviews', 'rc_limit' => 3 );
			$html        = $w->output();
			$this->assertStringContainsString( 'Elementor-Testbewertung', $html );
			$norm = static fn( string $h ): string => (string) preg_replace( '/rc-widget-\\d+/', 'rc-widget-N', $h );
			$this->assertSame( $norm( ElementorModule::render_settings( $w->settings ) ), $norm( $html ) );
		}
	}
}
