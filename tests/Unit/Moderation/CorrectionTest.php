<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Moderation;

use ReviewCaptain\Admin\CorrectionsPage;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Moderation\CorrectionHandler;
use ReviewCaptain\Moderation\CorrectionService;
use ReviewCaptain\Plugin;
use ReviewCaptain\Privacy\PersonalData;
use ReviewCaptain\Tests\TestCase;

/**
 * RC-125A: author corrections – identity, versioning, moderation, withdrawal, audit, privacy.
 */
final class CorrectionTest extends TestCase {

	private function enable(): void {
		$s                                      = Plugin::instance()->settings()->all();
		$s['submissions']['corrections_enabled'] = true;
		update_option( 'review_captain_settings', $s );
		Plugin::instance()->settings()->flush();
	}

	private function review( array $f = array() ): Review {
		$r                  = new Review();
		$r->origin          = $f['origin'] ?? Origin::LOCAL_SUBMISSION;
		$r->rating_value    = 4.0;
		$r->title           = 'Alt';
		$r->body            = 'Ursprünglicher Bewertungstext mit genug Zeichen.';
		$r->author_name     = 'Erika';
		$r->author_email    = $f['email'] ?? 'erika@example.org';
		$r->author_user_id  = $f['user'] ?? null;
		$r->status          = $f['status'] ?? ReviewStatus::APPROVED;
		$r->consent_publish = true;
		return Plugin::instance()->reviews()->insert( $r );
	}

	private function nonce(): string {
		return wp_create_nonce( CorrectionHandler::NONCE_ACTION );
	}

	public function test_disabled_by_default(): void {
		$this->review();
		$this->assertSame( array(), ( new CorrectionService( Plugin::instance() ) )->issue_links( 'erika@example.org' ) );
		$this->assertSame( 'disabled', ( new CorrectionHandler( Plugin::instance() ) )->process( array( 'op' => 'request' ) )['result'] );
	}

	public function test_link_only_for_correctable_reviews_and_neutral_answer(): void {
		$this->enable();
		$ok = $this->review();
		$this->review( array( 'status' => ReviewStatus::PENDING ) );
		$this->review( array( 'origin' => Origin::MANUAL ) );
		$h   = new CorrectionHandler( Plugin::instance() );
		$res = $h->process( array( 'op' => 'request', 'rc_email' => 'erika@example.org', CorrectionHandler::NONCE_FIELD => $this->nonce() ) );
		$this->assertSame( 'link_sent', $res['result'] );
		$this->assertCount( 1, \RC_Test_State::$mails );
		$this->assertStringContainsString( 'rc_correct=', \RC_Test_State::$mails[0]['message'] );
		$this->assertStringNotContainsString( 'Ursprünglicher', \RC_Test_State::$mails[0]['message'], 'no review text in mail' );
		// Unknown address: same answer, no mail (no enumeration).
		$res = $h->process( array( 'op' => 'request', 'rc_email' => 'nobody@example.org', CorrectionHandler::NONCE_FIELD => $this->nonce() ) );
		$this->assertSame( 'link_sent', $res['result'] );
		$this->assertCount( 1, \RC_Test_State::$mails );
		unset( $ok );
	}

	public function test_link_mail_uses_the_brand_name(): void {
		// SET-RC-P1: general.brand_name ("Erscheint in Hinweisen und Formularen") was used nowhere in the core.
		$this->enable();
		$this->review();
		$h = new CorrectionHandler( Plugin::instance() );
		$h->process( array( 'op' => 'request', 'rc_email' => 'erika@example.org', CorrectionHandler::NONCE_FIELD => $this->nonce() ) );
		$this->assertSame( 'Edit your review on Testsite', \RC_Test_State::$mails[0]['subject'], 'empty = site title' );

		$s                             = Plugin::instance()->settings()->all();
		$s['general']['brand_name']    = 'Laden Erika';
		update_option( 'review_captain_settings', $s );
		Plugin::instance()->settings()->flush();
		$h->process( array( 'op' => 'request', 'rc_email' => 'erika@example.org', CorrectionHandler::NONCE_FIELD => $this->nonce() ) );
		$last = end( \RC_Test_State::$mails );
		$this->assertSame( 'Edit your review on Laden Erika', $last['subject'] );
		$this->assertStringContainsString( 'correct or withdraw your review at Laden Erika', $last['message'] );
	}

	public function test_bad_nonce_and_bad_token(): void {
		$this->enable();
		$h = new CorrectionHandler( Plugin::instance() );
		$this->assertSame( 'nonce', $h->process( array( 'op' => 'request', 'rc_email' => 'erika@example.org' ) )['result'] );
		$this->assertSame( 'invalid_link', $h->process( array( 'op' => 'submit', 'rc_token' => 'forged', CorrectionHandler::NONCE_FIELD => $this->nonce() ) )['result'] );
	}

	public function test_proposal_stays_unpublished_until_approved_then_edited_label(): void {
		$this->enable();
		$r      = $this->review();
		$svc    = new CorrectionService( Plugin::instance() );
		$token  = $svc->issue_links( 'erika@example.org' )[ (int) $r->id ];
		$h      = new CorrectionHandler( Plugin::instance() );
		$res    = $h->process( array( 'op' => 'submit', 'rc_token' => $token, 'rc_rating' => '5', 'rc_title' => 'New', 'rc_body' => 'Korrigierter Bewertungstext mit genug Zeichen.', CorrectionHandler::NONCE_FIELD => $this->nonce() ) );
		$this->assertSame( 'proposed', $res['result'] );
		$this->assertSame( 'Alt', Plugin::instance()->reviews()->find( (int) $r->id )->title, 'public review unchanged' );
		$this->assertNull( $svc->review_for_token( $token ), 'token is single use' );
		$open = $svc->open();
		$this->assertCount( 1, $open );

		$this->login_with( 'review_captain_moderate' );
		$url = ( new CorrectionsPage( Plugin::instance() ) )->decide( array( 'revision' => $open[0]['id'], 'decision' => 'approve', '_wpnonce' => wp_create_nonce( CorrectionsPage::NONCE_ACTION ) ) );
		$this->assertStringContainsString( 'correction_approved', $url );
		$now = Plugin::instance()->reviews()->find( (int) $r->id );
		$this->assertSame( 'New', $now->title );
		$this->assertSame( 5.0, $now->rating_value );
		$this->assertNotEmpty( $now->meta['edited_at'] );
		$versions = CorrectionService::versions( (int) $r->id );
		$statuses = array_column( $versions, 'status' );
		$this->assertContains( 'approved', $statuses );
		$this->assertContains( 'superseded', $statuses );
		$this->assertContains( 'Alt', array_column( $versions, 'title' ), 'old text kept as version' );
		$this->assertGreaterThan( 0, $this->rows( 'audit_log' ) );
	}

	public function test_reject_keeps_published_text_and_unchanged_is_refused(): void {
		$this->enable();
		$r   = $this->review();
		$svc = new CorrectionService( Plugin::instance() );
		$this->assertTrue( is_wp_error( $svc->propose( $r, array(), 'link' ) ), 'unchanged proposal refused' );
		$id = $svc->propose( $r, array( 'body' => 'Ganz anderer Text, lang genug für die Prüfung.' ), 'link' );
		$this->assertIsInt( $id );
		$this->login_with( 'review_captain_moderate' );
		$this->assertTrue( $svc->reject( $id, 'off_topic' ) );
		$this->assertSame( 'Ursprünglicher Bewertungstext mit genug Zeichen.', Plugin::instance()->reviews()->find( (int) $r->id )->body );
		$this->assertFalse( $svc->reject( $id ), 'not open any more' );
	}

	public function test_decide_requires_capability(): void {
		$this->enable();
		$url = ( new CorrectionsPage( Plugin::instance() ) )->decide( array( 'revision' => 1, 'decision' => 'approve', '_wpnonce' => wp_create_nonce( CorrectionsPage::NONCE_ACTION ) ) );
		$this->assertStringContainsString( 'forbidden', $url );
	}

	public function test_withdraw_archives_immediately(): void {
		$this->enable();
		$r     = $this->review();
		$svc   = new CorrectionService( Plugin::instance() );
		$token = $svc->issue_links( 'erika@example.org' )[ (int) $r->id ];
		$res   = ( new CorrectionHandler( Plugin::instance() ) )->process( array( 'op' => 'withdraw', 'rc_token' => $token, CorrectionHandler::NONCE_FIELD => $this->nonce() ) );
		$this->assertSame( 'withdrawn', $res['result'] );
		$this->assertSame( ReviewStatus::ARCHIVED, Plugin::instance()->reviews()->find( (int) $r->id )->status );
	}

	public function test_logged_in_author_channel(): void {
		$this->enable();
		\RC_Test_State::$user_id = 7;
		$own   = $this->review( array( 'user' => 7 ) );
		$other = $this->review( array( 'user' => 8, 'email' => 'x@example.org' ) );
		$svc   = new CorrectionService( Plugin::instance() );
		$this->assertNotNull( $svc->review_for_user( (int) $own->id ) );
		$this->assertNull( $svc->review_for_user( (int) $other->id ) );
	}

	public function test_privacy_export_contains_versions_and_delete_purges_them(): void {
		$this->enable();
		$r   = $this->review();
		$svc = new CorrectionService( Plugin::instance() );
		$svc->propose( $r, array( 'body' => 'Vorgeschlagene neue Fassung mit genug Zeichen.' ), 'link' );
		$export = ( new PersonalData( Plugin::instance()->reviews(), Plugin::instance()->settings() ) )->export( 'erika@example.org' );
		$this->assertStringContainsString( 'Vorgeschlagene neue Fassung', wp_json_encode( $export, JSON_UNESCAPED_UNICODE ) );
		Plugin::instance()->reviews()->delete( (int) $r->id );
		$this->assertSame( array(), CorrectionService::versions( (int) $r->id ) );
	}

	public function test_external_reviews_are_not_correctable(): void {
		$r           = new Review();
		$r->platform = Platform::GOOGLE;
		$r->status   = ReviewStatus::APPROVED;
		$this->assertFalse( CorrectionService::correctable( $r ) );
	}

	public function test_double_click_on_submit_or_withdraw_gives_the_first_answer(): void {
		$this->enable();
		$r     = $this->review();
		$svc   = new CorrectionService( Plugin::instance() );
		$token = $svc->issue_links( 'erika@example.org' )[ (int) $r->id ];
		$h     = new CorrectionHandler( Plugin::instance() );
		$post  = array( 'op' => 'submit', 'rc_token' => $token, 'rc_rating' => '5', 'rc_body' => 'Korrigierter Bewertungstext mit genug Zeichen.', CorrectionHandler::NONCE_FIELD => $this->nonce() );
		$this->assertSame( 'proposed', $h->process( $post )['result'] );
		$again = $h->process( $post );
		$this->assertTrue( $again['ok'] );
		$this->assertSame( 'proposed', $again['result'], 'not "link expired" after a double click' );
		$this->assertCount( 1, $svc->open(), 'stored once' );
		$this->assertNull( $svc->review_for_token( $token ), 'a used link never opens the review again' );
		$this->assertSame( 'proposed', $h->process( array( 'op' => 'withdraw' ) + $post )['result'], 'a used link cannot withdraw' );
		$this->assertSame( ReviewStatus::APPROVED, Plugin::instance()->reviews()->find( (int) $r->id )->status );

		$w     = $this->review( array( 'email' => 'w@example.org' ) );
		$token = $svc->issue_links( 'w@example.org' )[ (int) $w->id ];
		$wpost = array( 'op' => 'withdraw', 'rc_token' => $token, CorrectionHandler::NONCE_FIELD => $this->nonce() );
		$this->assertSame( 'withdrawn', $h->process( $wpost )['result'] );
		$this->assertSame( 'withdrawn', $h->process( $wpost )['result'] );
	}

	public function test_title_follows_the_form_rules(): void {
		$this->enable();
		$r   = $this->review();
		$svc = new CorrectionService( Plugin::instance() );
		$res = $svc->propose( $r, array( 'title' => str_repeat( 'ü', 151 ) ), 'link' );
		$this->assertTrue( is_wp_error( $res ) );
		$this->assertSame( 'title', $res->get_error_message(), 'too long: refused, never cut inside a character' );

		$s                                = Plugin::instance()->settings()->all();
		$s['submissions']['allow_title'] = false;
		update_option( 'review_captain_settings', $s );
		Plugin::instance()->settings()->flush();
		$id = $svc->propose( $r, array( 'title' => 'Neuer Titel', 'body' => 'Ganz anderer Text, lang genug für die Prüfung.' ), 'link' );
		$this->assertIsInt( $id );
		$this->assertSame( 'Alt', $svc->find( $id )['title'], 'titles off: the stored title stays' );

		$token = $svc->issue_links( 'erika@example.org' )[ (int) $r->id ];
		$_GET[ CorrectionHandler::QUERY_TOKEN ] = $token;
		$html = ( new CorrectionHandler( Plugin::instance() ) )->shortcode();
		unset( $_GET[ CorrectionHandler::QUERY_TOKEN ] );
		$this->assertStringContainsString( 'name="rc_body"', $html );
		$this->assertStringNotContainsString( 'name="rc_title"', $html );
	}

	public function test_link_in_the_mail_is_absolute_when_the_form_posts_a_path(): void {
		$this->enable();
		$this->review();
		// The shortcode posts the current request path as rc_return (relative, as in WordPress).
		$res = ( new CorrectionHandler( Plugin::instance() ) )->process( array( 'op' => 'request', 'rc_email' => 'erika@example.org', 'rc_return' => '/bewertung-korrigieren/?lang=de', CorrectionHandler::NONCE_FIELD => $this->nonce() ) );
		$this->assertSame( 'link_sent', $res['result'] );
		$this->assertCount( 1, \RC_Test_State::$mails );
		$this->assertMatchesRegularExpression( '#\nhttps://example\.test/bewertung-korrigieren/\?lang=de&rc_correct=[A-Za-z0-9_-]+\n#', \RC_Test_State::$mails[0]['message'], 'a clickable link with the site host' );
		$this->assertStringStartsWith( 'https://example.test/bewertung-korrigieren/', $res['redirect'] );
		// A foreign host is still refused (wp_validate_redirect), protocol-relative too.
		$res = ( new CorrectionHandler( Plugin::instance() ) )->process( array( 'op' => 'request', 'rc_email' => 'x@example.org', 'rc_return' => '//evil.test/x', CorrectionHandler::NONCE_FIELD => $this->nonce() ) );
		$this->assertStringStartsWith( 'https://example.test/', $res['redirect'] );
	}

	public function test_anonymising_erasure_removes_versions_and_open_proposals(): void {
		$this->enable();
		$r = $this->review();
		$r->consent_publish = false; // Text is anonymised too.
		Plugin::instance()->reviews()->update( $r );
		$svc = new CorrectionService( Plugin::instance() );
		$id  = $svc->propose( $r, array( 'body' => 'Neue Fassung mit Klarname Erika Musterfrau, lang genug.' ), 'link' );
		$this->assertIsInt( $id );

		( new PersonalData( Plugin::instance()->reviews(), Plugin::instance()->settings() ) )->erase( 'erika@example.org' );
		$after = Plugin::instance()->reviews()->find( (int) $r->id );
		$this->assertNotNull( $after, 'anonymised, not deleted (erase_mode anonymize)' );
		$this->assertStringNotContainsString( 'Ursprünglicher', $after->body );
		$this->assertSame( array(), CorrectionService::versions( (int) $r->id ), 'no earlier or proposed text of the person stays' );

		// The open proposal can no longer bring the text back over the anonymised review.
		$this->login_with( 'review_captain_moderate' );
		$this->assertTrue( is_wp_error( $svc->approve( $id ) ) );
		$this->assertStringNotContainsString( 'Musterfrau', Plugin::instance()->reviews()->find( (int) $r->id )->body );
	}
}
