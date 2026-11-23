<?php
/**
 * SET-RC-10 moderation rules, SET-RC-29 proxy header, SET-RC-31 form limit.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Frontend;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Frontend\PublicTexts;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\RateLimiter;

final class ModerationRulesTest extends FrontendTestCase {

	private function handler(): FormHandler {
		/** @var FormHandler */
		return Plugin::instance()->get( 'form_handler' );
	}

	/**
	 * @param array<string, mixed> $o Overrides.
	 * @return array<string, mixed>
	 */
	private function post( array $o = array() ): array {
		return array_merge(
			array(
				'_rc_nonce'       => wp_create_nonce( FormHandler::NONCE_ACTION ),
				'rc_form'         => '1',
				'rc_ts'           => FormHandler::sign_timestamp( time() - 10 ),
				'rc_context_type' => '',
				'rc_context_id'   => '',
				'rc_context_sig'  => FormHandler::sign_context( '', '' ),
				'rc_return'       => 'https://example.test/kontakt/',
				'rc_rating'       => '5',
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

	/**
	 * History reasons of a review.
	 *
	 * @return list<string>
	 */
	private function history( int $id ): array {
		return array_map( static fn( array $e ): string => $e['action'] . ':' . $e['reason'], Plugin::instance()->reviews()->history( $id ) );
	}

	public function test_defaults_keep_todays_behaviour(): void {
		$s = Plugin::instance()->settings();
		$this->assertSame( 3, $s->get( 'submissions', 'min_seconds' ) );
		$this->assertSame( '', $s->get( 'submissions', 'hold_words' ) );
		$this->assertFalse( $s->get( 'submissions', 'hold_links' ) );
		$this->assertSame( '', $s->get( 'submissions', 'block_email_domains' ) );
		$this->assertSame( 'none', $s->get( 'submissions', 'trusted_proxy_header' ) );
		$this->assertSame( 3, FormHandler::min_seconds() );
	}

	public function test_hold_word_holds_auto_approved_review_and_records_reason(): void {
		$this->settings(
			array(
				'general'     => array( 'new_review_status' => 'approved' ),
				'submissions' => array( 'hold_words' => "Betrug\nrückgeld sofort" ),
			)
		);
		$clean = $this->handler()->process( $this->post() );
		$this->assertSame( ReviewStatus::APPROVED, $clean['review']->status );

		$held = $this->handler()->process( $this->post( array( 'rc_body' => 'Reiner BETRUG, die Ware kam nie an, sehr enttäuschend.' ) ) );
		$this->assertTrue( $held['ok'], 'held, not rejected' );
		$this->assertStringContainsString( 'rc_submitted=1', $held['redirect'], 'visitor sees the neutral "after review" message' );
		$stored = Plugin::instance()->reviews()->find( (int) $held['review']->id );
		$this->assertSame( ReviewStatus::PENDING, $stored->status );
		$this->assertStringContainsString( 'BETRUG', $stored->body, 'nothing is altered or dropped' );
		$this->assertContains( 'held_for_moderation:hold_words', $this->history( (int) $stored->id ) );

		$part = $this->handler()->process( $this->post( array( 'rc_body' => 'Betrugsverdacht hatte ich nicht, alles bestens gelaufen.' ) ) );
		$this->assertSame( ReviewStatus::APPROVED, $part['review']->status, 'whole words only' );
	}

	public function test_consent_text_version_is_recorded_and_archived(): void {
		$first   = $this->handler()->process( $this->post() );
		$archive = get_option( PublicTexts::CONSENT_ARCHIVE_OPTION, array() );
		$this->assertCount( 1, $archive );
		$version = (string) array_key_first( $archive );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{16}$/', $version );
		$this->assertSame( PublicTexts::defaults()['submissions.consent_text'], $archive[ $version ]['text'], 'the text the author saw' );
		$this->assertContains( 'consent_recorded:consent_text:' . $version, $this->history( (int) $first['review']->id ) );

		$this->settings( array( 'submissions' => array( 'consent_text' => 'Neuer Einwilligungstext {privacy_link}.' ) ) );
		$second  = $this->handler()->process( $this->post( array( 'rc_email' => 'dora@example.test' ) ) );
		$archive = get_option( PublicTexts::CONSENT_ARCHIVE_OPTION, array() );
		$this->assertCount( 2, $archive, 'the earlier version stays archived' );
		$this->assertArrayHasKey( $version, $archive );
		$this->assertNotContains( 'consent_recorded:consent_text:' . $version, $this->history( (int) $second['review']->id ) );

		$this->settings( array( 'submissions' => array( 'require_consent' => false ) ) );
		$none = $this->handler()->process( $this->post( array( 'rc_email' => 'emil@example.test', 'rc_consent' => '' ) ) );
		$this->assertSame( array(), array_filter( $this->history( (int) $none['review']->id ), static fn( string $e ): bool => str_starts_with( $e, 'consent_recorded' ) ), 'no consent, no record' );
	}

	public function test_hold_links(): void {
		$this->settings(
			array(
				'general'     => array( 'new_review_status' => 'approved' ),
				'submissions' => array( 'hold_links' => true ),
			)
		);
		$held = $this->handler()->process( $this->post( array( 'rc_body' => 'Mehr dazu auf www.example.test/angebot – wirklich sehr gut.' ) ) );
		$this->assertSame( ReviewStatus::PENDING, $held['review']->status );
		$this->assertContains( 'held_for_moderation:hold_links', $this->history( (int) $held['review']->id ) );

		$this->settings( array( 'general' => array( 'new_review_status' => 'approved' ) ) );
		$off = $this->handler()->process( $this->post( array( 'rc_body' => 'Mehr dazu auf https://example.test – wirklich sehr gut.' ) ) );
		$this->assertSame( ReviewStatus::APPROVED, $off['review']->status, 'off by default' );
	}

	public function test_filter_can_hold_or_release(): void {
		$this->settings( array( 'general' => array( 'new_review_status' => 'approved' ) ) );
		add_filter( 'review_captain_submission_hold_reasons', static fn( array $r ) => array_merge( $r, array( 'custom_rule' ) ) );
		$held = $this->handler()->process( $this->post() );
		$this->assertSame( ReviewStatus::PENDING, $held['review']->status );
		$this->assertContains( 'held_for_moderation:custom_rule', $this->history( (int) $held['review']->id ) );
	}

	public function test_blocked_email_domain_is_rejected_neutrally(): void {
		$this->settings( array( 'submissions' => array( 'block_email_domains' => "@wegwerf.example\nspam.test" ) ) );
		foreach ( array( 'max@wegwerf.example', 'a@mail.spam.test', 'B@SPAM.TEST' ) as $email ) {
			$result = $this->handler()->process( $this->post( array( 'rc_email' => $email ) ) );
			$this->assertFalse( $result['ok'], $email );
			$this->assertSame( array( 'email_blocked' ), $result['errors'] );
		}
		$this->assertSame( 0, $this->rows( 'reviews' ), 'nothing stored' );
		$this->assertStringNotContainsString( 'wegwerf', FormHandler::error_message( 'email_blocked' ) );
		$this->assertTrue( $this->handler()->process( $this->post( array( 'rc_email' => 'ok@notspam.test' ) ) )['ok'], 'only the domain and its subdomains' );
	}

	public function test_min_seconds_setting_and_filter(): void {
		$this->settings( array( 'submissions' => array( 'min_seconds' => 20 ) ) );
		$this->assertSame( 'too_fast', FormHandler::verify_timestamp( FormHandler::sign_timestamp( time() - 10 ) ) );
		$this->assertNull( FormHandler::verify_timestamp( FormHandler::sign_timestamp( time() - 25 ) ) );
		add_filter( 'review_captain_form_min_seconds', static fn() => 5 );
		$this->assertNull( FormHandler::verify_timestamp( FormHandler::sign_timestamp( time() - 10 ) ), 'filter wins' );
	}

	public function test_list_setting_parsing(): void {
		$this->assertSame( array( 'a', 'b c', 'd' ), FormHandler::list_setting( " A \r\nb c,,\n\nd\na" ) );
	}

	public function test_proxy_header_off_by_default_and_last_address_when_trusted(): void {
		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 203.0.113.9';
		$this->assertSame( '10.0.0.1', RateLimiter::client_ip(), 'header ignored unless configured' );

		$this->settings( array( 'submissions' => array( 'trusted_proxy_header' => 'x-forwarded-for' ) ) );
		$this->assertSame( '203.0.113.9', RateLimiter::client_ip(), 'address appended by the trusted proxy' );
		$key = FormHandler::rate_key();
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 198.51.100.7';
		$this->assertNotSame( $key, FormHandler::rate_key(), 'two clients behind the proxy are counted separately' );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'garbage';
		$this->assertSame( '10.0.0.1', RateLimiter::client_ip(), 'invalid header → REMOTE_ADDR' );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	public function test_form_item_limit_filter_has_upper_bound(): void {
		$ids = implode( ',', range( 1, 80 ) );
		$this->assertCount( 20, FormHandler::parse_context_list( $ids ) );
		add_filter( 'review_captain_form_max_items', static fn() => 30 );
		$this->assertCount( 30, FormHandler::parse_context_list( $ids ) );
		add_filter( 'review_captain_form_max_items', static fn() => 1000, 20 );
		$this->assertCount( FormHandler::MAX_ITEMS_LIMIT, FormHandler::parse_context_list( $ids ) );
	}
}
