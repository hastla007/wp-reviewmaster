# Changelog

Alle nennenswerten Änderungen am kostenlosen Core **Review Captain** (`review-captain`) stehen in dieser Datei.
Das Format folgt [Keep a Changelog 1.1.0](https://keepachangelog.com/de/1.1.0/), die Versionsnummern folgen [Semantic Versioning](https://semver.org/lang/de/).
Das Add-on Review Captain Pro hat ein eigenes Änderungsprotokoll (`captain-pro/addons/review-captain-pro/readme.txt`, `docs/RELEASE-NOTES-0.1.0.md`).

## [Unreleased]

### Added
- Trusted-Shops-Siegel `[review_captain_trustbadge]` mit Klick-Freigabe: das offizielle Trustbadge lädt erst nach Klick der Besucher (RC-077).
- Autorenkorrektur (RC-125A): Kundinnen und Kunden können freigegebene Bewertungen per Einmal-Link (oder angemeldet) korrigieren oder zurückziehen; Korrekturen erst nach Freigabe sichtbar, Karte zeigt „bearbeitet am …“; Schema v4 (`review_revisions`), Admin-Seite „Korrekturen“, Shortcode `[review_captain_correction]`; standardmäßig aus.
- Natives Elementor-Widget „Review Captain“ (Bewertungen, Formular, Profil-Button) als Hülle um den Widget-Vertrag v1, serverseitig gerendert, nur bei aktivem Elementor geladen (RC-119A).
- Übersetzungen Englisch (`en_US`) und Deutsch formal (`de_DE_formal`) für alle 936 Texte inkl. Block-Editor-JSON; `bin/compile-translations.php` erzeugt MO/JSON ohne WP-CLI und prüft Vollständigkeit und Platzhalter (RC-138).
- `tests/e2e/ci-db.sh`: Integration gegen echtes MySQL/MariaDB (Installation, Upgrade v2→v3, Race-Tests); CI-Jobs für MariaDB 12.3 und MySQL 9.7. Release-Stand und offene Gates: [docs/release/RELEASE-CHECKLIST.md](docs/release/RELEASE-CHECKLIST.md), Einschränkungen: [docs/release/KNOWN-LIMITATIONS.md](docs/release/KNOWN-LIMITATIONS.md).

### Changed
- Mindestversionen ergänzt (Entscheidung 17.11.2026, einheitlich für alle Captain-Plugins): WordPress 7.0, WooCommerce 11.2.2 (optional; 11.2.0 übernimmt Bestell-/Zahlungsdaten früherer Store-API-Checkouts im selben PHP-Prozess, behoben in 11.2.2, woocommerce#70637); getestet bis WordPress 7.1 / WooCommerce 11.3.
- Kartenüberschriften standardmäßig `h2` statt `h3` (Barrierefreiheit, F-07); Block, Shortcode und Elementor-Widget.
- WooCommerce: Core meldet Kompatibilität mit HPOS und Block-Checkout.
- Yoast-Mindestversion für die Graph-Integration auf 26.0 angehoben (aktuelle Major − 2, Entscheidung SEO-001); ältere Yoast-Versionen: kein Review-Markup, Reviews bleiben sichtbar.
- **Mindestversionen (MINVER-1, Entscheidung der Inhaberin 16.11.2026):** PHP 8.5 (Header `Requires PHP`, Laufzeitprüfung, `readme.txt`, `composer.json`, PHPCS `testVersion 8.5-`); CI-Unit-Matrix PHP 8.5 und 8.6. Datenbank: empfohlen MySQL 9.7 LTS oder MariaDB 12.3 LTS. Ältere Server lösen nur eine Warnung aus (`Support\Requirements`, `wp review-captain doctor`, Diagnose-Systeminfo), keine Sperre.
- Schema v3: indizierte Sortierspalte `sorted_at` und Keyset-Export (TEC-014, ADR-0010 vorgeschlagen).

### Performance
- Plattform-Zusammenfassung der Widgets wird je Cache-Generation zwischengespeichert; jeder Schreibzugriff auf Reviews invalidiert (TEC-012, RC-136).

### Fixed
- `AdminModule::menu()` funktioniert auch, wenn Add-ons das Core-Menü außerhalb des Adminbereichs aufbauen (Lizenzseite von Pro per WP-CLI).
- Verlorene Insert-Rennen bei `JobQueue::enqueue` und `upsert_external` liefern unter hoher Parallelität zuverlässig die Gewinnerzeile statt `false` (TEC-005, MariaDB-Racetest).

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

