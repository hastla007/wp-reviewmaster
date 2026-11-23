<?php
/**
 * German labels, help texts and sections for the settings schema (derived from docs/settings/reference.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Moderation\ReportStore;

/**
 * Presentation metadata only. The schema (types, defaults, validation) stays in SettingsSchema.
 * Fields added via `review_captain_settings_schema` may carry their own 'label', 'description' and
 * 'section' (heading on the settings page; see SettingsPage::render()).
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
				'brand_name'        => array( __( 'Marken- bzw. Firmenname', 'review-captain' ), __( 'Erscheint in E-Mails an Bewertende (z. B. Korrektur-Link) und mit Review Captain Pro als Absender der Einladungen. Leer = Titel deiner Website.', 'review-captain' ) ),
				'date_format'       => array( __( 'Datumsformat', 'review-captain' ), __( 'PHP-Datumsformat, z. B. d.m.Y. Leer = WordPress-Datumsformat. Es gilt die WordPress-Zeitzone.', 'review-captain' ) ),
				'new_review_status' => array( __( 'Status neuer Reviews', 'review-captain' ), __( 'Gilt für Einreichungen über das Formular. „Sofort freigeben“ veröffentlicht ungeprüfte Texte – empfohlen nur mit aktiver Moderation.', 'review-captain' ) ),
				'show_pro_hints'    => array( __( 'Pro-Hinweise zeigen', 'review-captain' ), __( 'Zeigt am Ende einzelner Einstellungsbereiche graue Hinweiszeilen zu Funktionen von Review Captain Pro und den Kasten „In Review Captain Pro“ in der Übersicht. Aus = keine Hinweise. Am Funktionsumfang ändert das nichts.', 'review-captain' ) ),
			),
			'sources'     => array(
				'sync_window_start'       => array( __( 'Synchronisierung ab', 'review-captain' ), __( 'Beginn des Zeitfensters für automatische Abgleiche (Website-Zeit).', 'review-captain' ) ),
				'sync_window_end'         => array( __( 'Synchronisierung bis', 'review-captain' ), __( 'Ende des Zeitfensters. Ende vor Beginn = Fenster über Mitternacht.', 'review-captain' ) ),
				'removed_review_behavior' => array( __( 'Bei der Quelle gelöschte Reviews', 'review-captain' ), __( 'Was passiert, wenn die Plattform meldet, dass ein Review gelöscht wurde. In beiden Fällen ist es sofort nicht mehr sichtbar und wird nach der Frist für externe Reviews (Datenschutz) endgültig gelöscht.', 'review-captain' ) ),
				'max_pages_per_run'       => array( __( 'Seiten je Abruf', 'review-captain' ), __( 'Höhere Werte holen große Bestände schneller, belasten aber Server und Anbieterkontingent.', 'review-captain' ) ),
				'pause_after_auth_errors' => array( __( 'Pause nach Anmeldefehlern', 'review-captain' ), __( 'Nach so vielen aufeinanderfolgenden Anmeldefehlern wird die Quelle pausiert. Vorhandene Reviews bleiben sichtbar.', 'review-captain' ) ),
			),
			'submissions' => array(
				'enabled'             => array( __( 'Einreichungen aktiv', 'review-captain' ), __( 'Hauptschalter für das Bewertungsformular. Aus = Formulare zeigen einen neutralen Hinweis.', 'review-captain' ) ),
				'allow_title'         => array( __( 'Titelfeld anbieten', 'review-captain' ), __( 'Zeigt im Formular ein optionales Feld „Titel“. Gilt für alle Formulare der Website.', 'review-captain' ) ),
				'min_text_length'     => array( __( 'Mindestlänge des Textes', 'review-captain' ), __( 'Zeichen. Muss kleiner als die Höchstlänge sein.', 'review-captain' ) ),
				'max_text_length'     => array( __( 'Höchstlänge des Textes', 'review-captain' ), __( 'Zeichen.', 'review-captain' ) ),
				'require_consent'     => array( __( 'Einwilligung zur Veröffentlichung abfragen', 'review-captain' ), __( 'Pflicht-Kontrollkästchen im Formular. Aus = du musst die Veröffentlichungsgrundlage anders sicherstellen.', 'review-captain' ) ),
				'notify_admin'        => array( __( 'Bei neuen Bewertungen benachrichtigen', 'review-captain' ), __( 'E-Mail mit Link zur Moderation – ohne Reviewtext und ohne Kontaktdaten.', 'review-captain' ) ),
				'notify_recipients'   => array( __( 'Empfänger der Benachrichtigungen', 'review-captain' ), __( 'Eine E-Mail-Adresse je Zeile (oder durch Komma getrennt), höchstens 10. Leer = Administrator-Adresse der Website. Gilt für neue Bewertungen, Meldungen und Fragen.', 'review-captain' ) ),
				'notify_subject'      => array( __( 'Betreff der Benachrichtigung', 'review-captain' ), __( 'Für neue Bewertungen. Platzhalter: {site}, {moderation_url}, {status}. Leer = „[Website] Neue Bewertung eingegangen“.', 'review-captain' ) ),
				'notify_body'         => array( __( 'Text der Benachrichtigung', 'review-captain' ), __( 'Für neue Bewertungen. Platzhalter: {site}, {moderation_url}, {status}. Leer = Standardtext. Reviewtext und Kontaktdaten werden nie eingefügt.', 'review-captain' ) ),
				'notify_questions'    => array( __( 'Bei neuen Fragen benachrichtigen', 'review-captain' ), __( 'Wirkt nur mit aktiven Produktfragen (Q&A). E-Mail mit Link zur Moderation, ohne Fragetext und ohne Kontaktdaten.', 'review-captain' ) ),
				'consent_text'        => array( __( 'Text der Einwilligung', 'review-captain' ), __( 'Rechtstext – bitte prüfen lassen. Steht neben dem Pflicht-Kontrollkästchen. Platzhalter {privacy_link} = Link zu deiner Datenschutzseite. Leer = Standardtext.', 'review-captain' ) ),
				'name_label'          => array( __( 'Beschriftung Namensfeld', 'review-captain' ), __( 'Leer = „Name (wird veröffentlicht)“. Mach deutlich, dass der Name öffentlich erscheint.', 'review-captain' ) ),
				'email_hint'          => array( __( 'Hinweis unter dem E-Mail-Feld', 'review-captain' ), __( 'Rechtstext – bitte prüfen lassen. Leer = „Wird nie veröffentlicht. Wir nutzen sie nur für Rückfragen und Datenschutzauskünfte.“', 'review-captain' ) ),
				'submit_label'        => array( __( 'Beschriftung der Absende-Schaltfläche', 'review-captain' ), __( 'Leer = „Bewertung senden“.', 'review-captain' ) ),
				'honeypot'            => array( __( 'Spam-Falle', 'review-captain' ), __( 'Unsichtbares Feld, das automatisierte Einsendungen erkennt. Nonce-Prüfung und Rate-Limit bleiben immer aktiv.', 'review-captain' ) ),
				'rate_limit_per_hour' => array( __( 'Einreichungen pro Stunde', 'review-captain' ), __( 'Je Absender. Zur Zählung wird kurzzeitig ein gehashter Wert gespeichert, keine Klar-IP.', 'review-captain' ) ),
				'tags_enabled'               => array( __( 'Interne Tags', 'review-captain' ), __( 'Moderierende können Reviews mit internen Schlagwörtern versehen und danach filtern. Tags werden nie öffentlich angezeigt oder exportiert.', 'review-captain' ) ),
				'reports_enabled'            => array( __( 'Öffentliches Melden', 'review-captain' ), __( 'Zeigt unter jeder Bewertung einen Link „Melden“. Gemeldete Reviews werden bis zur Prüfung ausgeblendet. Über Meldende wird nichts gespeichert.', 'review-captain' ) ),
				'report_threshold'           => array( __( 'Meldungen bis zum Ausblenden', 'review-captain' ), __( 'Ab so vielen offenen Meldungen wird ein Review auf „Gemeldet“ gesetzt.', 'review-captain' ) ),
				'report_rate_limit_per_hour' => array( __( 'Meldungen pro Stunde', 'review-captain' ), __( 'Je Absender (gehashter Wert, keine Klar-IP).', 'review-captain' ) ),
				'photos_enabled'             => array( __( 'Foto-Upload im Formular', 'review-captain' ), __( 'Optional ein Foto (JPG, PNG, WebP) je Bewertung. Bilder werden neu kodiert (Metadaten wie GPS werden entfernt) und erst nach Freigabe angezeigt.', 'review-captain' ) ),
				'photo_max_kb'               => array( __( 'Maximale Fotogröße (KB)', 'review-captain' ), __( 'Größere Dateien werden abgelehnt. Das Limit des Servers (upload_max_filesize) gilt zusätzlich.', 'review-captain' ) ),
				'multi_product_enabled'      => array( __( 'Formular für mehrere Produkte', 'review-captain' ), __( 'Ein Formular mit context_ids bewertet mehrere Produkte auf einmal. Je Person und Produkt ist dann nur eine Bewertung möglich.', 'review-captain' ) ),
				'qa_enabled'                 => array( __( 'Produktfragen (Q&A)', 'review-captain' ), __( 'Fragen und Antworten über [review_captain_qa]. Getrennt von Bewertungen, moderiert, ohne Schema-Markup.', 'review-captain' ) ),
				'feedback_campaigns_enabled' => array( __( 'Feedback-Links und QR-Codes', 'review-captain' ), __( 'Erzeugt Feedback-Links mit QR-Code (SVG) und optionalem WhatsApp-Click-to-Chat-Link.', 'review-captain' ) ),
				'report_reasons'             => array( __( 'Meldegründe', 'review-captain' ), __( 'Diese Gründe stehen im Formular „Melden“ zur Auswahl. Mindestens ein Grund bleibt immer aktiv.', 'review-captain' ) ),
				'report_notify'              => array( __( 'Bei Meldungen benachrichtigen', 'review-captain' ), __( 'E-Mail an die Empfänger der Benachrichtigungen je angenommener Meldung, mit Grund und Link zur Moderation – ohne Angaben zur meldenden Person.', 'review-captain' ) ),
				'report_confirmation'        => array( __( 'Rückmeldung an Meldende', 'review-captain' ), __( 'Erscheint nach dem Absenden einer Meldung. Leer = „Danke für Ihre Meldung. Wir prüfen die Bewertung.“', 'review-captain' ) ),
				'corrections_enabled'        => array( __( 'Korrekturen durch Verfasser', 'review-captain' ), __( 'Wer eine Bewertung abgegeben hat, kann sie über einen Einmal-Link per E-Mail (oder angemeldet) korrigieren oder zurückziehen. Korrekturen werden erst nach deiner Freigabe sichtbar; die Karte zeigt dann „bearbeitet am …“.', 'review-captain' ) ),
				'correction_link_hours'      => array( __( 'Gültigkeit des Korrektur-Links (Stunden)', 'review-captain' ), __( 'Der Link funktioniert einmal und verfällt danach automatisch.', 'review-captain' ) ),
				'min_seconds'                => array( __( 'Mindest-Ausfüllzeit (Sekunden)', 'review-captain' ), __( 'Schneller abgeschickte Formulare gelten als automatisiert und werden mit einem neutralen Hinweis abgewiesen.', 'review-captain' ) ),
				'hold_words'                 => array( __( 'Wörter, die zur Prüfung zurückhalten', 'review-captain' ), __( 'Ein Wort oder eine Wortgruppe je Zeile, Groß-/Kleinschreibung egal. Enthält eine Bewertung einen Eintrag, wartet sie auf Moderation – auch wenn neue Reviews sofort freigegeben werden. Es wird nie etwas gelöscht.', 'review-captain' ) ),
				'hold_links'                 => array( __( 'Bewertungen mit Links zurückhalten', 'review-captain' ), __( 'Bewertungen mit Webadresse (http, www.) im Titel oder Text warten auf Moderation.', 'review-captain' ) ),
				'block_email_domains'        => array( __( 'Gesperrte E-Mail-Domains', 'review-captain' ), __( 'Eine Domain je Zeile, z. B. wegwerfmail.example. Einreichungen mit Adressen dieser Domains (auch Subdomains) werden mit einem neutralen Hinweis abgelehnt und nicht gespeichert.', 'review-captain' ) ),
				'photo_max_edge'             => array( __( 'Längste Fotokante (Pixel)', 'review-captain' ), __( 'Größere Fotos werden beim Neukodieren verkleinert. Je Bewertung bleibt es bei einem Foto.', 'review-captain' ) ),
				'captcha_provider'           => array( __( 'Spam-Schutz (Captcha)', 'review-captain' ), __( '„Eingebaute Rechenprüfung“ lässt den Browser im Hintergrund kurz rechnen (Proof of Work), bevor Bewertungen, Meldungen und Fragen angenommen werden. Kein Rätsel, keine Cookies, keine Anfrage an Dritte, keine personenbezogenen Daten. Ohne JavaScript kann das Formular dann nicht abgeschickt werden; Besucher sehen einen Hinweis. Externe Anbieter nur per Filter und nur nach Rechtsprüfung.', 'review-captain' ) ),
				'trusted_proxy_header'       => array( __( 'Absenderadresse hinter einem Proxy', 'review-captain' ), __( 'Nur einstellen, wenn deine Website hinter einem Reverse Proxy oder CDN liegt, der diese Kopfzeile selbst setzt. Sonst können Absender die Sendebegrenzung umgehen. Verwendet wird die letzte Adresse der Kopfzeile, gespeichert nur als Hash.', 'review-captain' ) ),
			),
			'display'     => array(
				'show_stars'     => array( __( 'Sterne anzeigen', 'review-captain' ), __( 'Sterne werden immer zusätzlich als Text für Screenreader ausgegeben.', 'review-captain' ) ),
				'show_date'      => array( __( 'Datum anzeigen', 'review-captain' ), __( 'Zeigt das Veröffentlichungsdatum, sonst das Eingangsdatum. Einzelne Widgets können abweichen.', 'review-captain' ) ),
				'date_style'     => array( __( 'Datumsstil', 'review-captain' ), __( 'Relativ („vor 3 Tagen“) veraltet in zwischengespeicherten Seiten schneller.', 'review-captain' ) ),
				'show_author'    => array( __( 'Autor anzeigen', 'review-captain' ), __( 'Personenbezogen.', 'review-captain' ) ),
				'show_avatar'    => array( __( 'Profilbild anzeigen', 'review-captain' ), __( 'Personenbezogen; je nach WordPress-Einstellung wird dabei Gravatar kontaktiert.', 'review-captain' ) ),
				'empty_text'     => array( __( 'Text bei leerer Liste', 'review-captain' ), __( 'Leer = „Noch keine Bewertungen vorhanden.“', 'review-captain' ) ),
				'reduced_motion' => array( __( 'Bewegung reduzieren', 'review-captain' ), __( 'Keine automatischen Animationen oder Karussell-Rotation. Die Systemeinstellung der Besucher wird immer beachtet.', 'review-captain' ) ),
				'load_css'       => array( __( 'Plugin-CSS laden', 'review-captain' ), __( 'Aus = dein Theme muss die Ausgabe selbst gestalten (Klassen rc-…).', 'review-captain' ) ),
				'show_photos'    => array( __( 'Fotos anzeigen', 'review-captain' ), __( 'Zeigt Fotos freigegebener Bewertungen in den Karten. Personenbezogen möglich.', 'review-captain' ) ),
				'schema_enabled' => array( __( 'Bewertungs-Markup ausgeben (schema.org)', 'review-captain' ), __( 'Strukturierte Daten für eigene, auf dieser Website abgegebene und freigegebene Bewertungen. Externe Bewertungen erhalten nie Markup. Aus = kein Bewertungs-Markup; die sichtbare Ausgabe bleibt gleich.', 'review-captain' ) ),
				'verification_notice_show'     => array( __( 'Hinweis „So prüfen wir Bewertungen“ anzeigen', 'review-captain' ), __( 'Aufklappbarer Hinweis an jedem Bewertungs-Widget und am Formular. Informiert, ob und wie Bewertungen geprüft werden.', 'review-captain' ) ),
				'verification_notice_title'    => array( __( 'Überschrift des Hinweises', 'review-captain' ), __( 'Text des aufklappbaren Links, z. B. „So prüfen wir Bewertungen“. Leer = dieser Standardtext.', 'review-captain' ) ),
				'verification_notice'          => array( __( 'Text „So prüfen wir Bewertungen“', 'review-captain' ), __( 'Rechtstext – bitte prüfen lassen (Transparenzpflicht, je Land). Leer = neutraler Entwurf, der nur die Prüfstufen beschreibt, die auf deiner Website vorkommen. Beschreibe nur, was du wirklich prüfst.', 'review-captain' ) ),
				'verification_notice_position' => array( __( 'Position des Hinweises', 'review-captain' ), __( 'Über oder unter der Liste bzw. dem Formular.', 'review-captain' ) ),
				'label_purchase_verified'      => array( __( 'Kennzeichen „Kauf verifiziert“', 'review-captain' ), __( 'Leer = „Verifizierter Kauf“. Nur der Text ist einstellbar; die Stufe setzt allein eine angebundene, vertrauenswürdige Quelle.', 'review-captain' ) ),
				'label_attendance_confirmed'   => array( __( 'Kennzeichen „Teilnahme bestätigt“', 'review-captain' ), __( 'Leer = „Teilnahme bestätigt“. Nur der Text ist einstellbar; die Stufe setzt allein eine angebundene, vertrauenswürdige Quelle.', 'review-captain' ) ),
				'label_external'               => array( __( 'Kennzeichen „Externe Plattform“', 'review-captain' ), __( 'Leer = „Externe Plattformbewertung“. Quelle und Link zum Original bleiben immer sichtbar.', 'review-captain' ) ),
				'label_testimonial'            => array( __( 'Kennzeichen „Erfahrungsbericht“', 'review-captain' ), __( 'Leer = „Erfahrungsbericht dieser Website“.', 'review-captain' ) ),
				'label_unverified'             => array( __( 'Kennzeichen „Ungeprüft“', 'review-captain' ), __( 'Leer = „Ungeprüft“. Erscheint nur, wenn „Ungeprüfte Bewertungen kennzeichnen“ aktiv ist.', 'review-captain' ) ),
				'show_unverified_label'        => array( __( 'Ungeprüfte Bewertungen kennzeichnen', 'review-captain' ), __( 'Zeigt bei Bewertungen ohne Kauf- oder Teilnahmenachweis sichtbar das Kennzeichen „Ungeprüft“.', 'review-captain' ) ),
				'star_color'     => array( __( 'Sternfarbe', 'review-captain' ), __( 'Hex-Farbwert, z. B. #e0a800. Leer = Farbe aus Theme bzw. Plugin-CSS. Achte auf ausreichenden Kontrast.', 'review-captain' ) ),
				'accent_color'   => array( __( 'Akzentfarbe', 'review-captain' ), __( 'Für Schaltflächen, Links und Fokusrahmen. Hex-Farbwert; leer = Theme-Farbe.', 'review-captain' ) ),
				'rating_display' => array( __( 'Bewertung anzeigen als', 'review-captain' ), __( 'Für Screenreader steht die Bewertung immer als Text da. Andere Skalen als 1–5 werden nie in Sterne umgerechnet.', 'review-captain' ) ),
				'decimal_places' => array( __( 'Nachkommastellen bei Durchschnitten', 'review-captain' ), __( 'Gilt für Durchschnittswerte in der Zusammenfassung. Eigene Bewertungen bleiben ganze Sterne von 1 bis 5.', 'review-captain' ) ),
				'schema_contexts'    => array( __( 'Markup für', 'review-captain' ), __( 'Nur für diese Inhaltstypen werden strukturierte Daten ausgegeben.', 'review-captain' ) ),
				'schema_min_reviews' => array( __( 'Mindestzahl Bewertungen für Markup', 'review-captain' ), __( 'Strukturierte Daten erst ab so vielen geeigneten, sichtbaren Bewertungen auf der Seite.', 'review-captain' ) ),
				'widget_limit'         => array( __( 'Anzahl Bewertungen', 'review-captain' ), __( 'Vorgabe, wenn ein Widget, Block oder Shortcode keine eigene Anzahl hat (höchstens 50).', 'review-captain' ) ),
				'widget_layout'        => array( __( 'Darstellung', 'review-captain' ), __( 'Raster zeigt die Bewertungen als Karten nebeneinander, Liste untereinander. Gilt, wenn ein Widget, Block oder Shortcode keine eigene Darstellung hat.', 'review-captain' ) ),
				'widget_columns'       => array( __( 'Spalten im Raster', 'review-captain' ), __( 'Auf schmalen Bildschirmen werden es automatisch weniger.', 'review-captain' ) ),
				'widget_heading_level' => array( __( 'Überschriftenebene der Karten', 'review-captain' ), __( 'h2 bis h6. Wähle die Ebene passend zur Gliederung deiner Seite.', 'review-captain' ) ),
				'widget_new_tab'       => array( __( 'Links zum Original in neuem Tab', 'review-captain' ), __( 'Öffnet Links zur Bewertung bei der Plattform in einem neuen Tab. Der Link kündigt das für Screenreader an.', 'review-captain' ) ),
			),
			'privacy'     => array(
				'retention_external_days'  => array( __( 'Externe Reviews ohne Bestätigung (Tage)', 'review-captain' ), __( 'Wie lange ein Plattform-Review gespeichert bleibt, wenn es nicht mehr von der Quelle bestätigt wurde.', 'review-captain' ) ),
				'retention_rejected_days'  => array( __( 'Abgelehnte Reviews (Tage)', 'review-captain' ), __( 'Danach endgültige Löschung inklusive Verlauf.', 'review-captain' ) ),
				'privacy_notice'           => array( __( 'Datenschutzhinweis im Formular', 'review-captain' ), __( 'Leer = kurzer Standardtext mit Link zu deiner Datenschutzseite.', 'review-captain' ) ),
				'erase_mode'               => array( __( 'Verhalten bei Löschanfragen', 'review-captain' ), __( 'Was „Personenbezogene Daten löschen“ (Werkzeuge) mit Reviews dieser E-Mail-Adresse macht.', 'review-captain' ) ),
				'retention_logs_days'      => array( __( 'Protokolle (Tage)', 'review-captain' ), __( 'Protokolle enthalten keine Reviewtexte oder Zugangsdaten.', 'review-captain' ) ),
				'retention_jobs_days'      => array( __( 'Erledigte Hintergrundaufgaben (Tage)', 'review-captain' ), __( 'Danach werden abgeschlossene Hintergrundaufgaben gelöscht. Sie enthalten keine Reviewtexte.', 'review-captain' ) ),
				'retention_audit_days'     => array( __( 'Änderungsprotokoll der Einstellungen (Tage)', 'review-captain' ), __( 'Wie lange Einträge zu Einstellungen und Rollenrechten mit altem und neuem Wert aufbewahrt werden. Texte sind gekürzt, E-Mail-Adressen maskiert, Zugangsdaten nie enthalten.', 'review-captain' ) ),
				'retention_contact_days'   => array( __( 'Kontaktadresse zu Bewertungen (Tage)', 'review-captain' ), __( 'Danach wird die E-Mail-Adresse zur Bewertung entfernt; die Bewertung bleibt. 0 = unbegrenzt. Ohne Adresse sind Korrektur-Links für diese Bewertung nicht mehr möglich. Frist bitte rechtlich prüfen lassen.', 'review-captain' ) ),
				'retention_approved_days'  => array( __( 'Freigegebene eigene Bewertungen (Tage)', 'review-captain' ), __( 'Danach werden Bewertungen dieser Website endgültig gelöscht (gerechnet ab Eingang). 0 = unbegrenzt. Plattform-Bewertungen sind nicht betroffen. Frist bitte rechtlich prüfen lassen.', 'review-captain' ) ),
				'retention_corrections_days' => array( __( 'Entschiedene Korrekturfassungen (Tage)', 'review-captain' ), __( 'Frühere und abgelehnte Fassungen korrigierter Bewertungen werden danach gelöscht.', 'review-captain' ) ),
				'delete_data_on_uninstall' => array( __( 'Beim Löschen des Plugins alle Daten entfernen', 'review-captain' ), __( 'Aus (Standard) = alle Daten bleiben erhalten. An = beim Löschen des Plugins werden auf dieser Website entfernt: alle Bewertungen mit Fotos, Antworten, Korrekturfassungen, Meldungen und Schlagwörtern, Profile und Quellen, Fragen, Importe, Hintergrundaufgaben, Protokolle, alle Einstellungen und die Review-Captain-Rechte der Rollen. Zum Einschalten tippst du LÖSCHEN (oder DELETE) ein; der Zeitpunkt der Bestätigung wird gespeichert. Die Einstellung wird nicht exportiert. Geplante Aufgaben werden beim Löschen immer entfernt. Daten bei Google, Trustpilot usw. werden nie verändert.', 'review-captain' ) ),
				'delete_data_confirmed_at' => array( __( 'Löschung bestätigt am (UTC)', 'review-captain' ), __( 'Wird beim Bestätigen mit LÖSCHEN gesetzt und lässt sich nicht direkt ändern.', 'review-captain' ) ),
			),
			'access'      => array(
				'moderate_roles' => array( __( 'Reviews moderieren', 'review-captain' ), __( 'Eingereichte Reviews, Fragen und Korrekturen prüfen. Moderierende sehen eingereichte Reviews inklusive Kontaktadresse.', 'review-captain' ) ),
				'sources_roles'  => array( __( 'Quellen verwalten', 'review-captain' ), __( 'Profile und Plattform-Quellen anlegen, verbinden und synchronisieren.', 'review-captain' ) ),
				'requests_roles' => array( __( 'Anfragen verwalten', 'review-captain' ), __( 'Feedback-Links und (mit Review Captain Pro) Bewertungsanfragen verwalten.', 'review-captain' ) ),
				'reports_roles'  => array( __( 'Berichte & Diagnose ansehen', 'review-captain' ), __( 'Diagnose, Protokolle und Auswertungen ansehen.', 'review-captain' ) ),
				'trust_roles'    => array( __( 'Vertrauenszeichen verwalten', 'review-captain' ), __( 'Vertrauenszeichen und Badges verwalten (Review Captain Pro).', 'review-captain' ) ),
				'privacy_roles'  => array( __( 'Datenschutz verwalten', 'review-captain' ), __( 'Den Reiter „Datenschutz & Aufbewahrung“ ändern: Fristen, Löschanfragen, Deinstallation.', 'review-captain' ) ),
				'settings_roles' => array( __( 'Einstellungen ändern', 'review-captain' ), __( 'Die Einstellungen von Review Captain ändern, exportieren und importieren.', 'review-captain' ) ),
			),
			'advanced'    => array(
				'use_action_scheduler' => array( __( 'Action Scheduler verwenden', 'review-captain' ), __( 'Falls verfügbar (z. B. durch WooCommerce), sonst WP-Cron. Bei wenig Besucherverkehr empfehlen wir einen Server-Cron.', 'review-captain' ) ),
				'job_time_budget'      => array( __( 'Zeitbudget je Lauf (Sekunden)', 'review-captain' ), __( 'Nur ändern, wenn die Diagnose Zeitüberschreitungen meldet.', 'review-captain' ) ),
				'lease_seconds'        => array( __( 'Freigabe hängender Aufgaben (Sekunden)', 'review-captain' ), __( 'Muss länger als das Zeitbudget sein.', 'review-captain' ) ),
				'debug_logging'        => array( __( 'Fehlerprotokoll', 'review-captain' ), __( 'Schreibt fehlgeschlagene Hintergrundaufgaben (Nummer, Typ, Fehlercode) zusätzlich ins PHP-Fehlerprotokoll des Servers. Enthält keine Zugangsdaten, Reviewtexte oder E-Mail-Adressen.', 'review-captain' ) ),
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
				__( 'Datum', 'review-captain' )           => array( 'date_format' ),
				__( 'Neue Reviews', 'review-captain' )    => array( 'new_review_status' ),
				__( 'Hinweise', 'review-captain' )        => array( 'show_pro_hints' ),
			),
			'sources'     => array(
				__( 'Zeitfenster', 'review-captain' )       => array( 'sync_window_start', 'sync_window_end' ),
				__( 'Gelöschte Reviews', 'review-captain' ) => array( 'removed_review_behavior' ),
			),
			'submissions' => array(
				__( 'Formular', 'review-captain' )          => array( 'enabled', 'allow_title', 'min_text_length', 'max_text_length' ),
				__( 'Formulartexte', 'review-captain' )     => array( 'name_label', 'email_hint', 'submit_label' ),
				__( 'Einwilligung', 'review-captain' )      => array( 'require_consent', 'consent_text' ),
				__( 'Benachrichtigung', 'review-captain' )  => array( 'notify_admin', 'notify_recipients', 'notify_subject', 'notify_body', 'notify_questions' ),
				__( 'Moderationsregeln', 'review-captain' ) => array( 'hold_words', 'hold_links', 'block_email_domains', 'min_seconds' ),
				__( 'Schutz vor Missbrauch', 'review-captain' ) => array( 'honeypot', 'captcha_provider', 'rate_limit_per_hour', 'trusted_proxy_header' ),
				__( 'Korrekturen', 'review-captain' )       => array( 'corrections_enabled', 'correction_link_hours' ),
				__( 'Erweiterte Funktionen (nach 1.0)', 'review-captain' ) => array( 'tags_enabled', 'reports_enabled', 'report_threshold', 'report_rate_limit_per_hour', 'photos_enabled', 'photo_max_kb', 'photo_max_edge', 'multi_product_enabled', 'qa_enabled', 'feedback_campaigns_enabled' ),
				__( 'Meldungen', 'review-captain' )         => array( 'report_reasons', 'report_notify', 'report_confirmation' ),
			),
			'display'     => array(
				__( 'Inhalte', 'review-captain' )          => array( 'show_stars', 'show_date', 'date_style', 'show_author', 'show_avatar' ),
				__( 'Prüfung & Transparenz', 'review-captain' ) => array( 'verification_notice_show', 'verification_notice_title', 'verification_notice', 'verification_notice_position', 'show_unverified_label', 'label_purchase_verified', 'label_attendance_confirmed', 'label_external', 'label_testimonial', 'label_unverified' ),
				__( 'Bewertung und Farben', 'review-captain' ) => array( 'rating_display', 'decimal_places', 'star_color', 'accent_color' ),
				__( 'Widget-Vorgaben', 'review-captain' )  => array( 'widget_limit', 'widget_layout', 'widget_columns', 'widget_heading_level', 'widget_new_tab' ),
				__( 'Strukturierte Daten', 'review-captain' ) => array( 'schema_enabled', 'schema_contexts', 'schema_min_reviews' ),
				__( 'Leerer Zustand', 'review-captain' )   => array( 'empty_text' ),
				__( 'Barrierefreiheit', 'review-captain' ) => array( 'reduced_motion' ),
				__( 'Fotos', 'review-captain' )            => array( 'show_photos' ),
			),
			'privacy'     => array(
				__( 'Aufbewahrung', 'review-captain' )       => array( 'retention_external_days', 'retention_rejected_days', 'retention_contact_days', 'retention_approved_days', 'retention_corrections_days', 'retention_logs_days', 'retention_audit_days', 'retention_jobs_days' ),
				__( 'Datenschutzhinweis', 'review-captain' ) => array( 'privacy_notice' ),
				__( 'Löschanfragen', 'review-captain' )      => array( 'erase_mode' ),
				__( 'Deinstallation', 'review-captain' )     => array( 'delete_data_on_uninstall' ),
			),
			'access'      => array(
				__( 'Rechte je Rolle', 'review-captain' ) => array( 'moderate_roles', 'sources_roles', 'requests_roles', 'reports_roles', 'trust_roles', 'privacy_roles', 'settings_roles' ),
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
				'tombstone' => __( 'Inhalt sofort entfernen, nur Platzhalter behalten (empfohlen)', 'review-captain' ),
				'hide'      => __( 'Nur ausblenden, Inhalt bis Fristablauf speichern', 'review-captain' ),
			),
			'submissions.trusted_proxy_header' => array(
				'none'             => __( 'Keine – direkte Verbindungsadresse (empfohlen)', 'review-captain' ),
				'x-forwarded-for'  => 'X-Forwarded-For',
				'x-real-ip'        => 'X-Real-IP',
				'cf-connecting-ip' => 'CF-Connecting-IP (Cloudflare)',
			),
			'submissions.captcha_provider'    => array(
				'none' => __( 'Keiner', 'review-captain' ),
				'pow'  => __( 'Eingebaute Rechenprüfung (ohne Dritte)', 'review-captain' ),
			),
			'display.rating_display'          => array(
				'both'   => __( 'Sterne und Zahl', 'review-captain' ),
				'stars'  => __( 'Nur Sterne', 'review-captain' ),
				'number' => __( 'Nur Zahl', 'review-captain' ),
			),
			'display.widget_layout'           => array(
				'grid' => __( 'Raster', 'review-captain' ),
				'list' => __( 'Liste', 'review-captain' ),
			),
			'display.date_style'              => array(
				'absolute' => __( 'Absolut (12.11.2026)', 'review-captain' ),
				'relative' => __( 'Relativ (vor 3 Tagen)', 'review-captain' ),
			),
			'submissions.report_reasons'      => ReportStore::reason_labels(),
			'display.schema_contexts'         => array(
				'product' => __( 'Produkte', 'review-captain' ),
				'post'    => __( 'Beiträge', 'review-captain' ),
				'event'   => __( 'Veranstaltungen', 'review-captain' ),
			),
			'submissions.report_notify'       => array(
				'recipients' => __( 'Empfänger der Benachrichtigungen', 'review-captain' ),
				'none'       => __( 'Niemand', 'review-captain' ),
			),
			'display.verification_notice_position' => array(
				'below' => __( 'Unter der Liste bzw. dem Formular', 'review-captain' ),
				'above' => __( 'Über der Liste bzw. dem Formular', 'review-captain' ),
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
