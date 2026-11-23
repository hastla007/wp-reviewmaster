<?php
/**
 * SET-RC-11: spam protection hook and built-in proof-of-work captcha.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Security;

use ReviewCaptain\Moderation\ReportHandler;
use ReviewCaptain\Plugin;
use ReviewCaptain\Questions\QuestionHandler;
use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Security\PowCaptcha;
use ReviewCaptain\Spam\ProofOfWork\ProofOfWork;
use ReviewCaptain\Tests\Unit\M7\M7TestCase;

final class PowCaptchaTest extends M7TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Smallest difficulty keeps the brute-force solve in the tests fast.
		add_filter( 'review_captain_pow_max_number_v1', static fn(): int => 1000 );
	}

	/**
	 * A valid, solved payload from the site engine.
	 */
	private function solved(): string {
		$challenge = PowCaptcha::challenge();
		$number    = ProofOfWork::solve( $challenge );
		$this->assertNotNull( $number );
		return ProofOfWork::encode_solution( $challenge, (int) $number );
	}

	private function pow_on(): void {
		$this->enable( array( 'submissions' => array( 'captcha_provider' => 'pow' ) ) );
	}

	// ---- Core (dependency-free) ----

	public function test_challenge_is_created_signed_and_verifies_once(): void {
		$engine    = new ProofOfWork( 'secret', 1000 );
		$challenge = $engine->create( 417 );
		$this->assertSame( 'SHA-256', $challenge['algorithm'] );
		$this->assertSame( 1000, $challenge['maxnumber'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{24}\?expires=\d{10}$/', $challenge['salt'] );
		$this->assertSame( hash( 'sha256', $challenge['salt'] . '417' ), $challenge['challenge'] );
		$this->assertArrayNotHasKey( 'number', $challenge, 'the secret number never leaves the server' );
		$this->assertSame( 417, ProofOfWork::solve( $challenge ) );

		$used    = array();
		$consume = static function ( string $id, int $expires ) use ( &$used ): bool {
			if ( isset( $used[ $id ] ) ) {
				return false;
			}
			$used[ $id ] = $expires;
			return true;
		};
		$payload = ProofOfWork::encode_solution( $challenge, 417 );
		$this->assertSame( ProofOfWork::OK, $engine->verify( $payload, $consume ) );
		$this->assertSame( ProofOfWork::REPLAYED, $engine->verify( $payload, $consume ), 'single use' );
	}

	public function test_wrong_solution_and_tampering_are_invalid(): void {
		$engine    = new ProofOfWork( 'secret', 1000 );
		$challenge = $engine->create( 10 );
		$this->assertSame( ProofOfWork::INVALID, $engine->verify( ProofOfWork::encode_solution( $challenge, 11 ) ), 'wrong number' );
		$this->assertSame( ProofOfWork::INVALID, ( new ProofOfWork( 'other-site', 1000 ) )->verify( ProofOfWork::encode_solution( $challenge, 10 ) ), 'signature of another secret' );

		// Self-made challenge with a forged signature.
		$forged              = $challenge;
		$forged['salt']      = str_repeat( 'a', 24 ) . '?expires=' . ( time() + 600 );
		$forged['challenge'] = hash( 'sha256', $forged['salt'] . '1' );
		$this->assertSame( ProofOfWork::INVALID, $engine->verify( ProofOfWork::encode_solution( $forged, 1 ) ) );

		// Extended expiry with the original signature.
		$extended         = $challenge;
		$extended['salt'] = preg_replace( '/expires=\d+/', 'expires=' . ( time() + 99999 ), $challenge['salt'] );
		$this->assertSame( ProofOfWork::INVALID, $engine->verify( ProofOfWork::encode_solution( $extended, 10 ) ) );

		$this->assertSame( ProofOfWork::MISSING, $engine->verify( '' ) );
		$this->assertSame( ProofOfWork::INVALID, $engine->verify( 'not base64 !' ) );
		$this->assertSame( ProofOfWork::INVALID, $engine->verify( base64_encode( '{"algorithm":"SHA-1"}' ) ) );
		$this->assertSame( ProofOfWork::INVALID, $engine->verify( str_repeat( 'A', 4000 ) ), 'oversized' );
		$wrong_algo = json_decode( (string) base64_decode( ProofOfWork::encode_solution( $challenge, 10 ) ), true );
		$wrong_algo['algorithm'] = 'SHA-512';
		$this->assertSame( ProofOfWork::INVALID, $engine->verify( base64_encode( (string) json_encode( $wrong_algo ) ) ) );
		$string_number           = $wrong_algo;
		$string_number['algorithm'] = 'SHA-256';
		$string_number['number']    = '10';
		$this->assertSame( ProofOfWork::INVALID, $engine->verify( base64_encode( (string) json_encode( $string_number ) ) ), 'number must be an integer' );
	}

	public function test_expired_challenge_is_invalid(): void {
		$now       = 1800000000;
		$clock     = static function () use ( &$now ): int {
			return $now;
		};
		$engine    = new ProofOfWork( 'secret', 1000, 600, $clock );
		$challenge = $engine->create( 5 );
		$this->assertSame( $now + 600, $challenge['expires'] );
		$payload = ProofOfWork::encode_solution( $challenge, 5 );
		$now    += 601;
		$this->assertSame( ProofOfWork::INVALID, $engine->verify( $payload ) );
		$now -= 2;
		$this->assertSame( ProofOfWork::OK, $engine->verify( $payload ) );
	}

	public function test_difficulty_filter_is_clamped(): void {
		remove_all_filters( 'review_captain_pow_max_number_v1' );
		$this->assertSame( ProofOfWork::DEFAULT_MAX_NUMBER, PowCaptcha::max_number() );
		add_filter( 'review_captain_pow_max_number_v1', static fn(): int => 5 );
		$this->assertSame( ProofOfWork::MIN_MAX_NUMBER, PowCaptcha::max_number() );
		remove_all_filters( 'review_captain_pow_max_number_v1' );
		add_filter( 'review_captain_pow_max_number_v1', static fn(): int => PHP_INT_MAX );
		$this->assertSame( ProofOfWork::MAX_NUMBER_LIMIT, PowCaptcha::max_number() );
		$this->assertSame( ProofOfWork::MAX_NUMBER_LIMIT, PowCaptcha::challenge()['maxnumber'] );
	}

	// ---- Settings and glue ----

	public function test_default_provider_none_means_no_check(): void {
		$this->assertSame( 'none', Plugin::instance()->settings()->get( 'submissions', 'captcha_provider' ) );
		$this->assertFalse( PowCaptcha::enabled() );
		$this->assertNull( PowCaptcha::check( 'review', array() ) );
		$out = $this->form()->process( $this->form_post() );
		$this->assertTrue( $out['ok'], 'no rc_pow needed' );
		$this->assertStringNotContainsString( 'name="rc_pow"', $this->module->shortcode_form( array( 'v' => '1' ) ) );
		$this->assertNotContains( PowCaptcha::SCRIPT_HANDLE, $GLOBALS['rc_test_scripts'] );
	}

	public function test_unknown_provider_value_falls_back_to_none(): void {
		$this->enable( array( 'submissions' => array( 'captcha_provider' => 'recaptcha' ) ) );
		$this->assertSame( 'none', PowCaptcha::provider(), 'external providers only via the filter' );
	}

	public function test_ajax_hooks_are_registered_for_cached_pages(): void {
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_' . PowCaptcha::AJAX_ACTION, array( PowCaptcha::class, 'ajax_challenge' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_' . PowCaptcha::AJAX_ACTION, array( PowCaptcha::class, 'ajax_challenge' ) ) );
	}

	public function test_form_renders_accessible_field_and_script_when_enabled(): void {
		$this->pow_on();
		$html = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'name="rc_pow"', $html );
		$this->assertStringContainsString( 'role="status" aria-live="polite"', $html );
		$this->assertStringContainsString( '<noscript>', $html );
		$this->assertStringNotContainsString( 'expires=', $html, 'no challenge in the (cacheable) page' );
		$this->assertContains( PowCaptcha::SCRIPT_HANDLE, $GLOBALS['rc_test_scripts'] );
	}

	public function test_review_form_rejection_paths_store_nothing(): void {
		$this->pow_on();
		$before = $this->rows( 'reviews' );

		$missing = $this->form()->process( $this->form_post() );
		$this->assertFalse( $missing['ok'] );
		$this->assertSame( array( 'pow_missing' ), $missing['errors'] );
		$this->assertStringContainsString( 'rc_error=pow_missing', $missing['redirect'] );
		$this->assertStringNotContainsString( 'rc_draft', $missing['redirect'], 'spam rejections keep no draft' );
		$this->assertStringContainsString( 'JavaScript', FormHandler::error_message( 'pow_missing' ) );

		$invalid = $this->form()->process( $this->form_post( array( 'rc_pow' => base64_encode( '{"algorithm":"SHA-256"}' ) ) ) );
		$this->assertSame( array( 'pow_invalid' ), $invalid['errors'] );
		$this->assertNotSame( '', FormHandler::error_message( 'pow_invalid' ) );
		$this->assertStringNotContainsString( 'JavaScript', FormHandler::error_message( 'pow_invalid' ), 'neutral message' );

		$array = $this->form()->process( $this->form_post( array( 'rc_pow' => array( 'x' ) ) ) );
		$this->assertSame( array( 'pow_missing' ), $array['errors'] );
		$this->assertSame( $before, $this->rows( 'reviews' ) );

		$payload = $this->solved();
		$ok      = $this->form()->process( $this->form_post( array( 'rc_pow' => $payload ) ) );
		$this->assertTrue( $ok['ok'] );
		$this->assertSame( $before + 1, $this->rows( 'reviews' ) );

		$replay = $this->form()->process( $this->form_post( array( 'rc_pow' => $payload ) ) );
		$this->assertSame( array( 'pow_replayed' ), $replay['errors'] );
		$this->assertSame( $before + 1, $this->rows( 'reviews' ) );
		$this->assertSame( FormHandler::error_message( 'pow_invalid' ), FormHandler::error_message( 'pow_replayed' ) );
	}

	public function test_honeypot_still_wins_before_the_pow_check(): void {
		$this->pow_on();
		$out = $this->form()->process( $this->form_post( array( 'rc_website' => 'http://spam.example' ) ) );
		$this->assertTrue( $out['ok'], 'silent success as before' );
		$this->assertNull( $out['review'] );
	}

	public function test_external_filter_rejects_with_neutral_code_and_receives_context(): void {
		$seen = array();
		add_filter(
			'review_captain_spam_checks_v1',
			static function ( array $reasons, array $context ) use ( &$seen ): array {
				$seen = $context;
				return array_merge( $reasons, array( 'Turnstile_Failed' ) );
			},
			10,
			2
		);
		$before = $this->rows( 'reviews' );
		$out    = $this->form()->process( $this->form_post() );
		$this->assertSame( array( PowCaptcha::EXTERNAL_CODE ), $out['errors'], 'external reason is not revealed' );
		$this->assertSame( 'turnstile_failed', PowCaptcha::check( 'review', $this->form_post() ) );
		$this->assertSame( 'review', $seen['form'] );
		$this->assertSame( 'none', $seen['provider'] );
		$this->assertSame( 'Carla', $seen['post']['rc_name'] );
		$this->assertSame( $before, $this->rows( 'reviews' ) );
		$this->assertNotSame( '', FormHandler::error_message( PowCaptcha::EXTERNAL_CODE ) );
	}

	public function test_external_filter_runs_after_a_passed_pow_check(): void {
		$this->pow_on();
		$calls = 0;
		add_filter(
			'review_captain_spam_checks_v1',
			static function ( $reasons ) use ( &$calls ) {
				++$calls;
				return 'not a list';
			}
		);
		$this->assertSame( 'pow_missing', PowCaptcha::check( 'review', array() ) );
		$this->assertSame( 0, $calls, 'filter only runs when the built-in check passed' );
		$this->assertNull( PowCaptcha::check( 'review', array( 'rc_pow' => $this->solved() ) ) );
		$this->assertSame( 1, $calls );
	}

	public function test_report_form_is_protected(): void {
		$this->pow_on();
		$this->enable( array( 'submissions' => array( 'reports_enabled' => true ) ) );
		$r    = $this->review();
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'name="rc_pow"', $html );
		$post = array(
			'_rc_report_nonce' => wp_create_nonce( ReportHandler::NONCE_ACTION ),
			'rc_review'        => $r->uuid,
			'rc_reason'        => 'spam',
			'rc_return'        => 'https://example.test/bewertungen/',
		);
		$handler = new ReportHandler( Plugin::instance() );
		$fail    = $handler->process( $post );
		$this->assertFalse( $fail['ok'] );
		$this->assertSame( 'pow_missing', $fail['error'] );
		$this->assertStringContainsString( 'rc_reported=error', $fail['redirect'] );
		$this->assertSame( 0, $this->rows( 'review_reports' ) );
		$this->assertTrue( $handler->process( $post + array( 'rc_pow' => $this->solved() ) )['ok'] );
		$this->assertSame( 1, $this->rows( 'review_reports' ) );
	}

	public function test_question_form_is_protected(): void {
		$this->pow_on();
		$this->enable( array( 'submissions' => array( 'qa_enabled' => true ) ) );
		/** @var QuestionHandler $handler */
		$handler = Plugin::instance()->get( 'question_handler' );
		$this->assertStringContainsString( 'name="rc_pow"', $handler->shortcode( array( 'v' => '1', 'context_type' => 'product', 'context_id' => '42' ) ) );
		$post = array(
			'_rc_q_nonce'     => wp_create_nonce( QuestionHandler::NONCE_ACTION ),
			'rc_ts'           => FormHandler::sign_timestamp( time() - 10 ),
			'rc_context_type' => 'product',
			'rc_context_id'   => '42',
			'rc_context_sig'  => FormHandler::sign_context( 'product', '42' ),
			'rc_return'       => 'https://example.test/produkt/42/',
			'rc_question'     => 'Ist das Gerät auch für Linkshänder geeignet?',
			'rc_name'         => 'Tom',
			'rc_consent'      => '1',
		);
		$fail = $handler->process( $post + array( 'rc_pow' => 'garbage' ) );
		$this->assertSame( array( 'pow_invalid' ), $fail['errors'] );
		$this->assertArrayHasKey( 'pow_invalid', QuestionHandler::error_messages() );
		$this->assertTrue( $handler->process( $post + array( 'rc_pow' => $this->solved() ) )['ok'] );
	}
}
