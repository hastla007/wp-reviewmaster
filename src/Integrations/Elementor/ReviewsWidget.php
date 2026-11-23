<?php
/**
 * Elementor widget "Review Captain" (RC-119A). Only loaded by ElementorModule when Elementor is active.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Native widget; output is identical to the shortcode (server-side, no own JS).
 */
final class ReviewsWidget extends \Elementor\Widget_Base {

	/** Widget name. */
	public function get_name(): string {
		return 'review-captain';
	}

	/** Panel title. */
	public function get_title(): string {
		return __( 'Review Captain', 'review-captain' );
	}

	/** Panel icon. */
	public function get_icon(): string {
		return 'eicon-rating';
	}

	/** @return list<string> */
	public function get_categories(): array {
		return array( 'review-captain', 'general' );
	}

	/** @return list<string> */
	public function get_keywords(): array {
		return array( 'review', 'reviews', 'bewertung', 'testimonial', 'rating' );
	}

	/** Controls mirror the shortcode attributes of widget-v1. */
	protected function register_controls(): void {
		$this->start_controls_section( 'rc_content', array( 'label' => __( 'Content', 'review-captain' ) ) );
		$this->add_control(
			'rc_type',
			array(
				'label'   => __( 'Content type', 'review-captain' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'reviews',
				'options' => array(
					'reviews' => __( 'Show reviews', 'review-captain' ),
					'form'    => __( 'Review form', 'review-captain' ),
					'cta'     => __( 'Review profile button', 'review-captain' ),
				),
			)
		);
		$reviews = array( 'rc_type' => 'reviews' );
		// SET-RC-28: empty = global default from the settings (Darstellung › Widget-Vorgaben).
		$this->add_control( 'rc_layout', array( 'label' => __( 'Layout', 'review-captain' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'Default', 'review-captain' ), 'grid' => __( 'Grid', 'review-captain' ), 'list' => __( 'List', 'review-captain' ) ), 'condition' => $reviews ) );
		$this->add_control( 'rc_columns', array( 'label' => __( 'Columns (empty = default)', 'review-captain' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'max' => 4, 'default' => '', 'condition' => $reviews ) );
		$this->add_control( 'rc_limit', array( 'label' => __( 'Number (empty = default)', 'review-captain' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'max' => 50, 'default' => '', 'condition' => $reviews ) );
		$this->add_control( 'rc_platform', array( 'label' => __( 'Platform (empty = all)', 'review-captain' ), 'type' => \Elementor\Controls_Manager::TEXT, 'placeholder' => 'local,google', 'condition' => $reviews ) );
		$this->add_control( 'rc_kind', array( 'label' => __( 'Type', 'review-captain' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'Default', 'review-captain' ), 'review' => _x( 'Reviews', 'review kind', 'review-captain' ), 'testimonial' => __( 'Testimonials', 'review-captain' ), 'all' => __( 'All', 'review-captain' ) ), 'condition' => $reviews ) );
		$this->add_control( 'rc_min_rating', array( 'label' => __( 'Minimum rating', 'review-captain' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'max' => 5, 'step' => 0.5, 'condition' => $reviews ) );
		$this->add_control( 'rc_context_type', array( 'label' => __( 'Context', 'review-captain' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'Company in general', 'review-captain' ), 'post' => __( 'Post/page', 'review-captain' ), 'product' => __( 'Product', 'review-captain' ), 'event' => __( 'Event', 'review-captain' ) ), 'condition' => array( 'rc_type!' => 'cta' ) ) );
		$this->add_control( 'rc_context_id', array( 'label' => __( 'Context ID (current = current page)', 'review-captain' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'current', 'condition' => array( 'rc_type!' => 'cta', 'rc_context_type!' => '' ) ) );
		$this->add_control( 'rc_heading_level', array( 'label' => __( 'Heading level of the cards', 'review-captain' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'Default', 'review-captain' ), '2' => 'H2', '3' => 'H3', '4' => 'H4', '5' => 'H5', '6' => 'H6' ), 'condition' => $reviews ) );
		$this->add_control( 'rc_paginate', array( 'label' => __( 'Paginate', 'review-captain' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'condition' => $reviews ) );
		$this->add_control( 'rc_profile', array( 'label' => __( 'Profile ID', 'review-captain' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'condition' => array( 'rc_type' => 'cta' ) ) );
		$this->end_controls_section();
	}

	/** Server-side output, also used for the editor preview. */
	protected function render(): void {
		echo ElementorModule::render_settings( (array) $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer templates.
	}
}
