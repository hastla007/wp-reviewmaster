<?php
/**
 * Spam protection hook and built-in proof-of-work captcha (SET-RC-11).
 *
 * WordPress glue around ReviewCaptain\Spam\ProofOfWork\ProofOfWork – ported from Form Captain's
 * `FormCaptain\Security\PowCaptcha` (wp-formmaster commit ca81401) with the Review Captain prefix.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Security;

use ReviewCaptain\Plugin;
use ReviewCaptain\Spam\ProofOfWork\ProofOfWork;

/**
 * Provider `submissions.captcha_provider`: `none` (default) or `pow` (built-in, self-hosted: no
 * external request, no cookie, no personal data, no puzzle). External providers attach via the
 * filter `review_captain_spam_checks_v1` – only after legal review.
 *
 * Challenges are fetched by the browser from admin-ajax.php when the visitor starts using the form,
 * so they also work on fully cached pages. A challenge carries its own expiry and an HMAC
 * signature, so nothing is stored when it is issued. A solved challenge is accepted once: its hash
 * is kept in a transient until the challenge expires (same storage as the rate limits).
 */
final class PowCaptcha {

	public const PROVIDERS     = array( 'none', 'pow' );
	public const FIELD         = 'rc_pow';
	public const AJAX_ACTION   = 'review_captain_pow_challenge';
	public const SCRIPT_HANDLE = 'review-captain-pow';

	/**
	 * Public error code for reasons from external checks (the reason itself is not shown).
	 */
	public const EXTERNAL_CODE = 'spam_check';

	/**
	 * Transient prefix of used challenges.
	 */
	private const USED_PREFIX = 'review_captain_pow_';

	/**
	 * Configured provider (unknown values fall back to `none`).
	 */
	public static function provider(): string {
		$value = (string) Plugin::instance()->settings()->get( 'submissions', 'captcha_provider', 'none' );
		return in_array( $value, self::PROVIDERS, true ) ? $value : 'none';
	}

	/**
	 * Whether the built-in proof-of-work check is active.
	 */
	public static function enabled(): bool {
		return 'pow' === self::provider();
	}

	/**
	 * Effective difficulty (upper bound of the secret number).
	 */
	public static function max_number(): int {
		/**
		 * Difficulty of the built-in proof-of-work check: upper bound of the number the browser
		 * searches (on average half of it is tried). Clamped to 1000–10,000,000; default 100,000.
		 * Same meaning as Form Captain's `form_captain_pow_max_number_v1`.
		 *
		 * @param int $max_number Upper bound.
		 */
		return ProofOfWork::clamp_max_number( (int) apply_filters( 'review_captain_pow_max_number_v1', ProofOfWork::DEFAULT_MAX_NUMBER ) );
	}

	/**
	 * Engine with the site secret.
	 *
	 * @param callable|null $clock Clock (tests).
	 */
	public static function engine( ?callable $clock = null ): ProofOfWork {
		return new ProofOfWork( hash_hmac( 'sha256', 'review_captain_pow', wp_salt( 'nonce' ) ), self::max_number(), ProofOfWork::DEFAULT_TTL, $clock );
	}

	/**
	 * A new challenge for the browser (the secret number is never part of it).
	 *
	 * @return array<string, mixed>
	 */
	public static function challenge(): array {
		return self::engine()->create();
	}

	/**
	 * Handler for admin-ajax (logged-in and anonymous): returns a fresh, uncached challenge.
	 */
	public static function ajax_challenge(): void {
		nocache_headers();
		if ( ! self::enabled() ) {
			wp_send_json_error( array( 'code' => 'disabled' ), 404 );
		}
		wp_send_json_success( self::challenge() );
	}

	/**
	 * Single use: remember a solved challenge until it expires. Returns false when it was used.
	 *
	 * @param string $id      Challenge id (hash).
	 * @param int    $expires Expiry (Unix time).
	 */
	public static function consume( string $id, int $expires ): bool {
		$key = self::USED_PREFIX . substr( $id, 0, 40 );
		if ( false !== get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, max( 60, $expires - time() + 60 ) );
		return true;
	}

	/**
	 * Run the spam checks for a submission. Returns null when it passes, otherwise the reason
	 * (`pow_missing`, `pow_invalid`, `pow_replayed` or a reason from an external check).
	 *
	 * @param string               $form Form key: review, report or question.
	 * @param array<string, mixed> $post Unslashed POST data.
	 */
	public static function check( string $form, array $post ): ?string {
		$provider = self::provider();
		if ( 'pow' === $provider ) {
			$result = self::engine()->verify( (string) ( is_scalar( $post[ self::FIELD ] ?? null ) ? $post[ self::FIELD ] : '' ), array( self::class, 'consume' ) );
			if ( ProofOfWork::OK !== $result ) {
				return $result;
			}
		}
		/**
		 * Additional spam checks, e.g. an external captcha provider. Only after legal review
		 * („nur nach Rechtsprüfung“): an external provider usually transfers personal data.
		 * Return a list of reason codes; any reason rejects the submission with a neutral message
		 * and nothing is stored. The built-in proof-of-work check has already passed when this runs.
		 *
		 * @param list<string>         $reasons Reasons found so far (empty).
		 * @param array<string, mixed> $context Keys: form (review|report|question), provider (none|pow), post (unslashed POST data).
		 */
		$reasons = apply_filters(
			'review_captain_spam_checks_v1',
			array(),
			array(
				'form'     => $form,
				'provider' => $provider,
				'post'     => $post,
			)
		);
		if ( ! is_array( $reasons ) ) {
			return null;
		}
		foreach ( $reasons as $reason ) {
			$reason = is_string( $reason ) ? sanitize_key( $reason ) : '';
			if ( '' !== $reason ) {
				return $reason;
			}
		}
		return null;
	}

	/**
	 * Public error code for a reason (external reasons are not revealed to the visitor).
	 *
	 * @param string $reason Reason from check().
	 */
	public static function public_code( string $reason ): string {
		return in_array( $reason, array( ProofOfWork::MISSING, ProofOfWork::INVALID, ProofOfWork::REPLAYED ), true ) ? $reason : self::EXTERNAL_CODE;
	}

	/**
	 * Visitor messages for the public codes.
	 *
	 * @return array<string, string>
	 */
	public static function messages(): array {
		$neutral = __( 'Ihre Eingabe konnte nicht überprüft werden. Bitte laden Sie die Seite neu und senden Sie das Formular erneut.', 'review-captain' );
		return array(
			ProofOfWork::MISSING  => __( 'Das Formular konnte nicht überprüft werden. Bitte aktivieren Sie JavaScript und senden Sie das Formular erneut – die Prüfung läuft unbemerkt in Ihrem Browser, ohne Daten an Dritte.', 'review-captain' ),
			ProofOfWork::INVALID  => $neutral,
			ProofOfWork::REPLAYED => $neutral,
			self::EXTERNAL_CODE   => $neutral,
		);
	}

	/**
	 * Register the script (idempotent).
	 */
	public static function register_script(): void {
		wp_register_script( self::SCRIPT_HANDLE, REVIEW_CAPTAIN_URL . 'assets/js/proof-of-work.js', array(), REVIEW_CAPTAIN_VERSION, true );
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'reviewCaptainPow',
			array(
				'endpoint' => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX_ACTION,
				'i18n'     => array(
					'working' => __( 'Spam-Schutz: Prüfung läuft im Hintergrund …', 'review-captain' ),
					'ready'   => __( 'Spam-Schutz: bereit zum Absenden.', 'review-captain' ),
					'sending' => __( 'Spam-Schutz: Prüfung wird abgeschlossen, das Formular wird gleich gesendet …', 'review-captain' ),
					'failed'  => __( 'Spam-Schutz: Die Prüfung konnte nicht geladen werden. Bitte prüfen Sie Ihre Verbindung und senden Sie das Formular erneut.', 'review-captain' ),
				),
			)
		);
	}

	/**
	 * Markup for a form when the built-in check is active ('' otherwise): hidden field, status in
	 * an aria-live region and a notice for visitors without JavaScript. Enqueues the script.
	 *
	 * @param string $id Unique id prefix of the form.
	 */
	public static function field( string $id ): string {
		if ( ! self::enabled() ) {
			return '';
		}
		self::register_script();
		wp_enqueue_script( self::SCRIPT_HANDLE );
		$status = $id . '-pow-status';
		return '<div class="rc-pow" data-rc-pow="1">'
			. '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="" />'
			. '<p class="rc-pow__status rc-form__hint" id="' . esc_attr( $status ) . '" role="status" aria-live="polite"></p>'
			. '<noscript><p class="rc-pow__noscript rc-form__hint">' . esc_html__( 'Zum Absenden wird JavaScript benötigt: Ein unsichtbarer Spam-Schutz rechnet kurz in Ihrem Browser. Es werden keine Daten an Dritte übertragen und keine Cookies gesetzt.', 'review-captain' ) . '</p></noscript>'
			. '</div>';
	}
}
