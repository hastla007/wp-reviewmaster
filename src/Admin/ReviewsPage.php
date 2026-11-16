<?php
/**
 * Reviews admin: list, detail view with moderation history, manual external reviews (RC-038, RC-040, RC-043).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Frontend\Renderer;
use ReviewCaptain\Reviews\ReviewRepository;
use ReviewCaptain\Reviews\ReviewService;
use ReviewCaptain\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class ReviewsPage {

	private const PAGE = 'review-captain-reviews';

	public static function register_actions(): void {
		add_action( 'admin_post_review_captain_moderate', array( self::class, 'handle_moderate' ) );
		add_action( 'admin_post_review_captain_save_external', array( self::class, 'handle_save_external' ) );
		add_action( 'admin_post_review_captain_delete_review', array( self::class, 'handle_delete' ) );
	}

	public static function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		// phpcs:enable
		if ( 'view' === $action && $id ) {
			self::render_view( $id );
			return;
		}
		if ( in_array( $action, array( 'add', 'edit' ), true ) && current_user_can( 'rc_manage_reviews' ) ) {
			self::render_form( 'edit' === $action ? $id : 0 );
			return;
		}
		$table = new ReviewsListTable();
		$table->prepare_items();
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Reviews', 'review-captain' ) . '</h1>';
		if ( current_user_can( 'rc_manage_reviews' ) ) {
			echo ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&action=add' ) ) . '">' . esc_html__( 'Add review from another platform', 'review-captain' ) . '</a>';
			echo ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=review-captain-import' ) ) . '">' . esc_html__( 'Import & Export', 'review-captain' ) . '</a>';
		}
		echo '<hr class="wp-header-end">';
		Menu::notices();
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '">';
		$table->search_box( __( 'Search reviews', 'review-captain' ), 'rc-search' );
		echo '</form>';
		// Posts back to this page; WP_List_Table adds its own bulk nonce (bulk-reviews). Handled in load hook.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">';
		$table->display();
		echo '</form></div>';
	}

	private static function render_view( int $id ): void {
		$r = ReviewRepository::find( $id );
		echo '<div class="wrap">';
		if ( ! $r ) {
			echo '<h1>' . esc_html__( 'Review not found', 'review-captain' ) . '</h1></div>';
			return;
		}
		echo '<h1>' . esc_html__( 'Review', 'review-captain' ) . '</h1>';
		Menu::notices();
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">&larr; ' . esc_html__( 'Back to reviews', 'review-captain' ) . '</a></p>';
		echo '<div class="rc-columns"><div class="rc-main postbox"><div class="inside">';
		echo Renderer::card( $r, array( 'author_mode' => 'full', 'show_date' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Renderer.
		echo '<table class="widefat striped rc-meta-table"><tbody>';
		$labels = ReviewsListTable::status_labels();
		$rows   = array(
			__( 'Status', 'review-captain' )       => $labels[ $r['status'] ] ?? $r['status'],
			__( 'Source', 'review-captain' )       => ReviewService::provider_label( (string) $r['provider'] ),
			__( 'Origin', 'review-captain' )       => $r['origin'],
			__( 'Type', 'review-captain' )         => ReviewsListTable::type_label( (string) $r['type'] ),
			__( 'Verification', 'review-captain' ) => $r['verification'] . ( $r['verification_ref'] ? ' (' . $r['verification_ref'] . ')' : '' ),
			__( 'Context', 'review-captain' )      => trim( $r['context_type'] . ' ' . $r['context_id'] ),
			__( 'Language', 'review-captain' )     => (string) $r['language'],
			__( 'Imported/created', 'review-captain' ) => $r['created_at'] . ' UTC',
		);
		if ( $r['fetched_at'] ) {
			$rows[ __( 'Last checked at source', 'review-captain' ) ] = $r['fetched_at'] . ' UTC';
		}
		foreach ( $rows as $k => $v ) {
			echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( 'site' !== $r['provider'] ) {
			echo '<p class="description">' . esc_html__( 'Texts of reviews from other platforms cannot be changed here. Changes to the original are only possible on the platform.', 'review-captain' ) . '</p>';
		}
		echo '</div></div><div class="rc-side">';

		if ( current_user_can( 'rc_moderate_reviews' ) ) {
			echo '<div class="postbox"><div class="inside"><h2>' . esc_html__( 'Moderation', 'review-captain' ) . '</h2>';
			echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_moderate"><input type="hidden" name="id" value="' . (int) $id . '">';
			wp_nonce_field( 'rc_moderate_' . $id );
			echo '<p><label for="rc-to">' . esc_html__( 'New status', 'review-captain' ) . '</label><br><select id="rc-to" name="to">';
			foreach ( $labels as $k => $label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $r['status'], $k, false ), esc_html( $label ) );
			}
			echo '</select></p><p><label for="rc-reason">' . esc_html__( 'Reason (optional, stored in history)', 'review-captain' ) . '</label><br><input type="text" class="widefat" id="rc-reason" name="reason" maxlength="255"></p>';
			submit_button( __( 'Update status', 'review-captain' ), 'primary', 'submit', false );
			echo '</form></div></div>';
		}
		echo '<div class="postbox"><div class="inside"><h2>' . esc_html__( 'History', 'review-captain' ) . '</h2><ol class="rc-history">';
		foreach ( ReviewRepository::history( $id ) as $h ) {
			$user = $h['user_id'] ? get_userdata( (int) $h['user_id'] ) : null;
			echo '<li><strong>' . esc_html( $h['action'] ) . '</strong> – ' . esc_html( $h['created_at'] ) . ' UTC' . ( $user ? ' – ' . esc_html( $user->display_name ) : '' ) . ( $h['reason'] ? '<br><em>' . esc_html( $h['reason'] ) . '</em>' : '' ) . '</li>';
		}
		echo '</ol></div></div>';
		if ( current_user_can( 'rc_manage_reviews' ) ) {
			echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Delete this review permanently?', 'review-captain' ) ) . '\');"><input type="hidden" name="action" value="review_captain_delete_review"><input type="hidden" name="id" value="' . (int) $id . '">';
			wp_nonce_field( 'rc_delete_review_' . $id );
			submit_button( __( 'Delete permanently', 'review-captain' ), 'delete', 'submit', false );
			echo '</form>';
		}
		echo '</div></div></div>';
	}

	private static function render_form( int $id ): void {
		$r = $id ? ReviewRepository::find( $id ) : null;
		if ( $id && ( ! $r || 'manual' !== $r['origin'] ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'This review cannot be edited.', 'review-captain' ) . '</h1></div>';
			return;
		}
		$v = wp_parse_args(
			(array) $r,
			array(
				'provider'     => 'google',
				'rating_value' => '',
				'rating_min'   => 1,
				'rating_max'   => 5,
				'title'        => '',
				'content'      => '',
				'author_name'  => '',
				'published_at' => '',
				'source_url'   => '',
				'attribution'  => '',
				'type'         => 'service',
				'language'     => '',
				'context_type' => '',
				'context_id'   => '',
				'status'       => 'approved',
			)
		);
		echo '<div class="wrap"><h1>' . esc_html( $id ? __( 'Edit external review', 'review-captain' ) : __( 'Add review from another platform', 'review-captain' ) ) . '</h1>';
		Menu::notices();
		echo '<p class="description">' . esc_html__( 'Only add reviews you are allowed to show. Enter the original rating and text without changes and link to the original.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_save_external"><input type="hidden" name="id" value="' . (int) $id . '">';
		wp_nonce_field( 'rc_save_external' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="rc-provider">' . esc_html__( 'Platform', 'review-captain' ) . ' *</label></th><td><select id="rc-provider" name="provider">';
		foreach ( ReviewService::EXTERNAL_PROVIDERS as $p ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $p ), selected( $v['provider'], $p, false ), esc_html( ReviewService::provider_label( $p ) ) );
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Original rating', 'review-captain' ) . ' *</th><td>';
		printf( '<label>%s <input type="number" step="0.1" name="rating_value" value="%s" class="small-text" required></label> ', esc_html__( 'Rating', 'review-captain' ), esc_attr( (string) $v['rating_value'] ) );
		printf( '<label>%s <input type="number" name="rating_min" value="%d" class="small-text" min="0"></label> ', esc_html__( 'Scale from', 'review-captain' ), (int) $v['rating_min'] );
		printf( '<label>%s <input type="number" name="rating_max" value="%d" class="small-text" min="2" max="100"></label>', esc_html__( 'to', 'review-captain' ), (int) $v['rating_max'] );
		echo '<p class="description">' . esc_html__( 'Use the scale of the platform (e.g. 1 to 5 stars).', 'review-captain' ) . '</p></td></tr>';
		self::row( 'title', __( 'Title', 'review-captain' ), (string) $v['title'] );
		echo '<tr><th><label for="rc-content">' . esc_html__( 'Permitted text', 'review-captain' ) . '</label></th><td><textarea id="rc-content" name="content" rows="5" class="large-text">' . esc_textarea( (string) $v['content'] ) . '</textarea></td></tr>';
		self::row( 'author_name', __( 'Reviewer name (as shown on the platform)', 'review-captain' ), (string) $v['author_name'] );
		echo '<tr><th><label for="rc-published_at">' . esc_html__( 'Date of the review', 'review-captain' ) . ' *</label></th><td><input type="date" id="rc-published_at" name="published_at" value="' . esc_attr( $v['published_at'] ? substr( (string) $v['published_at'], 0, 10 ) : '' ) . '" required></td></tr>';
		self::row( 'source_url', __( 'Link to review or profile (https)', 'review-captain' ) . ' *', (string) $v['source_url'], 'url' );
		self::row( 'attribution', __( 'Source note', 'review-captain' ) . ' *', (string) $v['attribution'], 'text', __( 'Shown next to the review, e.g. “Review on Google”.', 'review-captain' ) );
		echo '<tr><th><label for="rc-type">' . esc_html__( 'Type', 'review-captain' ) . '</label></th><td><select id="rc-type" name="type">';
		foreach ( ReviewRepository::TYPES as $t ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $t ), selected( $v['type'], $t, false ), esc_html( ReviewsListTable::type_label( $t ) ) );
		}
		echo '</select></td></tr>';
		self::row( 'context_type', __( 'Context type (optional, e.g. product)', 'review-captain' ), (string) $v['context_type'] );
		self::row( 'context_id', __( 'Context ID (optional)', 'review-captain' ), (string) $v['context_id'] );
		if ( ! $id ) {
			echo '<tr><th>' . esc_html__( 'Status', 'review-captain' ) . '</th><td><label><input type="radio" name="status" value="approved" checked> ' . esc_html__( 'Publish', 'review-captain' ) . '</label> <label><input type="radio" name="status" value="pending"> ' . esc_html__( 'Save as pending', 'review-captain' ) . '</label></td></tr>';
		} else {
			self::row( 'edit_reason', __( 'Reason for the change', 'review-captain' ), '' );
		}
		echo '</tbody></table>';
		submit_button( __( 'Save review', 'review-captain' ) );
		echo '</form></div>';
	}

	private static function row( string $name, string $label, string $value, string $type = 'text', string $help = '' ): void {
		printf(
			'<tr><th><label for="rc-%1$s">%2$s</label></th><td><input type="%3$s" id="rc-%1$s" name="%1$s" value="%4$s" class="regular-text">%5$s</td></tr>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value ),
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
		);
	}

	public static function handle_moderate(): void {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		Capabilities::check( 'rc_moderate_reviews', 'rc_moderate_' . $id );
		$to     = isset( $_REQUEST['to'] ) ? sanitize_key( wp_unslash( $_REQUEST['to'] ) ) : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : null;
		$result = ReviewService::moderate( $id, $to, $reason ? $reason : null );
		$back   = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . self::PAGE );
		$back   = remove_query_arg( array( 'rc_msg', 'rc_err' ), $back );
		wp_safe_redirect( is_wp_error( $result ) ? add_query_arg( 'rc_err', rawurlencode( $result->get_error_message() ), $back ) : add_query_arg( 'rc_msg', 'moderated', $back ) );
		exit;
	}

	/**
	 * Bulk actions, called on load-{page} before output.
	 */
	public static function handle_bulk(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['ids'] ) ) {
			return;
		}
		$action = isset( $_POST['action'] ) && '-1' !== $_POST['action'] ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( '' === $action && isset( $_POST['action2'] ) && '-1' !== $_POST['action2'] ) {
			$action = sanitize_key( wp_unslash( $_POST['action2'] ) );
		}
		if ( '' === $action ) {
			return;
		}
		Capabilities::check( 'rc_moderate_reviews', 'bulk-reviews' );
		$ids    = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) );
		$errors = 0;
		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				if ( current_user_can( 'rc_manage_reviews' ) ) {
					ReviewService::delete( $id );
				}
				continue;
			}
			if ( is_wp_error( ReviewService::moderate( $id, $action, __( 'Bulk action', 'review-captain' ) ) ) ) {
				++$errors;
			}
		}
		Menu::back( self::PAGE, 'delete' === $action ? 'deleted' : 'moderated', $errors ? __( 'Some reviews could not be changed (status change not allowed).', 'review-captain' ) : '' );
	}

	public static function handle_save_external(): void {
		Capabilities::check( 'rc_manage_reviews', 'rc_save_external' );
		$input = wp_unslash( $_POST );
		$id    = absint( $input['id'] ?? 0 );
		$res   = $id ? ReviewService::update_manual_external( $id, $input ) : ReviewService::create_manual_external( $input );
		if ( is_wp_error( $res ) ) {
			Menu::back( self::PAGE, '', implode( ' ', $res->get_error_messages() ), array( 'action' => $id ? 'edit' : 'add', 'id' => (string) $id ) );
		}
		Menu::back( self::PAGE, 'saved', '', array( 'action' => 'view', 'id' => (string) ( $id ? $id : $res ) ) );
	}

	public static function handle_delete(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		Capabilities::check( 'rc_manage_reviews', 'rc_delete_review_' . $id );
		ReviewService::delete( $id );
		Menu::back( self::PAGE, 'deleted' );
	}
}
