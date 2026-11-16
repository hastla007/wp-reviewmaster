<?php
/**
 * German labels, help texts and sections for the settings schema (derived from docs/settings/reference.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

/**
 * Presentation metadata only. The schema (types, defaults, validation) stays in SettingsSchema.
 * Fields added via `review_captain_settings_schema` may carry their own 'label'/'description'.
 */
final class SettingsFields {

	/**
	 * Label + description per tab/key.
	 *
	 * @return array<string, array<string, array{0: string, 1: string}>>
	 */
	public static function texts(): array {
		return array(
			'general'     => array(
				'brand_name'        => array( __( 'Marken- bzw. Firmenname', 'review-captain' ), __( 'Erscheint in Hinweisen und Formularen. Leer = Titel deiner Website.', 'review-captain' ) ),
				'default_language'  => array( __( 'Standardsprache', 'review-captain' ), __( 'Sprachkürzel für Plugin-Texte im Frontend, z. B. de_DE. Leer = Sprache der Website. Reviewtexte werden nie übersetzt.', 'review-captain' ) ),
				'date_format'       => array( __( 'Datumsformat', 'review-captain' ), __( 'PHP-Datumsformat, z. B. d.m.Y. Leer = WordPress-Datumsformat. Es gilt die WordPress-Zeitzone.', 'review-captain' ) ),
				'new_review_status' => array( __( 'Status neuer Reviews', 'review-captain' ), __( 'Gilt für Einreichungen über das Formular. „Sofort freigeben“ veröffentlicht ungeprüfte Texte – empfohlen nur mit aktiver Moderation.', 'review-captain' ) ),
			),
			'sources'     => array(
				'sync_window_start'       => array( __( 'Synchronisierung ab', 'review-captain' ), __( 'Beginn des Zeitfensters für automatische Abgleiche (Website-Zeit).', 'review-captain' ) ),
				'sync_window_end'         => array( __( 'Synchronisierung bis', 'review-captain' ), __( 'Ende des Zeitfensters. Ende vor Beginn = Fenster über Mitternacht.', 'review-captain' ) ),
				'removed_review_behavior' => array( __( 'Bei der Quelle gelöschte Reviews', 'review-captain' ), __( 'Was passiert, wenn ein Review bei der Plattform gelöscht wurde oder nicht mehr abrufbar ist.', 'review-captain' ) ),
				'max_pages_per_run'       => array( __( 'Seiten je Abruf', 'review-captain' ), __( 'Höhere Werte holen große Bestände schneller, belasten aber Server und Anbieterkontingent.', 'review-captain' ) ),
				'pause_after_auth_errors' => array( __( 'Pause nach Anmeldefehlern', 'review-captain' ), __( 'Nach so vielen aufeinanderfolgenden Anmeldefehlern wird die Quelle pausiert. Vorhandene Reviews bleiben sichtbar.', 'review-captain' ) ),
			),
			'requests'    => array(
				'sender_name'       => array( __( 'Absendername', 'review-captain' ), __( 'Name im Absender von Einladungen. Leer = Markenname.', 'review-captain' ) ),
				'sender_email'      => array( __( 'Absenderadresse', 'review-captain' ), __( 'Ohne Absenderadresse wird nichts versendet. Die Domain muss für deinen Versanddienst eingerichtet sein.', 'review-captain' ) ),
				'send_window_start' => array( __( 'Versand ab', 'review-captain' ), __( 'Einladungen werden nur in diesem Zeitfenster verschickt (Website-Zeit).', 'review-captain' ) ),
				'send_window_end'   => array( __( 'Versand bis', 'review-captain' ), '' ),
				'daily_limit'       => array( __( 'Tageslimit', 'review-captain' ), __( 'Höchstzahl Einladungen pro Tag über alle Kampagnen.', 'review-captain' ) ),
				'cooldown_days'     => array( __( 'Mindestabstand je Empfänger (Tage)', 'review-captain' ), __( 'Innerhalb dieser Zeit erhält dieselbe Person keine weitere Einladung.', 'review-captain' ) ),
				'reminders_enabled' => array( __( 'Erinnerungen erlauben', 'review-captain' ), __( 'Kampagnen dürfen eine einzelne Erinnerung senden. Abmeldung stoppt jede Erinnerung.', 'review-captain' ) ),
			),
			'submissions' => array(
				'enabled'             => array( __( 'Einreichungen aktiv', 'review-captain' ), __( 'Hauptschalter für das Bewertungsformular. Aus = Formulare zeigen einen neutralen Hinweis.', 'review-captain' ) ),
				'allow_title'         => array( __( 'Titelfeld anbieten', 'review-captain' ), '' ),
				'min_text_length'     => array( __( 'Mindestlänge des Textes', 'review-captain' ), __( 'Zeichen. Muss kleiner als die Höchstlänge sein.', 'review-captain' ) ),
				'max_text_length'     => array( __( 'Höchstlänge des Textes', 'review-captain' ), __( 'Zeichen.', 'review-captain' ) ),
				'require_consent'     => array( __( 'Einwilligung zur Veröffentlichung abfragen', 'review-captain' ), __( 'Pflicht-Kontrollkästchen im Formular. Aus = du musst die Veröffentlichungsgrundlage anders sicherstellen.', 'review-captain' ) ),
				'notify_admin'        => array( __( 'Admin benachrichtigen', 'review-captain' ), __( 'E-Mail mit Link zur Moderation – ohne Reviewtext und ohne Kontaktdaten.', 'review-captain' ) ),
				'honeypot'            => array( __( 'Spam-Falle', 'review-captain' ), __( 'Unsichtbares Feld, das automatisierte Einsendungen erkennt. Nonce-Prüfung und Rate-Limit bleiben immer aktiv.', 'review-captain' ) ),
				'rate_limit_per_hour' => array( __( 'Einreichungen pro Stunde', 'review-captain' ), __( 'Je Absender. Zur Zählung wird kurzzeitig ein gehashter Wert gespeichert, keine Klar-IP.', 'review-captain' ) ),
			),
			'display'     => array(
				'show_stars'     => array( __( 'Sterne anzeigen', 'review-captain' ), __( 'Sterne werden immer zusätzlich als Text für Screenreader ausgegeben.', 'review-captain' ) ),
				'show_date'      => array( __( 'Datum anzeigen', 'review-captain' ), '' ),
				'date_style'     => array( __( 'Datumsstil', 'review-captain' ), __( 'Relativ („vor 3 Tagen“) veraltet in zwischengespeicherten Seiten schneller.', 'review-captain' ) ),
				'show_author'    => array( __( 'Autor anzeigen', 'review-captain' ), __( 'Personenbezogen.', 'review-captain' ) ),
				'show_avatar'    => array( __( 'Profilbild anzeigen', 'review-captain' ), __( 'Personenbezogen; je nach WordPress-Einstellung wird dabei Gravatar kontaktiert.', 'review-captain' ) ),
				'empty_text'     => array( __( 'Text bei leerer Liste', 'review-captain' ), __( 'Leer = „Noch keine Bewertungen vorhanden.“', 'review-captain' ) ),
				'reduced_motion' => array( __( 'Bewegung reduzieren', 'review-captain' ), __( 'Keine automatischen Animationen oder Karussell-Rotation. Die Systemeinstellung der Besucher wird immer beachtet.', 'review-captain' ) ),
				'load_css'       => array( __( 'Plugin-CSS laden', 'review-captain' ), __( 'Aus = dein Theme muss die Ausgabe selbst gestalten (Klassen rc-…).', 'review-captain' ) ),
			),
			'privacy'     => array(
				'retention_external_days'  => array( __( 'Externe Reviews ohne Bestätigung (Tage)', 'review-captain' ), __( 'Wie lange ein Plattform-Review gespeichert bleibt, wenn es nicht mehr von der Quelle bestätigt wurde.', 'review-captain' ) ),
				'retention_rejected_days'  => array( __( 'Abgelehnte Reviews (Tage)', 'review-captain' ), __( 'Danach endgültige Löschung inklusive Verlauf.', 'review-captain' ) ),
				'privacy_notice'           => array( __( 'Datenschutzhinweis im Formular', 'review-captain' ), __( 'Leer = kurzer Standardtext mit Link zu deiner Datenschutzseite.', 'review-captain' ) ),
				'erase_mode'               => array( __( 'Verhalten bei Löschanfragen', 'review-captain' ), __( 'Was „Personenbezogene Daten löschen“ (Werkzeuge) mit Reviews dieser E-Mail-Adresse macht.', 'review-captain' ) ),
				'retention_logs_days'      => array( __( 'Protokolle (Tage)', 'review-captain' ), __( 'Protokolle enthalten keine Reviewtexte oder Zugangsdaten.', 'review-captain' ) ),
				'retention_jobs_days'      => array( __( 'Erledigte Hintergrundaufgaben (Tage)', 'review-captain' ), '' ),
				'delete_data_on_uninstall' => array( __( 'Daten beim Deinstallieren löschen', 'review-captain' ), __( 'An = beim Löschen des Plugins werden alle Review-Captain-Daten dieser Website entfernt. Daten bei Google, Trustpilot usw. werden nie verändert.', 'review-captain' ) ),
			),
			'access'      => array(
				'moderate_roles' => array( __( 'Wer darf moderieren', 'review-captain' ), __( 'Moderierende sehen eingereichte Reviews inklusive Kontaktadresse. Administratoren behalten immer alle Rechte.', 'review-captain' ) ),
				'settings_roles' => array( __( 'Wer darf Einstellungen ändern', 'review-captain' ), __( 'Administratoren behalten immer alle Rechte.', 'review-captain' ) ),
			),
			'advanced'    => array(
				'use_action_scheduler' => array( __( 'Action Scheduler verwenden', 'review-captain' ), __( 'Falls verfügbar (z. B. durch WooCommerce), sonst WP-Cron. Bei wenig Besucherverkehr empfehlen wir einen Server-Cron.', 'review-captain' ) ),
				'job_time_budget'      => array( __( 'Zeitbudget je Lauf (Sekunden)', 'review-captain' ), __( 'Nur ändern, wenn die Diagnose Zeitüberschreitungen meldet.', 'review-captain' ) ),
				'lease_seconds'        => array( __( 'Freigabe hängender Aufgaben (Sekunden)', 'review-captain' ), __( 'Muss länger als das Zeitbudget sein.', 'review-captain' ) ),
				'debug_logging'        => array( __( 'Fehlerprotokoll', 'review-captain' ), __( 'Enthält keine Zugangsdaten, Reviewtexte oder E-Mail-Adressen.', 'review-captain' ) ),
			),
		);
	}

	/**
	 * Sections per tab (heading => keys). Keys not listed land in "Weitere Optionen".
	 *
	 * @return array<string, array<string, list<string>>>
	 */
	public static function sections(): array {
		return array(
			'general'     => array(
				__( 'Identität', 'review-captain' )       => array( 'brand_name' ),
				__( 'Sprache & Datum', 'review-captain' ) => array( 'default_language', 'date_format' ),
				__( 'Neue Reviews', 'review-captain' )    => array( 'new_review_status' ),
			),
			'sources'     => array(
				__( 'Zeitfenster', 'review-captain' )       => array( 'sync_window_start', 'sync_window_end' ),
				__( 'Gelöschte Reviews', 'review-captain' ) => array( 'removed_review_behavior' ),
			),
			'requests'    => array(
				__( 'Absender', 'review-captain' )      => array( 'sender_name', 'sender_email' ),
				__( 'Versandzeiten', 'review-captain' ) => array( 'send_window_start', 'send_window_end' ),
				__( 'Grenzen', 'review-captain' )       => array( 'daily_limit', 'cooldown_days' ),
				__( 'Erinnerungen', 'review-captain' )  => array( 'reminders_enabled' ),
			),
			'submissions' => array(
				__( 'Formular', 'review-captain' )          => array( 'enabled', 'allow_title', 'min_text_length', 'max_text_length' ),
				__( 'Einwilligung', 'review-captain' )      => array( 'require_consent' ),
				__( 'Benachrichtigung', 'review-captain' )  => array( 'notify_admin' ),
			),
			'display'     => array(
				__( 'Inhalte', 'review-captain' )          => array( 'show_stars', 'show_date', 'date_style', 'show_author', 'show_avatar' ),
				__( 'Leerer Zustand', 'review-captain' )   => array( 'empty_text' ),
				__( 'Barrierefreiheit', 'review-captain' ) => array( 'reduced_motion' ),
			),
			'privacy'     => array(
				__( 'Aufbewahrung', 'review-captain' )       => array( 'retention_external_days', 'retention_rejected_days' ),
				__( 'Datenschutzhinweis', 'review-captain' ) => array( 'privacy_notice' ),
				__( 'Löschanfragen', 'review-captain' )      => array( 'erase_mode' ),
				__( 'Deinstallation', 'review-captain' )     => array( 'delete_data_on_uninstall' ),
			),
			'access'      => array(
				__( 'Moderation', 'review-captain' )    => array( 'moderate_roles' ),
				__( 'Einstellungen', 'review-captain' ) => array( 'settings_roles' ),
			),
			'advanced'    => array(
				__( 'Hintergrundaufgaben', 'review-captain' ) => array( 'use_action_scheduler', 'job_time_budget', 'lease_seconds' ),
				__( 'Fehlersuche', 'review-captain' )         => array( 'debug_logging' ),
			),
		);
	}

	/**
	 * Enum choice labels.
	 *
	 * @param string $tab   Tab.
	 * @param string $key   Key.
	 * @param string $value Choice.
	 */
	public static function choice_label( string $tab, string $key, string $value ): string {
		$map = array(
			'general.new_review_status'       => array(
				'pending'  => __( 'Prüfung ausstehend (empfohlen)', 'review-captain' ),
				'approved' => __( 'Sofort freigeben', 'review-captain' ),
			),
			'sources.removed_review_behavior' => array(
				'tombstone' => __( 'Inhalt entfernen, Platzhalter behalten', 'review-captain' ),
				'hide'      => __( 'Nur ausblenden (Inhalt bis Fristablauf speichern)', 'review-captain' ),
			),
			'display.date_style'              => array(
				'absolute' => __( 'Absolut (12.11.2026)', 'review-captain' ),
				'relative' => __( 'Relativ (vor 3 Tagen)', 'review-captain' ),
			),
			'privacy.erase_mode'              => array(
				'anonymize' => __( 'Anonymisieren (Text und Bewertung bleiben)', 'review-captain' ),
				'delete'    => __( 'Review vollständig löschen', 'review-captain' ),
			),
		);
		return $map[ $tab . '.' . $key ][ $value ] ?? $value;
	}

	/**
	 * Label of a field.
	 *
	 * @param string               $tab   Tab.
	 * @param string               $key   Key.
	 * @param array<string, mixed> $field Field definition.
	 */
	public static function label( string $tab, string $key, array $field = array() ): string {
		$texts = self::texts();
		if ( isset( $texts[ $tab ][ $key ] ) ) {
			return $texts[ $tab ][ $key ][0];
		}
		return isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : $key;
	}

	/**
	 * Description of a field.
	 *
	 * @param string               $tab   Tab.
	 * @param string               $key   Key.
	 * @param array<string, mixed> $field Field definition.
	 */
	public static function description( string $tab, string $key, array $field = array() ): string {
		$texts = self::texts();
		if ( isset( $texts[ $tab ][ $key ] ) ) {
			return $texts[ $tab ][ $key ][1];
		}
		return isset( $field['description'] ) && is_string( $field['description'] ) ? $field['description'] : '';
	}
}
