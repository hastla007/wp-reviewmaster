<?php
/**
 * Product Q&A: public question form and output (RC-122).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Questions;

use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Frontend\Renderer;
use ReviewCaptain\Frontend\TemplateLoader;
use ReviewCaptain\Moderation\Notifier;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\PowCaptcha;
use ReviewCaptain\Security\RateLimiter;
use ReviewCaptain\Widgets\WidgetDefinition;

/**
 * Shortcode `[review_captain_qa v="1" context_type="product" context_id="123|current"]` and the
 * admin-post action `review_captain_question`.
 *
 * Requires `submissions.qa_enabled`. Same protection as the review form (nonce, honeypot, signed
 * timestamp and context, hashed rate limit). Output is plain HTML without structured data: no
 * QAPage/FAQPage markup is generated and nothing is passed to the SEO collector.
 */
class QuestionHandler {

	public const ACTION       = 'review_captain_question';
	public const NONCE_ACTION = 'review_captain_question';
	public const NONCE_FIELD  = '_rc_q_nonce';
	public const SHORTCODE    = 'review_captain_qa';

	public const MIN_LENGTH = 10;
	public const MAX_LENGTH = 1000;

	/**
	 * Instance counter.
	 */
	private int $instances = 0;

	/**
	 * Constructor.
	 *
	 * @param Plugin                  $plugin    Plugin.
	 * @param QuestionRepository|null $questions Repository.
	 */
	public function __construct( private Plugin $plugin, private ?QuestionRepository $questions = null ) {
		$this->questions = $questions ?? new QuestionRepository();
	}

	/**
	 * Repository.
	 */
	public function repository(): QuestionRepository {
		return $this->questions ?? new QuestionRepository();
	}

	/**
	 * admin-post handler.
	 */
	public function handle(): void {
		$result = $this->process( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().
		wp_safe_redirect( $result['redirect'], 303 );
		exit;
	}

	/**
	 * Process a question.
	 *
	 * @param array<string, mixed> $post Unslashed POST.
	 * @return array{ok: bool, errors: list<string>, question: ?array<string, mixed>, redirect: string}
	 */
	public function process( array $post ): array {
		$settings = $this->plugin->settings();
		$fallback = home_url( '/' );
		$return   = isset( $post['rc_return'] ) ? wp_validate_redirect( esc_url_raw( (string) $post['rc_return'] ), $fallback ) : $fallback;
		$return   = remove_query_arg( array( 'rc_question', 'rc_question_error' ), (string) strtok( (string) $return, '#' ) );
		$out      = static function ( bool $ok, array $errors = array(), ?array $question = null ) use ( $return ): array {
			return array(
				'ok'       => $ok,
				'errors'   => $errors,
				'question' => $question,
				'redirect' => $ok ? add_query_arg( 'rc_question', '1', $return ) . '#rc-qa' : add_query_arg( 'rc_question_error', implode( ',', $errors ), $return ) . '#rc-qa-errors',
			);
		};

		if ( ! $settings->get( 'submissions', 'qa_enabled', false ) ) {
			return $out( false, array( 'disabled' ) );
		}
		if ( ! wp_verify_nonce( (string) ( $post[ self::NONCE_FIELD ] ?? '' ), self::NONCE_ACTION ) ) {
			return $out( false, array( 'nonce' ) );
		}
		if ( $settings->get( 'submissions', 'honeypot', true ) && '' !== trim( (string) ( $post[ FormHandler::HONEYPOT_FIELD ] ?? '' ) ) ) {
			return $out( true );
		}
		$ts = FormHandler::verify_timestamp( (string) ( $post['rc_ts'] ?? '' ) );
		if ( null !== $ts ) {
			return $out( false, array( $ts ) );
		}
		$spam = PowCaptcha::check( 'question', $post );
		if ( null !== $spam ) {
			return $out( false, array( PowCaptcha::public_code( $spam ) ) );
		}
		$type = (string) ( $post['rc_context_type'] ?? '' );
		$cid  = (string) ( $post['rc_context_id'] ?? '' );
		if ( '' === $type || '' === $cid || ! FormHandler::verify_context( $type, $cid, (string) ( $post['rc_context_sig'] ?? '' ) ) ) {
			return $out( false, array( 'context' ) );
		}
		$limiter = new RateLimiter( 'question' );
		if ( $limiter->count() >= (int) $settings->get( 'submissions', 'rate_limit_per_hour', 5 ) ) {
			return $out( false, array( 'rate_limited' ) );
		}

		$question = sanitize_textarea_field( (string) ( $post['rc_question'] ?? '' ) );
		$name     = sanitize_text_field( (string) ( $post['rc_name'] ?? '' ) );
		$email    = sanitize_email( (string) ( $post['rc_email'] ?? '' ) );
		$errors   = array();
		$len      = mb_strlen( $question );
		if ( $len < self::MIN_LENGTH || $len > self::MAX_LENGTH ) {
			$errors[] = 'question';
		}
		if ( '' === $name || mb_strlen( $name ) > 100 ) {
			$errors[] = 'name';
		}
		if ( '' !== $email && ( ! is_email( $email ) || strlen( $email ) > 191 ) ) {
			$errors[] = 'email';
		}
		if ( $settings->get( 'submissions', 'require_consent', true ) && empty( $post['rc_consent'] ) ) {
			$errors[] = 'consent';
		}
		if ( $errors ) {
			return $out( false, $errors );
		}
		// Double click / resent POST (review P2): the same rendered form with the same content gets
		// the same ID, the UNIQUE uuid key stores it once.
		$key  = FormHandler::replay_key( (string) ( $post['rc_ts'] ?? '' ), $email, $type, $cid, 'question|' . $name . '|' . $question );
		$uuid = substr( $key, 0, 8 ) . '-' . substr( $key, 8, 4 ) . '-4' . substr( $key, 13, 3 ) . '-a' . substr( $key, 17, 3 ) . '-' . substr( $key, 20, 12 );
		$row  = $this->repository()->insert(
			array(
				'context_type' => $type,
				'context_id'   => $cid,
				'question'     => $question,
				'author_name'  => $name,
				'author_email' => '' !== $email ? $email : null,
				'uuid'         => $uuid,
			)
		);
		if ( null === $row ) {
			$existing = $this->repository()->find_by_uuid( $uuid );
			// Replay of a stored question: answer like the first request, store and notify nothing.
			return null !== $existing ? $out( true, array(), $existing ) : $out( false, array( 'save' ) );
		}
		$limiter->hit();
		$this->plugin->audit()->record( 'question.created', 'question', (int) $row['id'], array( 'status' => 'pending' ) );
		// SET-RC-09: hint + link only, never the question text or contact data.
		( new Notifier( $settings ) )->notify( Notifier::EVENT_QUESTION, array( 'moderation_url' => admin_url( 'admin.php?page=review-captain-questions' ) ) );
		return $out( true, array(), $row );
	}

	/**
	 * Messages for error codes.
	 *
	 * @return array<string, string>
	 */
	public static function error_messages(): array {
		return array(
			'disabled'     => __( 'Questions cannot be submitted at this time.', 'review-captain' ),
			'nonce'        => __( 'Your session has expired. Please reload the page.', 'review-captain' ),
			'too_fast'     => __( 'The form was submitted too quickly. Please try again.', 'review-captain' ),
			'expired'      => __( 'The form has expired. Please reload the page.', 'review-captain' ),
			'context'      => __( 'The form is invalid. Please reload the page.', 'review-captain' ),
			'rate_limited' => __( 'You have already submitted several questions. Please try again later.', 'review-captain' ),
			/* translators: 1: minimum length, 2: maximum length */
			'question'     => sprintf( __( 'Please write your question using %1$d to %2$d characters.', 'review-captain' ), self::MIN_LENGTH, self::MAX_LENGTH ),
			'name'         => __( 'Please enter your name (maximum 100 characters).', 'review-captain' ),
			'email'        => __( 'The email address is invalid.', 'review-captain' ),
			'consent'      => __( 'Please confirm your consent to publication.', 'review-captain' ),
			'save'         => __( 'Your question could not be saved. Please try again later.', 'review-captain' ),
		) + PowCaptcha::messages();
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 */
	public function shortcode( $atts ): string {
		$settings = $this->plugin->settings();
		if ( ! $settings->get( 'submissions', 'qa_enabled', false ) ) {
			return '';
		}
		$d = WidgetDefinition::from_atts( is_array( $atts ) ? $atts : array(), 'qa' );
		if ( ! $d->compatible || null === $d->context_type || null === $d->context_id ) {
			return '<!-- Review Captain: Q&A benötigt v="1", context_type und context_id. -->';
		}
		++$this->instances;
		$errors = array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( 1 === $this->instances && isset( $_GET['rc_question_error'] ) ) {
			$codes  = explode( ',', sanitize_text_field( wp_unslash( (string) $_GET['rc_question_error'] ) ) );
			$errors = array_values( array_intersect_key( self::error_messages(), array_flip( $codes ) ) );
		}
		$success = 1 === $this->instances && isset( $_GET['rc_question'] ) ? __( 'Thank you! Your question will be published after moderation.', 'review-captain' ) : '';
		// phpcs:enable
		$items = array();
		foreach ( $this->repository()->public_for_context( $d->context_type, $d->context_id, $d->limit > 0 ? max( $d->limit, 10 ) : 10 ) as $row ) {
			$items[] = array(
				'question' => (string) $row['question'],
				'author'   => (string) $row['author_name'],
				'answer'   => null !== $row['answer'] ? (string) $row['answer'] : '',
			);
		}
		if ( $settings->get( 'display', 'load_css', true ) ) {
			wp_enqueue_style( 'review-captain-frontend' );
		}
		return TemplateLoader::render(
			'questions.php',
			array(
				'id'              => 'rc-qa' . ( $this->instances > 1 ? '-' . $this->instances : '' ),
				'items'           => $items,
				'errors'          => $errors,
				'success'         => $success,
				'action'          => admin_url( 'admin-post.php' ),
				'hidden'          => array(
					'action'          => self::ACTION,
					'rc_ts'           => FormHandler::sign_timestamp( time() ),
					'rc_context_type' => $d->context_type,
					'rc_context_id'   => $d->context_id,
					'rc_context_sig'  => FormHandler::sign_context( $d->context_type, $d->context_id ),
					'rc_return'       => Renderer::current_url(),
				),
				'nonce_action'    => self::NONCE_ACTION,
				'nonce_name'      => self::NONCE_FIELD,
				'honeypot'        => (bool) $settings->get( 'submissions', 'honeypot', true ),
				'honeypot_name'   => FormHandler::HONEYPOT_FIELD,
				'require_consent' => (bool) $settings->get( 'submissions', 'require_consent', true ),
				'max_length'      => self::MAX_LENGTH,
				'pow'             => PowCaptcha::field(
					'rc-qa' . ( $this->instances > 1 ? '-' . $this->instances : '' ),
					PowCaptcha::scope(
						'question',
						array(
							'rc_context_type' => $d->context_type,
							'rc_context_id'   => $d->context_id,
						)
					)
				),
			)
		);
	}
}
