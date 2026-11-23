<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Frontend;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Plugin;

final class FormHandlerTest extends FrontendTestCase {

	protected function setUp(): void {
		parent::setUp();
		update_option( 'admin_email', 'admin@example.test' );
	}

	private function handler(): FormHandler {
		/** @var FormHandler */
		return Plugin::instance()->get( 'form_handler' );
	}

	/**
	 * Valid POST payload.
	 *
	 * @param array<string, mixed> $o Overrides.
	 * @return array<string, mixed>
	 */
	private function post( array $o = array(), string $type = '', string $cid = '' ): array {
		return array_merge(
			array(
				'_rc_nonce'       => wp_create_nonce( FormHandler::NONCE_ACTION ),
				'rc_form'         => '1',
				'rc_ts'           => FormHandler::sign_timestamp( time() - 10 ),
				'rc_context_type' => $type,
				'rc_context_id'   => $cid,
				'rc_context_sig'  => FormHandler::sign_context( $type, $cid ),
				'rc_return'       => 'https://example.test/kontakt/?x=1&rc_error=old',
				'rc_rating'       => '4',
				'rc_title'        => 'Gute Beratung',
				'rc_body'         => 'Die Beratung war ausführlich und freundlich, gerne wieder.',
				'rc_name'         => 'Carla',
				'rc_email'        => 'carla@example.test',
				'rc_consent'      => '1',
				'rc_website'      => '',
			),
			$o
		);
	}

	public function test_form_renders_protection_fields_without_preselection(): void {
		$out = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'name="_rc_nonce"', $out );
		$this->assertStringContainsString( 'name="rc_website"', $out );
		$this->assertStringContainsString( 'name="rc_ts"', $out );
		$this->assertStringContainsString( 'name="rc_context_sig"', $out );
		$this->assertStringContainsString( 'value="review_captain_submit"', $out );
		$this->assertStringContainsString( 'admin-post.php', $out );
		$this->assertSame( 5, substr_count( $out, 'type="radio" name="rc_rating"' ) );
		$this->assertStringNotContainsString( 'checked', $out );
		$this->assertStringContainsString( 'name="rc_consent"', $out );
		$this->assertStringContainsString( 'name="rc_title"', $out );
		$this->assertStringContainsString( 'Will never be published', $out );
	}

	public function test_form_respects_settings(): void {
		$this->settings(
			array(
				'submissions' => array( 'allow_title' => false, 'honeypot' => false ),
				'privacy'     => array( 'privacy_notice' => 'Hinweis <script>x</script> zum Datenschutz' ),
			)
		);
		$out = $this->module->shortcode_form( array() );
		$this->assertStringNotContainsString( 'name="rc_title"', $out );
		$this->assertStringNotContainsString( 'name="rc_website"', $out );
		$this->assertStringContainsString( 'Hinweis &lt;script&gt;x&lt;/script&gt; zum Datenschutz', $out );

		$this->settings( array( 'submissions' => array( 'enabled' => false ) ) );
		$off = $this->module->shortcode_form( array() );
		$this->assertStringNotContainsString( '<form', $off );
		$this->assertStringContainsString( 'Reviews cannot be submitted at this time.', $off );
	}

	public function test_valid_submission_creates_pending_local_review(): void {
		$this->login_with();
		$result = $this->handler()->process( $this->post( array(), 'product', '42' ) );
		$this->assertTrue( $result['ok'], implode( ',', $result['errors'] ) );
		$r = Plugin::instance()->reviews()->find( (int) $result['review']->id );
		$this->assertSame( ReviewStatus::PENDING, $r->status );
		$this->assertSame( Origin::LOCAL_SUBMISSION, $r->origin );
		$this->assertSame( Platform::LOCAL, $r->platform );
		$this->assertSame( Verification::UNVERIFIED, $r->verification );
		$this->assertTrue( $r->consent_publish );
		$this->assertNotNull( $r->consent_at );
		$this->assertSame( 1, $r->author_user_id );
		$this->assertSame( 'carla@example.test', $r->author_email );
		$this->assertSame( 'product', $r->context_type );
		$this->assertSame( '42', $r->context_id );
		$this->assertSame( 4.0, $r->rating_value );
		$this->assertStringContainsString( 'rc_submitted=1', $result['redirect'] );
		$this->assertStringStartsWith( 'https://example.test/kontakt/?x=1&rc_submitted=1', $result['redirect'] );
		$this->assertStringNotContainsString( 'rc_error', $result['redirect'] );
		$this->assertStringNotContainsString( '127.0.0.1', (string) wp_json_encode( $r->meta ) );

		// Admin notification: hint + link only.
		$this->assertCount( 1, \RC_Test_State::$mails );
		$mail = \RC_Test_State::$mails[0];
		$this->assertStringNotContainsString( 'ausführlich', $mail['message'] );
		$this->assertStringNotContainsString( 'carla@', $mail['message'] );
		$this->assertStringContainsString( 'wp-admin', $mail['message'] );
	}

	public function test_handle_redirects(): void {
		$_POST = $this->post();
		try {
			$this->handler()->handle();
			$this->fail( 'expected redirect' );
		} catch ( \RC_Test_Die $e ) {
			$this->assertStringStartsWith( 'redirect:https://example.test/kontakt/', $e->getMessage() );
		}
	}

	public function test_auto_approve_setting_and_no_notification(): void {
		$this->settings(
			array(
				'general'     => array( 'new_review_status' => 'approved' ),
				'submissions' => array( 'notify_admin' => false ),
			)
		);
		$result = $this->handler()->process( $this->post() );
		$this->assertSame( ReviewStatus::APPROVED, $result['review']->status );
		$this->assertStringContainsString( 'rc_submitted=2', $result['redirect'] );
		$this->assertCount( 0, \RC_Test_State::$mails );
		$this->assertNull( $result['review']->author_user_id );
	}

	public function test_invalid_nonce_rejected(): void {
		$result = $this->handler()->process( $this->post( array( '_rc_nonce' => 'forged' ) ) );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( array( 'nonce' ), $result['errors'] );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
	}

	public function test_honeypot_silently_drops(): void {
		$result = $this->handler()->process( $this->post( array( 'rc_website' => 'http://spam.example' ) ) );
		$this->assertTrue( $result['ok'] );
		$this->assertNull( $result['review'] );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
		$this->assertCount( 0, \RC_Test_State::$mails );
	}

	public function test_timestamp_checks(): void {
		$fast = $this->handler()->process( $this->post( array( 'rc_ts' => FormHandler::sign_timestamp( time() - 1 ) ) ) );
		$this->assertSame( array( 'too_fast' ), $fast['errors'] );
		$forged = $this->handler()->process( $this->post( array( 'rc_ts' => ( time() - 60 ) . ':' . str_repeat( 'a', 32 ) ) ) );
		$this->assertSame( array( 'expired' ), $forged['errors'] );
		$old = $this->handler()->process( $this->post( array( 'rc_ts' => FormHandler::sign_timestamp( time() - 2 * DAY_IN_SECONDS ) ) ) );
		$this->assertSame( array( 'expired' ), $old['errors'] );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
	}

	public function test_rate_limit_per_ip_hash(): void {
		$this->settings( array( 'submissions' => array( 'rate_limit_per_hour' => 2 ) ) );
		$this->assertTrue( $this->handler()->process( $this->post( array( 'rc_body' => 'Erste Bewertung mit ausreichend langem Text.' ) ) )['ok'] );
		$this->assertTrue( $this->handler()->process( $this->post( array( 'rc_body' => 'Zweite Bewertung mit ausreichend langem Text.' ) ) )['ok'] );
		$third = $this->handler()->process( $this->post( array( 'rc_body' => 'Dritte Bewertung mit ausreichend langem Text.' ) ) );
		$this->assertSame( array( 'rate_limited' ), $third['errors'] );
		$this->assertSame( 2, $this->rows( 'reviews' ) );

		// The plain IP is never stored.
		$this->assertStringNotContainsString( '127.0.0.1', serialize( \RC_Test_State::$options ) );
		$this->assertStringNotContainsString( '127.0.0.1', FormHandler::rate_key() );

		// Another IP is not affected.
		$_SERVER['REMOTE_ADDR'] = '10.0.0.9';
		$this->assertTrue( $this->handler()->process( $this->post() )['ok'] );
	}

	public function test_double_click_stores_one_review_and_answers_like_the_first(): void {
		$this->settings( array( 'general' => array( 'new_review_status' => 'approved' ) ) );
		$post   = $this->post();
		$first  = $this->handler()->process( $post );
		$mails  = count( \RC_Test_State::$mails );
		$second = $this->handler()->process( $post );
		$this->assertTrue( $second['ok'], 'the repeated POST is not an error' );
		$this->assertSame( $first['redirect'], $second['redirect'], 'same answer ("now visible")' );
		$this->assertSame( (int) $first['review']->id, (int) $second['review']->id );
		$this->assertSame( 1, $this->rows( 'reviews' ) );
		$this->assertSame( $mails, count( \RC_Test_State::$mails ), 'no second notification' );

		// Same form, changed text (e.g. back button, edited): a new review as before.
		$edited = $this->handler()->process( $this->post( array( 'rc_body' => 'Nachtrag: auch die Lieferung war schnell und gut verpackt.' ) ) );
		$this->assertNotSame( (int) $first['review']->id, (int) $edited['review']->id );
		$this->assertSame( 2, $this->rows( 'reviews' ) );

		// The key is cleared with the contact address (retention/erasure), like the multi-product key.
		$this->assertNotNull( $first['review']->submission_key );
		$this->assertStringNotContainsString( 'carla', (string) $first['review']->submission_key );
	}

	public function test_repeated_invalid_attempts_are_limited(): void {
		$this->settings( array( 'submissions' => array( 'rate_limit_per_hour' => 1 ) ) );
		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertSame( array( 'body_short' ), $this->handler()->process( $this->post( array( 'rc_body' => 'x' ) ) )['errors'] );
		}
		$this->assertSame( array( 'rate_limited' ), $this->handler()->process( $this->post() )['errors'] );
	}

	public function test_consent_required_unless_disabled(): void {
		$result = $this->handler()->process( $this->post( array( 'rc_consent' => '' ) ) );
		$this->assertSame( array( 'consent' ), $result['errors'] );
		$this->assertSame( 0, $this->rows( 'reviews' ) );

		$this->settings( array( 'submissions' => array( 'require_consent' => false ) ) );
		$ok = $this->handler()->process( $this->post( array( 'rc_consent' => '' ) ) );
		$this->assertTrue( $ok['ok'] );
		$this->assertFalse( $ok['review']->consent_publish );
		$this->assertNull( $ok['review']->consent_at );
	}

	public function test_field_validation_and_lengths(): void {
		$this->settings( array( 'submissions' => array( 'min_text_length' => 30, 'max_text_length' => 60 ) ) );
		$short = $this->handler()->process( $this->post( array( 'rc_body' => 'Zu kurz' ) ) );
		$this->assertContains( 'body_short', $short['errors'] );
		$long = $this->handler()->process( $this->post( array( 'rc_body' => str_repeat( 'a', 61 ) ) ) );
		$this->assertContains( 'body_long', $long['errors'] );
		$bad = $this->handler()->process( $this->post( array( 'rc_rating' => '6', 'rc_name' => '', 'rc_email' => 'kein-mail' ) ) );
		$this->assertSame( array( 'rating', 'name', 'email' ), $bad['errors'] );
		$this->assertStringContainsString( 'rc_error=rating%2Cname%2Cemail', $bad['redirect'] );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
	}

	public function test_errors_and_draft_shown_in_form(): void {
		$result = $this->handler()->process( $this->post( array( 'rc_body' => 'kurz', 'rc_rating' => '3' ) ) );
		parse_str( (string) parse_url( strtok( $result['redirect'], '#' ), PHP_URL_QUERY ), $query );
		$_GET = $query;
		$this->assertArrayHasKey( 'rc_draft', $query );
		$draft = get_transient( 'review_captain_draft_' . $query['rc_draft'] );
		$this->assertArrayNotHasKey( 'email', $draft, 'e-mail not kept in drafts' );

		$out = $this->module->shortcode_form( array() );
		$this->assertStringContainsString( 'role="alert"', $out );
		$this->assertStringContainsString( 'The review text is too short.', $out );
		$this->assertStringContainsString( 'aria-invalid="true"', $out );
		$this->assertStringContainsString( '>kurz</textarea>', $out );
		$this->assertStringContainsString( 'value="3" required checked="checked"', $out );
	}

	public function test_success_message_shown(): void {
		$_GET = array( 'rc_submitted' => '1', 'rc_form' => '1' );
		$this->assertStringContainsString( 'Thank you! Your review will be published after moderation.', $this->module->shortcode_form( array() ) );
	}

	public function test_context_signature_manipulation_rejected(): void {
		$post                    = $this->post( array(), 'product', '42' );
		$post['rc_context_id']   = '43';
		$this->assertSame( array( 'context' ), $this->handler()->process( $post )['errors'] );

		$post                    = $this->post();
		$post['rc_context_type'] = 'post';
		$post['rc_context_id']   = '1';
		$this->assertSame( array( 'context' ), $this->handler()->process( $post )['errors'] );

		$post                    = $this->post( array(), 'evil', '1' );
		$this->assertSame( array( 'context' ), $this->handler()->process( $post )['errors'] );

		$post                    = $this->post();
		$post['rc_context_sig']  = '';
		$this->assertSame( array( 'context' ), $this->handler()->process( $post )['errors'] );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
	}

	public function test_form_embeds_signed_context(): void {
		$out = $this->module->shortcode_form( array( 'context_type' => 'event', 'context_id' => '7' ) );
		$this->assertStringContainsString( 'name="rc_context_type" value="event"', $out );
		$this->assertStringContainsString( 'name="rc_context_sig" value="' . FormHandler::sign_context( 'event', '7' ) . '"', $out );
	}

	public function test_submission_disabled_rejected(): void {
		$this->settings( array( 'submissions' => array( 'enabled' => false ) ) );
		$this->assertSame( array( 'disabled' ), $this->handler()->process( $this->post() )['errors'] );
	}

	public function test_open_redirect_prevented(): void {
		$result = $this->handler()->process( $this->post( array( 'rc_return' => 'https://evil.example/phish' ) ) );
		$this->assertStringStartsWith( 'https://example.test/', $result['redirect'] );
	}
}
