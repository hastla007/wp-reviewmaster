# Einstellungen – Referenz für Betreiber (SET-023)

Status: an den implementierten Stand angeglichen (Standards und Wertebereiche exakt nach `src/Settings/SettingsSchema.php`), Stand 2026-11-16. Diese Seite ist für Website-Betreiber geschrieben. Alle Einstellungen gelten **nur für die aktuelle Website** (auch in Multisite-Netzwerken). Technische Details: [option-schema.md](option-schema.md).

Jeder Eintrag nennt: **Zweck** · **Gilt für** · **Standard** · **Folgen** · **Datenschutz** · **Voraussetzung**.

**Prüfung beim Speichern (SET-RC-19):** Ungültige Eingaben werden nicht still korrigiert. Die Seite listet sie oben mit Sprunglinks zum Feld auf, markiert das Feld (auch für Screenreader) und zeigt Ihre Eingabe zum Korrigieren erneut an; das Feld behält seinen bisherigen Wert, alle gültigen Änderungen des Bereichs werden gespeichert. Zusätzlich geprüft: Mindestlänge < Höchstlänge, Beginn ≠ Ende bei Versand- und Synchronisierungsfenster, Freigabe hängender Aufgaben > Zeitbudget je Lauf. Beim Import einer Konfiguration werden Werte dagegen wie bisher auf den erlaubten Bereich gesetzt (die Vorschau zeigt das Ergebnis).

**Änderungsprotokoll (SET-RC-17):** Jede Änderung wird mit Benutzer, Zeit, altem und neuem Wert protokolliert (Werkzeuge › Diagnose › Protokoll, Filter „Einstellungen“). Texte werden gekürzt, E-Mail-Adressen maskiert, Absendernamen und Zugangsdaten nie gespeichert.

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

### Pro-Hinweise zeigen (`show_pro_hints`)
- **Zweck:** Zeigt am Ende einzelner Einstellungsbereiche graue Hinweiszeilen zu Funktionen von Review Captain Pro (je ein Satz, kein Eingabefeld), den Kasten „In Review Captain Pro“ in der Übersicht und den Vermerk zur API-Verbindung auf der Quellen-Seite.
- **Standard:** an. **Folgen:** aus = keine Pro-Hinweise mehr. Am Funktionsumfang des kostenlosen Plugins ändert das nichts; Einstellungen, die Review Captain Pro selbst mitbringt, erscheinen weiterhin, sobald das Add-on sie anmeldet.
- **Voraussetzung:** keine.

## Quellen & Synchronisierung

Diese Einstellungen wirken erst, wenn eine Quelle automatisch abgeglichen wird (Review Captain Pro oder ein installierter Connector). Konten, Profile und Zugangsdaten verwalten Sie unter **Quellen**.

### Synchronisierungsfenster (`sync_window_start`, `sync_window_end`)
- **Zweck:** Zeitraum, in dem automatische Abgleiche starten dürfen, in Website-Zeit. **Standard:** 02:00–05:00.
- **Folgen:** außerhalb des Fensters startet kein automatischer Abgleich; „Jetzt synchronisieren“ auf der Quellen-Seite bleibt möglich. Ende vor Beginn = Fenster über Mitternacht; Beginn und Ende dürfen nicht gleich sein. Format HH:MM (24 h); ungültige Eingaben werden gemeldet und nicht gespeichert.
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

Diese Einstellungen bringt Review Captain Pro mit; sie erscheinen nur, solange die Funktion „Bewertungsanfragen“ des Add-ons aktiv ist. Ohne Pro (oder ohne gültige Lizenz) stehen hier nur graue Hinweiszeilen bzw., wenn die Pro-Hinweise ausgeschaltet sind, der Satz „Hier erscheinen Einstellungen von Erweiterungen.“. Ihre gespeicherten Werte bleiben in jedem Fall erhalten und gelten wieder, sobald die Funktion aktiv ist; es wird aber nichts versendet, solange sie es nicht ist. Einzelne Kampagnen verwalten Sie unter **Anfragen**.
Immer gilt, unabhängig von Einstellungen: höchstens eine Einladung je tatsächlichem Erlebnis und Empfänger; kein Versand ohne bekannte Versanderlaubnis; wer sich abmeldet, erhält von dieser Website keine Einladungen mehr; Einladungen werden nie nach Zufriedenheit gefiltert.

### Absendername / Absenderadresse (`sender_name`, `sender_email`)
- **Zweck:** Absender von Einladungen. **Standard:** Name = Markenname; Adresse leer = **kein Versand**.
- **Folgen:** Die Adresse muss zu einer Domain gehören, für die Ihr Versanddienst eingerichtet ist (SPF/DKIM), sonst landen Einladungen im Spam. Senden Sie vorher eine Testmail.
- **Datenschutz:** Adresse kann personenbezogen sein; Änderungen werden maskiert protokolliert.
- **Voraussetzung:** Pro, Recht „Review-Anfragen“, eingerichteter Versandweg.

### Versandzeitfenster (`send_window_start`, `send_window_end`)
- **Zweck:** Uhrzeiten, zu denen Einladungen verschickt werden dürfen (Website-Zeit). **Standard:** 09:00–18:00. Ende vor Beginn = Fenster über Mitternacht.
- **Folgen:** Einladungen außerhalb werden auf das nächste Fenster verschoben. Kampagnen dürfen das Fenster nur verkleinern. Beginn und Ende dürfen nicht gleich sein.

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

### Spam-Schutz (Captcha) (`captcha_provider`)
- **Zweck:** zusätzliche Prüfung vor dem Annehmen von Bewertungen, Meldungen und Fragen (SET-RC-11). **Standard:** „Keiner“ (`none`). Wählbar: „Eingebaute Rechenprüfung“ (`pow`).
- **Eingebaute Rechenprüfung:** Der Browser rechnet im Hintergrund kurz eine Aufgabe (Proof of Work, SHA-256), sobald jemand das Formular benutzt; danach wird ganz normal abgeschickt. Kein Rätsel, keine Bilder, nichts anzuklicken; der Stand wird in einer `aria-live`-Zeile angesagt. Keine Anfrage an Dritte, keine Cookies, keine personenbezogenen Daten. Dasselbe Verfahren wie in Form Captain; Beschreibung in [spam-proof-of-work.md](../spam-proof-of-work.md). Schwierigkeit per Filter `review_captain_pow_max_number_v1` (Standard 100 000, 1 000–10 000 000).
- **Folgen:** Ohne JavaScript kann das Formular nicht mehr abgeschickt werden; Besucher sehen vorab einen Hinweis und nach dem Absenden die Meldung „… bitte aktivieren Sie JavaScript …“ (`pow_missing`). Fehlende, falsche, abgelaufene oder doppelt benutzte Lösungen werden mit einem neutralen Hinweis abgelehnt (`pow_invalid`, `pow_replayed`); gespeichert wird nichts, auch kein Entwurf. Funktioniert mit Seiten-Caches (die Aufgabe wird beim ersten Benutzen über `admin-ajax.php` geholt).
- **Externe Anbieter:** nur über den Filter `review_captain_spam_checks_v1` und **nur nach Rechtsprüfung** (externe Dienste übertragen in der Regel personenbezogene Daten). Ein Grund aus dem Filter lehnt neutral ab (`spam_check`).
- **Datenschutz:** keiner beim eingebauten Verfahren; gespeichert wird bis zum Ablauf der Aufgabe (1 Stunde) nur ein Hash der benutzten Aufgabe.

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
- **Standard:** 20 bzw. 5 000 Zeichen; erlaubt 0–1000 bzw. 50–20 000. Der Mindestwert muss kleiner als der Höchstwert sein (wird beim Speichern geprüft). Abweichungen je Formular sind in v1 nicht vorgesehen.

### Titelfeld anbieten (`allow_title`)
- **Zweck:** optionales Feld „Titel“ im Formular. **Standard:** an. Gilt für alle Formulare der Website.

### Formulartexte (`name_label`, `email_hint`, `submit_label`) – SET-RC-04
- **Zweck:** Beschriftung des Namensfelds, Hinweis unter dem E-Mail-Feld und Text der Absende-Schaltfläche. **Standard:** leer = heutige Texte („Name (wird veröffentlicht)“, „Wird nie veröffentlicht. Wir nutzen sie nur für Rückfragen und Datenschutzauskünfte.“, „Bewertung senden“).
- **Grenzen:** 100 / 300 / 60 Zeichen, reiner Text (HTML wird entfernt). Filter `review_captain_public_text` (ID `submissions.name_label` usw.) gewinnt.
- **Datenschutz:** Der E-Mail-Hinweis ist ein **Rechtstext – bitte prüfen lassen**.

### Text der Einwilligung (`consent_text`) – SET-RC-04
- **Zweck:** Text neben dem Kontrollkästchen „Einwilligung zur Veröffentlichung“. **Standard:** leer = heutiger Text („Ich willige ein, dass meine Bewertung mit meinem Namen auf dieser Website veröffentlicht wird. …“).
- **Platzhalter:** `{privacy_link}` wird zum Link „Datenschutzerklärung“ auf Ihre WordPress-Datenschutzseite (ohne Datenschutzseite: das Wort ohne Link; Filter `review_captain_privacy_policy_url`). Höchstens 2 000 Zeichen, sonst reiner Text.
- **Rechtsprüfung:** **Rechtstext – bitte prüfen lassen.** Der Standardtext ist ein Entwurf und nicht rechtlich freigegeben.

### Benachrichtigungen (`notify_admin`, `notify_recipients`, `notify_subject`, `notify_body`, `notify_questions`) – SET-RC-09
- **Zweck:** E-Mail an die Moderation bei neuer Bewertung (`notify_admin`, Standard an), bei neuer Produktfrage (`notify_questions`, Standard an, wirkt nur mit „Produktfragen“) und bei Meldungen (siehe `report_notify`).
- **Empfänger:** `notify_recipients` – bis zu 10 Adressen, eine je Zeile oder durch Komma getrennt; ungültige Adressen werden verworfen und beim Speichern gemeldet. **Standard:** leer = Administrator-Adresse der Website. Filter `review_captain_notify_recipients` gewinnt.
- **Betreff/Text (nur Bewertungen):** leer = Standardtext. Platzhalter `{site}` (Website-Name), `{moderation_url}` (Link zur Moderation), `{status}` („wartet auf Moderation“ / „freigegeben“). Betreff höchstens 200, Text höchstens 2 000 Zeichen. Filter `review_captain_notification` kann jede Mail ändern oder unterdrücken.
- **Datenschutz:** Die Mails enthalten nie den Text der Bewertung oder Frage und keine Kontaktdaten der Verfasser oder Meldenden – auch nicht mit eigenem Text, denn dafür gibt es keinen Platzhalter.

### Erweiterte Funktionen nach 1.0 (alle standardmäßig aus)
- **Interne Tags (`tags_enabled`):** Schlagwörter für die Moderation, filterbar, nie öffentlich oder im Export.
- **Öffentliches Melden (`reports_enabled`, `report_threshold`, `report_rate_limit_per_hour`):** Link „Melden“ unter jeder freigegebenen Bewertung. Ab der eingestellten Zahl offener Meldungen (Standard 1) wird die Bewertung auf „Gemeldet“ gesetzt und bis zur Prüfung ausgeblendet. Über Meldende wird nichts gespeichert; zur Begrenzung dient ein kurzlebiger gehashter Wert.
- **Foto-Upload (`photos_enabled`, `photo_max_kb`, `photo_max_edge`):** ein optionales Foto je Bewertung (JPG, PNG, WebP, Standard höchstens 2 048 KB). Das Bild wird neu kodiert und dabei auf die längste Kante `photo_max_edge` verkleinert (Standard 1 600 px, erlaubt 400–4 000, erweiterte Einstellung; Filter `review_captain_photo_max_edge`); Metadaten wie der Aufnahmeort werden entfernt. Sichtbar erst nach Freigabe und mit „Fotos anzeigen“. Wird mit der Bewertung und bei Datenschutz-Löschungen entfernt. Mehrere Fotos je Bewertung sind nicht vorgesehen (Datenmodell, Foto-Adresse und Anzeige sind auf ein Foto ausgelegt; SET-RC-27).
- **Korrektur durch Autorinnen und Autoren (`corrections_enabled`, `correction_link_hours`):** Wer eine freigegebene Bewertung geschrieben hat, kann sie per Einmal-Link oder angemeldet korrigieren oder zurückziehen; Korrekturen erscheinen erst nach Freigabe. Der Einmal-Link gilt `correction_link_hours` Stunden (Standard 1, erlaubt 1–72, erweiterte Einstellung); Formularhinweis und E-Mail nennen die eingestellte Dauer.
- **Formular für mehrere Produkte (`multi_product_enabled`):** ein Formular mit `context_ids` bewertet mehrere Produkte; je Person und Produkt ist nur eine Bewertung möglich.
- **Produktfragen (`qa_enabled`):** Fragen und Antworten getrennt von Bewertungen, mit Moderation; ohne Schema-Markup.
- **Feedback-Links und QR-Codes (`feedback_campaigns_enabled`):** Link mit QR-Code (SVG, beschriftet „Feedback geben – Kein Ticket – kein Einlasscode“) und optional ein offizieller WhatsApp-Click-to-Chat-Link (`wa.me`).

### Korrekturen durch Verfasser (`corrections_enabled`)
- **Zweck:** Wer eine Bewertung abgegeben hat, kann sie über einen Einmal-Link per E-Mail (oder angemeldet) korrigieren oder zurückziehen. **Standard:** aus.
- **Folgen:** Korrekturen werden erst nach Freigabe sichtbar; die Karte zeigt „bearbeitet am …“. Fristen (Link 1 Stunde, Aufbewahrung 90 Tage) sind in v1 fest.

### Meldungen (`report_reasons`, `report_notify`, `report_confirmation`) – SET-RC-26
- **Meldegründe:** Auswahl aus der festen Liste Spam/Werbung, beleidigend, personenbezogene Daten, vermutlich keine echte Erfahrung, anderer Grund. **Standard:** alle. Mindestens ein Grund bleibt aktiv; nicht angebotene Gründe werden beim Absenden abgelehnt. Filter `review_captain_report_reasons` gewinnt.
- **Benachrichtigen:** „Empfänger der Benachrichtigungen“ (Standard) oder „Niemand“. Je angenommener Meldung eine Mail mit Grund und Link zur Liste der gemeldeten Bewertungen; keine Angaben zur meldenden Person.
- **Rückmeldung an Meldende:** Text nach dem Absenden, höchstens 300 Zeichen. **Standard:** leer = „Danke für Ihre Meldung. Wir prüfen die Bewertung.“

## Darstellung

Diese Werte sind die **Voreinstellung für neue Widgets**. Einzelne Widgets können abweichen. Die Quellenangabe externer Bewertungen (Plattformname, Link) wird immer angezeigt und lässt sich nicht abschalten.

### Sterne, Datum, Autor, Profilbild (`show_stars`, `show_date`, `show_author`, `show_avatar`)
- **Standard:** Sterne, Datum und Autor an; Profilbild aus. Das Datum ist das Veröffentlichungsdatum, sonst das Eingangsdatum.
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

### Hinweis „So prüfen wir Bewertungen“ (`verification_notice_show`, `verification_notice`, `verification_notice_position`) – SET-RC-01
- **Zweck:** aufklappbarer Hinweis (Link „So prüfen wir Bewertungen“) an jedem Bewertungs-Widget und am Bewertungsformular, der erklärt, ob und wie Bewertungen geprüft werden (Transparenzpflicht, z. B. EU-Omnibus-Richtlinie).
- **Standard:** an, unter der Liste bzw. dem Formular. Text leer = neutraler Entwurf, der **nur die Prüfstufen beschreibt, die auf Ihrer Website vorkommen** (am Formular zusätzlich „ohne Prüfkennzeichen“, weil Formular-Bewertungen ungeprüft sind). Eigener Text höchstens 3 000 Zeichen, reiner Text, Absätze bleiben erhalten.
- **Filter:** `review_captain_verification_notice` (`$text`, `$context` = `widget`|`form`, `$levels`) gewinnt; leerer Rückgabewert blendet den Hinweis aus. Pro-Widgets können `Renderer::verification_notice()` nutzen.
- **Rechtsprüfung:** **Rechtstext – bitte prüfen lassen.** Der Entwurf macht keine rechtlichen Aussagen; beschreiben Sie nur, was Sie tatsächlich prüfen.

### Texte der Prüfstufen (`label_purchase_verified`, `label_attendance_confirmed`, `label_external`, `label_testimonial`, `label_unverified`, `show_unverified_label`) – SET-RC-02
- **Zweck:** Wortlaut der Kennzeichen an den Bewertungskarten. **Standard:** leer = „Verifizierter Kauf“, „Teilnahme bestätigt“, „Externe Plattformbewertung“, „Erfahrungsbericht dieser Website“, „Ungeprüft“; je höchstens 60 Zeichen. Filter `review_captain_public_text` (ID `display.label_…`) gewinnt.
- **Folgen:** Nur der Text ist einstellbar. Welche Stufe eine Bewertung hat, setzt allein eine angebundene, vertrauenswürdige Quelle (Kauf/Teilnahme) bzw. der Abgleich (externe Plattform); keine Einstellung ändert die Stufe. „Ungeprüft“ erscheint nur mit `show_unverified_label` (Standard aus).

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
- **Standard:** 90 bzw. 30 Tage, jeweils erlaubt 1–3650. Protokolle enthalten keine Reviewtexte oder Zugangsdaten. Änderungen an Einstellungen und Rollen haben eine eigene Frist (nächster Eintrag).

### Änderungsprotokoll der Einstellungen (`retention_audit_days`)
- **Zweck:** Wie lange Einträge zu Einstellungs- und Rollenänderungen (mit altem und neuem Wert) aufbewahrt werden. **Standard:** 365 Tage, erlaubt 1–3650. Ein Filter `review_captain_retention_audit_days` hat Vorrang.
- **Folgen:** Sie können länger nachvollziehen, wer wann was geändert hat, ohne die übrigen Protokolle länger zu speichern.
- **Datenschutz:** enthält die Benutzer-ID; Texte gekürzt, E-Mail-Adressen maskiert, Absendernamen und Zugangsdaten nie.

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
- **Bestätigung (SET-RC-20):** Zum Einschalten tippen Sie `LÖSCHEN` in das Feld unter dem Schalter (auch `LOESCHEN` oder `DELETE`). Der Zeitpunkt wird gespeichert und angezeigt („Bestätigt am …“). Gelöscht wird nur mit dieser Bestätigung; Ausschalten hebt sie auf. Ein Konfigurationsimport kann die Löschung nicht einschalten. War der Schalter schon vor dieser Version an, bleiben die Daten erhalten, bis Sie einmal bestätigen; die Seite weist darauf hin.
- **WP-CLI:** `wp review-captain settings set privacy.delete_data_on_uninstall 1 --confirm-word=LÖSCHEN --user=<admin>`.

### Datenschutzhinweis im Formular (`privacy_notice`)
- **Standard:** leer = kurzer Standardtext mit Link zu Ihrer WordPress-Datenschutzseite. Eigener Text wird als reiner Text gespeichert (Absätze bleiben erhalten, HTML wird entfernt).

## Zugriff & Rollen

### Rechte je Rolle (`moderate_roles`, `sources_roles`, `requests_roles`, `reports_roles`, `trust_roles`, `privacy_roles`, `settings_roles`)
- **Zweck:** Eine Tabelle Rolle × Recht für alle sieben Rechte: Reviews moderieren, Quellen verwalten, Anfragen verwalten, Berichte & Diagnose ansehen, Vertrauenszeichen verwalten, Datenschutz verwalten, Einstellungen ändern.
- **Standard:** Moderieren: Administrator, Redakteur; alle übrigen Rechte: nur Administrator. Administratoren behalten immer alle Rechte (das Kästchen ist fest angehakt).
- **Folgen:** Moderierende sehen eingereichte Reviews inklusive Kontaktadresse. Wer „Datenschutz verwalten“ hat, ändert Aufbewahrungsfristen und die Löschung bei Deinstallation.
- **Sicherheit:** Sie können nur Rechte vergeben oder entziehen, die Sie selbst haben; die übrigen Spalten sind „nur lesen“. Das gilt auch für Import, Zurücksetzen und Wiederherstellen. Jede tatsächliche Änderung eines Rechts wird mit den Rollen vorher und nachher protokolliert.
- **Aktualisierung:** Ältere Versionen speicherten nur „Moderieren“ und „Einstellungen“. Die übrigen Spalten werden beim ersten Aufruf aus den Rollen übernommen, die das Recht heute haben (auch wenn es über ein Rollen-Plugin vergeben wurde); dabei ändert sich kein Recht.
- **Voraussetzung:** Sie benötigen selbst das Recht, Benutzerrollen zu vergeben.

## Erweitert

### Action Scheduler verwenden (`use_action_scheduler`)
- **Standard:** an (erweiterte Einstellung); wirkt nur, falls Action Scheduler verfügbar ist (z. B. durch WooCommerce). Sonst WP-Cron. Bei wenig Besucherverkehr empfehlen wir einen Server-Cron.

### Zeitbudget je Lauf (`job_time_budget`) / Freigabe hängender Jobs (`lease_seconds`)
- **Standard:** 20 bzw. 300 Sekunden; erlaubt 5–120 bzw. 60–3600 (erweiterte Einstellungen). Nur ändern, wenn die Diagnose Zeitüberschreitungen meldet. Die Freigabezeit muss länger als das Zeitbudget sein (wird beim Speichern geprüft).

### Fehlerprotokoll (`debug_logging`)
- **Standard:** aus (erweiterte Einstellung). An = jede fehlgeschlagene Hintergrundaufgabe schreibt zusätzlich eine Zeile ins PHP-Fehlerprotokoll des Servers: Aufgabennummer, Typ, Versuch, Fehlercode und Art des Fehlers (z. B. Ausnahmeklasse). Fehlermeldung und Nutzdaten werden bewusst nicht geschrieben, das Protokoll enthält daher keine Zugangsdaten, Reviewtexte oder E-Mail-Adressen. Schalten Sie es nach der Fehlersuche wieder aus (keine automatische Abschaltung in v1). Filter `review_captain_debug_log_line` (z. B. für eigene Logger; leerer Rückgabewert unterdrückt die Zeile).

## Grenzen, die per Filter angehoben werden können (SET-RC-31)

Für Entwickler; die Obergrenzen sind fest und schützen Server und Datenbank.

| Grenze | Standard | Filter | Obergrenze |
|---|---|---|---|
| Feedback-Links | 50 | `review_captain_max_feedback_links` | 200 (alle Links liegen in einer Option) |
| Produkte je Mehrprodukt-Formular | 20 | `review_captain_form_max_items` | 50 |
| Interne Tags je Bewertung | 20 | `review_captain_max_tags` | 50 |

## WP-CLI

Seit SET-RC-18 lassen sich alle Einstellungen auch auf der Kommandozeile verwalten. Es gelten dieselben Prüfungen und Rechte wie auf der Einstellungsseite; jede Änderung wird protokolliert. Lesen geht ohne Benutzer, Schreiben braucht `--user=<ID|Login>` mit dem Recht „Einstellungen ändern“ (für Datenschutz zusätzlich „Datenschutz verwalten“, für Rollen zusätzlich „Benutzerrollen vergeben“).

| Befehl | Wirkung |
|---|---|
| `wp review-captain settings list [--tab=<bereich>] [--format=table\|json\|csv\|yaml]` | alle Einstellungen mit aktuellem Wert, Standard, Typ und Herkunft |
| `wp review-captain settings get <bereich.schlüssel> [--format=plain\|json]` | einen Wert ausgeben, z. B. `submissions.min_text_length` |
| `wp review-captain settings set <bereich.schlüssel> <wert> [--confirm-word=LÖSCHEN] --user=<admin>` | einen Wert setzen; Ja/Nein als `1`/`0` (auch `true`/`false`, `on`/`off`, `ja`/`nein`), Rollen durch Komma getrennt. Ungültige Werte brechen mit Fehlermeldung ab, nichts wird korrigiert. |
| `wp review-captain settings reset <bereich>… \| --all --yes --user=<admin>` | Bereiche oder alles auf den Standard zurücksetzen; der vorherige Stand wird gesichert. Bereiche ohne Berechtigung werden übersprungen. |
| `wp review-captain settings export [--file=<pfad>]` | Konfiguration als JSON (ohne Zugangsdaten, wie der Download auf der Seite) |
| `wp review-captain settings import <datei> --dry-run --user=<admin>` | Vorschau „bisher/neu“ wie auf der Seite, nichts wird gespeichert |
| `wp review-captain settings import <datei> --yes --user=<admin>` | übernehmen, vorher wird gesichert (wiederherstellbar unter Einstellungen › Erweitert) |

## Abnahme

Entspricht SET-023, wenn:

- [ ] jede Einstellung aus [option-schema.md](option-schema.md) hier mit Zweck, Geltungsbereich, Standard, Folgen, Datenschutzbezug und Anbieter-/Lizenzvoraussetzung erklärt ist;
- [ ] Texte ohne interne Begriffe auskommen und mit Betreibern getestet sind (UX-013);
- [x] Standardwerte und Wertebereiche mit der PHP-Implementierung übereinstimmen (abgeglichen 2026-11-16);
- [ ] die Hilfetexte im Admin aus diesen Formulierungen abgeleitet sind.
