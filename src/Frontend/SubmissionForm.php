<?php
/**
 * Local review submission form (RC-039). Works without JS (classic POST); JS enhances via REST.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SubmissionForm {

	/**
	 * @param array<string,string> $atts context_type, context_id, type.
	 */
	public static function render( array $atts ): string {
		Assets::enqueue_frontend();
		wp_enqueue_script( 'review-captain-form' );
		wp_localize_script(
			'review-captain-form',
			'reviewCaptainForm',
			array(
				'endpoint' => esc_url_raw( rest_url( 'review-captain/v1/reviews' ) ),
				'nonceUrl' => esc_url_raw( rest_url( 'review-captain/v1/form-nonce' ) ),
				'thanks'   => __( 'Thank you! Your review will be published after moderation.', 'review-captain' ),
				'error'    => __( 'Your review could not be sent. Please check the fields and try again.', 'review-captain' ),
			)
		);

		$status  = isset( $_GET['rc_submitted'] ) ? sanitize_key( wp_unslash( $_GET['rc_submitted'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$consent = (string) Settings::get( 'consent_text' );
		$uid     = 'rc-form-' . wp_unique_id();
		$html    = '<div class="rc-form-wrap">';
		if ( 'ok' === $status ) {
			$html .= '<p class="rc-notice rc-notice--ok" role="status">' . esc_html__( 'Thank you! Your review will be published after moderation.', 'review-captain' ) . '</p>';
		} elseif ( 'error' === $status ) {
			$html .= '<p class="rc-notice rc-notice--error" role="alert">' . esc_html__( 'Your review could not be sent. Please check the fields and try again.', 'review-captain' ) . '</p>';
		}
		$html .= '<form class="rc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
		$html .= '<input type="hidden" name="action" value="review_captain_submit">';
		$html .= wp_nonce_field( 'rc_submit_review', '_rc_nonce', true, false );
		$html .= '<input type="hidden" name="context_type" value="' . esc_attr( $atts['context_type'] ?? '' ) . '">';
		$html .= '<input type="hidden" name="context_id" value="' . esc_attr( $atts['context_id'] ?? '' ) . '">';
		$html .= '<input type="hidden" name="type" value="' . esc_attr( $atts['type'] ?? 'general' ) . '">';
		$html .= '<input type="hidden" name="rc_started" value="' . esc_attr( (string) time() ) . '">';

		$html .= '<fieldset class="rc-form__rating"><legend>' . esc_html__( 'Your rating', 'review-captain' ) . ' <span aria-hidden="true">*</span></legend>';
		for ( $i = 5; $i >= 1; $i-- ) {
			/* translators: %d: number of stars */
			$label = sprintf( _n( '%d star', '%d stars', $i, 'review-captain' ), $i );
			$html .= '<input type="radio" id="' . esc_attr( "{$uid}-r{$i}" ) . '" name="rating" value="' . $i . '" required>';
			$html .= '<label for="' . esc_attr( "{$uid}-r{$i}" ) . '" title="' . esc_attr( $label ) . '"><span aria-hidden="true">★</span><span class="rc-sr">' . esc_html( $label ) . '</span></label>';
		}
		$html .= '</fieldset>';

		$html .= self::field( $uid, 'title', __( 'Title', 'review-captain' ), 'text', false, 255 );
		$html .= '<p class="rc-form__field"><label for="' . esc_attr( "{$uid}-content" ) . '">' . esc_html__( 'Your review', 'review-captain' ) . '</label>';
		$html .= '<textarea id="' . esc_attr( "{$uid}-content" ) . '" name="content" rows="5" maxlength="' . (int) Settings::get( 'max_text_length' ) . '"' . ( Settings::get( 'min_text_length' ) > 0 ? ' minlength="' . (int) Settings::get( 'min_text_length' ) . '" required' : '' ) . '></textarea></p>';
		$html .= self::field( $uid, 'author_name', __( 'Name (shown publicly)', 'review-captain' ), 'text', true, 191 );
		$html .= self::field( $uid, 'email', __( 'E-mail (not published)', 'review-captain' ), 'email', (bool) Settings::get( 'require_email' ), 191 );

		if ( Settings::get( 'honeypot_enabled' ) ) {
			$html .= '<p class="rc-hp" aria-hidden="true"><label for="' . esc_attr( "{$uid}-website" ) . '">Website</label><input type="text" id="' . esc_attr( "{$uid}-website" ) . '" name="rc_website" tabindex="-1" autocomplete="off"></p>';
		}
		if ( '' !== $consent ) {
			$html .= '<p class="rc-form__consent"><label><input type="checkbox" name="consent" value="1" required> ' . esc_html( $consent ) . '</label></p>';
		}
		$html .= '<p class="rc-form__submit"><button type="submit" class="rc-btn">' . esc_html__( 'Submit review', 'review-captain' ) . '</button></p>';
		$html .= '<p class="rc-form__live" aria-live="polite"></p>';
		$html .= '</form></div>';
		return $html;
	}

	private static function field( string $uid, string $name, string $label, string $type, bool $required, int $max ): string {
		$id = $uid . '-' . $name;
		return '<p class="rc-form__field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' ) . '</label>'
			. '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" maxlength="' . (int) $max . '"' . ( $required ? ' required' : '' ) . ( 'email' === $type ? ' autocomplete="email"' : '' ) . ( 'author_name' === $name ? ' autocomplete="name"' : '' ) . '></p>';
	}
}
