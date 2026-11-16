<?php
/**
 * Elementor integration (RC-119A, owner decision 2026-11-16).
 *
 * A thin native Elementor widget around the versioned widget contract (widget-v1). Rendering is
 * the same server-side output as the shortcode; no own frontend JS. Loaded only when Elementor
 * fires its widget registration hook, so the Core never depends on Elementor classes.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Integrations\Elementor;

use ReviewCaptain\Frontend\FrontendModule;
use ReviewCaptain\Module;
use ReviewCaptain\Plugin;

/**
 * Registers the widget with Elementor and maps its settings to shortcode attributes.
 */
final class ElementorModule implements Module {

	/** Widget types offered in the Elementor panel (Core types only; Pro adds its own via widget-v1). */
	public const TYPES = array( 'reviews', 'form', 'cta' );

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
	}

	/**
	 * Elementor hook: register the widget.
	 *
	 * @param object $widgets_manager \Elementor\Widgets_Manager.
	 */
	public function register_widget( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		require_once __DIR__ . '/ReviewsWidget.php';
		$widgets_manager->register( new ReviewsWidget() );
	}

	/**
	 * Elementor hook: own panel category "Review Captain".
	 *
	 * @param object $elements_manager \Elementor\Elements_Manager.
	 */
	public function register_category( $elements_manager ): void {
		if ( is_object( $elements_manager ) && method_exists( $elements_manager, 'add_category' ) ) {
			$elements_manager->add_category( 'review-captain', array( 'title' => __( 'Review Captain', 'review-captain' ), 'icon' => 'eicon-star' ) );
		}
	}

	/**
	 * Map Elementor control values to shortcode attributes of widget-v1. Unknown keys are dropped,
	 * empty values omitted, so the shortcode defaults apply exactly as for [review_captain].
	 *
	 * @param array<string, mixed> $settings Elementor settings.
	 * @return array<string, string>
	 */
	public static function settings_to_atts( array $settings ): array {
		$type = in_array( $settings['rc_type'] ?? 'reviews', self::TYPES, true ) ? (string) $settings['rc_type'] : 'reviews';
		$map  = array(
			'rc_layout'        => 'layout',
			'rc_columns'       => 'columns',
			'rc_limit'         => 'limit',
			'rc_platform'      => 'platform',
			'rc_kind'          => 'kind',
			'rc_min_rating'    => 'min_rating',
			'rc_context_type'  => 'context_type',
			'rc_context_id'    => 'context_id',
			'rc_heading_level' => 'heading_level',
			'rc_profile'       => 'profile',
			'rc_paginate'      => 'paginate',
			'rc_show_source'   => 'show_source',
			'rc_show_date'     => 'show_date',
			'rc_show_author'   => 'show_author',
		);
		$atts = array(
			'v'    => '1',
			'type' => $type,
		);
		foreach ( $map as $control => $attr ) {
			if ( ! array_key_exists( $control, $settings ) ) {
				continue;
			}
			$value = $settings[ $control ];
			if ( is_array( $value ) ) {
				$value = implode( ',', array_map( 'strval', $value ) );
			}
			if ( 'yes' === $value ) {
				$value = '1';
			}
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$atts[ $attr ] = $value;
			}
		}
		return $atts;
	}

	/**
	 * Server-side output for Elementor settings (same code path as the shortcodes).
	 *
	 * @param array<string, mixed> $settings Elementor settings.
	 */
	public static function render_settings( array $settings ): string {
		$frontend = Plugin::instance()->module( FrontendModule::class );
		if ( ! $frontend instanceof FrontendModule ) {
			// Module list not booted (tests, unusual load order): use a private instance.
			$frontend = new FrontendModule();
			$frontend->register( Plugin::instance() );
		}
		$atts = self::settings_to_atts( $settings );
		return match ( $atts['type'] ) {
			'form'  => $frontend->shortcode_form( $atts ),
			'cta'   => $frontend->shortcode_cta( $atts ),
			default => $frontend->shortcode_reviews( $atts ),
		};
	}
}
