# Changelog

Alle nennenswerten Änderungen am kostenlosen Core **Review Captain** (`review-captain`) stehen in dieser Datei.
Das Format folgt [Keep a Changelog 1.1.0](https://keepachangelog.com/de/1.1.0/), die Versionsnummern folgen [Semantic Versioning](https://semver.org/lang/de/).
Das Add-on Review Captain Pro hat ein eigenes Änderungsprotokoll (`captain-pro/addons/review-captain-pro/readme.txt`, `docs/RELEASE-NOTES-0.1.0.md`).

## [Unreleased]

### Added
- Gefilterte Anzeige offenlegen (SET-RC-03, Entscheidung der Inhaberin E3 vom 24.11.2026): Filtert ein Widget nach Mindestbewertung oder Plattform, erscheint automatisch der Hinweis „Es werden nur Bewertungen ab X Sternen angezeigt“ (`<p class="rc-filter-notice">`, Text `display.filter_notice` mit Platzhaltern `{stars}`/`{platform}`, leer = Standardtext; Filter `review_captain_public_text`). Neuer Schalter `display.allow_min_rating` (Standard an; aus = `min_rating` in Shortcode, Block und Elementor ignoriert; Filter `review_captain_allow_min_rating`). Die Zusammenfassung rechnet weiter über alle freigegebenen Bewertungen. Wortlaut: Rechtsprüfung offen.
- Nur Käufer dürfen bewerten (SET-RC-32, Entscheidung E6, Free): `submissions.require_purchase` und `submissions.purchase_max_age_days` (0 = keine Frist). Das Formular nimmt nur Bewertungen an, deren Kauf oder Teilnahme eine vertrauenswürdige Quelle bestätigt (WooCommerce: angemeldeter Kunde mit bezahlter Bestellung des Produkts; sonst Filter `review_captain_purchase_proof`, z. B. Einladungslink mit `rc_invite`); alle anderen sehen statt des Formulars einen klaren Hinweis, der Handler lehnt mit `purchase_login`/`purchase_required`/`purchase_too_old` ab, nichts wird gespeichert. Angenommene Bewertungen tragen die bestätigte Prüfstufe. Neue Klasse `Frontend\PurchaseGate`. Skala bleibt 1–5; automatische Freigaberegeln bleiben Pro (SET-RC-35).
- Einstellungsmigration (Entscheidungen der Inhaberin vom 24.11.2026): `Settings\SettingsMigrator` bringt `review_captain_settings` einmalig auf `SettingsSchema::VERSION` 2 (`Settings::OPTION_VERSION` wird jetzt gelesen, einmal je Anfrage und Website sowie in `Installer::install_site()`; Audit `settings.migrated`). Neue Installationen erhalten die neuen Vorgaben, bestehende behalten ihr Verhalten: `privacy.retention_contact_days` bleibt dort 0 = unbegrenzt (E5/SET-RC-08), und wer `submissions.notify_admin` aus hatte, bekommt `report_notify` = „Niemand“ und `notify_questions` = aus (P2-13). Eine Wiederherstellung älterer Sicherungen durchläuft dieselben Schritte.
- Konfigurationsexport „Mit Kontaktangaben exportieren“ (P1-8): Kästchen unter „Konfiguration herunterladen“ und `wp review-captain settings export --with-contacts`, Standard aus. Ohne sie fehlen Felder mit `privacy = config` (Markenname, Empfänger der Benachrichtigungen, Pro-Absenderangaben); JSON und Audit `settings.exported` vermerken `with_contacts`. Geheimnisse werden weiterhin nie exportiert.
- Datenbankschema v5 (Entscheidungen E4/E7 der Inhaberin vom 24.11.2026): neue Spalten `reviews.incentivised`, `reviews.reply_body`, `reviews.reply_at`; Migrationsschritt 5 (idempotent).
- Kennzeichen „Gegen einen Vorteil erhalten“ (SET-RC-12): Merkmal an der Bewertung, setzbar im Bewertungs-Editor (alle Eintragsarten), über `WorkflowService::create_draft()` (`incentivised: true`, nur bool), den neuen Filter `review_captain_submission_incentivised` (Einladungs-Kampagnen) und die CSV-Spalte `incentivised` (Fehler `invalid_flag`). Ist es gesetzt, zeigt die Karte immer das Kennzeichen `rc-badge--incentivised` (Text `display.label_incentivised`, leer = „Für diese Bewertung wurde ein Vorteil gewährt“; kein Schalter, auch ein leerer Filterwert oder eine eigene Theme-Vorlage blendet es nicht aus – `Renderer::render_card()` fügt es dann ein) und die Bewertung erhält nie strukturierte Daten (neue Regel E3b, Grund `incentivised`). Ein Plattform-Abgleich setzt das Merkmal nie zurück. In CSV-Export, Datenauskunft und Konfigurationsexport enthalten. Rechtsprüfung des Standardtextes offen.
- Händlerantwort auf eigene Bewertungen (SET-RC-13): Text und Datum je lokaler Bewertung im Bewertungs-Editor („Deine Antwort“, „Datum der Antwort“; höchstens 5 000 Zeichen), Ausgabe als `<section class="rc-reply">` unter der Bewertung (`$rc['reply']` in `templates/review-card.php`); Einstellungen `display.show_replies` (Standard an) und `display.reply_label` (leer = „Antwort von {brand}“, `{brand}` = `general.brand_name` bzw. Titel der Website). Zählt nie in Durchschnitte, wird nie ausgezeichnet; steht in Datenauskunft und CSV-Export (`reply_body`, `reply_at`), der Eraser (Anonymisieren) und der Tombstone leeren sie. Antworten auf Plattformbewertungen bleiben Pro (`review.platform_replies`, Name entschieden, nicht gebaut); `Review::validate()` lehnt sie im Kern ab (`reply_on_external`).
- Einstellungen für Texte und Transparenz (SET-RC-01, -02, -04, -09, -26): Hinweis „So prüfen wir Bewertungen“ an jedem Bewertungs-Widget und am Formular (`display.verification_notice_show`/`_notice`/`_notice_position`, neutraler Entwurf nur für die Prüfstufen, die auf der Website vorkommen, Filter `review_captain_verification_notice`; Rechtstext, Rechtsprüfung offen); Texte der Prüfstufen (`display.label_*`) und Schalter `display.show_unverified_label` – die Stufe selbst bleibt nicht einstellbar; neue Kennzeichen „Teilnahme bestätigt“ und „Externe Plattformbewertung“ an den Karten; Einwilligungstext mit Platzhalter `{privacy_link}`, Beschriftung Name, E-Mail-Hinweis und Absende-Schaltfläche (`submissions.consent_text`, `name_label`, `email_hint`, `submit_label`; leer = bisheriger Text); Benachrichtigungen mit eigenen Empfängern, Betreff und Text (`submissions.notify_recipients`, `notify_subject`, `notify_body`, Platzhalter `{site}`, `{moderation_url}`, `{status}`), zusätzlich bei Meldungen (`submissions.report_notify`) und neuen Fragen (`submissions.notify_questions`), weiterhin ohne Bewertungstext und Kontaktdaten (`Moderation\Notifier`, Filter `review_captain_notify_recipients`, `review_captain_notification`); Meldegründe und Rückmeldung an Meldende (`submissions.report_reasons`, `report_confirmation`, Filter `review_captain_report_reasons`). Filter `review_captain_public_text` für alle neuen Texte. Neuer Feldtyp `email_list`, Feldmerkmale `max_length`, `max_items`, `min_items`, `choices` für Listen.
- Fehlende Beschriftungen und Hilfetexte ergänzt (SET-RC-05 Teil d, SET-RC-36): `submissions.corrections_enabled` (Abschnitt „Korrekturen“), `display.schema_enabled` (Abschnitt „Suchmaschinen“), `requests.send_window_end`, `submissions.allow_title`, `display.show_date`, `privacy.retention_jobs_days`.
- Einstellungen, Zugriff & Rollen (SET-RC-16): Matrix Rolle × Recht für alle sieben Rechte (neu verteilbar: Quellen, Anfragen, Berichte, Vertrauenszeichen, Datenschutz; neue Schlüssel `access.sources_roles`, `requests_roles`, `reports_roles`, `trust_roles`, `privacy_roles`). Administratoren behalten alles, nur selbst gehaltene Rechte sind vergebbar, je tatsächlich geänderter Capability ein Protokolleintrag mit Rollen vorher/nachher. Bestehende Installationen übernehmen die neuen Listen einmalig aus dem heutigen Rollenstand; `moderate_roles`/`settings_roles` bleiben unverändert.
- Änderungsprotokoll mit altem und neuem Wert (SET-RC-17): `settings.changed`, `.reset`, `.imported`, `.restored` tragen `changes` (`{field, from, to}`); E-Mail-Adressen maskiert, Texte auf 40 Zeichen gekürzt, Absendernamen und Geheimnisse nur `[verborgen]`. Neue Einstellung `privacy.retention_audit_days` (Standard 365, Filter `review_captain_retention_audit_days`) für Einstellungs- und Rolleneinträge, getrennt von `retention_logs_days`. Diagnose-Protokoll mit Filter „Einstellungen“ (alt → neu).
- WP-CLI `wp review-captain settings list|get|set|reset|export|import` (SET-RC-18): gleiche Prüfung, Rechte, Bestätigungswort und Protokollierung wie die Einstellungsseite; Schreiben braucht `--user`; `reset` je Bereich oder `--all` mit `--yes`; `import --dry-run` zeigt die Vorschau.
- Bestätigungswort für „Daten beim Deinstallieren löschen“ (SET-RC-20): Einschalten nur mit `LÖSCHEN` (auch `LOESCHEN`, `DELETE`); der Zeitpunkt wird als `privacy.delete_data_confirmed_at` gespeichert und angezeigt.
- Einstellungen für den Verkauf (SET-RC-08/10/21/22/23/27/28/29/31), alle mit Standard = bisheriges Verhalten:
  - Moderationsregeln: `submissions.hold_words`, `submissions.hold_links` (halten nur zur Prüfung zurück, Grund im Verlauf `held_for_moderation`), `submissions.block_email_domains` (neutrale Ablehnung `email_blocked`), `submissions.min_seconds` (bisher Konstante 3). Filter `review_captain_submission_hold_reasons`, `review_captain_blocked_email_domains`, `review_captain_form_min_seconds`.
  - Aufbewahrung: `privacy.retention_contact_days` (E-Mail-Adresse wird nach N Tagen entfernt, Bewertung bleibt; 0 = unbegrenzt, Standardfrist wartet auf Entscheidung E5), `privacy.retention_approved_days` (0 = nie), `privacy.retention_corrections_days` (90). Vorschau „betrifft N Bewertungen“ im Reiter Datenschutz, Verlauf `contact_removed`, Audit `retention.contacts_removed`.
  - Korrektur-Link-Gültigkeit `submissions.correction_link_hours` (1–72 h, Standard 1); Hinweis und E-Mail nennen die eingestellte Dauer.
  - Darstellung: `display.rating_display` (Sterne/Zahl/beides), `display.decimal_places` (0–2) für Durchschnitte, `display.star_color`/`display.accent_color` (Hex, als CSS-Variablen; neuer Feldtyp `color`).
  - Strukturierte Daten: `display.schema_contexts` (Produkt/Beitrag/Veranstaltung, nur einschränkend) und `display.schema_min_reviews`; neue Gründe `context_disabled`/`below_minimum`, SEO-Diagnose nennt die Einschränkungen.
  - Globale Widget-Vorgaben `display.widget_limit`, `widget_layout`, `widget_columns`, `widget_heading_level`, `widget_new_tab` (Filter `review_captain_widget_defaults`); Block- und Elementor-Steuerelemente ohne eigenen Wert folgen ihnen.
  - `submissions.photo_max_edge` (400–4000 px, Standard 1600) und `submissions.trusted_proxy_header` (aus; X-Forwarded-For/X-Real-IP/CF-Connecting-IP für die Sendebegrenzung hinter einem Proxy).
  - Grenzen per Filter anhebbar mit fester Obergrenze: `review_captain_max_feedback_links` (50, max. 200), `review_captain_form_max_items` (20, max. 50), `review_captain_max_tags` (20, max. 50).
- Spam-Schutz mit eingebauter Rechenprüfung (SET-RC-11, Entscheidung S7): neue Einstellung `submissions.captcha_provider` (`none` Standard, `pow`) im Abschnitt „Schutz vor Missbrauch“. `pow` ist dasselbe selbst gehostete Proof-of-Work-Verfahren wie in Form Captain: `Spam\ProofOfWork\ProofOfWork` und `assets/js/proof-of-work.js` sind unveränderte Kopien (nur Namespace bzw. globale Variable `window.reviewCaptainPow`), dazu `Security\PowCaptcha` und `assets/js/pow-form.js`. Keine Anfrage an Dritte, keine Cookies, keine personenbezogenen Daten, kein Rätsel; Status in einer `aria-live`-Zeile, Aufgabe per `admin-ajax.php` (funktioniert mit Seiten-Caches), gebunden an Formular und Kontext, 30 Minuten gültig, jede Lösung nur einmal (atomar über `add_option()`, tägliche Wartung räumt auf). Gilt für Bewertungs-, Melde- und Frageformular; Ablehnung neutral ohne Speichern (`pow_missing` mit Hinweis auf JavaScript, `pow_invalid`, `pow_replayed`). Filter `review_captain_pow_max_number_v1` (Schwierigkeit, Standard 50 000) und `review_captain_spam_checks_v1` (externe Anbieter, nur nach Rechtsprüfung). Beschreibung `docs/spam-proof-of-work.md`.
- Release-Prüfung `tests/Unit/Settings/SettingsReadersTest.php`: jedes Core-Feld hat eine Beschriftung und einen Leser in `src/` (Ausnahmeliste für nur von Pro gelesene Felder, SET-RC-05).
- Vertrag 1.1.0 (RC-4): Payload `review_captain.review.v1` der Hooks `review_captain_review_created_v1`, `_status_changed_v1` und `_removed_v1` enthält zusätzlich `rating_value`, `rating_scale_min` und `rating_scale_max`. `rating_value` ist `null`, wenn die Bewertung keine Sterne hat. Neu ist außerdem `removal`: `tombstoned` (Status „entfernt“, Zeile bleibt) oder `deleted` (Zeile gelöscht) bei `review_captain_review_removed_v1`, sonst `null`. ADR-0007 stellt klar: Zusätzliche optionale Felder heben nur MINOR.
- Öffentliche Vertragsversion `REVIEW_CAPTAIN_CONTRACT_VERSION` = `1.0.0` (SemVer, unabhängig von der Plugin-Version; Suite-Spezifikation R-3.1, RC-2), dokumentiert in `docs/contracts/README.md`, geprüft durch `tests/Unit/Support/ContractVersionTest.php` und `bin/check-plugin-meta.php`.

### Changed
- `privacy.retention_contact_days` (E5, Rechtsprüfung offen): Standard 365 Tage für neue Installationen (bestehende behalten 0 = unbegrenzt); die Frist entfernt jetzt auch die E-Mail-Adresse von Produktfragen, Frage und Antwort bleiben (P4-6, Audit `retention.question_contacts_removed`, Vorschau im Reiter Datenschutz). Feld heißt „Kontaktadresse zu Bewertungen und Produktfragen (Tage)“. Website-Zustand, Dashboard und Reiter Datenschutz mahnen, solange keine Frist gesetzt ist (S9).
- Von Autorinnen und Autoren zurückgezogene Bewertungen werden wie abgelehnte nach `privacy.retention_rejected_days` (Standard 90) samt Verlauf gelöscht (P2-14); von der Moderation archivierte bleiben.
- `general.brand_name` ist jetzt eine Kontaktangabe (`privacy = config`): nur mit „Mit Kontaktangaben exportieren“ im Export, im Änderungsprotokoll nur `[verborgen]`; der Diagnose-Export enthält keine Kontaktangaben mehr. Exporte tragen `schema_version` 2 (ältere Plugin-Versionen lehnen sie ab).
- Pro-Hinweise statt gesperrter Pro-Felder (SET-RC-06, Entscheidung S1/S5 vom 23.11.2026): Der Core definiert die sieben Felder des Reiters „Anfragen & Zustellung“ (`requests.sender_name`, `sender_email`, `send_window_start`, `send_window_end`, `daily_limit`, `cooldown_days`, `reminders_enabled`) nicht mehr selbst und kennt keine Lizenzschlüssel mehr (Schlüssel `entitlement` aus dem Schema entfernt). Review Captain Pro meldet die Felder selbst an (SET-RC-07). Ohne Add-on zeigt der Core am Ende der Reiter Allgemein, Quellen, Anfragen und Darstellung nur graue Hinweiszeilen zu verkauften Pro-Funktionen (`Admin\SettingsHints`, Format `{id, tab, title, text, link}`, Filter `review_captain_settings_hints` und `review_captain_settings_hint_link`, Standard ohne Link); kein Schloss am Reiter, kein deaktiviertes Feld, kein Hinweis „benötigt Pro“. Ein Feld mit `replaces_hint` ersetzt seine Zeile. Neue Einstellung `general.show_pro_hints` („Pro-Hinweise zeigen“, Standard an) blendet alle Hinweise aus, auch den Kasten „In Review Captain Pro“ der Übersicht (Filter `review_captain_show_pro_info` hat Vorrang) und den Vermerk auf der Quellen-Seite. Ein Reiter ohne Felder zeigt kein leeres Formular und keine Speichern-Schaltfläche. Add-on-Felder dürfen `label`, `description`, `section` und `replaces_hint` tragen; unvollständige Felder aus dem Filter werden verworfen, `owner` ist für sie standardmäßig `pro`. Nicht schreibbare Add-on-Felder tragen den neutralen Vermerk „Von einer Erweiterung verwaltet; derzeit nicht änderbar.“. Gespeicherte Werte bleiben bei Speichern, Zurücksetzen, Import und Export unverändert erhalten. Wächtertest `tests/Unit/Settings/NoLicenseKnowledgeTest.php`.
- Einstellungen prüfen beim Speichern, statt still zu korrigieren (SET-RC-19): ungültige Werte behalten den bisherigen Wert und werden in einer Fehlerliste mit Sprungmarken und `aria-invalid` am Feld gemeldet; neue Abgleiche Mindest- < Höchstlänge, Fensterbeginn ≠ -ende (Versand, Synchronisierung), `lease_seconds` > `job_time_budget` (Filter `review_captain_settings_cross_check`). Import und programmatische Wege korrigieren wie bisher.
- **Deinstallation löscht Daten nur noch mit Bestätigung** (SET-RC-20): War „Daten beim Deinstallieren löschen“ schon vor diesem Update an, bleiben die Daten erhalten, bis jemand das Wort eintippt; die Datenschutz-Seite weist darauf hin. Ein Konfigurationsimport kann die Löschung nicht einschalten.
- `sources.removed_review_behavior` wirkt jetzt (SET-RC-05): Standard `tombstone` leert bei der Quelle gelöschte Reviews sofort (Titel, Text, Name, Sternwert, Avatar-/Antwort-Meta, Foto; Platzhalter mit Abgleichschlüssel bleibt bis `retention_external_days`); `hide` behält den Inhalt bis zur Frist. Filter `review_captain_removed_review_behavior` kann nur verschärfen.
- `advanced.debug_logging` wirkt jetzt: fehlgeschlagene Hintergrundaufgaben schreiben eine Zeile ohne Fehlermeldung/Payload ins PHP-Fehlerprotokoll (Filter `review_captain_debug_log_line`).
- Block `review-captain/reviews`: Attribute `limit`, `layout`, `columns`, `headingLevel`, `newTab` ohne festen Default (globale Vorgaben greifen); Elementor-Steuerelemente stehen auf „Standard“.
- Mindestversionen ergänzt (Entscheidung 17.11.2026, einheitlich für alle Captain-Plugins): WordPress 7.0, WooCommerce 11.2.2 (optional; 11.2.0 übernimmt Bestell-/Zahlungsdaten früherer Store-API-Checkouts im selben PHP-Prozess, behoben in 11.2.2, woocommerce#70637); getestet bis WordPress 7.1 / WooCommerce 11.3.
- `docs/contracts/workflow-provider-v1.md` als nicht normativ gekennzeichnet (Inhaberentscheidung F-12); maßgeblich ist die Suite-Spezifikation in `wp-workflowmaster` (RC-1).
- Kartenüberschriften standardmäßig `h2` statt `h3` (Barrierefreiheit, F-07); Block, Shortcode und Elementor-Widget.
- WooCommerce: Core meldet Kompatibilität mit HPOS und Block-Checkout.
- Yoast-Mindestversion für die Graph-Integration auf 26.0 angehoben (aktuelle Major − 2, Entscheidung SEO-001); ältere Yoast-Versionen: kein Review-Markup, Reviews bleiben sichtbar.
- **Mindestversionen (MINVER-1, Entscheidung der Inhaberin 16.11.2026):** PHP 8.5 (Header `Requires PHP`, Laufzeitprüfung, `readme.txt`, `composer.json`, PHPCS `testVersion 8.5-`); CI-Unit-Matrix PHP 8.5 und 8.6. Datenbank: empfohlen MySQL 9.7 LTS oder MariaDB 12.3 LTS. Ältere Server lösen nur eine Warnung aus (`Support\Requirements`, `wp review-captain doctor`, Diagnose-Systeminfo), keine Sperre.
- Schema v3: indizierte Sortierspalte `sorted_at` und Keyset-Export (TEC-014, ADR-0010 vorgeschlagen).

### Removed
- Einstellung `general.default_language` (wurde nie ausgewertet; es gilt die Sprache von WordPress). Gespeicherte Werte stören nicht (SET-RC-05).

### Performance
- Plattform-Zusammenfassung der Widgets wird je Cache-Generation zwischengespeichert; jeder Schreibzugriff auf Reviews invalidiert (TEC-012, RC-136).

### Fixed
- `AdminModule::menu()` funktioniert auch, wenn Add-ons das Core-Menü außerhalb des Adminbereichs aufbauen (Lizenzseite von Pro per WP-CLI).
- Verlorene Insert-Rennen bei `JobQueue::enqueue` und `upsert_external` liefern unter hoher Parallelität zuverlässig die Gewinnerzeile statt `false` (TEC-005, MariaDB-Racetest).

### Added
- Autorenkorrektur (RC-125A): Kundinnen und Kunden können freigegebene Bewertungen per Einmal-Link (oder angemeldet) korrigieren oder zurückziehen; Korrekturen erst nach Freigabe sichtbar, Karte zeigt „bearbeitet am …“; Schema v4 (`review_revisions`), Admin-Seite „Korrekturen“, Shortcode `[review_captain_correction]`; standardmäßig aus.
- Natives Elementor-Widget „Review Captain“ (Bewertungen, Formular, Profil-Button) als Hülle um den Widget-Vertrag v1, serverseitig gerendert, nur bei aktivem Elementor geladen (RC-119A).
- Übersetzungen Englisch (`en_US`) und Deutsch formal (`de_DE_formal`) für alle 936 Texte inkl. Block-Editor-JSON; `bin/compile-translations.php` erzeugt MO/JSON ohne WP-CLI und prüft Vollständigkeit und Platzhalter (RC-138).
- `tests/e2e/ci-db.sh`: Integration gegen echtes MySQL/MariaDB (Installation, Upgrade v2→v3, Race-Tests); CI-Jobs für MariaDB 12.3 und MySQL 9.7. Release-Stand und offene Gates: [docs/release/RELEASE-CHECKLIST.md](docs/release/RELEASE-CHECKLIST.md), Einschränkungen: [docs/release/KNOWN-LIMITATIONS.md](docs/release/KNOWN-LIMITATIONS.md).

## [0.1.0-dev] – unveröffentlicht (Stand 2026-11-16)

Erste Entwicklungsversion. Kein Release: RC-141 (Release Candidate) und RC-142 (Go/No-Go) sind offen.

### Added

#### Plugin-Grundlage und Datenmodell
- Repository-Wurzel ist das Plugin `review-captain` (ADR-0004); Bootstrap, Autoloader, Modul-Container.
- Versioniertes Schema in eigenen Tabellen statt CPT (ADR-0001): `reviews`, `review_events`, `profiles`, `jobs`, `import_batches`, `audit_log`, mit Unique-Keys für Dedupe und Idempotenz; Migrator mit Upgrade-Sperre.
- Domänen-Enums (Plattform, Status, Art, Kontext) und `Review`-Entity mit Validierung (https-Quelle, Originalskala).
- `ReviewRepository`: CRUD, Moderationsübergänge mit optimistischem Locking, Zusammenfassung je Plattform, Bearbeitungshistorie.
- Capabilities und Rechtematrix, Audit-Log mit Allowlist, Ereignisse erst nach dem Commit, öffentliche Hooks v1 mit Präfix `review_captain_` (ADR-0002, ADR-0007, `docs/hooks.md`).
- Einstellungsschema mit sicheren Defaults, Validierung, Tabs und Pro-Feldern (gesperrt ohne Add-on).

#### Hintergrundjobs, Sicherheit und Betrieb
- Eigener Job-Runner mit Lease (ADR-0003): idempotentes Enqueue, atomarer Claim, Retry-Klassen, Backoff, Dead-Letter, Zeitbudget, Handler-Filter `review_captain_process_job_v1`, Redaktion von Secrets in Fehlermeldungen.
- Verschlüsselung mit libsodium inkl. Schlüsselrotation (ADR-0005), Aufbewahrung (Retention), Reconciler, Budgets.
- WP-CLI `wp review-captain jobs|maintenance|reconcile|doctor|dev seed|schema`.
- Connector-Jobs pausieren mit `rc.source.unavailable`, solange der Connector nicht aktiv ist; stündliche Wiederaufnahme.
- Frontend-Embed-Richtlinie `EmbedPolicy` (TEC-013): feste Host-Allowlist (nur verkleinerbar), click-to-load ohne Drittanbieter-Request vor dem Klick, `rel`-Attribute externer Links.

#### Admin
- Dashboard mit Erststart-Checkliste, Bewertungsliste mit Filtern und Massenmoderation (Pflichtbegründung), Editor für Bewertungen.
- Manuelle externe Bewertungen nur mit Herkunftsangabe; „verifiziert“ ist im Admin nicht setzbar.
- Quellen (Profile mit Profil-/Bewertungslink), Widgets-Seite mit Shortcode-Generator, Einstellungen aus dem Schema mit progressiver Offenlegung.
- Konfigurationsexport/-import mit Diff-Vorschau und Rollback-Snapshot, ohne Secrets und personenbezogene Felder.
- Nur lesende Diagnose mit JSON-Export (redigiert).

#### Frontend, Widgets und SEO
- Widget-Registry mit gemeinsamer `WidgetDefinition` für Shortcode `[review_captain v="1"]` und Blöcke (identische serverseitige Ausgabe, Widget-Vertrag v1); Registrierung weiterer Typen durch Add-ons.
- Templates für Karte, Raster, Liste, Plattformzusammenfassung, Testimonial-Kennzeichnung und neutralen Bewertungs-CTA ohne Review-Gating; ohne Pflicht-JavaScript.
- Bewertungsformular mit Nonce, Honeypot, signiertem Zeitstempel und Kontext, gehashtem IP-Rate-Limit, Einwilligung.
- Strukturierte Daten nur für zulässige, lokal gesammelte und gerenderte Bewertungen (Regeln E1–E8), Integration in den Yoast-Graph, JSON-LD-Fallback, `noindex` für `rc_*`-Parameter, SEO-Statusbericht; Einstellung `display.schema_enabled`.

#### Import/Export und Datenschutz
- CSV-Import mit Prüflauf, Dedupe, Herabstufung der Verifizierung und Rollback; CSV-Export mit Formelschutz, E-Mail nur nach ausdrücklicher Auswahl.
- WordPress-Datenschutz-Exporter und -Eraser (anonymisieren/löschen), Aufbewahrungsfristen, Deinstallation je Site nur mit Opt-in.
- Veraltete Import-Uploads werden vor jedem Upload und in der täglichen Wartung entfernt.

#### Verträge und Erweiterbarkeit
- Provider-Vertrag v1 mit `SyncService` (idempotentes Upsert, Tombstones), Fixture-Provider und Contract-Testcase.
- Connector-Vertrag v1: Interfaces, Validator, Registry mit Zuständen `paused`/`source_unavailable`; Demo-Connector und Connector-SDK unter `examples/`.
- Spezifikationen unter `docs/`: Architektur, ADRs, Verträge (Widget v1, Review-/Testimonial-Schema, Experience-Ownership, Workflow-Provider), Einstellungen, SEO/Yoast, Connectoren, Provider-Matrix, Betrieb (Runbook, Datenschutz-Checkliste, API-Änderungsroutine), Nutzerdoku (Onboarding, Plattform-Setup).

#### Build, Qualität, Übersetzung
- CI, reproduzierbares Release-ZIP (`bin/build-zip.sh`), Prüfung der Plugin-Metadaten (`bin/check-plugin-meta.php`), Hook-Liste (`bin/list-hooks.php`, in CI geprüft).
- Testumgebung mit SQLite und WordPress-Stubs (`vendor/bin/phpunit`), Unit-/Integrationstests inkl. Sicherheits-Regressionstests (`tests/Unit/Security/`).
- POT-Datei der Textdomain `review-captain`; Quellsprache Deutsch als Arbeitsannahme (ADR-0006).

### Changed
- Plan-Schlüssel suiteweit `single`/`business`/`agency`.
- Gutschein-Teil-Einlösung: nur die erste bestätigte Einlösung zählt als Erlebnis (Entscheidung dokumentiert).
- `ErrorCode::all()` liefert nur `rc.*`-Codes; `block.json` deklariert `type`/`newTab`.
- Dokumentation an den Code angeglichen (Schema, Provider-/Connector-Verträge, Widget v1, Einstellungen, Jobs, Secrets, Fehlercodes).

### Security
Sicherheitsprüfung RC-134 ([docs/security/review-2026-11.md](docs/security/review-2026-11.md)); keine kritischen oder hohen Befunde.
- SR-01: Rate-Limit fasst IPv6-Adressen auf /64 zusammen (Umgehung durch Adressrotation).
- SR-02: Deinstallation entfernt gespeicherte CSV-Import-Uploads immer.
- SR-03: Rollen-Tab vergibt keine Capabilities, die der handelnde Nutzer nicht hat.
- SR-06: `ReviewRepository::update()` überschreibt keine gleichzeitige Moderation mehr.
- SR-07: Das Formular füllt die E-Mail-Adresse angemeldeter Nutzer nicht mehr vor.
- Offene Empfehlungen (SR-04, SR-05, SR-08 bis SR-13): siehe [KNOWN-LIMITATIONS.md](docs/release/KNOWN-LIMITATIONS.md).

