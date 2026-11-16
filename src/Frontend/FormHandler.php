<?php
/**
 * Local review submission (RC-039, SET-013).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Plugin;
use ReviewCaptain\Widgets\WidgetDefinition;

/**
 * Handles POSTs to admin-post.php?action=review_captain_submit (logged-in and anonymous).
 *
 * Protection: nonce, honeypot, signed render timestamp (min. 3 s), rate limit per IP hash
 * (wp_hash; the plain IP is never stored), signed context, strict field validation.
 * Result is communicated via redirect back (rc_submitted / rc_error).
 */
class FormHandler {

	public const ACTION         = 'review_captain_submit';
	public const NONCE_ACTION   = 'review_captain_submit';
	public const NONCE_FIELD    = '_rc_nonce';
	public const HONEYPOT_FIELD = 'rc_website';

	public const MIN_SECONDS = 3;
	public const MAX_AGE     = DAY_IN_SECONDS;

	/**
	 * Draft lifetime (keeps entered values after a validation error; no e-mail stored).
	 */
	public const DRAFT_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Plugin.
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * admin-post handler: process and redirect.
	 */
	public function handle(): void {
		$result = $this->process( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().
		$this->redirect( $result['redirect'] );
	}

	/**
	 * Process a submission. Returns the outcome and redirect target (no output, no exit).
	 *
	 * @param array<string, mixed> $post Unslashed POST data.
	 * @return array{ok: bool, errors: list<string>, review: ?Review, redirect: string}
	 */
	public function process( array $post ): array {
		$settings = $this->plugin->settings();
		$return   = $this->return_url( $post );
		$form     = isset( $post['rc_form'] ) && ctype_digit( (string) $post['rc_form'] ) ? (string) $post['rc_form'] : '1';

		$fail = function ( array $errors, array $draft = array() ) use ( $return, $form ): array {
			$args = array(
				'rc_error' => implode( ',', $errors ),
				'rc_form'  => $form,
			);
			if ( $draft ) {
				$this->rate_hit( 'fails' );
				$token = strtolower( wp_generate_password( 20, false, false ) );
				$token = preg_replace( '/[^a-z0-9]/', '', $token );
				set_transient( 'review_captain_draft_' . $token, $draft, self::DRAFT_TTL );
				$args['rc_draft'] = $token;
			}
			return array(
				'ok'       => false,
				'errors'   => $errors,
				'review'   => null,
				'redirect' => add_query_arg( $args, $return ) . '#rc-form-' . $form,
			);
		};

		if ( ! $settings->get( 'submissions', 'enabled', true ) ) {
			return $fail( array( 'disabled' ) );
		}
		if ( ! wp_verify_nonce( (string) ( $post[ self::NONCE_FIELD ] ?? '' ), self::NONCE_ACTION ) ) {
			return $fail( array( 'nonce' ) );
		}

		$success = function ( ?Review $review, bool $approved ) use ( $return, $form ): array {
			return array(
				'ok'       => true,
				'errors'   => array(),
				'review'   => $review,
				'redirect' => add_query_arg(
					array(
						'rc_submitted' => $approved ? '2' : '1',
						'rc_form'      => $form,
					),
					$return
				) . '#rc-form-' . $form,
			);
		};

		// Honeypot: silently pretend success, store nothing.
		if ( $settings->get( 'submissions', 'honeypot', true ) && '' !== trim( (string) ( $post[ self::HONEYPOT_FIELD ] ?? '' ) ) ) {
			return $success( null, false );
		}

		$ts_error = self::verify_timestamp( (string) ( $post['rc_ts'] ?? '' ) );
		if ( null !== $ts_error ) {
			return $fail( array( $ts_error ) );
		}

		$type = (string) ( $post['rc_context_type'] ?? '' );
		$cid  = (string) ( $post['rc_context_id'] ?? '' );
		if ( ! self::verify_context( $type, $cid, (string) ( $post['rc_context_sig'] ?? '' ) ) ) {
			return $fail( array( 'context' ) );
		}

		// Accepted submissions count fully; failed validations (which store a short-lived
		// draft) are limited to three times the hourly limit.
		$limit = (int) $settings->get( 'submissions', 'rate_limit_per_hour', 5 );
		if ( $this->rate_count() >= $limit || $this->rate_count( 'fails' ) >= 3 * $limit ) {
			return $fail( array( 'rate_limited' ) );
		}

		// Field validation.
		$errors  = array();
		$rating  = (string) ( $post['rc_rating'] ?? '' );
		$title   = $settings->get( 'submissions', 'allow_title', true ) ? sanitize_text_field( (string) ( $post['rc_title'] ?? '' ) ) : '';
		$body    = sanitize_textarea_field( (string) ( $post['rc_body'] ?? '' ) );
		$name    = sanitize_text_field( (string) ( $post['rc_name'] ?? '' ) );
		$email   = sanitize_email( (string) ( $post['rc_email'] ?? '' ) );
		$consent = ! empty( $post['rc_consent'] );
		$min     = (int) $settings->get( 'submissions', 'min_text_length', 20 );
		$max     = (int) $settings->get( 'submissions', 'max_text_length', 5000 );
		$len     = mb_strlen( $body );

		if ( ! in_array( $rating, array( '1', '2', '3', '4', '5' ), true ) ) {
			$errors[] = 'rating';
		}
		if ( mb_strlen( $title ) > 150 ) {
			$errors[] = 'title_long';
		}
		if ( $len < max( 1, $min ) ) {
			$errors[] = 'body_short';
		} elseif ( $len > $max ) {
			$errors[] = 'body_long';
		}
		if ( '' === $name || mb_strlen( $name ) > 100 ) {
			$errors[] = 'name';
		}
		if ( '' === $email || ! is_email( $email ) || strlen( $email ) > 191 ) {
			$errors[] = 'email';
		}
		if ( $settings->get( 'submissions', 'require_consent', true ) && ! $consent ) {
			$errors[] = 'consent';
		}
		if ( $errors ) {
			return $fail(
				$errors,
				array(
					'rating' => in_array( $rating, array( '1', '2', '3', '4', '5' ), true ) ? $rating : '',
					'title'  => mb_substr( $title, 0, 150 ),
					'body'   => mb_substr( $body, 0, $max ),
					'name'   => mb_substr( $name, 0, 100 ),
				)
			);
		}

		$now                  = gmdate( 'Y-m-d H:i:s' );
		$review               = new Review();
		$review->kind         = Kind::REVIEW;
		$review->origin       = Origin::LOCAL_SUBMISSION;
		$review->platform     = Platform::LOCAL;
		$review->verification = Verification::UNVERIFIED;
		$review->status       = 'approved' === $settings->get( 'general', 'new_review_status', 'pending' ) ? ReviewStatus::APPROVED : ReviewStatus::PENDING;
		$review->rating_value = (float) $rating;
		$review->title        = $title;
		$review->body         = $body;
		$review->author_name  = $name;
		$review->author_email = $email;
		$review->language     = substr( (string) determine_locale(), 0, 20 );
		$review->consent_publish = $consent;
		$review->consent_at      = $consent ? $now : null;
		$review->author_user_id  = is_user_logged_in() ? (int) get_current_user_id() : null;
		$review->context_type    = '' !== $type ? $type : null;
		$review->context_id      = '' !== $type && '' !== $cid ? $cid : null;

		try {
			$review = $this->plugin->reviews()->insert( $review );
		} catch ( \Throwable $e ) {
			return $fail( array( 'save' ) );
		}

		$this->rate_hit();
		if ( $settings->get( 'submissions', 'notify_admin', true ) ) {
			$this->notify_admin( $review );
		}
		return $success( $review, ReviewStatus::APPROVED === $review->status );
	}

	/**
	 * Messages/draft for a rendered form instance (from the redirect query).
	 *
	 * @param int $number Form instance number.
	 * @return array{success: string, errors: list<string>, draft: array<string, string>}
	 */
	public static function request_state( int $number ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$state = array(
			'success' => '',
			'errors'  => array(),
			'draft'   => array(),
		);
		$form  = isset( $_GET['rc_form'] ) ? sanitize_key( wp_unslash( (string) $_GET['rc_form'] ) ) : '1';
		if ( (string) $number !== $form ) {
			return $state;
		}
		if ( isset( $_GET['rc_submitted'] ) ) {
			$state['success'] = '2' === $_GET['rc_submitted']
				? __( 'Danke! Ihre Bewertung ist jetzt sichtbar.', 'review-captain' )
				: __( 'Danke! Ihre Bewertung wird nach Prüfung veröffentlicht.', 'review-captain' );
		}
		if ( isset( $_GET['rc_error'] ) ) {
			$codes           = explode( ',', sanitize_text_field( wp_unslash( (string) $_GET['rc_error'] ) ) );
			$state['errors'] = array_values( array_intersect( $codes, array_keys( self::error_messages() ) ) );
		}
		if ( isset( $_GET['rc_draft'] ) ) {
			$token = preg_replace( '/[^a-z0-9]/', '', (string) wp_unslash( $_GET['rc_draft'] ) );
			$draft = '' !== $token ? get_transient( 'review_captain_draft_' . $token ) : false;
			if ( is_array( $draft ) ) {
				$state['draft'] = array_map( 'strval', array_intersect_key( $draft, array_flip( array( 'rating', 'title', 'body', 'name' ) ) ) );
			}
		}
		// phpcs:enable
		return $state;
	}

	/**
	 * All error messages.
	 *
	 * @return array<string, string>
	 */
	public static function error_messages(): array {
		return array(
			'disabled'     => __( 'Bewertungen können derzeit nicht abgegeben werden.', 'review-captain' ),
			'nonce'        => __( 'Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und senden Sie das Formular erneut.', 'review-captain' ),
			'too_fast'     => __( 'Das Formular wurde zu schnell abgeschickt. Bitte versuchen Sie es erneut.', 'review-captain' ),
			'expired'      => __( 'Das Formular ist abgelaufen. Bitte laden Sie die Seite neu.', 'review-captain' ),
			'context'      => __( 'Das Formular ist ungültig. Bitte laden Sie die Seite neu.', 'review-captain' ),
			'rate_limited' => __( 'Sie haben bereits mehrere Bewertungen gesendet. Bitte versuchen Sie es später erneut.', 'review-captain' ),
			'rating'       => __( 'Bitte wählen Sie eine Bewertung von 1 bis 5 Sternen.', 'review-captain' ),
			'title_long'   => __( 'Der Titel ist zu lang (höchstens 150 Zeichen).', 'review-captain' ),
			'body_short'   => __( 'Der Bewertungstext ist zu kurz.', 'review-captain' ),
			'body_long'    => __( 'Der Bewertungstext ist zu lang.', 'review-captain' ),
			'name'         => __( 'Bitte geben Sie Ihren Namen an (höchstens 100 Zeichen).', 'review-captain' ),
			'email'        => __( 'Bitte geben Sie eine gültige E-Mail-Adresse an.', 'review-captain' ),
			'consent'      => __( 'Bitte bestätigen Sie die Einwilligung zur Veröffentlichung.', 'review-captain' ),
			'save'         => __( 'Ihre Bewertung konnte nicht gespeichert werden. Bitte versuchen Sie es später erneut.', 'review-captain' ),
		);
	}

	/**
	 * Message for an error code.
	 *
	 * @param string $code Code.
	 */
	public static function error_message( string $code ): string {
		return self::error_messages()[ $code ] ?? '';
	}

	/**
	 * Signed render timestamp "ts:sig".
	 *
	 * @param int $ts Unix time.
	 */
	public static function sign_timestamp( int $ts ): string {
		return $ts . ':' . wp_hash( 'rc_ts|' . $ts, 'nonce' );
	}

	/**
	 * Verify timestamp. Returns error code or null.
	 *
	 * @param string $value Value.
	 */
	public static function verify_timestamp( string $value ): ?string {
		if ( ! preg_match( '/^(\d{9,11}):([a-f0-9]{32,64})$/', $value, $m ) || ! hash_equals( wp_hash( 'rc_ts|' . $m[1], 'nonce' ), $m[2] ) ) {
			return 'expired';
		}
		$age = time() - (int) $m[1];
		if ( $age < self::MIN_SECONDS ) {
			return 'too_fast';
		}
		return $age > self::MAX_AGE ? 'expired' : null;
	}

	/**
	 * Signature for the form context (prevents visitors from choosing arbitrary contexts).
	 *
	 * @param string $type Context type.
	 * @param string $id   Context ID.
	 */
	public static function sign_context( string $type, string $id ): string {
		return wp_hash( 'rc_ctx|' . $type . '|' . $id, 'nonce' );
	}

	/**
	 * Verify context: whitelist + signature.
	 *
	 * @param string $type Type.
	 * @param string $id   ID.
	 * @param string $sig  Signature.
	 */
	public static function verify_context( string $type, string $id, string $sig ): bool {
		if ( '' !== $type && ! in_array( $type, WidgetDefinition::CONTEXT_TYPES, true ) ) {
			return false;
		}
		if ( '' !== $id && ! preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $id ) ) {
			return false;
		}
		if ( '' === $type && '' !== $id ) {
			return false;
		}
		return '' !== $sig && hash_equals( self::sign_context( $type, $id ), $sig );
	}

	/**
	 * Rate-limit key from the hashed IP (never the plain IP).
	 */
	public static function rate_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		return 'review_captain_rl_' . substr( wp_hash( 'rc_ip|' . $ip, 'nonce' ), 0, 24 );
	}

	/**
	 * Counter value in the current window.
	 *
	 * @param string $counter count|fails.
	 */
	private function rate_count( string $counter = 'count' ): int {
		$v = get_transient( self::rate_key() );
		return is_array( $v ) && isset( $v[ $counter ], $v['start'] ) && (int) $v['start'] > time() - HOUR_IN_SECONDS ? (int) $v[ $counter ] : 0;
	}

	/**
	 * Increment a counter (fixed one-hour window).
	 *
	 * @param string $counter count|fails.
	 */
	private function rate_hit( string $counter = 'count' ): void {
		$key = self::rate_key();
		$v   = get_transient( $key );
		if ( ! is_array( $v ) || ! isset( $v['start'] ) || (int) $v['start'] <= time() - HOUR_IN_SECONDS ) {
			$v = array(
				'count' => 0,
				'fails' => 0,
				'start' => time(),
			);
		}
		$v[ $counter ] = (int) ( $v[ $counter ] ?? 0 ) + 1;
		set_transient( $key, $v, max( 60, HOUR_IN_SECONDS - ( time() - (int) $v['start'] ) ) );
	}

	/**
	 * Notify the admin – no review text, no e-mail address, only a hint and a link.
	 *
	 * @param Review $review Review.
	 */
	private function notify_admin( Review $review ): void {
		$to = (string) get_option( 'admin_email', '' );
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}
		$site = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
		/**
		 * Filter the moderation URL used in notification mails.
		 *
		 * @param string $url    URL.
		 * @param Review $review Review.
		 */
		$url = (string) apply_filters( 'review_captain_moderation_url', admin_url( 'admin.php?page=review-captain' ), $review );
		wp_mail(
			$to,
			/* translators: %s: site name */
			sprintf( __( '[%s] Neue Bewertung eingegangen', 'review-captain' ), $site ),
			ReviewStatus::APPROVED === $review->status
				/* translators: %s: moderation URL */
				? sprintf( __( "Auf Ihrer Website wurde eine neue Bewertung abgegeben und automatisch freigegeben.\n\nZur Übersicht: %s", 'review-captain' ), $url )
				/* translators: %s: moderation URL */
				: sprintf( __( "Auf Ihrer Website wurde eine neue Bewertung abgegeben. Sie wartet auf Moderation.\n\nJetzt prüfen: %s", 'review-captain' ), $url )
		);
	}

	/**
	 * Validated return URL without previous rc_* parameters.
	 *
	 * @param array<string, mixed> $post POST.
	 */
	private function return_url( array $post ): string {
		$fallback = home_url( '/' );
		$url      = isset( $post['rc_return'] ) ? esc_url_raw( (string) $post['rc_return'] ) : '';
		$url      = '' !== $url ? wp_validate_redirect( $url, $fallback ) : $fallback;
		$url      = strtok( (string) $url, '#' );
		return remove_query_arg( array( 'rc_submitted', 'rc_error', 'rc_form', 'rc_draft' ), false !== $url ? $url : $fallback );
	}

	/**
	 * Redirect and stop.
	 *
	 * @param string $url URL.
	 */
	protected function redirect( string $url ): void {
		wp_safe_redirect( $url, 303 );
		exit;
	}
}
