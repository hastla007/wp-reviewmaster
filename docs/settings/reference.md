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

Eine eigene Standardsprache gibt es nicht (mehr): Plugin-Texte folgen der Sprache der Website bzw. des Benutzerprofils (WordPress-Einstellungen). Die frühere Einstellung `default_language` wurde nie ausgewertet und ist entfernt (SET-RC-05); ein gespeicherter Wert stört nicht und wird ignoriert.

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
- **Zweck:** Was passiert, wenn die Quelle meldet, dass ein Review dort gelöscht wurde. In beiden Fällen wird es sofort nicht mehr angezeigt (Status „entfernt“).
- **Standard:** „Inhalt sofort entfernen, nur Platzhalter behalten“ (Tombstone): Titel, Text, Name, Sternwert, Profilbild-/Antwortangaben und ein Foto werden sofort gelöscht. Es bleibt nur ein leerer Platzhalter mit Plattform, Quell-Link, Datum und Abgleichschlüssel, damit derselbe Eintrag beim nächsten Abgleich wiedererkannt wird. Taucht das Review bei der Quelle wieder auf, wird es mit dem aktuellen Inhalt neu befüllt und wartet erneut auf Moderation. Im Verlauf steht „content_removed“.
- **Alternative:** „Nur ausblenden, Inhalt bis Fristablauf speichern“: Review wird nicht mehr angezeigt, der Inhalt bleibt bis zum Ablauf von `retention_external_days` gespeichert.
- **Folgen:** Platzhalter und ausgeblendete Reviews werden nach `retention_external_days` endgültig gelöscht. Anbieter-Adapter dürfen „Inhalt entfernen“ erzwingen (Filter `review_captain_removed_review_behavior`, kann nur verschärfen), nie lockern.
- **Datenschutz:** betrifft Speicherung fremder Texte und Namen; der Standard speichert nach der Löschung bei der Quelle nichts Inhaltliches mehr.

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
- **Zweck:** unsichtbares Feld, das automatisierte Einsendungen erkennt. **Standard:** an (erweiterte Einstellung). Nonce-Prüfung, Mindestausfüllzeit und Rate-Limit bleiben immer aktiv.

### Mindest-Ausfüllzeit (`min_seconds`)
- **Zweck:** Formulare, die schneller als nach dieser Zeit abgeschickt werden, gelten als automatisiert und werden mit einem neutralen Hinweis abgewiesen. **Standard:** 3 Sekunden, erlaubt 1–120 (erweiterte Einstellung). Filter `review_captain_form_min_seconds` (0–120) hat Vorrang.

### Moderationsregeln (`hold_words`, `hold_links`, `block_email_domains`)
- **Wörter, die zur Prüfung zurückhalten (`hold_words`):** ein Wort oder eine Wortgruppe je Zeile (auch Komma-getrennt), Groß-/Kleinschreibung egal, nur ganze Wörter. Enthalten Titel, Text oder Name einen Eintrag, bekommt die Bewertung den Status „Prüfung ausstehend“ – auch wenn neue Reviews sonst sofort freigegeben werden. **Standard:** leer.
- **Bewertungen mit Links zurückhalten (`hold_links`):** Bewertungen mit `http://`, `https://` oder `www.` warten auf Moderation. **Standard:** aus.
- **Gesperrte E-Mail-Domains (`block_email_domains`):** eine Domain je Zeile; gilt auch für Subdomains. Solche Einreichungen werden mit dem neutralen Hinweis „Mit dieser E-Mail-Adresse kann keine Bewertung abgegeben werden …“ abgelehnt und nicht gespeichert. **Standard:** leer. Filter `review_captain_blocked_email_domains` hat Vorrang.
- **Folgen:** Die Regeln löschen nie etwas still; zurückgehaltene Bewertungen sehen Besucher mit der normalen Meldung „wird nach Prüfung veröffentlicht“. Der Grund steht im Verlauf der Bewertung („held_for_moderation“ mit `hold_words` bzw. `hold_links`, nie das gefundene Wort). Eigene Regeln: Filter `review_captain_submission_hold_reasons`.
- **Datenschutz:** keiner (es werden nur Regelkennungen gespeichert).

### Absenderadresse hinter einem Proxy (`trusted_proxy_header`)
- **Zweck:** Welche Adresse für die Sendebegrenzung (Bewertungen, Meldungen, Fragen) gezählt wird. **Standard:** „Keine“ = direkte Verbindungsadresse (`REMOTE_ADDR`). Wählbar: `X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP` (erweiterte Einstellung). Filter `review_captain_trusted_proxy_header` hat Vorrang.
- **Folgen:** Nur einstellen, wenn Ihre Website hinter einem Reverse Proxy/CDN liegt, der diese Kopfzeile selbst setzt – sonst zählen alle Besucher als ein Absender. Bei einer Liste (`X-Forwarded-For`) gilt die letzte gültige Adresse (die Ihr Proxy angehängt hat); ungültige Werte fallen auf `REMOTE_ADDR` zurück. Ohne vertrauenswürdigen Proxy könnten Absender die Begrenzung durch eine gefälschte Kopfzeile umgehen.
- **Datenschutz:** gespeichert wird weiterhin nur ein gekürzter Hash, nie die Adresse.

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
- **Foto-Upload (`photos_enabled`, `photo_max_kb`, `photo_max_edge`):** ein optionales Foto je Bewertung (JPG, PNG, WebP, Standard höchstens 2 048 KB). Das Bild wird neu kodiert und dabei auf die längste Kante `photo_max_edge` verkleinert (Standard 1 600 px, erlaubt 400–4 000, erweiterte Einstellung; Filter `review_captain_photo_max_edge`); Metadaten wie der Aufnahmeort werden entfernt. Sichtbar erst nach Freigabe und mit „Fotos anzeigen“. Wird mit der Bewertung und bei Datenschutz-Löschungen entfernt. Mehrere Fotos je Bewertung sind nicht vorgesehen (Datenmodell, Foto-Adresse und Anzeige sind auf ein Foto ausgelegt; SET-RC-27).
- **Korrektur durch Autorinnen und Autoren (`corrections_enabled`, `correction_link_hours`):** Wer eine freigegebene Bewertung geschrieben hat, kann sie per Einmal-Link oder angemeldet korrigieren oder zurückziehen; Korrekturen erscheinen erst nach Freigabe. Der Einmal-Link gilt `correction_link_hours` Stunden (Standard 1, erlaubt 1–72, erweiterte Einstellung); Formularhinweis und E-Mail nennen die eingestellte Dauer.
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

### Bewertung anzeigen als (`rating_display`) und Nachkommastellen (`decimal_places`)
- **Bewertung anzeigen als:** „Sterne und Zahl“ (Standard, bisheriges Verhalten), „Nur Sterne“ oder „Nur Zahl“. Für Screenreader steht die Bewertung immer als Text da („4 von 5 Sternen“). Sterne gibt es nur für die Skala 1–5; Bewertungen mit anderer Skala (z. B. 8 von 10) zeigen immer die Zahl. „Sterne anzeigen“ aus (`show_stars`) blendet Sterne in jedem Fall aus.
- **Nachkommastellen bei Durchschnitten:** 0–2, Standard 1. Gilt für die Durchschnittswerte der Zusammenfassung je Plattform; ganze Zahlen erscheinen ohne Nachkommastellen. Eigene Bewertungen bleiben ganze Sterne von 1 bis 5 (Entscheidung E6).

### Sternfarbe und Akzentfarbe (`star_color`, `accent_color`)
- **Standard:** leer = Farbe aus Theme bzw. Plugin-CSS. Erlaubt sind Hex-Werte (`#rgb` oder `#rrggbb`); ungültige Eingaben werden abgewiesen.
- **Folgen:** Die Werte werden als CSS-Variablen `--rc-color-star` und `--rc-color-accent` an das Plugin-CSS angehängt (nur wenn „Plugin-CSS laden“ an ist). Achten Sie auf ausreichenden Kontrast (Barrierefreiheit).

### Widget-Vorgaben (`widget_limit`, `widget_layout`, `widget_columns`, `widget_heading_level`, `widget_new_tab`)
- **Zweck:** Vorgaben für Widgets, Blöcke, Shortcodes und Elementor-Widgets, die keinen eigenen Wert haben. **Standard:** 6 Bewertungen (1–50), Raster, 3 Spalten (1–4), Überschriftenebene h2 (h2–h6), Links zum Original im selben Tab.
- **Folgen:** Ein Wert am einzelnen Widget hat immer Vorrang. Blöcke und Elementor-Widgets ohne eigenen Wert („Vorgabe aus den Einstellungen“ bzw. „Standard“) folgen diesen Vorgaben. Filter `review_captain_widget_defaults` hat Vorrang vor den Einstellungen.

### Bewertungs-Markup ausgeben (`schema_enabled`, `schema_contexts`, `schema_min_reviews`)
- **Zweck:** Ob Review Captain strukturierte Daten (schema.org) für eigene, auf dieser Website abgegebene und freigegebene Bewertungen ausgibt.
- **Standard:** an, für Produkte, Beiträge und Veranstaltungen, ab 1 Bewertung. **Folgen:** aus = keinerlei Bewertungs-Markup durch Review Captain; die sichtbare Ausgabe bleibt gleich. Externe Bewertungen und Erfahrungsberichte (Testimonials) erhalten unabhängig davon nie Markup – dafür gibt es bewusst keinen Schalter.
- **Markup für (`schema_contexts`):** einzelne Inhaltstypen abschalten (kann nur einschränken).
- **Mindestzahl (`schema_min_reviews`):** strukturierte Daten erst ab so vielen geeigneten, sichtbaren Bewertungen auf der Seite (1–50; Filter `review_captain_schema_min_reviews` hat Vorrang).
- **Diagnose:** „Einstellungen → SEO & Yoast“ nennt eingeschränkte Inhaltstypen, die Mindestzahl und warum ggf. nichts ausgegeben wird (Gründe `context_disabled`, `below_minimum`).
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

### Kontaktadresse zu Bewertungen (`retention_contact_days`)
- **Zweck:** Nach so vielen Tagen ab Eingang wird die E-Mail-Adresse einer Bewertung entfernt (dazu der daraus abgeleitete Schlüssel gegen Doppelbewertungen); die Bewertung, ihr Status und eine Verknüpfung mit einem Benutzerkonto bleiben. **Standard:** 0 = unbegrenzt (bisheriges Verhalten), erlaubt 0–3650.
- **Folgen:** Ohne Adresse sind Korrektur-Links und die Zuordnung per E-Mail bei Auskunfts-/Löschanfragen für diese Bewertung nicht mehr möglich. Der Reiter zeigt vorab, bei wie vielen Bewertungen die nächste Bereinigung die Adresse entfernt. Jede betroffene Bewertung erhält im Verlauf „contact_removed“, das Änderungsprotokoll den Eintrag `retention.contacts_removed` mit Anzahl. Der Textvorschlag für die Datenschutzerklärung nennt die Frist, sobald sie gesetzt ist.
- **Rechtsprüfung:** Eine Standardfrist ist bewusst nicht gesetzt (wartet auf Entscheidung E5 der Inhaberin); lassen Sie die passende Frist rechtlich prüfen.

### Freigegebene eigene Bewertungen (`retention_approved_days`)
- **Zweck:** Freigegebene Bewertungen dieser Website werden so viele Tage nach Eingang endgültig gelöscht. **Standard:** 0 = unbegrenzt, erlaubt 0–3650.
- **Folgen:** betrifft nur eigene Bewertungen (keine Erfahrungsberichte, keine Plattform-Bewertungen, keine anderen Status). Der Reiter zeigt vorab die Anzahl. **Rechtsprüfung:** Frist rechtlich prüfen lassen.

### Entschiedene Korrekturfassungen (`retention_corrections_days`)
- **Standard:** 90 Tage nach der Entscheidung (erlaubt 1–3650); danach werden frühere und abgelehnte Fassungen korrigierter Bewertungen gelöscht.

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
- **Standard:** aus (erweiterte Einstellung). An = jede fehlgeschlagene Hintergrundaufgabe schreibt zusätzlich eine Zeile ins PHP-Fehlerprotokoll des Servers: Aufgabennummer, Typ, Versuch, Fehlercode und Art des Fehlers (z. B. Ausnahmeklasse). Fehlermeldung und Nutzdaten werden bewusst nicht geschrieben, das Protokoll enthält daher keine Zugangsdaten, Reviewtexte oder E-Mail-Adressen. Schalten Sie es nach der Fehlersuche wieder aus (keine automatische Abschaltung in v1). Filter `review_captain_debug_log_line` (z. B. für eigene Logger; leerer Rückgabewert unterdrückt die Zeile).

## Grenzen, die per Filter angehoben werden können (SET-RC-31)

Für Entwickler; die Obergrenzen sind fest und schützen Server und Datenbank.

| Grenze | Standard | Filter | Obergrenze |
|---|---|---|---|
| Feedback-Links | 50 | `review_captain_max_feedback_links` | 200 (alle Links liegen in einer Option) |
| Produkte je Mehrprodukt-Formular | 20 | `review_captain_form_max_items` | 50 |
| Interne Tags je Bewertung | 20 | `review_captain_max_tags` | 50 |

## Abnahme

Entspricht SET-023, wenn:

- [ ] jede Einstellung aus [option-schema.md](option-schema.md) hier mit Zweck, Geltungsbereich, Standard, Folgen, Datenschutzbezug und Anbieter-/Lizenzvoraussetzung erklärt ist;
- [ ] Texte ohne interne Begriffe auskommen und mit Betreibern getestet sind (UX-013);
- [x] Standardwerte und Wertebereiche mit der PHP-Implementierung übereinstimmen (abgeglichen 2026-11-16);
- [ ] die Hilfetexte im Admin aus diesen Formulierungen abgeleitet sind.
