<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Settings;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Moderation\ReportHandler;
use ReviewCaptain\Plugin;
use ReviewCaptain\Questions\QuestionHandler;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\Unit\M7\M7TestCase;

/**
 * SET-RC-01, -02, -04, -09, -26: configurable public texts, verification notice, notifications, reports.
 */
final class SalesTextsTest extends M7TestCase {

	protected function setUp(): void {
		parent::setUp();
		update_option( 'admin_email', 'admin@example.test' );
	}

	// ---------------------------------------------------------------- schema

	public function test_defaults_keep_todays_behaviour(): void {
		$s = new Settings();
		$this->assertTrue( $s->get( 'display', 'verification_notice_show' ) );
		$this->assertSame( '', $s->get( 'display', 'verification_notice' ) );
		$this->assertSame( 'below', $s->get( 'display', 'verification_notice_position' ) );
		$this->assertFalse( $s->get( 'display', 'show_unverified_label' ) );
		foreach ( array( 'label_purchase_verified', 'label_attendance_confirmed', 'label_unverified', 'label_external', 'label_testimonial' ) as $key ) {
			$this->assertSame( '', $s->get( 'display', $key ), $key );
		}
		foreach ( array( 'consent_text', 'email_hint', 'submit_label', 'name_label', 'notify_subject', 'notify_body', 'report_confirmation' ) as $key ) {
			$this->assertSame( '', $s->get( 'submissions', $key ), $key );
		}
		$this->assertSame( array(), $s->get( 'submissions', 'notify_recipients' ) );
		$this->assertSame( array( 'spam', 'offensive', 'personal_data', 'fake', 'other' ), $s->get( 'submissions', 'report_reasons' ) );
		$this->assertSame( 'recipients', $s->get( 'submissions', 'report_notify' ) );
		$this->assertTrue( $s->get( 'submissions', 'notify_questions' ) );
	}

	public function test_sanitize_valid_and_invalid_values(): void {
		$out = ( new Settings() )->sanitize(
			array(
				'display'     => array(
					'verification_notice'          => "Zeile 1\n\n<b>Zeile 2</b>",
					'verification_notice_position' => 'sideways',
					'label_purchase_verified'      => str_repeat( 'x', 200 ),
				),
				'submissions' => array(
					'notify_recipients' => "a@example.test, kaputt\nB@example.test;a@example.test",
					'report_reasons'    => array( 'spam', 'evil', 'fake' ),
					'report_notify'     => 'everyone',
					'submit_label'      => '<script>x</script>Absenden',
				),
			)
		);
		$this->assertSame( "Zeile 1\n\nZeile 2", $out['display']['verification_notice'] );
		$this->assertSame( 'below', $out['display']['verification_notice_position'] );
		$this->assertSame( 60, mb_strlen( $out['display']['label_purchase_verified'] ) );
		$this->assertSame( array( 'a@example.test', 'B@example.test' ), $out['submissions']['notify_recipients'] );
		$this->assertSame( array( 'spam', 'fake' ), $out['submissions']['report_reasons'] );
		$this->assertSame( 'recipients', $out['submissions']['report_notify'] );
		$this->assertStringNotContainsString( '<script>', $out['submissions']['submit_label'] );

		$none = ( new Settings() )->sanitize( array( 'submissions' => array( 'report_reasons' => array( '' ) ) ) );
		$this->assertSame( SettingsSchema::fields()['submissions']['report_reasons']['default'], $none['submissions']['report_reasons'], 'at least one reason' );

		$many = SettingsSchema::sanitize_email_list( SettingsSchema::fields()['submissions']['notify_recipients'], array_map( static fn( $i ) => "m{$i}@example.test", range( 1, 15 ) ) );
		$this->assertCount( 10, $many );
	}

	public function test_reset_restores_text_defaults(): void {
		$this->enable(
			array(
				'display'     => array( 'verification_notice' => 'Eigen', 'label_unverified' => 'Ohne Nachweis' ),
				'submissions' => array( 'consent_text' => 'Eigen', 'notify_recipients' => array( 'x@example.test' ) ),
			)
		);
		$s = Plugin::instance()->settings();
		$s->reset( array( 'display', 'submissions' ) );
		$s->flush();
		$this->assertSame( '', $s->get( 'display', 'verification_notice' ) );
		$this->assertSame( '', $s->get( 'display', 'label_unverified' ) );
		$this->assertSame( '', $s->get( 'submissions', 'consent_text' ) );
		$this->assertSame( array(), $s->get( 'submissions', 'notify_recipients' ) );
	}

	// ---------------------------------------------------------------- SET-RC-01

	public function test_verification_notice_on_widget_and_form_and_switch_off(): void {
		$this->review( array( 'verification' => Verification::PURCHASE_VERIFIED ) );
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( '<details class="rc-verification">', $html );
		$this->assertStringContainsString( 'So prüfen wir Bewertungen', $html );
		$this->assertStringContainsString( '„Verifizierter Kauf“: Der Kauf wurde', $html );
		$this->assertStringNotContainsString( 'Teilnahme', $html, 'only levels that occur' );
		$this->assertStringNotContainsString( 'externen Plattform', $html );
		$this->assertGreaterThan( strpos( $html, 'rc-widget__items' ), strpos( $html, 'rc-verification' ), 'below by default' );

		$form = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'rc-verification', $form );
		$this->assertStringContainsString( 'Bewertungen ohne Prüfkennzeichen', $form, 'form adds the unverified level' );

		$this->enable( array( 'display' => array( 'verification_notice_show' => false ) ) );
		$this->assertStringNotContainsString( 'rc-verification', $this->shortcode( array( 'v' => '1' ) ) );
		$this->assertStringNotContainsString( 'rc-verification', $this->module->shortcode_form( array( 'v' => '1' ) ) );
	}

	public function test_verification_notice_custom_text_position_and_filter(): void {
		$this->review( array( 'platform' => Platform::GOOGLE ) );
		$this->enable( array( 'display' => array( 'verification_notice' => 'Wir <b>prüfen</b> alles selbst.', 'verification_notice_position' => 'above' ) ) );
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'Wir &lt;b&gt;prüfen&lt;/b&gt; alles selbst.', $html, 'stored text is escaped on output' );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertLessThan( strpos( $html, 'rc-widget__items' ), strpos( $html, 'rc-verification' ), 'above the list' );

		add_filter( 'review_captain_verification_notice', static fn( $text, $context ) => 'form' === $context ? '' : 'Gefiltert', 10, 2 );
		$this->assertStringContainsString( 'Gefiltert', $this->shortcode( array( 'v' => '1' ) ) );
		$this->assertStringNotContainsString( 'rc-verification', $this->module->shortcode_form( array( 'v' => '1' ) ), 'filter wins, empty hides' );
	}

	public function test_verification_notice_title_configurable(): void {
		$this->review();
		$this->enable( array( 'display' => array( 'verification_notice_title' => 'Wie wir <prüfen>' ) ) );
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( '<summary class="rc-verification__toggle">Wie wir &lt;prüfen&gt;</summary>', $html );
		$this->assertStringNotContainsString( 'So prüfen wir Bewertungen', $html );
		$this->assertStringContainsString( 'Wie wir &lt;prüfen&gt;', $this->module->shortcode_form( array( 'v' => '1' ) ), 'same title at the form' );

		$this->enable( array( 'display' => array( 'verification_notice_title' => '' ) ) );
		$this->assertStringContainsString( 'So prüfen wir Bewertungen', $this->shortcode( array( 'v' => '1' ) ), 'empty = default' );
		$this->assertSame( '', SettingsSchema::fields()['display']['verification_notice_title']['default'] );
	}

	// ---------------------------------------------------------------- SET-RC-02

	public function test_level_labels_configurable_and_unverified_switch(): void {
		$this->review( array( 'verification' => Verification::PURCHASE_VERIFIED, 'author_name' => 'K' ) );
		$this->review( array( 'verification' => Verification::ATTENDANCE_CONFIRMED, 'author_name' => 'T' ) );
		$this->review( array( 'kind' => Kind::TESTIMONIAL, 'author_name' => 'E' ) );
		$this->review( array( 'platform' => Platform::GOOGLE ) );
		$this->review( array( 'author_name' => 'U' ) );

		$html = $this->shortcode( array( 'v' => '1', 'limit' => '10' ) );
		$this->assertStringContainsString( '<span class="rc-verified rc-verified--attendance">Teilnahme bestätigt</span>', $html );
		$this->assertStringContainsString( '<span class="rc-badge rc-badge--external">Externe Plattformbewertung</span>', $html );
		$this->assertStringNotContainsString( 'rc-badge--unverified', $html, 'unverified label off by default' );

		$this->enable(
			array(
				'display' => array(
					'label_purchase_verified' => 'Gekauft bei uns',
					'label_testimonial'       => 'Kundenstimme',
					'label_unverified'        => 'Ohne Nachweis',
					'show_unverified_label'   => true,
				),
			)
		);
		$html = $this->shortcode( array( 'v' => '1', 'limit' => '10' ) );
		$this->assertStringContainsString( '<span class="rc-verified">Gekauft bei uns</span>', $html );
		$this->assertStringContainsString( '<span class="rc-badge rc-badge--testimonial">Kundenstimme</span>', $html );
		$this->assertSame( 1, substr_count( $html, '<span class="rc-badge rc-badge--unverified">Ohne Nachweis</span>' ), 'only the unverified review, not the testimonial' );
		$this->assertStringContainsString( '„Ohne Nachweis“: Ein Kauf oder eine Nutzung', $html );

		// Only the text changes: the stored level of each review stays untouched.
		$levels = array_map( static fn( $r ) => $r->verification->value, Plugin::instance()->reviews()->query( array( 'limit' => 10 ) ) );
		$this->assertSame( 1, count( array_keys( $levels, 'purchase_verified', true ) ) );
	}

	// ---------------------------------------------------------------- SET-RC-04

	public function test_form_texts_default_and_custom_with_privacy_link(): void {
		$form = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'Ich willige ein, dass meine Bewertung mit meinem Namen', $form );
		$this->assertStringContainsString( 'Name (wird veröffentlicht)', $form );
		$this->assertStringContainsString( 'Wird nie veröffentlicht.', $form );
		$this->assertStringContainsString( '>Bewertung senden</button>', $form );

		add_filter( 'review_captain_privacy_policy_url', static fn() => 'https://example.test/datenschutz/' );
		$this->enable(
			array(
				'submissions' => array(
					'consent_text' => 'Ich stimme der Veröffentlichung zu. Details: {privacy_link} <i>x</i>',
					'name_label'   => 'Vorname',
					'email_hint'   => 'Nur für Rückfragen.',
					'submit_label' => 'Abschicken',
				),
			)
		);
		$form = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'Details: <a href="https://example.test/datenschutz/">Datenschutzerklärung</a>', $form );
		$this->assertStringNotContainsString( '<i>', $form );
		$this->assertStringContainsString( 'Vorname', $form );
		$this->assertStringContainsString( 'Nur für Rückfragen.', $form );
		$this->assertStringContainsString( '>Abschicken</button>', $form );
		$this->assertStringNotContainsString( 'Ich willige ein', $form );

		add_filter( 'review_captain_public_text', static fn( $text, $id ) => 'submissions.submit_label' === $id ? 'Per Filter' : $text, 10, 2 );
		$this->assertStringContainsString( '>Per Filter</button>', $this->module->shortcode_form( array( 'v' => '1' ) ) );
	}

	public function test_privacy_placeholder_without_privacy_page_is_plain_text(): void {
		$this->enable( array( 'submissions' => array( 'consent_text' => 'Siehe {privacy_link}.' ) ) );
		$form = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'Siehe Datenschutzerklärung.', $form );
		$this->assertStringNotContainsString( '{privacy_link}', $form );
	}

	// ---------------------------------------------------------------- SET-RC-09

	public function test_review_notification_recipients_subject_body_without_review_text(): void {
		$this->form()->process( $this->form_post() );
		$this->assertCount( 1, \RC_Test_State::$mails );
		$this->assertSame( array( 'admin@example.test' ), \RC_Test_State::$mails[0]['to'], 'default = admin_email' );

		\RC_Test_State::$mails = array();
		$this->enable(
			array(
				'submissions' => array(
					'notify_recipients' => array( 'mod1@example.test', 'mod2@example.test' ),
					'notify_subject'    => 'Neu auf {site}',
					'notify_body'       => "Status: {status}\nLink: {moderation_url}",
				),
			)
		);
		$this->form()->process( $this->form_post( array( 'rc_email' => 'zweite@example.test' ) ) );
		$this->assertCount( 1, \RC_Test_State::$mails );
		$mail = \RC_Test_State::$mails[0];
		$this->assertSame( array( 'mod1@example.test', 'mod2@example.test' ), $mail['to'] );
		$this->assertStringStartsWith( 'Neu auf ', $mail['subject'] );
		$this->assertStringContainsString( 'Status: wartet auf Moderation', $mail['message'] );
		$this->assertStringContainsString( 'admin.php?page=review-captain', $mail['message'] );
		$this->assertStringNotContainsString( 'ausführlich', $mail['message'] );
		$this->assertStringNotContainsString( 'zweite@', $mail['message'] );

		\RC_Test_State::$mails = array();
		add_filter( 'review_captain_notify_recipients', static fn() => array( 'filter@example.test' ) );
		$this->form()->process( $this->form_post( array( 'rc_email' => 'dritte@example.test' ) ) );
		$this->assertSame( array( 'filter@example.test' ), \RC_Test_State::$mails[0]['to'], 'filter wins' );
	}

	public function test_report_notification_and_reasons(): void {
		$this->enable( array( 'submissions' => array( 'reports_enabled' => true, 'report_reasons' => array( 'spam', 'fake' ) ) ) );
		$r    = $this->review();
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'value="spam"', $html );
		$this->assertStringContainsString( 'value="fake"', $html );
		$this->assertStringNotContainsString( 'value="offensive"', $html );

		$report = fn( string $reason ) => ( new ReportHandler( Plugin::instance() ) )->process(
			array(
				'_rc_report_nonce' => wp_create_nonce( ReportHandler::NONCE_ACTION ),
				'rc_review'        => $r->uuid,
				'rc_reason'        => $reason,
				'rc_return'        => 'https://example.test/',
			)
		);
		$this->assertSame( 'invalid', $report( 'offensive' )['error'], 'disabled reason rejected' );
		$this->assertCount( 0, \RC_Test_State::$mails );

		$this->assertTrue( $report( 'fake' )['ok'] );
		$this->assertCount( 1, \RC_Test_State::$mails );
		$mail = \RC_Test_State::$mails[0];
		$this->assertStringContainsString( 'Bewertung gemeldet', $mail['subject'] );
		$this->assertStringContainsString( 'Vermutlich keine echte Erfahrung', $mail['message'] );
		$this->assertStringContainsString( 'reported=1', $mail['message'] );
		$this->assertStringNotContainsString( 'Sehr guter Service', $mail['message'] );
	}

	public function test_report_notify_none_and_confirmation_text(): void {
		$this->enable( array( 'submissions' => array( 'reports_enabled' => true, 'report_notify' => 'none', 'report_confirmation' => 'Danke, wir schauen es uns an.' ) ) );
		$r = $this->review();
		( new ReportHandler( Plugin::instance() ) )->process(
			array(
				'_rc_report_nonce' => wp_create_nonce( ReportHandler::NONCE_ACTION ),
				'rc_review'        => $r->uuid,
				'rc_reason'        => 'spam',
				'rc_return'        => 'https://example.test/',
			)
		);
		$this->assertCount( 0, \RC_Test_State::$mails );

		$this->review();
		$_GET['rc_reported'] = '1';
		$html                = $this->shortcode( array( 'v' => '1' ) );
		unset( $_GET['rc_reported'] );
		$this->assertStringContainsString( 'Danke, wir schauen es uns an.', $html );
	}

	public function test_question_notification_switch(): void {
		$this->enable( array( 'submissions' => array( 'qa_enabled' => true ) ) );
		$ask = static function (): array {
			/** @var QuestionHandler $handler */
			$handler = Plugin::instance()->get( 'question_handler' );
			return $handler->process(
				array(
					'_rc_q_nonce'     => wp_create_nonce( QuestionHandler::NONCE_ACTION ),
					'rc_ts'           => FormHandler::sign_timestamp( time() - 10 ),
					'rc_context_type' => 'product',
					'rc_context_id'   => '42',
					'rc_context_sig'  => FormHandler::sign_context( 'product', '42' ),
					'rc_return'       => 'https://example.test/produkt/42/',
					'rc_question'     => 'Ist das Gerät auch für Linkshänder geeignet?',
					'rc_name'         => 'Tom',
					'rc_email'        => 'tom@example.test',
					'rc_consent'      => '1',
				)
			);
		};
		$this->assertTrue( $ask()['ok'] );
		$this->assertCount( 1, \RC_Test_State::$mails );
		$this->assertStringContainsString( 'review-captain-questions', \RC_Test_State::$mails[0]['message'] );
		$this->assertStringNotContainsString( 'Linkshänder', \RC_Test_State::$mails[0]['message'] );
		$this->assertStringNotContainsString( 'tom@', \RC_Test_State::$mails[0]['message'] );

		\RC_Test_State::$mails = array();
		$this->enable( array( 'submissions' => array( 'notify_questions' => false ) ) );
		$this->assertTrue( $ask()['ok'] );
		$this->assertCount( 0, \RC_Test_State::$mails );
	}
}
