<?php
/**
 * Frontend module: shortcodes, blocks, styles and form handler.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Moderation\ReportHandler;
use ReviewCaptain\Module;
use ReviewCaptain\Questions\QuestionHandler;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\EmbedPolicy;
use ReviewCaptain\Seo\VisibleReviews;
use ReviewCaptain\Widgets\WidgetDefinition;
use ReviewCaptain\Widgets\WidgetRegistry;

/**
 * Wires the public output into WordPress. No DB writes, no remote requests on register().
 */
final class FrontendModule implements Module {

	public const STYLE_HANDLE = 'review-captain-frontend';

	public const SHORTCODE_REVIEWS = 'review_captain';
	public const SHORTCODE_FORM    = 'review_captain_form';
	public const SHORTCODE_CTA     = 'review_captain_cta';

	/**
	 * Plugin.
	 */
	private Plugin $plugin;

	/**
	 * Register hooks and services.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		$this->plugin = $plugin;
		VisibleReviews::from_plugin( $plugin );
		$plugin->factory( 'widget_registry', static fn(): WidgetRegistry => new WidgetRegistry() );
		$plugin->factory( 'renderer', static fn( Plugin $p ): Renderer => new Renderer( $p, VisibleReviews::from_plugin( $p ) ) );
		$plugin->factory( 'form_handler', static fn( Plugin $p ): FormHandler => new FormHandler( $p ) );
		$plugin->factory( 'report_handler', static fn( Plugin $p ): ReportHandler => new ReportHandler( $p ) );
		$plugin->factory( 'question_handler', static fn( Plugin $p ): QuestionHandler => new QuestionHandler( $p ) );
		$plugin->factory( 'correction_handler', static fn( Plugin $p ): \ReviewCaptain\Moderation\CorrectionHandler => new \ReviewCaptain\Moderation\CorrectionHandler( $p ) );
		// RC-125A author corrections (checks its own setting).
		add_action( 'admin_post_' . \ReviewCaptain\Moderation\CorrectionHandler::ACTION, array( $this, 'handle_correction' ) );
		add_action( 'admin_post_nopriv_' . \ReviewCaptain\Moderation\CorrectionHandler::ACTION, array( $this, 'handle_correction' ) );

		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_early' ) );
		add_action( 'admin_post_' . FormHandler::ACTION, array( $this, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . FormHandler::ACTION, array( $this, 'handle_submit' ) );
		// M7 endpoints: each checks its own setting (all off by default) before doing anything.
		foreach ( array( ReportHandler::ACTION => 'handle_report', QuestionHandler::ACTION => 'handle_question', PhotoStore::ACTION => 'handle_photo' ) as $action => $method ) {
			add_action( 'admin_post_' . $action, array( $this, $method ) );
			add_action( 'admin_post_nopriv_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * admin-post: author correction (RC-125A).
	 */
	public function handle_correction(): void {
		$this->plugin->get( 'correction_handler' )->handle();
	}

	/**
	 * [review_captain_correction] (RC-125A).
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 */
	public function shortcode_correction( $atts = array() ): string {
		return $this->plugin->get( 'correction_handler' )->shortcode( $atts );
	}

	/**
	 * admin-post: public report (RC-124).
	 */
	public function handle_report(): void {
		/** @var ReportHandler $handler */
		$handler = $this->plugin->get( 'report_handler' );
		$handler->handle();
	}

	/**
	 * admin-post: product question (RC-122).
	 */
	public function handle_question(): void {
		/** @var QuestionHandler $handler */
		$handler = $this->plugin->get( 'question_handler' );
		$handler->handle();
	}

	/**
	 * admin-post: deliver a review photo (RC-121).
	 */
	public function handle_photo(): void {
		PhotoStore::serve();
	}

	/**
	 * [review_captain_qa v="1" context_type="product" context_id=""].
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 */
	public function shortcode_qa( $atts ): string {
		/** @var QuestionHandler $handler */
		$handler = $this->plugin->get( 'question_handler' );
		return $handler->shortcode( $atts );
	}

	/**
	 * The shared widget registry (core types registered, extensions booted).
	 */
	public function registry(): WidgetRegistry {
		/** @var WidgetRegistry $registry */
		$registry = $this->plugin->get( 'widget_registry' );
		if ( ! $registry->has_core() ) {
			$this->renderer()->register_types( $registry );
		}
		return $registry;
	}

	/**
	 * Renderer.
	 */
	public function renderer(): Renderer {
		/** @var Renderer */
		return $this->plugin->get( 'renderer' );
	}

	/**
	 * Init: shortcodes, blocks, assets, widget registry.
	 */
	public function on_init(): void {
		add_shortcode( self::SHORTCODE_REVIEWS, array( $this, 'shortcode_reviews' ) );
		add_shortcode( self::SHORTCODE_FORM, array( $this, 'shortcode_form' ) );
		add_shortcode( self::SHORTCODE_CTA, array( $this, 'shortcode_cta' ) );
		add_shortcode( QuestionHandler::SHORTCODE, array( $this, 'shortcode_qa' ) );
		add_shortcode( \ReviewCaptain\Moderation\CorrectionHandler::SHORTCODE, array( $this, 'shortcode_correction' ) );
		self::register_assets();
		$this->register_blocks();
		$this->registry()->boot();
	}

	/**
	 * [review_captain v="1" …].
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 */
	public function shortcode_reviews( $atts ): string {
		return $this->registry()->render( WidgetDefinition::from_atts( is_array( $atts ) ? $atts : array(), 'reviews' ) );
	}

	/**
	 * [review_captain_form v="1" context_type="" context_id=""].
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 */
	public function shortcode_form( $atts ): string {
		$atts         = is_array( $atts ) ? $atts : array();
		$atts['type'] = 'form';
		return $this->registry()->render( WidgetDefinition::from_atts( $atts, 'form' ) );
	}

	/**
	 * [review_captain_cta v="1" profile="ID"].
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 */
	public function shortcode_cta( $atts ): string {
		$atts         = is_array( $atts ) ? $atts : array();
		$atts['type'] = 'cta';
		return $this->registry()->render( WidgetDefinition::from_atts( $atts, 'cta' ) );
	}

	/**
	 * Block render callback for review-captain/reviews.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_reviews_block( $attributes = array() ): string {
		$atts         = WidgetDefinition::block_to_atts( is_array( $attributes ) ? $attributes : array() );
		$atts['type'] = 'reviews';
		return $this->registry()->render( WidgetDefinition::from_atts( $atts, 'reviews' ) );
	}

	/**
	 * Block render callback for review-captain/form.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_form_block( $attributes = array() ): string {
		$atts         = WidgetDefinition::block_to_atts( is_array( $attributes ) ? $attributes : array() );
		$atts['type'] = 'form';
		return $this->registry()->render( WidgetDefinition::from_atts( $atts, 'form' ) );
	}

	/**
	 * admin-post handler.
	 */
	public function handle_submit(): void {
		/** @var FormHandler $handler */
		$handler = $this->plugin->get( 'form_handler' );
		$handler->handle();
	}

	/**
	 * Register blocks from block.json (dynamic, server-rendered).
	 */
	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type( REVIEW_CAPTAIN_DIR . 'blocks/reviews', array( 'render_callback' => array( $this, 'render_reviews_block' ) ) );
		register_block_type( REVIEW_CAPTAIN_DIR . 'blocks/form', array( 'render_callback' => array( $this, 'render_form_block' ) ) );
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'review-captain-reviews-editor-script', 'review-captain', REVIEW_CAPTAIN_DIR . 'languages' );
			wp_set_script_translations( 'review-captain-form-editor-script', 'review-captain', REVIEW_CAPTAIN_DIR . 'languages' );
		}
	}

	/**
	 * Register (not enqueue) frontend assets. Idempotent.
	 */
	public static function register_assets(): void {
		wp_register_style( self::STYLE_HANDLE, REVIEW_CAPTAIN_URL . 'assets/css/frontend.css', array(), REVIEW_CAPTAIN_VERSION );
		wp_register_script( EmbedPolicy::SCRIPT_HANDLE, REVIEW_CAPTAIN_URL . 'assets/js/frontend-embed.js', array(), REVIEW_CAPTAIN_VERSION, true );
	}

	/**
	 * Enqueue CSS in the head when the main content contains a widget (avoids a late footer
	 * stylesheet). Widgets outside the content enqueue themselves on render.
	 */
	public function maybe_enqueue_early(): void {
		if ( ! $this->plugin->settings()->get( 'display', 'load_css', true ) || ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! isset( $post->post_content ) ) {
			return;
		}
		if ( ContentScanner::contains_widget( (string) $post->post_content ) ) {
			self::register_assets();
			wp_enqueue_style( self::STYLE_HANDLE );
		}
	}
}
