# Einstellungen – Referenz für Betreiber (SET-023)

Status: an den implementierten Stand angeglichen (Standards und Wertebereiche exakt nach `src/Settings/SettingsSchema.php`), Stand 2026-11-16. Diese Seite ist für Website-Betreiber geschrieben. Alle Einstellungen gelten **nur für die aktuelle Website** (auch in Multisite-Netzwerken). Technische Details: [option-schema.md](option-schema.md).

Jeder Eintrag nennt: **Zweck** · **Gilt für** · **Standard** · **Folgen** · **Datenschutz** · **Voraussetzung**.

---

## Allgemein

### Marken- bzw. Firmenname (`brand_name`)
- **Zweck:** Name in Hinweisen, Formularen und (mit Pro) Einladungen.
- **Gilt für:** ganze Website. **Standard:** leer = Titel Ihrer Website.
- **Folgen:** erscheint z. B. im Text „Ihre Bewertung für {Name}“.
- **Datenschutz:** ist bei Einzelunternehmen oft ein Personenname; wird beim Konfigurationsexport nur nach Rückfrage mitgenommen.
- **Voraussetzung:** keine.

### Standardsprache (`default_language`)
- **Zweck:** Sprache für Plugin-Texte im Frontend, wenn nichts anderes gilt.
- **Standard:** leer = Sprache der Website. **Folgen:** nur installierte Sprachen sind wählbar. Reviewtexte werden nie übersetzt.
- **Datenschutz:** keiner. **Voraussetzung:** Sprachdatei installiert.

### Datumsformat (`date_format`)
- **Zweck:** Darstellung von Review-Daten. **Standard:** leer = WordPress-Datumsformat.
- **Gilt für:** Website; einzelne Widgets dürfen abweichen. **Folgen:** gilt nur bei „absolutem“ Datum (siehe Darstellung).
- **Datenschutz:** keiner. **Voraussetzung:** keine.

### Status neuer Reviews (`new_review_status`)
- **Zweck:** Ob über das Formular eingereichte Reviews sofort öffentlich sind.
- **Standard:** „Prüfung ausstehend“ (empfohlen). **Folgen:** „Sofort freigeben“ veröffentlicht ungeprüfte Texte – inklusive Spam oder personenbezogener Angaben Dritter. Empfohlen nur mit aktiver Moderation.
- **Datenschutz:** betrifft Veröffentlichung personenbezogener Daten; Änderung wird protokolliert.
- **Voraussetzung:** keine. Zeitzone: es gilt die WordPress-Zeitzone (Einstellungen → Allgemein).

## Quellen & Synchronisierung

Diese Einstellungen wirken erst, wenn eine Quelle automatisch abgeglichen wird (Review Captain Pro oder ein installierter Connector). Konten, Profile und Zugangsdaten verwalten Sie unter **Quellen**.

### Synchronisierungsfenster (`sync_window_start`, `sync_window_end`)
- **Zweck:** Zeitraum, in dem automatische Abgleiche starten dürfen, in Website-Zeit. **Standard:** 02:00–05:00.
- **Folgen:** außerhalb des Fensters startet kein automatischer Abgleich; „Jetzt synchronisieren“ auf der Quellen-Seite bleibt möglich. Ende vor Beginn = Fenster über Mitternacht. Format HH:MM (24 h); ungültige Eingaben werden auf den Standard zurückgesetzt.
- **Datenschutz:** keiner. **Voraussetzung:** synchronisierende Quelle.

### Seiten je Abruf (`max_pages_per_run`)
- **Zweck:** Wie viele Ergebnisseiten eine Quelle pro Lauf abrufen darf. **Standard:** 5, erlaubt 1–50 (erweiterte Einstellung).
- **Folgen:** höhere Werte holen große Bestände schneller, belasten aber Server und Anbieterkontingent. Der Rest folgt im nächsten Lauf.
- **Datenschutz:** keiner. **Voraussetzung:** synchronisierende Quelle; Anbieterlimits haben Vorrang.

### Pause nach Anmeldefehlern (`pause_after_auth_errors`)
- **Zweck:** Nach so vielen aufeinanderfolgenden Anmelde-/Berechtigungsfehlern wird die Quelle pausiert. **Standard:** 3, erlaubt 1–20 (erweiterte Einstellung).
- **Folgen:** verhindert wiederholte fehlschlagende Anfragen und mögliche Kontosperren. Sie sehen einen Hinweis auf Dashboard und Quellen-Seite; nach „Neu verbinden“ läuft die Quelle weiter. Vorhandene Reviews bleiben sichtbar.
- **Datenschutz:** keiner.

### Verhalten bei entfernten Reviews (`removed_review_behavior`)
- **Zweck:** Was passiert, wenn ein Review bei der Quelle gelöscht oder nicht mehr abrufbar ist.
- **Standard:** „Inhalt entfernen, Platzhalter behalten“ (Tombstone): Text und Name werden gelöscht, ein leerer Eintrag verhindert, dass das Review erneut importiert wird.
- **Alternative:** „Nur ausblenden“: Review wird nicht mehr angezeigt, Inhalt bleibt bis zum Ablauf der Aufbewahrungsfrist gespeichert.
- **Folgen:** Manche Anbieter verlangen Löschung; dann wird unabhängig von dieser Einstellung entfernt.
- **Datenschutz:** betrifft Speicherung fremder Texte und Namen.

## Anfragen & Zustellung (Review Captain Pro)

Ohne Pro sehen Sie diese Einstellungen gesperrt. Bei abgelaufener Lizenz bleiben Ihre Werte gespeichert, es wird aber nichts versendet. Einzelne Kampagnen verwalten Sie unter **Anfragen**.
Immer gilt, unabhängig von Einstellungen: höchstens eine Einladung je tatsächlichem Erlebnis und Empfänger; kein Versand ohne bekannte Versanderlaubnis; wer sich abmeldet, erhält von dieser Website keine Einladungen mehr; Einladungen werden nie nach Zufriedenheit gefiltert.

### Absendername / Absenderadresse (`sender_name`, `sender_email`)
- **Zweck:** Absender von Einladungen. **Standard:** Name = Markenname; Adresse leer = **kein Versand**.
- **Folgen:** Die Adresse muss zu einer Domain gehören, für die Ihr Versanddienst eingerichtet ist (SPF/DKIM), sonst landen Einladungen im Spam. Senden Sie vorher eine Testmail.
- **Datenschutz:** Adresse kann personenbezogen sein; Änderungen werden maskiert protokolliert.
- **Voraussetzung:** Pro, Recht „Review-Anfragen“, eingerichteter Versandweg.

### Versandzeitfenster (`send_window_start`, `send_window_end`)
- **Zweck:** Uhrzeiten, zu denen Einladungen verschickt werden dürfen (Website-Zeit). **Standard:** 09:00–18:00.
- **Folgen:** Einladungen außerhalb werden auf das nächste Fenster verschoben. Kampagnen dürfen das Fenster nur verkleinern.

### Tageslimit (`daily_limit`)
- **Zweck:** Höchstzahl Einladungen pro Tag über alle Kampagnen. **Standard:** 200, erlaubt 1–100 000.
- **Folgen:** schützt vor Versandfehlern und Spam-Einstufung. Überzählige Einladungen warten auf den Folgetag.

### Mindestabstand je Empfänger (`cooldown_days`)
- **Zweck:** Tage, in denen dieselbe Person keine weitere Einladung erhält. **Standard:** 90, erlaubt 0–3650.
- **Folgen:** Kampagnen dürfen den Abstand nur verlängern.
- **Datenschutz:** dafür wird eine minimale Empfängerreferenz gespeichert; Frist im Tab Datenschutz.

### Erinnerungen erlauben (`reminders_enabled`)
- **Zweck:** Ob Kampagnen eine einzelne Erinnerung senden dürfen. **Standard:** aus.
- **Folgen:** auch eingeschaltet sendet nur eine Kampagne Erinnerungen, die sie selbst aktiviert. Abmeldung stoppt jede Erinnerung.

## Einreichungen & Moderation

### Einreichungen aktiv (`enabled`)
- **Zweck:** Hauptschalter für das Bewertungsformular. **Standard:** an.
- **Folgen:** aus = Formulare zeigen einen neutralen Hinweis, es wird nichts angenommen. Bereits gespeicherte Reviews bleiben.

### Einwilligung zur Veröffentlichung abfragen (`require_consent`)
- **Zweck:** Pflicht-Kontrollkästchen „Ich bin einverstanden, dass meine Bewertung mit Namen veröffentlicht wird“. **Standard:** an.
- **Folgen:** aus = Sie müssen die Veröffentlichungsgrundlage anders sicherstellen; Reviews werden ohne Einwilligungsvermerk gespeichert.
- **Datenschutz:** zentral; Änderung wird protokolliert. Lassen Sie die Rechtsgrundlage prüfen.

### Spam-Falle (`honeypot`)
- **Zweck:** unsichtbares Feld, das automatisierte Einsendungen erkennt. **Standard:** an (erweiterte Einstellung). Nonce-Prüfung, Mindestausfüllzeit (3 Sekunden) und Rate-Limit bleiben immer aktiv.

### Einreichungen pro Stunde (`rate_limit_per_hour`)
- **Zweck:** Höchstzahl Einsendungen je Absender und Stunde. **Standard:** 5, erlaubt 1–1000 (erweiterte Einstellung). Fehlgeschlagene Versuche werden bis zum Dreifachen zugelassen.
- **Datenschutz:** zur Zählung wird ein gekürzter, gehashter Absenderwert kurzzeitig gespeichert (keine Klar-IP; bei IPv6 das /64-Netz).

### Mindest-/Höchstlänge des Textes (`min_text_length`, `max_text_length`)
- **Standard:** 20 bzw. 5 000 Zeichen; erlaubt 0–1000 bzw. 50–20 000. Wählen Sie den Mindestwert kleiner als den Höchstwert (wird in v1 nicht automatisch geprüft). Abweichungen je Formular sind in v1 nicht vorgesehen.

### Titelfeld anbieten (`allow_title`)
- **Standard:** an. Gilt für alle Formulare der Website.

### Admin benachrichtigen (`notify_admin`)
- **Zweck:** E-Mail an die Administrator-Adresse der Website bei neuer Einreichung. **Standard:** an.
- **Datenschutz:** die E-Mail enthält nur einen Link zur Moderation, keinen Reviewtext und keine Kontaktdaten.

### Erweiterte Funktionen nach 1.0 (alle standardmäßig aus)
- **Interne Tags (`tags_enabled`):** Schlagwörter für die Moderation, filterbar, nie öffentlich oder im Export.
- **Öffentliches Melden (`reports_enabled`, `report_threshold`, `report_rate_limit_per_hour`):** Link „Melden“ unter jeder freigegebenen Bewertung. Ab der eingestellten Zahl offener Meldungen (Standard 1) wird die Bewertung auf „Gemeldet“ gesetzt und bis zur Prüfung ausgeblendet. Über Meldende wird nichts gespeichert; zur Begrenzung dient ein kurzlebiger gehashter Wert.
- **Foto-Upload (`photos_enabled`, `photo_max_kb`):** ein optionales Foto je Bewertung (JPG, PNG, WebP, Standard höchstens 2 048 KB). Das Bild wird neu kodiert; Metadaten wie der Aufnahmeort werden entfernt. Sichtbar erst nach Freigabe und mit „Fotos anzeigen“. Wird mit der Bewertung und bei Datenschutz-Löschungen entfernt.
- **Formular für mehrere Produkte (`multi_product_enabled`):** ein Formular mit `context_ids` bewertet mehrere Produkte; je Person und Produkt ist nur eine Bewertung möglich.
- **Produktfragen (`qa_enabled`):** Fragen und Antworten getrennt von Bewertungen, mit Moderation; ohne Schema-Markup.
- **Feedback-Links und QR-Codes (`feedback_campaigns_enabled`):** Link mit QR-Code (SVG, beschriftet „Feedback geben – Kein Ticket – kein Einlasscode“) und optional ein offizieller WhatsApp-Click-to-Chat-Link (`wa.me`).

## Darstellung

Diese Werte sind die **Voreinstellung für neue Widgets**. Einzelne Widgets können abweichen. Die Quellenangabe externer Bewertungen (Plattformname, Link) wird immer angezeigt und lässt sich nicht abschalten.

### Sterne, Datum, Autor, Profilbild (`show_stars`, `show_date`, `show_author`, `show_avatar`)
- **Standard:** Sterne, Datum und Autor an; Profilbild aus.
- **Folgen:** Sterne werden immer zusätzlich als Text („4 von 5 Sternen“) für Screenreader ausgegeben.
- **Datenschutz:** Autor und Profilbild sind personenbezogen. Profilbilder lokaler Reviews nutzen die WordPress-Avatarfunktion; je nach WordPress-Einstellung wird dabei Gravatar kontaktiert.

### Datumsstil (`date_style`)
- **Standard:** absolut („12.11.2026“). Relativ („vor 3 Tagen“) veraltet in zwischengespeicherten Seiten schneller.

### Text bei leerer Liste (`empty_text`)
- **Standard:** leer = „Es sind noch keine Bewertungen vorhanden.“

### Bewegung reduzieren (`reduced_motion`)
- **Standard:** an = keine automatischen Animationen oder Karussell-Rotation. Wünscht ein Besucher im Betriebssystem reduzierte Bewegung, wird das immer beachtet.

### Bewertungs-Markup ausgeben (`schema_enabled`)
- **Zweck:** Ob Review Captain strukturierte Daten (schema.org) für eigene, auf dieser Website abgegebene und freigegebene Bewertungen ausgibt.
- **Standard:** an. **Folgen:** aus = keinerlei Bewertungs-Markup durch Review Captain; die sichtbare Ausgabe bleibt gleich. Externe Bewertungen erhalten unabhängig davon nie Markup.
- **Datenschutz:** keiner. **Voraussetzung:** keine; mit Yoast SEO wird das Markup in dessen Schema-Graph eingefügt.

### Fotos anzeigen (`show_photos`)
- **Standard:** aus. An = Fotos freigegebener Bewertungen erscheinen in den Karten. **Datenschutz:** Fotos können Personen zeigen.

### Plugin-CSS laden (`load_css`)
- **Standard:** an (erweiterte Einstellung). Aus = Ihr Theme muss die Ausgabe selbst gestalten (Klassen `rc-…`).

## Datenschutz & Aufbewahrung

Lassen Sie Fristen und Texte von Ihrer Datenschutzberatung prüfen. Diese Einstellungen dürfen nur Personen mit dem Recht „Datenschutz verwalten“ ändern.

### Externe Reviews ohne Bestätigung (`retention_external_days`)
- **Zweck:** Wie lange ein importiertes Plattform-Review gespeichert bleibt, wenn es nicht mehr erfolgreich von der Quelle bestätigt wurde (z. B. getrennte Verbindung). **Standard:** 30 Tage, erlaubt 1–3650.
- **Folgen:** danach wird der Inhalt entfernt. Strengere Anbietervorgaben gelten automatisch.

### Abgelehnte Reviews (`retention_rejected_days`)
- **Standard:** 90 Tage nach Ablehnung (erlaubt 1–3650), dann endgültige Löschung inklusive Verlauf.

### Protokolle (`retention_logs_days`) und erledigte Hintergrundjobs (`retention_jobs_days`)
- **Standard:** 90 bzw. 30 Tage, jeweils erlaubt 1–3650. Protokolle enthalten keine Reviewtexte oder Zugangsdaten.

### Verhalten bei Löschanfragen (`erase_mode`)
- **Zweck:** Was das WordPress-Werkzeug „Personenbezogene Daten löschen“ mit Reviews dieser E-Mail-Adresse macht.
- **Standard:** „Anonymisieren“: Name wird zu „Anonym“, E-Mail und Nutzerverknüpfung werden entfernt, Text und Bewertung bleiben. **Alternative:** „Löschen“: das Review wird vollständig entfernt.
- **Datenschutz:** Enthält der Text selbst personenbezogene Angaben, prüfen und bearbeiten bzw. löschen Sie ihn zusätzlich manuell.

### Daten beim Deinstallieren löschen (`delete_data_on_uninstall`)
- **Standard:** aus = Reviews und Einstellungen bleiben beim Löschen des Plugins erhalten. An = beim Löschen des Plugins werden alle Review-Captain-Daten dieser Website entfernt. Daten bei Google, Trustpilot usw. werden nie verändert.

### Datenschutzhinweis im Formular (`privacy_notice`)
- **Standard:** leer = kurzer Standardtext mit Link zu Ihrer WordPress-Datenschutzseite. Eigener Text wird als reiner Text gespeichert (Absätze bleiben erhalten, HTML wird entfernt).

## Zugriff & Rollen

### Wer darf moderieren (`moderate_roles`) / Einstellungen ändern (`settings_roles`)
- **Standard:** Moderation: Administrator, Redakteur; Einstellungen: Administrator. Administratoren behalten immer alle Rechte.
- **Folgen:** Moderierende sehen eingereichte Reviews inklusive Kontaktadresse. Weitere Rechte (Quellen verbinden, Datenschutz, Berichte) haben standardmäßig nur Administratoren.
- **Voraussetzung:** Sie benötigen selbst das Recht, Benutzerrollen zu vergeben.

## Erweitert

### Action Scheduler verwenden (`use_action_scheduler`)
- **Standard:** an (erweiterte Einstellung); wirkt nur, falls Action Scheduler verfügbar ist (z. B. durch WooCommerce). Sonst WP-Cron. Bei wenig Besucherverkehr empfehlen wir einen Server-Cron.

### Zeitbudget je Lauf (`job_time_budget`) / Freigabe hängender Jobs (`lease_seconds`)
- **Standard:** 20 bzw. 300 Sekunden; erlaubt 5–120 bzw. 60–3600 (erweiterte Einstellungen). Nur ändern, wenn die Diagnose Zeitüberschreitungen meldet. Wählen Sie die Freigabezeit länger als das Zeitbudget (wird in v1 nicht automatisch geprüft).

### Fehlerprotokoll (`debug_logging`)
- **Standard:** aus (erweiterte Einstellung). Schalten Sie es nach der Fehlersuche wieder aus (keine automatische Abschaltung in v1). Enthält keine Zugangsdaten, Reviewtexte oder E-Mail-Adressen.

## Abnahme

Entspricht SET-023, wenn:

- [ ] jede Einstellung aus [option-schema.md](option-schema.md) hier mit Zweck, Geltungsbereich, Standard, Folgen, Datenschutzbezug und Anbieter-/Lizenzvoraussetzung erklärt ist;
- [ ] Texte ohne interne Begriffe auskommen und mit Betreibern getestet sind (UX-013);
- [x] Standardwerte und Wertebereiche mit der PHP-Implementierung übereinstimmen (abgeglichen 2026-11-16);
- [ ] die Hilfetexte im Admin aus diesen Formulierungen abgeleitet sind.
