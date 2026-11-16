<?php
/**
 * Widget definitions: list, editor with live preview (UX-003, RC-118).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Frontend\Renderer;
use ReviewCaptain\Reviews\ReviewRepository;
use ReviewCaptain\Reviews\ReviewService;
use ReviewCaptain\Widgets\WidgetRepository;

defined( 'ABSPATH' ) || exit;

final class WidgetsPage {

	private const PAGE = 'review-captain-widgets';

	public static function register_actions(): void {
		add_action( 'admin_post_review_captain_save_widget', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_review_captain_delete_widget', array( self::class, 'handle_delete' ) );
	}

	public static function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		// phpcs:enable
		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			self::render_editor( $id );
			return;
		}
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Widgets', 'review-captain' ) . '</h1> <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&action=add' ) ) . '">' . esc_html__( 'Add widget', 'review-captain' ) . '</a><hr class="wp-header-end">';
		Menu::notices();
		$widgets = WidgetRepository::all();
		if ( ! $widgets ) {
			echo '<div class="rc-empty"><h2>' . esc_html__( 'No widgets yet', 'review-captain' ) . '</h2><p>' . esc_html__( 'A widget defines which reviews are shown and how. You can then place it with the “Review Captain Widget” block or a shortcode.', 'review-captain' ) . '</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&action=add' ) ) . '">' . esc_html__( 'Create first widget', 'review-captain' ) . '</a></p></div></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'review-captain' ) . '</th><th>' . esc_html__( 'Layout', 'review-captain' ) . '</th><th>' . esc_html__( 'Shortcode', 'review-captain' ) . '</th><th>' . esc_html__( 'Updated', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $widgets as $w ) {
			echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&action=edit&id=' . (int) $w['id'] ) ) . '"><strong>' . esc_html( $w['title'] ) . '</strong></a></td><td>' . esc_html( $w['layout'] ) . '</td><td><code>[review_captain_widget id="' . (int) $w['id'] . '"]</code></td><td>' . esc_html( $w['updated_at'] ) . ' UTC</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function render_editor( int $id ): void {
		$widget = $id ? WidgetRepository::find( $id ) : null;
		$config = $widget ? $widget['config'] : WidgetRepository::default_config();
		$layout = $widget ? $widget['layout'] : 'grid';
		$title  = $widget ? $widget['title'] : '';
		echo '<div class="wrap"><h1>' . esc_html( $id ? __( 'Edit widget', 'review-captain' ) : __( 'Add widget', 'review-captain' ) ) . '</h1>';
		Menu::notices();
		echo '<div class="rc-columns"><div class="rc-main"><form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_save_widget"><input type="hidden" name="id" value="' . (int) $id . '">';
		wp_nonce_field( 'rc_save_widget' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="rc-w-title">' . esc_html__( 'Title (internal)', 'review-captain' ) . '</label></th><td><input type="text" id="rc-w-title" name="title" class="regular-text" value="' . esc_attr( $title ) . '" required></td></tr>';
		echo '<tr><th>' . esc_html__( 'Sources', 'review-captain' ) . '</th><td><fieldset>';
		foreach ( array_merge( array( 'site' ), ReviewService::EXTERNAL_PROVIDERS ) as $p ) {
			printf( '<label><input type="checkbox" name="providers[]" value="%s"%s> %s</label><br>', esc_attr( $p ), checked( in_array( $p, $config['providers'], true ), true, false ), esc_html( ReviewService::provider_label( $p ) ) );
		}
		echo '<p class="description">' . esc_html__( 'Nothing selected = all sources. The source of external reviews is always shown.', 'review-captain' ) . '</p></fieldset></td></tr>';
		echo '<tr><th><label for="rc-w-layout">' . esc_html__( 'Layout', 'review-captain' ) . '</label></th><td><select id="rc-w-layout" name="layout">';
		foreach ( (array) apply_filters( 'review_captain_widget_layouts', WidgetRepository::CORE_LAYOUTS ) as $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $l ), selected( $layout, $l, false ), esc_html( ucfirst( $l ) ) );
		}
		echo '</select>';
		if ( ! has_filter( 'review_captain_widget_layouts' ) ) {
			echo '<p class="description">' . esc_html__( 'Carousel, badge and rating bar are available with Review Captain Pro.', 'review-captain' ) . '</p>';
		}
		echo '</td></tr>';
		printf( '<tr><th><label for="rc-w-limit">%s</label></th><td><input type="number" id="rc-w-limit" name="limit" min="1" max="24" value="%d" class="small-text"></td></tr>', esc_html__( 'Number of reviews', 'review-captain' ), (int) $config['limit'] );
		printf( '<tr><th><label for="rc-w-columns">%s</label></th><td><input type="number" id="rc-w-columns" name="columns" min="0" max="4" value="%d" class="small-text"><p class="description">%s</p></td></tr>', esc_html__( 'Columns', 'review-captain' ), (int) $config['columns'], esc_html__( '0 = automatic', 'review-captain' ) );
		printf( '<tr><th><label for="rc-w-rating">%s</label></th><td><input type="number" id="rc-w-rating" name="rating_min" min="0" max="5" value="%d" class="small-text"><p class="description">%s</p></td></tr>', esc_html__( 'Minimum rating shown', 'review-captain' ), (int) $config['rating_min'], esc_html__( 'Display filter only. It never influences who is asked for a review.', 'review-captain' ) );
		echo '<tr><th>' . esc_html__( 'Type', 'review-captain' ) . '</th><td>';
		foreach ( ReviewRepository::TYPES as $t ) {
			printf( '<label><input type="checkbox" name="types[]" value="%s"%s> %s</label> ', esc_attr( $t ), checked( in_array( $t, $config['types'], true ), true, false ), esc_html( ReviewsListTable::type_label( $t ) ) );
		}
		echo '</td></tr>';
		printf( '<tr><th><label for="rc-w-ctype">%s</label></th><td><input type="text" id="rc-w-ctype" name="context_type" value="%s" class="regular-text" placeholder="product"> <input type="text" name="context_id" value="%s" class="small-text" aria-label="%s" placeholder="ID"></td></tr>', esc_html__( 'Context', 'review-captain' ), esc_attr( $config['context_type'] ), esc_attr( $config['context_id'] ), esc_attr__( 'Context ID', 'review-captain' ) );
		printf( '<tr><th><label for="rc-w-ids">%s</label></th><td><input type="text" id="rc-w-ids" name="ids" value="%s" class="regular-text"><p class="description">%s</p></td></tr>', esc_html__( 'Hand-picked reviews', 'review-captain' ), esc_attr( implode( ',', $config['ids'] ) ), esc_html__( 'Optional: comma-separated review IDs.', 'review-captain' ) );
		printf( '<tr><th>%s</th><td><label><input type="checkbox" name="include_testimonials" value="1"%s> %s</label><br><label><input type="checkbox" name="show_summary" value="1"%s> %s</label></td></tr>', esc_html__( 'Content', 'review-captain' ), checked( $config['include_testimonials'], true, false ), esc_html__( 'Include testimonials', 'review-captain' ), checked( $config['show_summary'], true, false ), esc_html__( 'Show summary per source', 'review-captain' ) );
		echo '</tbody></table>';
		submit_button( __( 'Save widget', 'review-captain' ) );
		echo '</form>';
		if ( $id ) {
			echo '<p>' . esc_html__( 'Shortcode:', 'review-captain' ) . ' <code>[review_captain_widget id="' . (int) $id . '"]</code></p>';
			echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Delete this widget? Pages using it will show nothing.', 'review-captain' ) ) . '\');"><input type="hidden" name="action" value="review_captain_delete_widget"><input type="hidden" name="id" value="' . (int) $id . '">';
			wp_nonce_field( 'rc_delete_widget_' . $id );
			submit_button( __( 'Delete widget', 'review-captain' ), 'delete', 'submit', false );
			echo '</form>';
		}
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Structured data (SEO): only approved reviews collected on this website for a product, course, event or app are marked up – and only on the page of that item. Reviews from other platforms, reviews about your company and testimonials never get review markup. Rich results are not guaranteed.', 'review-captain' ) . '</p></div>';
		echo '</div><div class="rc-side"><h2>' . esc_html__( 'Preview', 'review-captain' ) . '</h2><div class="rc-preview">';
		wp_enqueue_style( 'review-captain' );
		$preview = Renderer::widget( $config, $layout );
		echo '' !== $preview ? $preview : '<p>' . esc_html__( 'No reviews match these settings yet.', 'review-captain' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Renderer.
		echo '</div></div></div></div>';
	}

	public static function handle_save(): void {
		review_captain_check_request( 'rc_manage_widgets', 'rc_save_widget' );
		$in     = wp_unslash( $_POST );
		$config = array(
			'providers'            => (array) ( $in['providers'] ?? array() ),
			'types'                => (array) ( $in['types'] ?? array() ),
			'limit'                => (int) ( $in['limit'] ?? 6 ),
			'columns'              => (int) ( $in['columns'] ?? 0 ),
			'rating_min'           => (int) ( $in['rating_min'] ?? 0 ),
			'context_type'         => (string) ( $in['context_type'] ?? '' ),
			'context_id'           => (string) ( $in['context_id'] ?? '' ),
			'ids'                  => array_filter( array_map( 'absint', explode( ',', (string) ( $in['ids'] ?? '' ) ) ) ),
			'include_testimonials' => ! empty( $in['include_testimonials'] ),
			'show_summary'         => ! empty( $in['show_summary'] ),
		);
		$id     = WidgetRepository::save( absint( $in['id'] ?? 0 ), (string) ( $in['title'] ?? '' ), (string) ( $in['layout'] ?? 'grid' ), $config );
		Menu::back(
			self::PAGE,
			'saved',
			'',
			array(
				'action' => 'edit',
				'id'     => (string) $id,
			)
		);
	}

	public static function handle_delete(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		review_captain_check_request( 'rc_manage_widgets', 'rc_delete_widget_' . $id );
		WidgetRepository::delete( $id );
		Menu::back( self::PAGE, 'deleted' );
	}
}
