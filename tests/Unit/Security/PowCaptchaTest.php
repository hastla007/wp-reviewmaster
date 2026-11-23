<?php
/**
 * SET-RC-11: spam protection hook and built-in proof-of-work captcha.
 *
 * The core cases are ported from Form Captain's ProofOfWorkTest (wp-formmaster ca81401).
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Security;

use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Moderation\ReportHandler;
use ReviewCaptain\Plugin;
use ReviewCaptain\Questions\QuestionHandler;
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
	 * Engine as in Form Captain's test: fixed key, in-memory single-use store, fixed clock.
	 */
	private function engine( int $now = 1700000000 ): ProofOfWork {
		$used = array();
		return new ProofOfWork(
			str_repeat( 'k', 32 ),
			static function ( string $challenge ) use ( &$used ): bool {
				if ( isset( $used[ $challenge ] ) ) {
					return false;
				}
				$used[ $challenge ] = true;
				return true;
			},
			1000,
			600,
			static fn(): int => $now
		);
	}

	/**
	 * A valid, solved payload from the site engine for a scope.
	 *
	 * @param string $scope Scope.
	 */
	private function solved( string $scope ): string {
		$challenge = PowCaptcha::challenge( $scope );
		$number    = ProofOfWork::solve( $challenge );
		$this->assertNotNull( $number );
		return ProofOfWork::encode( $challenge, (int) $number );
	}

	private function pow_on(): void {
		$this->enable( array( 'submissions' => array( 'captcha_provider' => 'pow' ) ) );
	}

	// ---- Core (ported from Form Captain) ----

	public function test_core_creates_and_checks_challenges(): void {
		$pow       = $this->engine();
		$challenge = $pow->create( 'form-a' );
		$this->assertSame( 'SHA-256', $challenge['algorithm'] );
		$this->assertSame( 1000, $challenge['maxnumber'] );
		$this->assertSame( 1700000600, $challenge['expires'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{24}\.\d+\.[0-9a-f]{16}$/', $challenge['salt'] );
		$this->assertArrayNotHasKey( 'number', $challenge, 'the secret number never leaves the server' );
		$number = ProofOfWork::solve( $challenge );
		$this->assertNotNull( $number );

		$this->assertSame( ProofOfWork::MISSING, $pow->verify( '', 'form-a' ) );
		$this->assertSame( ProofOfWork::MALFORMED, $pow->verify( 'not base64 json', 'form-a' ) );
		$this->assertSame( ProofOfWork::WRONG_SCOPE, $pow->verify( ProofOfWork::encode( $challenge, $number ), 'form-b' ) );
		$this->assertSame( ProofOfWork::WRONG_SOLUTION, $pow->verify( ProofOfWork::encode( $challenge, $number + 1 ), 'form-a' ) );
		$forged              = $challenge;
		$forged['challenge'] = hash( 'sha256', $challenge['salt'] . '0' );
		$this->assertSame( ProofOfWork::BAD_SIGNATURE, $pow->verify( ProofOfWork::encode( $forged, 0 ), 'form-a' ) );
		$this->assertSame( ProofOfWork::OK, $pow->verify( ProofOfWork::encode( $challenge, $number ), 'form-a' ) );
		$this->assertSame( ProofOfWork::REPLAYED, $pow->verify( ProofOfWork::encode( $challenge, $number ), 'form-a' ), 'single use' );

		$late = $this->engine( 1700000601 );
		$this->assertSame( ProofOfWork::EXPIRED, $late->verify( ProofOfWork::encode( $challenge, $number ), 'form-a' ) );
	}

	public function test_core_file_has_no_host_dependencies(): void {
		$code = (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Spam/ProofOfWork/ProofOfWork.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertDoesNotMatchRegularExpression( '/\b(apply_filters|get_option|wp_[a-z_]+|__|esc_[a-z_]+)\s*\(/', $code );
		$this->assertStringNotContainsString( 'use ReviewCaptain\\', $code );
	}

	public function test_core_rejects_malformed_payloads_and_touches_the_store_only_for_correct_solutions(): void {
		$calls = 0;
		$pow   = new ProofOfWork(
			str_repeat( 'k', 32 ),
			static function () use ( &$calls ): bool {
				++$calls;
				return true;
			},
			1000,
			600,
			static fn(): int => 1700000000
		);
		$challenge = $pow->create( 'review|product|42' );
		$number    = (int) ProofOfWork::solve( $challenge );
		$data      = json_decode( (string) base64_decode( ProofOfWork::encode( $challenge, $number ) ), true );

		$this->assertSame( ProofOfWork::MALFORMED, $pow->verify( str_repeat( 'A', 1025 ), 'review|product|42' ), 'payload > 1024 bytes' );
		$this->assertSame( ProofOfWork::MALFORMED, $pow->verify( base64_encode( (string) json_encode( array( 'algorithm' => 'SHA-512' ) + $data ) ), 'review|product|42' ) );
		$this->assertSame( ProofOfWork::MALFORMED, $pow->verify( base64_encode( (string) json_encode( array( 'number' => (string) $number ) + $data ) ), 'review|product|42' ), 'number must be an integer' );
		$this->assertSame( ProofOfWork::MALFORMED, $pow->verify( base64_encode( (string) json_encode( array( 'number' => 1000001 ) + $data ) ), 'review|product|42' ) );
		$this->assertSame( ProofOfWork::MALFORMED, $pow->verify( base64_encode( (string) json_encode( array( 'salt' => 'abc?expires=1' ) + $data ) ), 'review|product|42' ) );

		// Extended expiry with the original signature: the salt is part of the challenge hash.
		$extended = array( 'salt' => preg_replace( '/\.\d+\./', '.1800000000.', $challenge['salt'] ) ) + $data;
		$this->assertSame( ProofOfWork::WRONG_SOLUTION, $pow->verify( $extended, 'review|product|42' ) );
		$other = new ProofOfWork( str_repeat( 'x', 32 ), static fn(): bool => true, 1000, 600, static fn(): int => 1700000000 );
		$this->assertSame( ProofOfWork::BAD_SIGNATURE, $other->verify( ProofOfWork::encode( $challenge, $number ), 'review|product|42' ), 'key of another site' );
		$this->assertSame( 0, $calls, 'store untouched for rejected solutions' );

		$this->assertSame( ProofOfWork::OK, $pow->verify( $data, 'review|product|42' ), 'decoded array accepted' );
		$this->assertSame( 1, $calls );
	}

	public function test_core_needs_a_long_key(): void {
		$this->expectException( \InvalidArgumentException::class );
		new ProofOfWork( 'short', static fn(): bool => true );
	}

	// ---- Settings and glue ----

	public function test_difficulty_filter_is_clamped(): void {
		remove_all_filters( 'review_captain_pow_max_number_v1' );
		$this->assertSame( 50000, PowCaptcha::max_number() );
		add_filter( 'review_captain_pow_max_number_v1', static fn(): int => 5 );
		$this->assertSame( ProofOfWork::MIN_MAX_NUMBER, PowCaptcha::max_number() );
		remove_all_filters( 'review_captain_pow_max_number_v1' );
		add_filter( 'review_captain_pow_max_number_v1', static fn(): int => PHP_INT_MAX );
		$this->assertSame( 1000000, PowCaptcha::max_number() );
		$this->assertSame( 1000000, PowCaptcha::challenge( 'review||' )['maxnumber'] );
	}

	public function test_default_provider_none_means_no_check(): void {
		$this->assertSame( 'none', Plugin::instance()->settings()->get( 'submissions', 'captcha_provider' ) );
		$this->assertFalse( PowCaptcha::enabled() );
		$this->assertNull( PowCaptcha::check( 'review', array() ) );
		$out = $this->form()->process( $this->form_post() );
		$this->assertTrue( $out['ok'], 'no _rc_pow needed' );
		$this->assertStringNotContainsString( 'name="_rc_pow"', $this->module->shortcode_form( array( 'v' => '1' ) ) );
		$this->assertNotContains( PowCaptcha::FORM_HANDLE, $GLOBALS['rc_test_scripts'] );
	}

	public function test_unknown_provider_value_falls_back_to_none(): void {
		$this->enable( array( 'submissions' => array( 'captcha_provider' => 'recaptcha' ) ) );
		$this->assertSame( 'none', PowCaptcha::provider(), 'external providers only via the filter' );
	}

	public function test_ajax_hooks_are_registered_for_cached_pages(): void {
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_' . PowCaptcha::AJAX_ACTION, array( PowCaptcha::class, 'ajax_challenge' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_' . PowCaptcha::AJAX_ACTION, array( PowCaptcha::class, 'ajax_challenge' ) ) );
	}

	public function test_scopes_bind_form_and_context(): void {
		$this->assertSame( 'review|product|42', PowCaptcha::scope( 'review', array( 'rc_context_type' => 'product', 'rc_context_id' => '42' ) ) );
		$this->assertSame( 'question|product|42', PowCaptcha::scope( 'question', array( 'rc_context_type' => 'product', 'rc_context_id' => '42', 'rc_x' => array() ) ) );
		$this->assertSame( 'report|ab-cd', PowCaptcha::scope( 'report', array( 'rc_review' => 'AB-CD' ) ) );
		$this->assertSame( 'review||', PowCaptcha::scope( 'review', array( 'rc_context_type' => array( 'x' ) ) ) );
		$this->assertTrue( PowCaptcha::valid_scope( 'review||' ) );
		$this->assertTrue( PowCaptcha::valid_scope( 'report|ab-cd' ) );
		$this->assertFalse( PowCaptcha::valid_scope( '' ) );
		$this->assertFalse( PowCaptcha::valid_scope( 'contact|x' ) );
		$this->assertFalse( PowCaptcha::valid_scope( 'review' ) );
		$this->assertFalse( PowCaptcha::valid_scope( "review|a\n|b" ) );
		$this->assertFalse( PowCaptcha::valid_scope( 'review|' . str_repeat( 'x', 200 ) ) );

		$this->pow_on();
		$other = $this->solved( 'review|product|43' );
		$this->assertSame( PowCaptcha::REASON_INVALID, PowCaptcha::check( 'review', array( '_rc_pow' => $other, 'rc_context_type' => 'product', 'rc_context_id' => '42' ) ), 'solution for another product' );
		$this->assertSame( PowCaptcha::REASON_INVALID, PowCaptcha::check( 'question', array( '_rc_pow' => $this->solved( 'review|product|42' ), 'rc_context_type' => 'product', 'rc_context_id' => '42' ) ), 'solution for another form' );
	}

	public function test_single_use_store_is_an_option_until_expiry_and_is_purged(): void {
		$this->assertTrue( PowCaptcha::consume( str_repeat( 'a', 64 ), 1700000000 ) );
		$this->assertFalse( PowCaptcha::consume( str_repeat( 'a', 64 ), 1700000000 ), 'second insert fails' );
		$this->assertTrue( PowCaptcha::consume( str_repeat( 'b', 64 ), 1700000000 ) );
		$names = array_values( array_filter( array_keys( \RC_Test_State::$options ), static fn( $n ): bool => str_starts_with( (string) $n, PowCaptcha::USED_PREFIX ) ) );
		$this->assertCount( 2, $names );
		$this->assertSame( '1700000060', get_option( $names[0] ), 'kept until expiry + 60 s' );
		$this->assertSame( 0, PowCaptcha::purge_used(), 'no options table in the unit tests: nothing to scan' );
	}

	public function test_form_renders_accessible_field_and_script_when_enabled(): void {
		$this->pow_on();
		$html = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'name="_rc_pow"', $html );
		$this->assertStringContainsString( 'data-rc-pow-scope="review|', $html );
		$this->assertStringContainsString( 'role="status" aria-live="polite"', $html );
		$this->assertStringContainsString( '<noscript>', $html );
		$this->assertStringNotContainsString( 'signature', $html, 'no challenge in the (cacheable) page' );
		$this->assertContains( PowCaptcha::FORM_HANDLE, $GLOBALS['rc_test_scripts'] );
	}

	public function test_review_form_rejection_paths_store_nothing(): void {
		$this->pow_on();
		$before = $this->rows( 'reviews' );
		$scope  = PowCaptcha::scope( 'review', $this->form_post() );

		$missing = $this->form()->process( $this->form_post() );
		$this->assertFalse( $missing['ok'] );
		$this->assertSame( array( 'pow_missing' ), $missing['errors'] );
		$this->assertStringContainsString( 'rc_error=pow_missing', $missing['redirect'] );
		$this->assertStringNotContainsString( 'rc_draft', $missing['redirect'], 'spam rejections keep no draft' );
		$this->assertStringContainsString( 'JavaScript', FormHandler::error_message( 'pow_missing' ) );

		$invalid = $this->form()->process( $this->form_post( array( '_rc_pow' => base64_encode( '{"algorithm":"SHA-256"}' ) ) ) );
		$this->assertSame( array( 'pow_invalid' ), $invalid['errors'] );
		$this->assertNotSame( '', FormHandler::error_message( 'pow_invalid' ) );
		$this->assertStringNotContainsString( 'JavaScript', FormHandler::error_message( 'pow_invalid' ), 'neutral message' );

		$array = $this->form()->process( $this->form_post( array( '_rc_pow' => array( 'x' ) ) ) );
		$this->assertSame( array( 'pow_invalid' ), $array['errors'] );
		$this->assertSame( $before, $this->rows( 'reviews' ) );

		$payload = $this->solved( $scope );
		$ok      = $this->form()->process( $this->form_post( array( '_rc_pow' => $payload ) ) );
		$this->assertTrue( $ok['ok'] );
		$this->assertSame( $before + 1, $this->rows( 'reviews' ) );

		$replay = $this->form()->process( $this->form_post( array( '_rc_pow' => $payload ) ) );
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
		$this->assertNull( PowCaptcha::check( 'review', array( '_rc_pow' => $this->solved( 'review||' ) ) ) );
		$this->assertSame( 1, $calls );
	}

	public function test_report_form_is_protected(): void {
		$this->pow_on();
		$this->enable( array( 'submissions' => array( 'reports_enabled' => true ) ) );
		$r    = $this->review();
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'name="_rc_pow"', $html );
		$this->assertStringContainsString( 'data-rc-pow-scope="report|' . $r->uuid . '"', $html );
		$post    = array(
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
		$this->assertTrue( $handler->process( $post + array( '_rc_pow' => $this->solved( 'report|' . $r->uuid ) ) )['ok'] );
		$this->assertSame( 1, $this->rows( 'review_reports' ) );
	}

	public function test_question_form_is_protected(): void {
		$this->pow_on();
		$this->enable( array( 'submissions' => array( 'qa_enabled' => true ) ) );
		/** @var QuestionHandler $handler */
		$handler = Plugin::instance()->get( 'question_handler' );
		$html    = $handler->shortcode( array( 'v' => '1', 'context_type' => 'product', 'context_id' => '42' ) );
		$this->assertStringContainsString( 'name="_rc_pow"', $html );
		$this->assertStringContainsString( 'data-rc-pow-scope="question|product|42"', $html );
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
		$fail = $handler->process( $post + array( '_rc_pow' => 'garbage' ) );
		$this->assertSame( array( 'pow_invalid' ), $fail['errors'] );
		$this->assertArrayHasKey( 'pow_invalid', QuestionHandler::error_messages() );
		$this->assertTrue( $handler->process( $post + array( '_rc_pow' => $this->solved( 'question|product|42' ) ) )['ok'] );
	}
}
