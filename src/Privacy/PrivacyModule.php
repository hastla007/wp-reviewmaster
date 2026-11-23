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
		add_filter( 'site_status_tests', array( $this, 'site_status_tests' ) );
	}

	/**
	 * Site Health: reminder while no retention period for the contact address is set (owner decision S9).
	 *
	 * @param mixed $tests Tests.
	 * @return array<string, mixed>
	 */
	public function site_status_tests( mixed $tests ): array {
		$tests                                       = is_array( $tests ) ? $tests : array();
		$tests['direct']                             = isset( $tests['direct'] ) && is_array( $tests['direct'] ) ? $tests['direct'] : array();
		$tests['direct']['review_captain_retention'] = array(
			'label' => __( 'Review Captain: Aufbewahrung der Kontaktadressen', 'review-captain' ),
			'test'  => array( $this, 'retention_status' ),
		);
		return $tests;
	}

	/**
	 * Result of the Site Health test "retention of contact addresses". Names no period itself –
	 * which period fits is a legal question (country profile SET-RC-15 only suggests one).
	 *
	 * @return array<string, mixed>
	 */
	public function retention_status(): array {
		$plugin = $this->plugin ?? Plugin::instance();
		$days   = (int) $plugin->settings()->get( 'privacy', 'retention_contact_days', 0 );
		$result = array(
			'label'       => __( 'Für die Kontaktadressen zu Bewertungen ist eine Aufbewahrungsfrist eingestellt', 'review-captain' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Datenschutz', 'review-captain' ),
				'color' => 'blue',
			),
			/* translators: %s: number of days */
			'description' => '<p>' . esc_html( sprintf( __( 'Review Captain entfernt die E-Mail-Adresse zu einer Bewertung %s Tage nach Eingang. Die Bewertung bleibt.', 'review-captain' ), number_format_i18n( $days ) ) ) . '</p>',
			'actions'     => '',
			'test'        => 'review_captain_retention',
		);
		if ( $days > 0 ) {
			return $result;
		}
		$result['badge']['color'] = 'orange';
		$result['label']          = __( 'Review Captain speichert Kontaktadressen zu Bewertungen unbegrenzt', 'review-captain' );
		$result['status']         = 'recommended';
		$result['description']    = '<p>' . esc_html__( 'Für die E-Mail-Adressen zu Bewertungen ist keine Aufbewahrungsfrist eingestellt. Lege im Reiter Datenschutz fest, nach wie vielen Tagen sie entfernt werden; die Bewertungen bleiben. Welche Frist für deine Website passt, lass bitte rechtlich prüfen.', 'review-captain' ) . '</p>';
		$result['actions']        = '<p><a href="' . esc_url( \ReviewCaptain\Admin\View::page_url( \ReviewCaptain\Admin\SettingsPage::SLUG, array( 'tab' => 'privacy' ) ) ) . '">' . esc_html__( 'Aufbewahrung einstellen', 'review-captain' ) . '</a></p>';
		return $result;
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
		$settings = \ReviewCaptain\Plugin::instance()->settings();
		// P3: the suggestion must not claim a moderation or a consent the settings switched off.
		$moderated = 'approved' !== $settings->get( 'general', 'new_review_status', 'pending' );
		$consent   = false !== $settings->get( 'submissions', 'require_consent', true );
		if ( $moderated && $consent ) {
			$publish = __( 'Ihre Bewertung wird erst nach Prüfung und nur dann veröffentlicht, wenn Sie der Veröffentlichung zugestimmt haben. Den Zeitpunkt Ihrer Einwilligung speichern wir als Nachweis.', 'review-captain' );
		} elseif ( $consent ) {
			$publish = __( 'Ihre Bewertung wird nur veröffentlicht, wenn Sie der Veröffentlichung zugestimmt haben. Den Zeitpunkt Ihrer Einwilligung speichern wir als Nachweis.', 'review-captain' );
		} elseif ( $moderated ) {
			$publish = __( 'Ihre Bewertung wird erst nach Prüfung veröffentlicht.', 'review-captain' );
		} else {
			$publish = '';
		}
		$parts = array(
			'<p><strong>' . __( 'Vorschlag – bitte an Ihre tatsächliche Nutzung anpassen.', 'review-captain' ) . '</strong></p>',
			'<p>' . __( 'Wenn Sie auf dieser Website eine Bewertung oder einen Erfahrungsbericht abgeben, speichern wir Ihre Bewertung, den Text, den von Ihnen angegebenen Namen sowie Ihre E-Mail-Adresse und – falls Sie angemeldet sind – die Kennung Ihres Benutzerkontos. Die E-Mail-Adresse wird nicht veröffentlicht; sie dient der Rückfrage, der Missbrauchsabwehr und der Zuordnung bei Auskunfts- und Löschanfragen.', 'review-captain' ) . '</p>',
			'' !== $publish ? '<p>' . $publish . '</p>' : '',
			'<p>' . __( 'Bewertungen von externen Plattformen (z. B. Google oder Trustpilot) werden nur mit dem dort sichtbaren Anzeigenamen, Datum, Text und einem Link zur Quelle angezeigt und regelmäßig mit der Quelle abgeglichen; wird eine Bewertung dort gelöscht, entfernen wir sie auch hier.', 'review-captain' ) . '</p>',
			'<p>' . __( 'Sie können über die Datenschutz-Werkzeuge dieser Website Auskunft über Ihre gespeicherten Bewertungen verlangen sowie deren Löschung oder Anonymisierung beantragen. Bei einer Anonymisierung wird Ihr Name durch „Anonym“ ersetzt und Ihre E-Mail-Adresse entfernt; der Bewertungstext bleibt nur erhalten, wenn Sie seiner Veröffentlichung zugestimmt hatten.', 'review-captain' ) . '</p>',
			'<p>' . ( $moderated
				? __( 'Falls aktiviert: Ein mit der Bewertung hochgeladenes Foto wird vor dem Speichern neu kodiert, wobei Bildmetadaten wie der Aufnahmeort entfernt werden; es wird erst nach Prüfung angezeigt und mit der Bewertung gelöscht. Produktfragen speichern wir mit Name und optional E-Mail-Adresse; Meldungen von Bewertungen speichern wir ohne Angaben zur meldenden Person.', 'review-captain' )
				: __( 'Falls aktiviert: Ein mit der Bewertung hochgeladenes Foto wird vor dem Speichern neu kodiert, wobei Bildmetadaten wie der Aufnahmeort entfernt werden; es wird mit der Bewertung angezeigt und mit ihr gelöscht. Produktfragen speichern wir mit Name und optional E-Mail-Adresse; Meldungen von Bewertungen speichern wir ohne Angaben zur meldenden Person.', 'review-captain' ) ) . '</p>',
			'<p>' . __( 'Abgelehnte Einreichungen und technische Protokolle werden nach den eingestellten Aufbewahrungsfristen automatisch gelöscht.', 'review-captain' ) . '</p>',
		);
		// SET-RC-08: only when the operator set a retention period for the contact address.
		$contact_days = (int) $settings->get( 'privacy', 'retention_contact_days', 0 );
		if ( $contact_days > 0 ) {
			/* translators: %s: number of days */
			$parts[] = '<p>' . sprintf( __( 'Die E-Mail-Adresse zu einer Bewertung löschen wir %s Tage nach Eingang der Bewertung; die Bewertung selbst bleibt erhalten.', 'review-captain' ), number_format_i18n( $contact_days ) ) . '</p>';
		}
		// P3: the period for approved own reviews (SET-RC-08) belongs into the suggestion as well.
		$approved_days = (int) $settings->get( 'privacy', 'retention_approved_days', 0 );
		if ( $approved_days > 0 ) {
			/* translators: %s: number of days */
			$parts[] = '<p>' . sprintf( __( 'Bewertungen, die Sie auf dieser Website abgegeben haben, löschen wir %s Tage nach Eingang vollständig.', 'review-captain' ), number_format_i18n( $approved_days ) ) . '</p>';
		}
		return implode( "\n", array_filter( $parts ) );
	}
}
