<?php
/**
 * Feedback links with QR code and WhatsApp click-to-chat (RC-126).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Feedback\CampaignStore;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Only registered while `submissions.feedback_campaigns_enabled` is on. Capability
 * `review_captain_manage_settings`.
 */
final class FeedbackPage {

	public const SLUG = 'review-captain-feedback';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Page callback.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		$store = new CampaignStore();
		echo '<div class="wrap rc-admin">';
		echo View::header( __( 'Feedback links & QR codes', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo View::notice( 'info', __( 'QR codes are always labeled “Give feedback” and carry the note “Not a ticket – not an admission code”. Please do not print them next to admission or ticket codes. Platform profiles are subject to their own policies: ask all customers equally, no incentives.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		foreach ( $store->all() as $campaign ) {
			$link = $store->link( $campaign );
			$wa   = $store->whatsapp_link( $campaign );
			$svg  = $store->qr_svg( $campaign );
			echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html( (string) $campaign['label'] ) . '</h2></div><div class="inside">';
			if ( '' === $link ) {
				echo View::notice( 'warning', __( 'The target is no longer available (profile deleted or without an https link).', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<p><strong>' . esc_html__( 'Feedback link:', 'review-captain' ) . '</strong> <code>' . esc_html( $link ) . '</code></p>';
			}
			if ( '' !== $wa ) {
				echo '<p><strong>' . esc_html__( 'WhatsApp (click to chat):', 'review-captain' ) . '</strong> <code>' . esc_html( $wa ) . '</code></p>';
			}
			if ( '' !== $svg ) {
				echo '<div class="rc-admin-qr" style="max-width:240px">' . $svg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated SVG, all text escaped in CampaignStore::qr_svg().
				echo '<p><a class="button" href="' . esc_url( View::action_url( 'feedback_qr', array( 'id' => (string) $campaign['id'] ) ) ) . '">' . esc_html__( 'Download QR code as SVG', 'review-captain' ) . '</a></p>';
			} elseif ( '' !== $link ) {
				echo '<p class="description">' . esc_html__( 'The link is too long for the built-in QR code generator. Please use the link directly or choose a shorter target page.', 'review-captain' ) . '</p>';
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . View::form_fields( 'delete_campaign' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $campaign['id'] ) . '" /><button type="submit" class="button button-link-delete">' . esc_html__( 'Delete', 'review-captain' ) . '</button></form>';
			echo '</div></div>';
		}

		echo '<h2>' . esc_html__( 'Create new feedback link', 'review-captain' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . View::form_fields( 'save_campaign' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="rc-c-label">' . esc_html__( 'Label', 'review-captain' ) . '</label></th><td><input type="text" id="rc-c-label" name="campaign[label]" class="regular-text" maxlength="80" required /></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Target', 'review-captain' ) . '</th><td><fieldset><label><input type="radio" name="campaign[target]" value="page" checked /> ' . esc_html__( 'Page on this website with review form', 'review-captain' ) . '</label><br />';
		echo '<label for="rc-c-url" class="screen-reader-text">' . esc_html__( 'Target page', 'review-captain' ) . '</label><input type="url" id="rc-c-url" name="campaign[target_url]" class="regular-text" placeholder="' . esc_attr( home_url( '/bewertung/' ) ) . '" /><br />';
		echo '<label><input type="radio" name="campaign[target]" value="profile" /> ' . esc_html__( 'Review profile (sources & profiles)', 'review-captain' ) . '</label> ';
		echo '<select name="campaign[profile_id]" aria-label="' . esc_attr__( 'Profile', 'review-captain' ) . '"><option value="">' . esc_html__( '– Select profile –', 'review-captain' ) . '</option>';
		foreach ( ( new Profiles() )->all() as $profile ) {
			echo '<option value="' . esc_attr( (string) $profile['id'] ) . '">' . esc_html( (string) $profile['label'] ) . '</option>';
		}
		echo '</select></fieldset></td></tr>';
		echo '<tr><th scope="row"><label for="rc-c-wa">' . esc_html__( 'WhatsApp number (optional)', 'review-captain' ) . '</label></th><td><input type="text" id="rc-c-wa" name="campaign[whatsapp_number]" class="regular-text" inputmode="numeric" placeholder="4930123456" aria-describedby="rc-c-wa-desc" /><p class="description" id="rc-c-wa-desc">' . esc_html__( 'International format without +, spaces, or leading 0. Only generates an official wa.me link; no messages are sent.', 'review-captain' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="rc-c-wa-text">' . esc_html__( 'Prefilled text (optional)', 'review-captain' ) . '</label></th><td><textarea id="rc-c-wa-text" name="campaign[whatsapp_text]" rows="2" class="large-text" maxlength="300"></textarea></td></tr>';
		echo '</tbody></table><p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Create', 'review-captain' ) . '</button></p></form></div>';
	}

	/**
	 * Handler: create (admin-post: review_captain_save_campaign).
	 */
	public function handle_save(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SETTINGS, 'save_campaign' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$input             = isset( $_POST['campaign'] ) && is_array( $_POST['campaign'] ) ? wp_unslash( $_POST['campaign'] ) : array();
		[ $saved, $errors ] = ( new CampaignStore() )->save( $input );
		if ( null === $saved ) {
			View::redirect( Notices::url( View::page_url( self::SLUG ), 'campaign_invalid', $errors ) );
			return;
		}
		$this->plugin->audit()->record( 'feedback.campaign_created', 'campaign', (string) $saved['id'], array() );
		View::redirect( Notices::url( View::page_url( self::SLUG ), 'campaign_saved' ) );
	}

	/**
	 * Handler: delete (admin-post: review_captain_delete_campaign).
	 */
	public function handle_delete(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SETTINGS, 'delete_campaign' );
		$id = sanitize_key( wp_unslash( (string) ( $_POST['id'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		( new CampaignStore() )->delete( $id );
		View::redirect( Notices::url( View::page_url( self::SLUG ), 'campaign_deleted' ) );
	}

	/**
	 * Handler: SVG download (admin-post: review_captain_feedback_qr, GET with nonce).
	 */
	public function handle_qr(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SETTINGS, 'feedback_qr' );
		$id       = sanitize_key( wp_unslash( (string) ( $_GET['id'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$store    = new CampaignStore();
		$campaign = $store->get( $id );
		$svg      = null !== $campaign ? $store->qr_svg( $campaign ) : '';
		if ( '' === $svg ) {
			wp_die( esc_html__( 'QR code not available.', 'review-captain' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: image/svg+xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="feedback-' . $id . '.svg"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated SVG.
		exit;
	}
}
