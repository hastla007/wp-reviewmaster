<?php
/**
 * Public endpoints and shortcode for author corrections (RC-125A).
 *
 * admin-post.php?action=review_captain_correction with op=request|submit|withdraw.
 * Shortcode [review_captain_correction]: e-mail form, or with ?rc_correct=<token> the edit form.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Moderation;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\RateLimiter;

/**
 * Handler.
 */
class CorrectionHandler {

	public const ACTION       = 'review_captain_correction';
	public const NONCE_ACTION = 'review_captain_correction';
	public const NONCE_FIELD  = '_rc_correction_nonce';
	public const SHORTCODE    = 'review_captain_correction';
	public const QUERY_TOKEN  = 'rc_correct';
	public const QUERY_RESULT = 'rc_corrected';

	private CorrectionService $service;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {
		$this->service = new CorrectionService( $plugin );
	}

	/** admin-post handler. */
	public function handle(): void {
		$result = $this->process( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().
		wp_safe_redirect( $result['redirect'], 303 );
		exit;
	}

	/**
	 * Process a request.
	 *
	 * @param array<string, mixed> $post Unslashed POST.
	 * @return array{ok: bool, result: string, redirect: string}
	 */
	public function process( array $post ): array {
		$return = self::return_url( $post );
		$out    = static fn( bool $ok, string $result ): array => array(
			'ok'       => $ok,
			'result'   => $result,
			'redirect' => add_query_arg( self::QUERY_RESULT, $result, $return ),
		);
		if ( ! $this->service->enabled() ) {
			return $out( false, 'disabled' );
		}
		if ( ! wp_verify_nonce( (string) ( $post[ self::NONCE_FIELD ] ?? '' ), self::NONCE_ACTION ) ) {
			return $out( false, 'nonce' );
		}
		$op = (string) ( $post['op'] ?? '' );
		if ( 'request' === $op ) {
			$limiter = new RateLimiter( 'correction' );
			if ( $limiter->count() >= 5 ) {
				return $out( false, 'rate_limited' );
			}
			$limiter->hit();
			foreach ( $this->service->issue_links( (string) ( $post['rc_email'] ?? '' ) ) as $id => $token ) {
				$this->mail_link( (string) ( $post['rc_email'] ?? '' ), (int) $id, add_query_arg( self::QUERY_TOKEN, $token, $return ) );
			}
			return $out( true, 'link_sent' ); // Same answer whether or not reviews exist (no enumeration).
		}
		$token = (string) ( $post['rc_token'] ?? '' );
		$used  = '' !== $token ? $this->service->used_result( $token ) : null;
		if ( null !== $used ) {
			// Double click or resent form: the first request already did the work.
			return $out( true, $used );
		}
		$review = '' !== $token ? $this->service->review_for_token( $token ) : $this->service->review_for_user( (int) ( $post['rc_review'] ?? 0 ) );
		if ( null === $review ) {
			return $out( false, 'invalid_link' );
		}
		$channel = '' !== $token ? 'link' : 'user';
		if ( 'withdraw' === $op ) {
			$ok = $this->service->withdraw( $review );
			if ( $ok && '' !== $token ) {
				$this->service->consume_token( $token, 'withdrawn' );
			}
			return $out( $ok, $ok ? 'withdrawn' : 'error' );
		}
		if ( 'submit' === $op ) {
			$res = $this->service->propose(
				$review,
				array(
					'rating_value' => $post['rc_rating'] ?? $review->rating_value,
					'title'        => $post['rc_title'] ?? $review->title,
					'body'         => $post['rc_body'] ?? $review->body,
				),
				$channel
			);
			if ( is_wp_error( $res ) ) {
				return $out( false, 'invalid_' . sanitize_key( (string) $res->get_error_message() ) );
			}
			if ( '' !== $token ) {
				$this->service->consume_token( $token, 'proposed' );
			}
			return $out( true, 'proposed' );
		}
		return $out( false, 'error' );
	}

	/**
	 * Shortcode output.
	 *
	 * @param array<string, mixed>|string $atts Attributes (unused).
	 */
	public function shortcode( $atts = array() ): string {
		if ( ! $this->service->enabled() ) {
			return '';
		}
		$token  = isset( $_GET[ self::QUERY_TOKEN ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_TOKEN ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token is the credential.
		$result = isset( $_GET[ self::QUERY_RESULT ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_RESULT ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$html   = '<div class="rc-correction">';
		if ( '' !== $result ) {
			$html .= '<p class="rc-form__notice" role="status">' . esc_html( self::message( $result ) ) . '</p>';
		}
		$review = '' !== $token ? $this->service->review_for_token( $token ) : null;
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$hidden = '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">' . wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false )
			. '<input type="hidden" name="rc_return" value="' . esc_attr( remove_query_arg( array( self::QUERY_TOKEN, self::QUERY_RESULT ) ) ) . '">';
		if ( null === $review ) {
			$html .= '<form method="post" action="' . $action . '">' . $hidden . '<input type="hidden" name="op" value="request">'
				. '<p><label for="rc-corr-email">' . esc_html__( 'E-Mail-Adresse, mit der Sie bewertet haben', 'review-captain' ) . '</label><br><input id="rc-corr-email" type="email" name="rc_email" required autocomplete="email"></p>'
				. '<p><button type="submit">' . esc_html__( 'Link zum Bearbeiten senden', 'review-captain' ) . '</button></p></form>';
		} else {
			$html .= '<form method="post" action="' . $action . '">' . $hidden . '<input type="hidden" name="rc_token" value="' . esc_attr( $token ) . '">'
				. '<p><label for="rc-corr-rating">' . esc_html__( 'Bewertung (1–5)', 'review-captain' ) . '</label><br><input id="rc-corr-rating" type="number" name="rc_rating" min="1" max="5" step="0.5" value="' . esc_attr( (string) $review->rating_value ) . '" required></p>'
				. ( $this->plugin->settings()->get( 'submissions', 'allow_title', true ) ? '<p><label for="rc-corr-title">' . esc_html__( 'Titel', 'review-captain' ) . '</label><br><input id="rc-corr-title" type="text" name="rc_title" maxlength="150" value="' . esc_attr( $review->title ) . '"></p>' : '' )
				. '<p><label for="rc-corr-body">' . esc_html__( 'Text', 'review-captain' ) . '</label><br><textarea id="rc-corr-body" name="rc_body" rows="6" required>' . esc_textarea( $review->body ) . '</textarea></p>'
				. '<p class="description">' . esc_html__( 'Die Änderung wird erst nach Prüfung veröffentlicht. Bis dahin bleibt die bisherige Fassung sichtbar.', 'review-captain' ) . '</p>'
				. '<p><button type="submit" name="op" value="submit">' . esc_html__( 'Korrektur einreichen', 'review-captain' ) . '</button> '
				. '<button type="submit" name="op" value="withdraw" class="rc-correction__withdraw">' . esc_html__( 'Bewertung zurückziehen', 'review-captain' ) . '</button></p></form>';
		}
		return $html . '</div>';
	}

	/**
	 * User-facing message for a result code.
	 *
	 * @param string $code Result code.
	 */
	public static function message( string $code ): string {
		return match ( $code ) {
			'link_sent'    => sprintf(
				/* translators: %s: validity, e.g. "eine Stunde" or "24 Stunden" */
				__( 'Wenn zu dieser Adresse veröffentlichte Bewertungen existieren, haben wir Ihnen einen Link geschickt. Er ist %s gültig.', 'review-captain' ),
				self::validity_text()
			),
			'proposed'     => __( 'Danke! Ihre Korrektur wird geprüft und danach veröffentlicht.', 'review-captain' ),
			'withdrawn'    => __( 'Ihre Bewertung wurde zurückgezogen und wird nicht mehr angezeigt.', 'review-captain' ),
			'invalid_link' => __( 'Der Link ist abgelaufen oder ungültig. Bitte fordern Sie einen neuen an.', 'review-captain' ),
			'rate_limited' => __( 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.', 'review-captain' ),
			'invalid_unchanged' => __( 'Es wurde nichts geändert.', 'review-captain' ),
			default        => __( 'Das hat nicht geklappt. Bitte prüfen Sie Ihre Angaben.', 'review-captain' ),
		};
	}

	/**
	 * Validity of the one-time link as text (submissions.correction_link_hours, SET-RC-21).
	 */
	private static function validity_text(): string {
		$hours = (int) ( CorrectionService::token_ttl() / HOUR_IN_SECONDS );
		/* translators: %s: number of hours */
		return 1 === $hours ? __( 'eine Stunde', 'review-captain' ) : sprintf( _n( '%s Stunde', '%s Stunden', $hours, 'review-captain' ), number_format_i18n( $hours ) );
	}

	/**
	 * Send the one-time link (no review text in the mail).
	 *
	 * @param string $email E-mail.
	 * @param int    $id    Review ID.
	 * @param string $url   Link.
	 */
	private function mail_link( string $email, int $id, string $url ): void {
		$site = Notifier::site_name( $this->plugin->settings() );
		/* translators: %s: site name */
		$subject = sprintf( __( 'Ihre Bewertung bei %s bearbeiten', 'review-captain' ), $site );
		/* translators: 1: site name, 2: link, 3: validity, e.g. "eine Stunde" or "24 Stunden" */
		$body = sprintf( __( "Hallo,\n\nüber diesen Link können Sie Ihre Bewertung bei %1\$s korrigieren oder zurückziehen. Der Link ist %3\$s gültig und funktioniert einmal:\n\n%2\$s\n\nWenn Sie das nicht angefordert haben, ignorieren Sie diese E-Mail.", 'review-captain' ), $site, $url, self::validity_text() );
		wp_mail( $email, $subject, $body );
		unset( $id );
	}

	/**
	 * Safe return URL.
	 *
	 * @param array<string, mixed> $post POST.
	 */
	private static function return_url( array $post ): string {
		$fallback = home_url( '/' );
		$url      = isset( $post['rc_return'] ) ? esc_url_raw( (string) $post['rc_return'] ) : '';
		$url      = '' !== $url ? wp_validate_redirect( $url, $fallback ) : $fallback;
		$url      = strtok( (string) $url, '#' );
		$url      = false !== $url ? $url : $fallback;
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			// The form posts the current request path (relative). The link goes out by e-mail, so it
			// needs scheme and host: those of the site, never of the request (review P2).
			$origin = (string) preg_replace( '#^(https?://[^/?\#]+).*$#i', '$1', $fallback );
			$url    = $origin . $url;
		}
		return remove_query_arg( array( self::QUERY_TOKEN, self::QUERY_RESULT ), $url );
	}
}
