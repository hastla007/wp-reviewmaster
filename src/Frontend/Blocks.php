<?php
/**
 * Dynamic Gutenberg blocks (RC-050): widget, form, CTA. Rendered server-side; editor preview via ServerSideRender.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Widgets\WidgetRepository;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_blocks' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_data' ) );
	}

	public static function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type(
			REVIEW_CAPTAIN_DIR . 'blocks/widget',
			array( 'render_callback' => array( self::class, 'render_widget' ) )
		);
		register_block_type(
			REVIEW_CAPTAIN_DIR . 'blocks/form',
			array( 'render_callback' => array( self::class, 'render_form' ) )
		);
		register_block_type(
			REVIEW_CAPTAIN_DIR . 'blocks/cta',
			array( 'render_callback' => array( self::class, 'render_cta' ) )
		);
	}

	public static function editor_data(): void {
		$widgets = array_map(
			static function ( $w ) {
				return array(
					'value' => (int) $w['id'],
					'label' => $w['title'],
				);
			},
			current_user_can( 'edit_posts' ) ? WidgetRepository::all() : array()
		);
		wp_add_inline_script(
			'review-captain-blocks',
			'window.reviewCaptainBlocks = ' . wp_json_encode(
				array(
					'widgets'  => $widgets,
					'adminUrl' => admin_url( 'admin.php?page=review-captain-widgets' ),
					'layouts'  => (array) apply_filters( 'review_captain_widget_layouts', WidgetRepository::CORE_LAYOUTS ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * @param array<string,mixed> $attrs Block attributes.
	 */
	public static function render_widget( array $attrs ): string {
		$widget_id = isset( $attrs['widgetId'] ) ? absint( $attrs['widgetId'] ) : 0;
		if ( $widget_id ) {
			$widget = WidgetRepository::find( $widget_id );
			if ( ! $widget || 'active' !== $widget['status'] ) {
				return self::editor_hint( __( 'This widget no longer exists.', 'review-captain' ) );
			}
			$html = Renderer::widget( $widget['config'], (string) $widget['layout'], 'rc-widget-' . $widget_id );
		} else {
			$html = Renderer::widget(
				array(
					'providers'            => (array) ( $attrs['providers'] ?? array() ),
					'limit'                => (int) ( $attrs['limit'] ?? 6 ),
					'rating_min'           => (int) ( $attrs['ratingMin'] ?? 0 ),
					'columns'              => (int) ( $attrs['columns'] ?? 0 ),
					'include_testimonials' => ! empty( $attrs['testimonials'] ),
					'context_type'         => (string) ( $attrs['contextType'] ?? '' ),
					'context_id'           => (string) ( $attrs['contextId'] ?? '' ),
				),
				sanitize_key( (string) ( $attrs['layout'] ?? 'grid' ) )
			);
		}
		if ( '' === $html ) {
			return self::editor_hint( __( 'This widget currently shows no reviews – check its filters.', 'review-captain' ) );
		}
		return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}

	/**
	 * @param array<string,mixed> $attrs Block attributes.
	 */
	public static function render_form( array $attrs ): string {
		return '<div ' . get_block_wrapper_attributes() . '>' . SubmissionForm::render(
			array(
				'context_type' => (string) ( $attrs['contextType'] ?? '' ),
				'context_id'   => (string) ( $attrs['contextId'] ?? '' ),
				'type'         => (string) ( $attrs['reviewType'] ?? 'general' ),
			)
		) . '</div>';
	}

	/**
	 * @param array<string,mixed> $attrs Block attributes.
	 */
	public static function render_cta( array $attrs ): string {
		$html = Shortcodes::cta(
			array(
				'profiles' => implode( ',', array_map( 'absint', (array) ( $attrs['profiles'] ?? array() ) ) ),
				'text'     => (string) ( $attrs['text'] ?? '' ),
			)
		);
		return '' === $html ? self::editor_hint( __( 'Add a public profile link under Review Captain → Sources first.', 'review-captain' ) ) : '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}

	/**
	 * Hints are only visible in the editor preview, never to visitors.
	 */
	private static function editor_hint( string $text ): string {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'edit_posts' ) ) {
			return '<p class="rc-editor-hint">' . esc_html( $text ) . '</p>';
		}
		return '';
	}
}
