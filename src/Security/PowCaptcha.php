<?php
/**
 * Spam protection hook and built-in proof-of-work captcha (SET-RC-11).
 *
 * WordPress side of ReviewCaptain\Spam\ProofOfWork\ProofOfWork (a copy of Form Captain's core,
 * wp-formmaster ca81401); counterpart of Form Captain's `FormCaptain\Security\PowCaptcha`.
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
 * - Key: derived from wp_salt( 'nonce' ), never output.
 * - Scope: a challenge is bound to one form and its context (see scope()); a solution for one
 *   product's review form is not accepted by another product's or by the report form.
 * - Challenges are only handed out by the admin-ajax action, never in (cacheable) HTML.
 * - Single use: an option `review_captain_pow_used_<hash>` per solved challenge, written with
 *   add_option() (one INSERT on the unique option_name, so two parallel requests cannot both win),
 *   kept until expiry + 60 s and removed by the daily maintenance (purge_used()).
 * - Unlike Form Captain there is no no-JS fallback: Review Captain's forms are plain POSTs, so a
 *   fallback would let bots skip the check by not sending a solution.
 */
final class PowCaptcha {

	public const PROVIDER      = 'pow';
	public const PROVIDERS     = array( 'none', self::PROVIDER );
	public const FIELD         = '_rc_pow';
	public const AJAX_ACTION   = 'review_captain_pow_challenge';
	public const SCRIPT_HANDLE = 'review-captain-pow';
	public const FORM_HANDLE   = 'review-captain-pow-form';
	public const FORMS         = array( 'review', 'report', 'question' );

	public const REASON_MISSING  = 'pow_missing';
	public const REASON_INVALID  = 'pow_invalid';
	public const REASON_REPLAYED = 'pow_replayed';

	/**
	 * Public error code for reasons from external checks (the reason itself is not shown).
	 */
	public const EXTERNAL_CODE = 'spam_check';

	/**
	 * Option prefix of used challenges (inside the plugin prefix, so uninstall removes leftovers).
	 */
	public const USED_PREFIX = 'review_captain_pow_used_';

	/**
	 * Highest length of a scope (longer requests for a challenge are refused).
	 */
	private const MAX_SCOPE = 200;

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
		return self::PROVIDER === self::provider();
	}

	/**
	 * Effective difficulty (upper bound of the secret number).
	 */
	public static function max_number(): int {
		/**
		 * Difficulty of the built-in proof-of-work check (technical value): highest number the
		 * browser may have to try (on average half of it). Default 50 000, kept within
		 * 1 000–1 000 000. Same meaning as Form Captain's `form_captain_pow_max_number_v1`.
		 *
		 * @param int $max_number Upper bound.
		 */
		$max = (int) apply_filters( 'review_captain_pow_max_number_v1', ProofOfWork::DEFAULT_MAX_NUMBER );
		return max( ProofOfWork::MIN_MAX_NUMBER, min( ProofOfWork::MAX_MAX_NUMBER, $max ) );
	}

	/**
	 * Engine with the site key and the single-use store.
	 *
	 * @param callable|null $clock Clock (tests).
	 */
	public static function engine( ?callable $clock = null ): ProofOfWork {
		return new ProofOfWork(
			hash_hmac( 'sha256', 'review-captain/pow/v1', wp_salt( 'nonce' ) ),
			array( self::class, 'consume' ),
			self::max_number(),
			ProofOfWork::DEFAULT_TTL,
			$clock
		);
	}

	/**
	 * Scope of a form: form type plus its context. Used when rendering (from the hidden fields)
	 * and when checking (from the POST data), so both sides compute the same string.
	 *
	 * @param string               $form review, report or question.
	 * @param array<string, mixed> $data Hidden fields or POST data.
	 */
	public static function scope( string $form, array $data ): string {
		$value = static fn( string $key ): string => is_scalar( $data[ $key ] ?? null ) ? (string) $data[ $key ] : '';
		if ( 'report' === $form ) {
			return 'report|' . strtolower( $value( 'rc_review' ) );
		}
		return $form . '|' . $value( 'rc_context_type' ) . '|' . $value( 'rc_context_id' );
	}

	/**
	 * Whether a scope sent by the browser may receive a challenge.
	 *
	 * @param string $scope Scope.
	 */
	public static function valid_scope( string $scope ): bool {
		if ( '' === $scope || strlen( $scope ) > self::MAX_SCOPE || 1 === preg_match( '/[\x00-\x1f\x7f]/', $scope ) ) {
			return false;
		}
		return in_array( strstr( $scope, '|', true ), self::FORMS, true );
	}

	/**
	 * A new challenge for a scope (the secret number is never part of it).
	 *
	 * @param string $scope Scope from scope().
	 * @return array<string, mixed>
	 */
	public static function challenge( string $scope ): array {
		return self::engine()->create( $scope );
	}

	/**
	 * Handler for admin-ajax (logged-in and anonymous): a fresh, uncached challenge for the posted
	 * scope. No nonce: it would go stale on cached pages, and the challenge is signed anyway.
	 */
	public static function ajax_challenge(): void {
		nocache_headers();
		if ( ! self::enabled() ) {
			wp_send_json_error( array( 'code' => 'disabled' ), 404 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public, signed challenge; see above.
		$scope = isset( $_POST['scope'] ) && is_string( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : '';
		if ( ! self::valid_scope( $scope ) ) {
			wp_send_json_error( array( 'code' => 'scope' ), 400 );
		}
		wp_send_json_success( self::challenge( $scope ) );
	}

	/**
	 * Single-use store: true only the first time a challenge is presented.
	 *
	 * @param string $challenge Challenge hash.
	 * @param int    $expires   Expiry (Unix time).
	 */
	public static function consume( string $challenge, int $expires ): bool {
		$name = self::USED_PREFIX . substr( hash( 'sha256', 'review-captain/pow-used|' . $challenge ), 0, 40 );
		// Same value for the same challenge, so a parallel duplicate INSERT changes no row and fails.
		return (bool) add_option( $name, (string) ( $expires + 60 ), '', false );
	}

	/**
	 * Remove expired single-use entries (daily maintenance). Returns the number removed.
	 *
	 * @param int $limit Highest number per run.
	 */
	public static function purge_used( int $limit = 1000 ): int {
		global $wpdb;
		if ( ! isset( $wpdb->options ) || ! is_string( $wpdb->options ) ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- maintenance scan of own options.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT %d", $wpdb->esc_like( self::USED_PREFIX ) . '%', time(), $limit ) );
		$count = 0;
		foreach ( (array) $names as $name ) {
			if ( delete_option( (string) $name ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Run the spam checks for a submission. Returns null when it passes, otherwise the reason
	 * (`pow_missing`, `pow_invalid`, `pow_replayed` or a reason from an external check).
	 *
	 * @param string               $form Form type: review, report or question.
	 * @param array<string, mixed> $post Unslashed POST data.
	 */
	public static function check( string $form, array $post ): ?string {
		$provider = self::provider();
		if ( self::PROVIDER === $provider ) {
			$result = self::engine()->verify( $post[ self::FIELD ] ?? null, self::scope( $form, $post ) );
			if ( ProofOfWork::OK !== $result ) {
				return self::reason( $result );
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
	 * Spam reason for a result of ProofOfWork::verify() (same mapping as Form Captain).
	 *
	 * @param string $result Result constant.
	 */
	public static function reason( string $result ): string {
		if ( ProofOfWork::MISSING === $result ) {
			return self::REASON_MISSING;
		}
		return ProofOfWork::REPLAYED === $result ? self::REASON_REPLAYED : self::REASON_INVALID;
	}

	/**
	 * Public error code for a reason (external reasons are not revealed to the visitor).
	 *
	 * @param string $reason Reason from check().
	 */
	public static function public_code( string $reason ): string {
		return in_array( $reason, array( self::REASON_MISSING, self::REASON_INVALID, self::REASON_REPLAYED ), true ) ? $reason : self::EXTERNAL_CODE;
	}

	/**
	 * Visitor messages for the public codes.
	 *
	 * @return array<string, string>
	 */
	public static function messages(): array {
		$neutral = __( 'Your input could not be verified. Please reload the page and submit the form again.', 'review-captain' );
		return array(
			self::REASON_MISSING  => __( 'The form could not be verified. Please enable JavaScript and submit the form again – the check runs unnoticed in your browser, without sending data to third parties.', 'review-captain' ),
			self::REASON_INVALID  => $neutral,
			self::REASON_REPLAYED => $neutral,
			self::EXTERNAL_CODE   => $neutral,
		);
	}

	/**
	 * Register the solver and the form script (idempotent).
	 */
	public static function register_script(): void {
		wp_register_script( self::SCRIPT_HANDLE, REVIEW_CAPTAIN_URL . 'assets/js/proof-of-work.js', array(), REVIEW_CAPTAIN_VERSION, true );
		wp_register_script( self::FORM_HANDLE, REVIEW_CAPTAIN_URL . 'assets/js/pow-form.js', array( self::SCRIPT_HANDLE ), REVIEW_CAPTAIN_VERSION, true );
		wp_localize_script(
			self::FORM_HANDLE,
			'reviewCaptainPowForm',
			array(
				'endpoint' => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX_ACTION,
				'i18n'     => array(
					'working' => __( 'Spam protection: check running in the background …', 'review-captain' ),
					'ready'   => __( 'Spam protection: ready to submit.', 'review-captain' ),
					'sending' => __( 'Spam protection: finishing the check, the form will be sent shortly …', 'review-captain' ),
					'failed'  => __( 'Spam protection: the check could not be loaded. Please check your connection and submit the form again.', 'review-captain' ),
				),
			)
		);
	}

	/**
	 * Markup for a form when the built-in check is active ('' otherwise): hidden field, status in
	 * an aria-live region and a notice for visitors without JavaScript. Enqueues the scripts.
	 *
	 * @param string $id    Unique id prefix of the form.
	 * @param string $scope Scope from scope().
	 */
	public static function field( string $id, string $scope ): string {
		if ( ! self::enabled() ) {
			return '';
		}
		self::register_script();
		wp_enqueue_script( self::FORM_HANDLE );
		$status = $id . '-pow-status';
		return '<div class="rc-pow" data-rc-pow="1" data-rc-pow-scope="' . esc_attr( $scope ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="" />'
			. '<p class="rc-pow__status rc-form__hint" id="' . esc_attr( $status ) . '" role="status" aria-live="polite"></p>'
			. '<noscript><p class="rc-pow__noscript rc-form__hint">' . esc_html__( 'JavaScript is required to submit: an invisible spam protection computes briefly in your browser. No data is sent to third parties and no cookies are set.', 'review-captain' ) . '</p></noscript>'
			. '</div>';
	}
}
