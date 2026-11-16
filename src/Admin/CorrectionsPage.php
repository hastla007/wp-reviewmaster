<?php
/**
 * Admin page "Korrekturen" (RC-125A): open author corrections with old/new comparison.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Moderation\CorrectionService;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * List + approve/reject (admin-post review_captain_correction_decide).
 */
class CorrectionsPage {

	public const SLUG         = 'review-captain-corrections';
	public const ACTION       = 'review_captain_correction_decide';
	public const NONCE_ACTION = 'review_captain_correction_decide';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Decide one proposal. Returns the redirect URL (also used by tests).
	 *
	 * @param array<string, mixed> $post Unslashed POST.
	 */
	public function decide( array $post ): string {
		$base = admin_url( 'admin.php?page=' . self::SLUG );
		if ( ! current_user_can( Capabilities::MODERATE ) || ! wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::NONCE_ACTION ) ) {
			return add_query_arg( 'rc_notice', 'forbidden', $base );
		}
		$service = new CorrectionService( $this->plugin );
		$id      = (int) ( $post['revision'] ?? 0 );
		if ( 'approve' === ( $post['decision'] ?? '' ) ) {
			$res = $service->approve( $id );
			return add_query_arg( 'rc_notice', true === $res ? 'correction_approved' : 'correction_conflict', $base );
		}
		$ok = $service->reject( $id, (string) ( $post['reason'] ?? 'moderation' ) );
		return add_query_arg( 'rc_notice', $ok ? 'correction_rejected' : 'correction_conflict', $base );
	}

	/** admin-post handler. */
	public function handle(): void {
		wp_safe_redirect( $this->decide( wp_unslash( $_POST ) ), 303 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in decide().
		exit;
	}

	/** Render the page. */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MODERATE ) ) {
			return;
		}
		$service = new CorrectionService( $this->plugin );
		$notice  = isset( $_GET['rc_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['rc_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap"><h1>' . esc_html__( 'Korrekturen', 'review-captain' ) . '</h1>';
		echo '<p>' . esc_html__( 'Von Autorinnen und Autoren eingereichte Änderungen an bereits freigegebenen Bewertungen. Die bisherige Fassung bleibt sichtbar, bis du die neue freigibst.', 'review-captain' ) . '</p>';
		if ( '' !== $notice ) {
			$msg = match ( $notice ) {
				'correction_approved' => __( 'Korrektur freigegeben und veröffentlicht.', 'review-captain' ),
				'correction_rejected' => __( 'Korrektur abgelehnt. Die bisherige Fassung bleibt veröffentlicht.', 'review-captain' ),
				'correction_conflict' => __( 'Die Bewertung wurde inzwischen geändert oder die Korrektur ist nicht mehr offen. Bitte neu laden.', 'review-captain' ),
				default               => __( 'Dafür fehlt dir die Berechtigung.', 'review-captain' ),
			};
			echo '<div class="notice notice-info"><p>' . esc_html( $msg ) . '</p></div>';
		}
		$open = $service->open();
		if ( ! $open ) {
			echo '<p><em>' . esc_html__( 'Keine offenen Korrekturen.', 'review-captain' ) . '</em></p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Bisher', 'review-captain' ) . '</th><th>' . esc_html__( 'Neu', 'review-captain' ) . '</th><th>' . esc_html__( 'Aktion', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $open as $rev ) {
			$r = $this->plugin->reviews()->find( (int) $rev['review_id'] );
			if ( null === $r ) {
				continue;
			}
			$cell = static fn( $rating, string $title, string $body ): string => '<strong>' . esc_html( (string) $rating ) . ' / 5</strong> ' . esc_html( $title ) . '<br>' . nl2br( esc_html( $body ) );
			echo '<tr><td>' . $cell( $r->rating_value, $r->title, $r->body ) . '</td><td>' . $cell( $rev['rating_value'], (string) $rev['title'], (string) $rev['body'] ) . '</td><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $cell.
			foreach ( array( 'approve' => __( 'Freigeben', 'review-captain' ), 'reject' => __( 'Ablehnen', 'review-captain' ) ) as $decision => $label ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
					. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="revision" value="' . esc_attr( (string) $rev['id'] ) . '">'
					. '<input type="hidden" name="decision" value="' . esc_attr( $decision ) . '">' . wp_nonce_field( self::NONCE_ACTION, '_wpnonce', true, false ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					. '<button class="button' . ( 'approve' === $decision ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form> ';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
