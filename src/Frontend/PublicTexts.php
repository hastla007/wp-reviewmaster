<?php
/**
 * Configurable public texts: form texts, verification labels and notice (SET-RC-01, -02, -04, -26).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Settings\Settings;

/**
 * Resolves public texts: stored setting, else the translated default; the filter
 * `review_captain_public_text` has the last word. Only texts are configurable – which
 * verification level a review carries is never influenced by these settings.
 */
final class PublicTexts {

	public const PRIVACY_PLACEHOLDER = '{privacy_link}';

	/**
	 * Option with every consent text version shown at the form (P3, proof of consent):
	 * version => {text, privacy_url, first_used (UTC)}. Not autoloaded; removed on uninstall with data.
	 */
	public const CONSENT_ARCHIVE_OPTION = 'review_captain_consent_texts';

	/**
	 * Pseudo level for testimonials (kind, not a verification state).
	 */
	public const TESTIMONIAL = 'testimonial';

	/**
	 * Translated defaults keyed by "tab.key".
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'submissions.consent_text'           => __( 'Ich willige ein, dass meine Bewertung mit meinem Namen auf dieser Website veröffentlicht wird. Meine E-Mail-Adresse wird nicht veröffentlicht. Die Einwilligung kann ich jederzeit widerrufen.', 'review-captain' ),
			'submissions.name_label'             => __( 'Name (wird veröffentlicht)', 'review-captain' ),
			'submissions.email_hint'             => __( 'Wird nie veröffentlicht. Wir nutzen sie nur für Rückfragen und Datenschutzauskünfte.', 'review-captain' ),
			'submissions.submit_label'           => __( 'Bewertung senden', 'review-captain' ),
			'submissions.report_confirmation'    => __( 'Danke für Ihre Meldung. Wir prüfen die Bewertung.', 'review-captain' ),
			'display.verification_notice_title'  => __( 'So prüfen wir Bewertungen', 'review-captain' ),
			'display.label_purchase_verified'    => __( 'Verifizierter Kauf', 'review-captain' ),
			'display.label_attendance_confirmed' => __( 'Teilnahme bestätigt', 'review-captain' ),
			'display.label_unverified'           => __( 'Ungeprüft', 'review-captain' ),
			'display.label_external'             => __( 'Externe Plattformbewertung', 'review-captain' ),
			'display.label_testimonial'          => __( 'Erfahrungsbericht dieser Website', 'review-captain' ),
		);
	}

	/**
	 * Effective text of a setting (empty = translated default; filter wins).
	 *
	 * @param Settings $settings Settings.
	 * @param string   $tab      Tab.
	 * @param string   $key      Key.
	 */
	public static function get( Settings $settings, string $tab, string $key ): string {
		$id   = $tab . '.' . $key;
		$text = trim( (string) $settings->get( $tab, $key, '' ) );
		if ( '' === $text ) {
			$text = self::defaults()[ $id ] ?? '';
		}
		/**
		 * Filter a configurable public text (form texts, verification labels, report confirmation).
		 *
		 * @param string $text Text (setting or translated default).
		 * @param string $id   "tab.key", e.g. "submissions.consent_text" or "display.label_unverified".
		 */
		$filtered = apply_filters( 'review_captain_public_text', $text, $id );
		return is_string( $filtered ) ? $filtered : $text;
	}

	/**
	 * Setting key of the label of a level.
	 *
	 * @param string $level Verification value or self::TESTIMONIAL.
	 */
	public static function label_key( string $level ): string {
		return match ( $level ) {
			Verification::PURCHASE_VERIFIED->value    => 'label_purchase_verified',
			Verification::ATTENDANCE_CONFIRMED->value => 'label_attendance_confirmed',
			Verification::EXTERNAL_PLATFORM->value    => 'label_external',
			self::TESTIMONIAL                         => 'label_testimonial',
			default                                   => 'label_unverified',
		};
	}

	/**
	 * Public label of a level.
	 *
	 * @param Settings $settings Settings.
	 * @param string   $level    Verification value or self::TESTIMONIAL.
	 */
	public static function level_label( Settings $settings, string $level ): string {
		return self::get( $settings, 'display', self::label_key( $level ) );
	}

	/**
	 * Consent text as HTML: escaped text, {privacy_link} replaced by a link to the privacy page
	 * (plain word if no privacy page is set).
	 *
	 * @param Settings $settings Settings.
	 */
	public static function consent_html( Settings $settings ): string {
		$html = esc_html( self::get( $settings, 'submissions', 'consent_text' ) );
		if ( ! str_contains( $html, self::PRIVACY_PLACEHOLDER ) ) {
			return $html;
		}
		$url  = self::privacy_url();
		$word = esc_html__( 'Datenschutzerklärung', 'review-captain' );
		$link = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . $word . '</a>' : $word;
		return str_replace( self::PRIVACY_PLACEHOLDER, $link, $html );
	}

	/**
	 * Version of the effective consent text (text and privacy link) for the proof of consent.
	 * The consent text is configurable (SET-RC-04), so a review records which version was
	 * agreed to; the text of each version is archived once in self::CONSENT_ARCHIVE_OPTION.
	 *
	 * @param Settings $settings Settings.
	 * @return string 16 hex characters.
	 */
	public static function consent_version( Settings $settings ): string {
		$text    = self::get( $settings, 'submissions', 'consent_text' );
		$url     = self::privacy_url();
		$version = substr( hash( 'sha256', $text . "\n" . $url ), 0, 16 );
		$archive = get_option( self::CONSENT_ARCHIVE_OPTION, array() );
		$archive = is_array( $archive ) ? $archive : array();
		if ( ! isset( $archive[ $version ] ) ) {
			$archive[ $version ] = array(
				'text'        => $text,
				'privacy_url' => $url,
				'first_used'  => gmdate( 'Y-m-d H:i:s' ),
			);
			update_option( self::CONSENT_ARCHIVE_OPTION, $archive, false );
		}
		return $version;
	}

	/**
	 * URL of the privacy policy page ('' = none).
	 */
	public static function privacy_url(): string {
		$url = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		/**
		 * Filter the privacy policy URL used for {privacy_link} in the consent text.
		 *
		 * @param string $url URL ('' = no link).
		 */
		$url = apply_filters( 'review_captain_privacy_policy_url', $url );
		return is_string( $url ) && preg_match( '#^https?://#i', $url ) ? $url : '';
	}

	/**
	 * Neutral draft of the verification notice. Describes only the given levels; no legal claims.
	 * Legal review pending (EU Omnibus directive) – operators replace it via display.verification_notice.
	 *
	 * @param Settings $settings Settings.
	 * @param array    $levels   Levels that occur: list of Verification values and/or self::TESTIMONIAL.
	 */
	public static function default_verification_notice( Settings $settings, array $levels ): string {
		$quote = static fn( string $level ): string => '„' . self::level_label( $settings, $level ) . '“';
		$out   = array();
		foreach ( array( Verification::PURCHASE_VERIFIED->value, Verification::ATTENDANCE_CONFIRMED->value, Verification::UNVERIFIED->value, Verification::EXTERNAL_PLATFORM->value, self::TESTIMONIAL ) as $level ) {
			if ( ! in_array( $level, $levels, true ) ) {
				continue;
			}
			switch ( $level ) {
				case Verification::PURCHASE_VERIFIED->value:
					/* translators: %s: label of the level in quotes */
					$out[] = sprintf( __( '%s: Der Kauf wurde von einem an diese Website angebundenen System (zum Beispiel dem Bestellsystem) bestätigt.', 'review-captain' ), $quote( $level ) );
					break;
				case Verification::ATTENDANCE_CONFIRMED->value:
					/* translators: %s: label of the level in quotes */
					$out[] = sprintf( __( '%s: Die Teilnahme wurde von einem an diese Website angebundenen System (zum Beispiel der Ticketverwaltung) bestätigt.', 'review-captain' ), $quote( $level ) );
					break;
				case Verification::UNVERIFIED->value:
					$out[] = $settings->get( 'display', 'show_unverified_label', false )
						/* translators: %s: label of the level in quotes */
						? sprintf( __( '%s: Ein Kauf oder eine Nutzung ist bei dieser Bewertung nicht nachgewiesen.', 'review-captain' ), $quote( $level ) )
						: __( 'Bewertungen ohne Prüfkennzeichen: Ein Kauf oder eine Nutzung ist nicht nachgewiesen.', 'review-captain' );
					break;
				case Verification::EXTERNAL_PLATFORM->value:
					/* translators: %s: label of the level in quotes */
					$out[] = sprintf( __( '%s: Die Bewertung stammt von einer externen Plattform und ist mit Quelle und Link zum Original gekennzeichnet. Ob und wie sie dort geprüft wurde, richtet sich nach den Regeln der Plattform.', 'review-captain' ), $quote( $level ) );
					break;
				case self::TESTIMONIAL:
					/* translators: %s: label of the level in quotes */
					$out[] = sprintf( __( '%s: Erfahrungsbericht, den diese Website selbst ausgewählt und veröffentlicht hat.', 'review-captain' ), $quote( $level ) );
					break;
			}
		}
		return implode( "\n\n", $out );
	}
}
