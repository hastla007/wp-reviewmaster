<?php
/**
 * Text testimonials (Core). Media testimonials, trustmarks and awards are added by Pro (review.social_proof).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Testimonials\TestimonialRepository;

defined( 'ABSPATH' ) || exit;

final class TestimonialsPage {

	private const PAGE = 'review-captain-testimonials';

	public static function register_actions(): void {
		add_action( 'admin_post_review_captain_save_testimonial', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_review_captain_testimonial_revoke', array( self::class, 'handle_revoke' ) );
		add_action( 'admin_post_review_captain_testimonial_delete', array( self::class, 'handle_delete' ) );
	}

	public static function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$edit = isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'add', 'edit' ), true );
		// phpcs:enable
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Testimonials', 'review-captain' ) . '</h1>';
		if ( ! $edit ) {
			echo ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&action=add' ) ) . '">' . esc_html__( 'Add testimonial', 'review-captain' ) . '</a>';
		}
		echo '<hr class="wp-header-end">';
		Menu::notices();
		echo '<p class="description">' . esc_html__( 'Testimonials are curated statements of your customers. They are always labelled as testimonials and never shown as platform reviews. Publish only with documented permission.', 'review-captain' ) . '</p>';

		if ( $edit ) {
			$t = $id ? TestimonialRepository::find( $id ) : null;
			$v = wp_parse_args(
				(array) $t,
				array(
					'quote'             => '',
					'display_name'      => '',
					'display_name_mode' => 'full',
					'role_label'        => '',
					'context_type'      => '',
					'context_id'        => '',
					'status'            => 'draft',
					'valid_until'       => '',
					'consent'           => '',
				)
			);
			$consent = json_decode( (string) $v['consent'], true );
			$note    = is_array( $consent ) ? (string) ( $consent['text']['note'] ?? '' ) : '';
			echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_save_testimonial"><input type="hidden" name="id" value="' . (int) $id . '">';
			wp_nonce_field( 'rc_save_testimonial' );
			echo '<table class="form-table" role="presentation"><tbody>';
			echo '<tr><th><label for="rc-t-quote">' . esc_html__( 'Approved text', 'review-captain' ) . ' *</label></th><td><textarea id="rc-t-quote" name="quote" rows="5" class="large-text" required>' . esc_textarea( (string) $v['quote'] ) . '</textarea></td></tr>';
			printf( '<tr><th><label for="rc-t-name">%s</label></th><td><input type="text" id="rc-t-name" name="display_name" value="%s" class="regular-text"> <select name="display_name_mode" aria-label="%s">', esc_html__( 'Name', 'review-captain' ), esc_attr( (string) $v['display_name'] ), esc_attr__( 'Name display', 'review-captain' ) );
			$modes = array(
				'full'       => __( 'Full name', 'review-captain' ),
				'first_name' => __( 'First name only', 'review-captain' ),
				'initials'   => __( 'Initials', 'review-captain' ),
				'anonymous'  => __( 'Anonymous', 'review-captain' ),
			);
			foreach ( $modes as $k => $l ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $v['display_name_mode'], $k, false ), esc_html( $l ) );
			}
			echo '</select></td></tr>';
			printf( '<tr><th><label for="rc-t-role">%s</label></th><td><input type="text" id="rc-t-role" name="role_label" value="%s" class="regular-text"></td></tr>', esc_html__( 'Role / description', 'review-captain' ), esc_attr( (string) $v['role_label'] ) );
			printf( '<tr><th><label for="rc-t-ctx">%s</label></th><td><input type="text" id="rc-t-ctx" name="context_type" value="%s" placeholder="product"> <input type="text" name="context_id" value="%s" class="small-text" aria-label="%s"></td></tr>', esc_html__( 'Context', 'review-captain' ), esc_attr( (string) $v['context_type'] ), esc_attr( (string) $v['context_id'] ), esc_attr__( 'Context ID', 'review-captain' ) );
			printf( '<tr><th><label for="rc-t-consent">%s</label></th><td><input type="text" id="rc-t-consent" name="consent_note" value="%s" class="large-text"><p class="description">%s</p></td></tr>', esc_html__( 'Proof of permission', 'review-captain' ), esc_attr( $note ), esc_html__( 'How and when did the person agree (e.g. “signed form 2026-11-02, stored in CRM”)? Required for publishing.', 'review-captain' ) );
			printf( '<tr><th><label for="rc-t-valid">%s</label></th><td><input type="date" id="rc-t-valid" name="valid_until" value="%s"><p class="description">%s</p></td></tr>', esc_html__( 'Permission valid until', 'review-captain' ), esc_attr( $v['valid_until'] ? substr( (string) $v['valid_until'], 0, 10 ) : '' ), esc_html__( 'Optional. Expired testimonials are hidden automatically.', 'review-captain' ) );
			echo '<tr><th>' . esc_html__( 'Status', 'review-captain' ) . '</th><td><select name="status">';
			foreach ( array( 'draft' => __( 'Draft', 'review-captain' ), 'approved' => __( 'Published', 'review-captain' ), 'archived' => __( 'Archived', 'review-captain' ) ) as $k => $l ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $v['status'], $k, false ), esc_html( $l ) );
			}
			echo '</select></td></tr></tbody></table>';
			submit_button( __( 'Save testimonial', 'review-captain' ) );
			echo '</form>';
			if ( $id ) {
				echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '" class="rc-inline"><input type="hidden" name="action" value="review_captain_testimonial_revoke"><input type="hidden" name="id" value="' . (int) $id . '">';
				wp_nonce_field( 'rc_testimonial_revoke_' . $id );
				submit_button( __( 'Record withdrawal of permission', 'review-captain' ), 'secondary', 'submit', false );
				echo '</form> <form method="post" action="' . esc_url( Menu::post_url() ) . '" class="rc-inline" onsubmit="return confirm(\'' . esc_js( __( 'Delete permanently?', 'review-captain' ) ) . '\');"><input type="hidden" name="action" value="review_captain_testimonial_delete"><input type="hidden" name="id" value="' . (int) $id . '">';
				wp_nonce_field( 'rc_testimonial_delete_' . $id );
				submit_button( __( 'Delete', 'review-captain' ), 'delete', 'submit', false );
				echo '</form>';
			}
			echo '</div>';
			return;
		}

		$rows = TestimonialRepository::all();
		if ( ! $rows ) {
			echo '<div class="rc-empty"><p>' . esc_html__( 'No testimonials yet.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Text', 'review-captain' ) . '</th><th>' . esc_html__( 'Name', 'review-captain' ) . '</th><th>' . esc_html__( 'Status', 'review-captain' ) . '</th><th>' . esc_html__( 'Valid until', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $t ) {
			$status = $t['revoked_at'] ? __( 'Permission withdrawn', 'review-captain' ) : $t['status'];
			echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&action=edit&id=' . (int) $t['id'] ) ) . '">' . esc_html( wp_trim_words( (string) $t['quote'], 14, '…' ) ) . '</a>' . ( 'text' !== $t['kind'] ? ' <span class="rc-pill">' . esc_html( $t['kind'] ) . '</span>' : '' ) . '</td><td>' . esc_html( (string) $t['display_name'] ) . '</td><td>' . esc_html( $status ) . '</td><td>' . esc_html( $t['valid_until'] ? substr( (string) $t['valid_until'], 0, 10 ) : '—' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function handle_save(): void {
		Capabilities::check( 'rc_manage_testimonials', 'rc_save_testimonial' );
		$in  = wp_unslash( $_POST );
		$id  = absint( $in['id'] ?? 0 );
		$res = TestimonialRepository::save_text( $id, $in );
		if ( is_wp_error( $res ) ) {
			Menu::back( self::PAGE, '', $res->get_error_message(), array( 'action' => $id ? 'edit' : 'add', 'id' => (string) $id ) );
		}
		Menu::back( self::PAGE, 'saved', '', array( 'action' => 'edit', 'id' => (string) $res ) );
	}

	public static function handle_revoke(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		Capabilities::check( 'rc_manage_testimonials', 'rc_testimonial_revoke_' . $id );
		TestimonialRepository::revoke( $id );
		Menu::back( self::PAGE, 'saved' );
	}

	public static function handle_delete(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		Capabilities::check( 'rc_manage_testimonials', 'rc_testimonial_delete_' . $id );
		TestimonialRepository::delete( $id );
		Menu::back( self::PAGE, 'deleted' );
	}
}
