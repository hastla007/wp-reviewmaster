<?php
/**
 * Privacy integration (RC-047).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Privacy;

use ReviewCaptain\Module;
use ReviewCaptain\Plugin;

/**
 * Registers WordPress personal data exporter/eraser and a privacy policy text suggestion.
 */
final class PrivacyModule implements Module {

	private ?Plugin $plugin = null;

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		$this->plugin = $plugin;
		$plugin->factory( 'privacy', static fn( Plugin $p ) => new PersonalData( $p->reviews(), $p->settings() ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
	}

	/**
	 * Personal data service.
	 */
	public function personal_data(): PersonalData {
		$plugin = $this->plugin ?? Plugin::instance();
		/** @var PersonalData */
		return $plugin->has( 'privacy' ) ? $plugin->get( 'privacy' ) : new PersonalData( $plugin->reviews(), $plugin->settings() );
	}

	/**
	 * Add exporter.
	 *
	 * @param mixed $exporters Exporters.
	 * @return array<string, array{exporter_friendly_name: string, callback: callable}>
	 */
	public function register_exporter( mixed $exporters ): array {
		$exporters                           = is_array( $exporters ) ? $exporters : array();
		$exporters[ PersonalData::GROUP_ID ] = array(
			'exporter_friendly_name' => __( 'Review Captain', 'review-captain' ),
			'callback'               => fn( string $email, int $page = 1 ): array => $this->personal_data()->export( $email, $page ),
		);
		return $exporters;
	}

	/**
	 * Add eraser.
	 *
	 * @param mixed $erasers Erasers.
	 * @return array<string, array{eraser_friendly_name: string, callback: callable}>
	 */
	public function register_eraser( mixed $erasers ): array {
		$erasers                           = is_array( $erasers ) ? $erasers : array();
		$erasers[ PersonalData::GROUP_ID ] = array(
			'eraser_friendly_name' => __( 'Review Captain', 'review-captain' ),
			'callback'             => fn( string $email, int $page = 1 ): array => $this->personal_data()->erase( $email, $page ),
		);
		return $erasers;
	}

	/**
	 * Suggested privacy policy text (German).
	 */
	public function add_policy_content(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'Review Captain', 'review-captain' ), wp_kses_post( self::policy_text() ) );
		}
	}

	/**
	 * Policy text suggestion.
	 */
	public static function policy_text(): string {
		$parts = array(
			'<p><strong>' . __( 'Vorschlag – bitte an Ihre tatsächliche Nutzung anpassen.', 'review-captain' ) . '</strong></p>',
			'<p>' . __( 'Wenn Sie auf dieser Website eine Bewertung oder einen Erfahrungsbericht abgeben, speichern wir Ihre Bewertung, den Text, den von Ihnen angegebenen Namen sowie Ihre E-Mail-Adresse und – falls Sie angemeldet sind – die Kennung Ihres Benutzerkontos. Die E-Mail-Adresse wird nicht veröffentlicht; sie dient der Rückfrage, der Missbrauchsabwehr und der Zuordnung bei Auskunfts- und Löschanfragen.', 'review-captain' ) . '</p>',
			'<p>' . __( 'Ihre Bewertung wird erst nach Prüfung und nur dann veröffentlicht, wenn Sie der Veröffentlichung zugestimmt haben. Den Zeitpunkt Ihrer Einwilligung speichern wir als Nachweis.', 'review-captain' ) . '</p>',
			'<p>' . __( 'Bewertungen von externen Plattformen (z. B. Google oder Trustpilot) werden nur mit dem dort sichtbaren Anzeigenamen, Datum, Text und einem Link zur Quelle angezeigt und regelmäßig mit der Quelle abgeglichen; wird eine Bewertung dort gelöscht, entfernen wir sie auch hier.', 'review-captain' ) . '</p>',
			'<p>' . __( 'Sie können über die Datenschutz-Werkzeuge dieser Website Auskunft über Ihre gespeicherten Bewertungen verlangen sowie deren Löschung oder Anonymisierung beantragen. Bei einer Anonymisierung wird Ihr Name durch „Anonym“ ersetzt und Ihre E-Mail-Adresse entfernt; der Bewertungstext bleibt nur erhalten, wenn Sie seiner Veröffentlichung zugestimmt hatten.', 'review-captain' ) . '</p>',
			'<p>' . __( 'Falls aktiviert: Ein mit der Bewertung hochgeladenes Foto wird vor dem Speichern neu kodiert, wobei Bildmetadaten wie der Aufnahmeort entfernt werden; es wird erst nach Prüfung angezeigt und mit der Bewertung gelöscht. Produktfragen speichern wir mit Name und optional E-Mail-Adresse; Meldungen von Bewertungen speichern wir ohne Angaben zur meldenden Person.', 'review-captain' ) . '</p>',
			'<p>' . __( 'Abgelehnte Einreichungen und technische Protokolle werden nach den eingestellten Aufbewahrungsfristen automatisch gelöscht.', 'review-captain' ) . '</p>',
		);
		// SET-RC-08: only when the operator set a retention period for the contact address.
		$contact_days = (int) \ReviewCaptain\Plugin::instance()->settings()->get( 'privacy', 'retention_contact_days', 0 );
		if ( $contact_days > 0 ) {
			/* translators: %s: number of days */
			$parts[] = '<p>' . sprintf( __( 'Die E-Mail-Adresse zu einer Bewertung löschen wir %s Tage nach Eingang der Bewertung; die Bewertung selbst bleibt erhalten.', 'review-captain' ), number_format_i18n( $contact_days ) ) . '</p>';
		}
		return implode( "\n", $parts );
	}
}
