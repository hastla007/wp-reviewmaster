<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsFields;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;

/**
 * SET-RC-01, -02, -04, -05(d), -09, -26, -36: labels, help texts, sections, save and transfer.
 */
final class SalesTextsSettingsPageTest extends AdminTestCase {

	private const NEW_FIELDS = array(
		'display'     => array( 'verification_notice_show', 'verification_notice', 'verification_notice_position', 'label_purchase_verified', 'label_attendance_confirmed', 'label_unverified', 'label_external', 'label_testimonial', 'show_unverified_label', 'schema_enabled' ),
		'submissions' => array( 'consent_text', 'name_label', 'email_hint', 'submit_label', 'notify_recipients', 'notify_subject', 'notify_body', 'notify_questions', 'report_reasons', 'report_notify', 'report_confirmation', 'corrections_enabled', 'allow_title' ),
		'requests'    => array( 'send_window_end' ),
		'privacy'     => array( 'retention_jobs_days' ),
	);

	private function page(): SettingsPage {
		return new SettingsPage( $this->plugin() );
	}

	public function test_every_new_field_has_label_help_text_and_section(): void {
		$sections = SettingsFields::sections();
		foreach ( self::NEW_FIELDS as $tab => $keys ) {
			$in_sections = array_merge( ...array_values( $sections[ $tab ] ) );
			foreach ( $keys as $key ) {
				$this->assertNotSame( $key, SettingsFields::label( $tab, $key ), "$tab.$key label" );
				$this->assertNotSame( '', SettingsFields::description( $tab, $key ), "$tab.$key help text" );
				$this->assertContains( $key, $in_sections, "$tab.$key section" );
			}
		}
		$this->assertStringContainsString( 'Rechtstext – bitte prüfen lassen', SettingsFields::description( 'submissions', 'consent_text' ) );
		$this->assertStringContainsString( '{privacy_link}', SettingsFields::description( 'submissions', 'consent_text' ) );
		$this->assertStringContainsString( 'Rechtstext – bitte prüfen lassen', SettingsFields::description( 'display', 'verification_notice' ) );
	}

	public function test_render_submissions_and_display_tabs(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		update_option( Settings::OPTION, array( 'submissions' => array( 'notify_recipients' => array( 'a@example.test', 'b@example.test' ) ) ) );
		$_GET = array( 'page' => SettingsPage::SLUG, 'tab' => 'submissions' );
		ob_start();
		$this->page()->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Korrekturen durch Verfasser', $html );
		$this->assertStringNotContainsString( '>corrections_enabled<', $html, 'no raw key' );
		$this->assertStringContainsString( "a@example.test\nb@example.test</textarea>", $html );
		$this->assertStringContainsString( 'Vermutlich keine echte Erfahrung', $html, 'reason checkboxes show labels' );
		$this->assertStringContainsString( 'Text der Einwilligung', $html );

		$_GET = array( 'page' => SettingsPage::SLUG, 'tab' => 'display' );
		ob_start();
		$this->page()->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Bewertungs-Markup ausgeben (schema.org)', $html );
		$this->assertStringContainsString( 'Prüfung &amp; Transparenz', $html );
		$this->assertStringContainsString( 'Über der Liste bzw. dem Formular', $html );
	}

	public function test_save_reports_dropped_recipients(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$result = $this->page()->save_tab(
			'submissions',
			array(
				'submissions' => array(
					'notify_recipients' => "ok@example.test\nkaputt",
					'report_reasons'    => array( '', 'spam' ),
					'report_notify'     => 'none',
					'consent_text'      => 'Eigener Text {privacy_link}',
				),
			)
		);
		$this->assertContains( 'submissions-notify_recipients', $result['invalid'] );
		$s = new Settings();
		$this->assertSame( array( 'ok@example.test' ), $s->get( 'submissions', 'notify_recipients' ) );
		$this->assertSame( array( 'spam' ), $s->get( 'submissions', 'report_reasons' ) );
		$this->assertSame( 'none', $s->get( 'submissions', 'report_notify' ) );
		$this->assertSame( 'Eigener Text {privacy_link}', $s->get( 'submissions', 'consent_text' ) );

		$ok = $this->page()->save_tab( 'submissions', array( 'submissions' => array( 'notify_recipients' => 'x@example.test, y@example.test' ) ) );
		$this->assertNotContains( 'submissions-notify_recipients', $ok['invalid'] );
	}

	public function test_export_import_round_trip(): void {
		update_option(
			Settings::OPTION,
			array(
				'display'     => array( 'verification_notice' => 'So prüfen wir.', 'label_unverified' => 'Ohne Nachweis', 'show_unverified_label' => true ),
				'submissions' => array( 'notify_recipients' => array( 'mod@example.test' ), 'report_reasons' => array( 'spam' ), 'submit_label' => 'Los' ),
			)
		);
		$transfer = new ConfigTransfer( new Settings() );
		$doc      = $transfer->export();
		$this->assertSame( 'So prüfen wir.', $doc['settings']['display']['verification_notice'] );
		$this->assertSame( array( 'mod@example.test' ), $doc['settings']['submissions']['notify_recipients'] );

		update_option( Settings::OPTION, array() );
		$preview = ( new ConfigTransfer( new Settings() ) )->preview( (string) wp_json_encode( $doc ) );
		$this->assertNotNull( $preview );
		$this->assertSame( 'Ohne Nachweis', $preview['values']['display']['label_unverified'] );
		$this->assertTrue( $preview['values']['display']['show_unverified_label'] );
		$this->assertSame( array( 'spam' ), $preview['values']['submissions']['report_reasons'] );
		$this->assertSame( 'Los', $preview['values']['submissions']['submit_label'] );

		$doc['settings']['submissions']['notify_recipients'] = array( 'kaputt', 'gut@example.test' );
		$doc['settings']['submissions']['report_reasons']    = array( 'evil' );
		$bad = ( new ConfigTransfer( new Settings() ) )->preview( (string) wp_json_encode( $doc ) );
		$this->assertSame( array( 'gut@example.test' ), $bad['values']['submissions']['notify_recipients'] );
		$this->assertSame( array( 'spam', 'offensive', 'personal_data', 'fake', 'other' ), $bad['values']['submissions']['report_reasons'] );
	}
}
