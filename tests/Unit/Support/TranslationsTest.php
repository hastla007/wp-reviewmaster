<?php
/**
 * SET-RC-14: English source strings, de_DE ("du") and de_DE_formal ("Sie") complete and consistent,
 * de_AT/de_CH derived. The German output of real code paths is checked through the compiled .mo files.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Support;

use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Moderation\CorrectionHandler;
use ReviewCaptain\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/bin/check-i18n-language.php';

final class TranslationsTest extends TestCase {

	private const LANG = __DIR__ . '/../../../languages/';

	protected function tearDown(): void {
		rc_test_load_translations( null );
		parent::tearDown();
	}

	/**
	 * Gettext keys of a PO/POT file.
	 *
	 * @return array<string, list<string>> key => translations
	 */
	private function entries( string $file ): array {
		$out = array();
		foreach ( rc_i18n_read_po( self::LANG . $file ) as $e ) {
			$out[ ( null === $e['ctx'] ? '' : $e['ctx'] . "\x04" ) . $e['id'] ] = $e['str'];
		}
		return $out;
	}

	public function test_language_checks_pass(): void {
		$this->assertSame( array(), rc_i18n_language_problems( dirname( __DIR__, 3 ) ) );
	}

	public function test_every_pot_entry_is_translated_in_each_german_file(): void {
		$pot = $this->entries( 'review-captain.pot' );
		$this->assertGreaterThan( 1200, count( $pot ) );
		foreach ( array( 'de_DE', 'de_DE_formal', 'de_AT', 'de_CH' ) as $locale ) {
			$po = $this->entries( "review-captain-$locale.po" );
			$this->assertSame( array(), array_keys( array_diff_key( $pot, $po ) ), "$locale: missing" );
			$this->assertSame( array(), array_keys( array_diff_key( $po, $pot ) ), "$locale: obsolete" );
			foreach ( $po as $key => $forms ) {
				$this->assertNotSame( '', implode( '', $forms ), "$locale: untranslated $key" );
			}
		}
		$this->assertFileDoesNotExist( self::LANG . 'review-captain-en_US.po', 'English is the source language' );
	}

	public function test_heuristics_find_the_typical_cases(): void {
		$this->assertNotNull( rc_i18n_german_source( 'Bitte gib eine ganze Zahl ein.' ) );
		$this->assertNotNull( rc_i18n_german_source( 'Einstellungen gespeichert.' ) );
		$this->assertNotNull( rc_i18n_german_source( 'Zur Prüfung' ) );
		$this->assertNull( rc_i18n_german_source( 'Please enter a whole number.' ) );
		$this->assertNull( rc_i18n_german_source( 'To switch it on, type DELETE (or LÖSCHEN).' ), 'all-caps confirmation word' );

		$this->assertNotNull( rc_i18n_address_problem( 'Bitte prüfen Sie Ihre Angaben.', 'du' ) );
		$this->assertNotNull( rc_i18n_address_problem( 'Danke für Ihre Meldung.', 'du' ) );
		$this->assertNull( rc_i18n_address_problem( 'Bitte prüfe deine Angaben.', 'du' ) );
		$this->assertNull( rc_i18n_address_problem( 'Sie wartet auf Moderation.', 'du' ), '"it", not the reader' );

		$this->assertNotNull( rc_i18n_address_problem( 'Bitte prüfe deine Angaben.', 'Sie' ) );
		$this->assertNotNull( rc_i18n_address_problem( 'Wähle eine Quelle.', 'Sie' ) );
		$this->assertNotNull( rc_i18n_address_problem( 'Bitte lade die Seite neu.', 'Sie' ) );
		$this->assertNotNull( rc_i18n_address_problem( 'Du kannst das später ändern.', 'Sie' ) );
		$this->assertNull( rc_i18n_address_problem( 'Bitte laden Sie die Seite neu.', 'Sie' ) );
		$this->assertNull( rc_i18n_address_problem( 'Versuche', 'Sie' ), 'noun "attempts"' );
	}

	public function test_public_texts_use_du_in_de_DE_and_sie_in_de_DE_formal(): void {
		rc_test_load_translations( 'de_DE' );
		$this->assertSame( 'Wenn zu dieser Adresse veröffentlichte Bewertungen existieren, haben wir dir einen Link geschickt. Er ist eine Stunde gültig.', CorrectionHandler::message( 'link_sent' ) );
		$this->assertSame( 'Du hast bereits mehrere Bewertungen gesendet. Bitte versuche es später erneut.', FormHandler::error_message( 'rate_limited' ) );
		$this->assertSame( 'Dafür fehlt dir die Berechtigung.', __( 'You do not have permission to do this.', 'review-captain' ) );

		rc_test_load_translations( 'de_DE_formal' );
		$this->assertSame( 'Wenn zu dieser Adresse veröffentlichte Bewertungen existieren, haben wir Ihnen einen Link geschickt. Er ist eine Stunde gültig.', CorrectionHandler::message( 'link_sent' ) );
		$this->assertSame( 'Sie haben bereits mehrere Bewertungen gesendet. Bitte versuchen Sie es später erneut.', FormHandler::error_message( 'rate_limited' ) );
		$this->assertSame( 'Dafür fehlt Ihnen die Berechtigung.', __( 'You do not have permission to do this.', 'review-captain' ) );

		rc_test_load_translations( 'de_CH' );
		$this->assertSame( 'Wenn zu dieser Adresse veröffentlichte Bewertungen existieren, haben wir Ihnen einen Link geschickt. Er ist eine Stunde gültig.', CorrectionHandler::message( 'link_sent' ) );
		$this->assertSame( 'Bitte geben Sie Ihren Namen an (höchstens 100 Zeichen).', FormHandler::error_message( 'name' ) );

		rc_test_load_translations( null );
		$this->assertSame( 'You have already submitted several reviews. Please try again later.', FormHandler::error_message( 'rate_limited' ) );
	}

	public function test_context_and_plural_entries_are_translated(): void {
		rc_test_load_translations( 'de_DE' );
		$this->assertSame( '„%s“', _x( '“%s”', 'quotation marks', 'review-captain' ) );
		$this->assertSame( 'Ereignis', _x( 'Event', 'log entry', 'review-captain' ) );
		$this->assertSame( 'Veranstaltung', _x( 'Event', 'reference type', 'review-captain' ) );
		$this->assertSame( 'Event', __( 'Event', 'review-captain' ) );
		$this->assertSame( 'Bewertungsformular (Review Captain)', _x( 'Review form (Review Captain)', 'block title', 'review-captain' ) );
		$this->assertSame( '%s Stunden', _n( '%s hour', '%s hours', 24, 'review-captain' ) );
		$this->assertSame( '%s Stunde', _n( '%s hour', '%s hours', 1, 'review-captain' ) );
	}

	public function test_de_ch_writes_ss_instead_of_sharp_s(): void {
		rc_test_load_translations( 'de_CH' );
		$this->assertSame( 'Bewertung liegt ausserhalb der Skala', __( 'Rating is outside the scale', 'review-captain' ) );
		rc_test_load_translations( 'de_DE_formal' );
		$this->assertSame( 'Bewertung liegt außerhalb der Skala', __( 'Rating is outside the scale', 'review-captain' ) );
		$this->assertStringNotContainsString( 'ß', (string) file_get_contents( self::LANG . 'review-captain-de_CH.po' ) );
	}
}
