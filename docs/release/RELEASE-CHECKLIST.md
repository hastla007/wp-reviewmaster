# Release-Checkliste Review Captain (Core) und Review Captain Pro

Status: Entwurf, Stand 2026-11-16 (RC-140). **Kein Release.** Diese Datei führt alle Gates zusammen und ist die Vorlage für RC-142 (Go/No-Go). Ein Release ist **No-Go**, solange ein Pflichtpunkt nicht ✅ ist oder nicht als bewusstes Nichtziel beschlossen wurde.

Quellen (verbindlich bleiben die Einzeldokumente):

- [TODO.md → Release-Gates](../../TODO.md#release-gates) und die Meilensteine M0–M9
- [docs/settings/release-gate.md](../settings/release-gate.md) (SET-024)
- [docs/connectors/release-gate.md](../connectors/release-gate.md) (CON-019)
- [docs/seo/release-checklist.md](../seo/release-checklist.md) (SEO-016/017)
- ergänzend: [docs/security/review-2026-11.md](../security/review-2026-11.md), [docs/ops/privacy-checklist.md](../ops/privacy-checklist.md), [KNOWN-LIMITATIONS.md](KNOWN-LIMITATIONS.md)

Legende: ✅ erledigt (in TODO.md abgehakt bzw. Nachweis vorhanden) · ⬜ offen · ◐ teilweise (Doku vorhanden, Test/Nachweis fehlt) · 🚫 blockiert durch externe Freigabe/Zugang. Status je Punkt aus TODO.md, Stand des letzten Commits `ecb475c`.

## 0. Protokoll

| Feld | Wert |
|---|---|
| Version Core / Pro | 0.1.0-dev / 0.1.0 (Entwicklungsvorschau) |
| Prüfer / Datum | – |
| WordPress / PHP / MySQL bzw. MariaDB / WooCommerce | – |
| Entscheidung RC-142 | **No-Go** (offene Pflichtpunkte, siehe unten) |

## 1. Gate „Core v1.0“ (TODO.md)

| # | Kriterium | Status | Nachweis / offen |
|---|---|---|---|
| C1 | M0 abgeschlossen (Umfang, Freigaben) | ⬜ | offen: RC-001 … RC-007 (Namen, 1.0-Scope, Free/Pro-Grenze, Planmodell, Entitlements, Kompatibilitätsziele, Support/Kanäle); RC-009 … RC-016 (Plattformzugänge, Datenschutz, Medienrechte, Marken). Erledigt: 1 von 16 |
| C2 | M0.5 abgeschlossen (Abläufe, Design) | ◐ | 10 erledigt; offen: UX-001 (Zielnutzer validieren), UX-006 (Prototyptest), UX-013 (Begriffe testen) |
| C3 | M1 abgeschlossen (Verträge, Datenmodell) | ◐ | 13 erledigt; offen: RC-022 (Experience-ID-Ownership je Quelle), RC-026 (Auth-Modell je Provider) |
| C4 | M1.5 abgeschlossen (Betrieb, Sicherheit) | ◐ | 13 erledigt; offen: TEC-005 (Race-Conditions abnehmen), TEC-014 (Skalierung 100k Reviews/10k Jobs) |
| C5 | M2 abgeschlossen (Free Core) | ✅ | Plugin-Grundlage 6/6, lokale Reviews/Datenverwaltung 11/11, Free-Frontend 7/7 |
| C6 | Core funktioniert ohne Pro, WooCommerce und externe Konten | ◐ | Architektur und Unit-Tests (SQLite-Stubs); Nachweis auf echter WP-Installation offen (RC-131, RC-133) |
| C7 | Datenschutzexport/-löschung, Moderation, sichere Frontend-Ausgabe bestehen die Prüfungen | ◐ | RC-134 ✅ (Security-Review); RC-135 Datenschutzprüfung ⬜; Checkliste `docs/ops/privacy-checklist.md` noch nicht abgehakt |
| C8 | Release-ZIP besteht die Prüfungen | ⬜ | RC-141 ⬜; `bin/build-zip.sh`, `bin/check-plugin-meta.php` vorhanden |

## 2. Gate „Pro v1.0“ (TODO.md)

| # | Kriterium | Status | Nachweis / offen |
|---|---|---|---|
| P1 | M1.5 und M3 abgeschlossen | ◐ | M3: 7 erledigt; offen RC-058 (Produkt/Pläne in WP License Captain), RC-064 (E2E-Lizenzlauf) |
| P2 | Zugesagte 1.0-Provider haben echte Testverbindungen und dokumentierten Fallback | 🚫 | RC-002 (welche Provider in 1.0) ⬜; RC-009 … RC-013 ⬜; RC-132 (Provider-Sandboxen) ⬜; alle Plattformpunkte UNVERIFIED (`captain-pro/addons/review-captain-pro/docs/PLATFORMS.md`) |
| P3 | Einladungsqueue, -grenze, Opt-out, Authentifizierung, Sync-Diagnose, Medien-/Widget-Funktionen im 1.0-Scope | ◐ | Anfrage-Engine 10/10 ✅; M6 12 erledigt, offen RC-119 (Page Builder), RC-120 (Layoutkonflikte mobil); M4 offen RC-069, RC-072, RC-076, RC-077 (Trusted-Shops-Widget, im Pro-Branch umgesetzt, Abnahme gegen Anbieterdoku offen) |
| P4 | Lizenzablauf entzieht nur Add-on-Funktionen und löscht keine Reviews | ◐ | in Pro umgesetzt und getestet (Rückfall auf Core-Grid bzw. Profil-Link, Lesewege); E2E gegen echten Lizenzserver offen (RC-064) |
| P5 | Suite-Adapter im 1.0-Scope | ◐ | 7 erledigt; offen RC-100, RC-101 (Pass Captain), RC-102 (Affiliate), RC-104 (Form-Captain-Kompatibilität), RC-106 (Suite-E2E) |

## 3. Gate „Gesamtprojekt fertig“ (TODO.md)

| # | Kriterium | Status |
|---|---|---|
| G1 | Alle verpflichtenden Core-/Pro-Aufgaben M0–M8 abgeschlossen oder als Nichtziel beschlossen | ⬜ |
| G2 | Plattformen, APIs, Tarife, Lizenzen aktuell dokumentiert | ⬜ (`docs/providers/matrix.md`: keine Zeile bestätigt) |
| G3 | Backups, Updates, Datenschutzlöschung, Fehlerdiagnose, Supportbetrieb handhabbar | ◐ (Runbook OPS-004 ✅; RC-139, OPS-003, OPS-005, OPS-009 ⬜) |
| G4 | M7-Wettbewerbsfunktionen abgeschlossen oder priorisiert verschoben | ⬜ (RC-121 … RC-129 offen; Verschiebebeschluss fehlt) |

## 4. Qualität, Sicherheit und Release (M8)

| Task | Inhalt | Status |
|---|---|---|
| RC-130 | Unit-/Integrationstests vervollständigen | ✅ |
| RC-131 | Pluginverbund-E2E-Umgebung (Core/Pro + Woo + Suite) | ⬜ |
| RC-132 | Provider-Sandboxen (Auth, Sync, Löschung, Quota, Widerruf) | 🚫 |
| RC-133 | WordPress-/Woo-Kompatibilitätsmatrix (Minimum/aktuell, HPOS, Checkout-Modi) | ⬜ |
| RC-134 | Sicherheitsprüfung | ✅ (offene Empfehlungen SR-04, SR-05, SR-08 … SR-13, siehe KNOWN-LIMITATIONS) |
| RC-135 | Datenschutzprüfung | ⬜ |
| RC-136 | Performanceprüfung | ⬜ |
| RC-137 | Barrierefreiheit und Mobile-QA | ⬜ |
| RC-138 | Übersetzungen (en, de, de formal) | ⬜ (Core: deutsche Msgids, ADR-0006 offen; Pro: de_DE und de_DE_formal vollständig per Test) |
| RC-139 | Migration/Backup/Restore | ⬜ |
| RC-140 | Releaseunterlagen | ◐ (dieser Stand: CHANGELOG.md, readme.txt-Abschnitte, diese Checkliste, KNOWN-LIMITATIONS; Supportmatrix und Troubleshooting fehlen) |
| RC-141 | Release Candidate bauen (getrennte ZIPs, keine Dev-Dateien/Secrets, Signaturen) | ⬜ |
| RC-142 | Go/No-Go | ⬜ |
| RC-143 | Veröffentlichen | ⬜ |
| RC-144 | Erstes Supportfenster auswerten | ⬜ |

Pilot und Betrieb (M9): OPS-001 ✅, OPS-004 ✅; offen OPS-002 (Plattform-Setup veröffentlichen; Entwurf `docs/user/platform-setup.md`), OPS-003, OPS-005 (Entwurf `docs/ops/api-change-routine.md`), OPS-006 … OPS-009.

## 5. Einstellungs-Gate (SET-024)

Status laut `docs/settings/release-gate.md` §2: alle Gruppen G1–G9 mit Doku (`D`), **Tests (`T`) für alle Zeilen offen**, G10 (Barrierefreiheit) überall offen. SET-020 (Usability-Test) ⬜, übrige SET-Tasks ✅.

| # | Prüfung | Status |
|---|---|---|
| S1 | §2: jede Zeile in allen Spalten `D`+`T` | ⬜ |
| S2 | Neuinstallation ohne Option → Defaults, keine Notices | ⬜ |
| S3 | Unberechtigte können Einstellungen weder lesen noch speichern | ⬜ |
| S4 | Multisite: Site A ändert Site B nicht | ⬜ |
| S5 | Konfigurationsexport ohne Secrets, P1-Felder nur nach Bestätigung | ⬜ |
| S6 | Reset löscht keine Reviews; „Alle Daten löschen“ nur mit Bestätigung | ⬜ |
| S7 | Pro deaktiviert/Lizenz abgelaufen: `requests`-Werte unverändert | ⬜ |
| S8 | Hilfetexte ohne interne Begriffe (TTL, Backoff, Lease, Entitlement) | ⬜ |

## 6. Connector-Gate (CON-019)

Freigabe erst, wenn A–E vollständig sind. Status: alle Punkte in `docs/connectors/release-gate.md` ⬜ (nicht abgehakt), obwohl die zugehörigen Bau-Tasks in TODO.md ✅ sind (CON-001 … CON-019 außer CON-006 Lizenzgrenze und CON-016 E2E-Abnahme).

| Block | Inhalt | Status |
|---|---|---|
| A | Core: Vertrag = Code (CT-V-03), Registry/Validator (CT-R-*), kein Fatal bei inkompatiblen Connectoren, inaktiv nach Registrierung, Jobrunner (CT-J-*), Fehlercodes, kein Lizenzcode | ◐ (Code und Tests vorhanden, Abhaken mit Nachweis offen) |
| B | Pro: Manager-Seite, Capability+Nonce, typisierte Konfiguration, Dry-Run, Health, Lifecycle, Entitlement `review.custom_connectors` | ◐ |
| C | Demo-Connector, SDK, Laden ohne Core, Developer Guide gegen Code geprüft | ◐ |
| D | E2E-Szenarien (12) auf Testsite | ⬜ (CON-016) |
| E | Datenschutz/Sicherheit (Datenmatrix, Log-Snapshot, Exporter/Eraser, „keine Sandbox“-Hinweis, Escaping) | ⬜ |
| F | Checkliste für Drittanbieter-Entwickler | Doku, kein Gate für den eigenen Release |

Offene Lizenzpunkte (RC-005, CON-006) blockieren die technische Abnahme nicht, wohl aber den kommerziellen Release.

## 7. SEO-Gate (SEO-016/017)

Vor **jedem** Core- und Pro-Release vollständig durchlaufen. SEO-001 (Kompatibilitätsmatrix) ⬜, SEO-016 (Abnahme der Szenarien) ⬜, übrige SEO-Tasks ✅.

| # | Bereich | Status |
|---|---|---|
| E1 | Richtlinien und Versionen (Google-Snippet-Richtlinien, Yoast-Versionen, Erkennung anderer SEO-Plugins) | ⬜ |
| E2 | Ausgabebesitz: keine Title/Meta/Canonical/OG von Review Captain; kein Schreiben in Yoast | ⬜ (Code-Suche durchführen) |
| E3 | 15 Szenarien strukturierte Daten inkl. Rich-Results-Test und Schema Markup Validator | ⬜ |
| E4 | Indexierung (`rc_page` noindex, Canonical, Sitemaps, keine öffentlichen Review-URLs) | ⬜ |
| E5 | Rendering ohne JS, Page-Cache, HTML-Minify, Statusseite, Widget-Hinweise, seo-guide aktuell | ⬜ |

## 8. Datenschutz, Sicherheit und externe Dienste (Release-Pflicht)

| # | Prüfung | Status |
|---|---|---|
| D1 | `readme.txt` Core: Abschnitt „External services“ (Core macht keine externen Requests) | ✅ (2026-11-16) |
| D2 | `readme.txt` Pro: Lizenzserver, Plattform-APIs je Anbieter mit übermittelten Daten, Anbieter-Widgets nach Klick/Consent | ✅ (2026-11-16; AGB-/Datenschutz-Links der Anbieter vor Verkauf prüfen) |
| D3 | Datenschutzleitfaden-Text (`wp_add_privacy_policy_content`) für Formular, Fristen, aktive Embeds, Pro-Anbieter | ◐ (Pro: Text für Anbieter-Widgets; Core-Text gegen Checkliste prüfen) |
| D4 | `docs/ops/privacy-checklist.md` vollständig abgehakt | ⬜ |
| D5 | Keine offenen kritischen/hohen Security-Befunde | ✅ (RC-134: keine; offene niedrige/Info-Befunde bewusst bewerten) |
| D6 | „Kein Request vor Consent“ im Browser-Netzwerkprotokoll (Embeds, TrustBox, Trustbadge, eTrusted) | ⬜ |
| D7 | Pro-Lizenzserver erhält keine Review-, Kunden- oder Plattformdaten | ◐ (dokumentiert, Netzwerkmitschnitt offen) |

## 9. Releaseunterlagen (RC-140)

| Unterlage | Status |
|---|---|
| README.md (Repository) | ✅ vorhanden |
| readme.txt Core: Beschreibung, Installation, FAQ, Screenshots-Liste, External services, Changelog, Upgrade Notice | ✅ (Screenshots selbst noch zu erstellen; „Tested up to: 6.7“ vor Release aktualisieren; Readme-Sprache s. ADR-0006) |
| readme.txt Pro: Changelog, Upgrade Notice, External services | ✅ |
| CHANGELOG.md (Keep a Changelog) | ✅ |
| Release Notes Pro (`captain-pro/addons/review-captain-pro/docs/RELEASE-NOTES-0.1.0.md`) | ✅ Entwurf |
| Datenschutztext | ◐ |
| Plattform-Setup (`docs/user/platform-setup.md`) | ◐ Entwurf, Anbieterangaben ungeprüft |
| Troubleshooting | ⬜ (Fehlercodes in `docs/architecture/error-codes.md`, Runbook vorhanden; Kundentext fehlt) |
| Supportmatrix (WP/PHP/DB/Woo/Builder) | ⬜ (abhängig von RC-006, RC-119, RC-133) |
| Bekannte Einschränkungen | ✅ [KNOWN-LIMITATIONS.md](KNOWN-LIMITATIONS.md) |

## 10. Release Candidate (RC-141) – technische Prüfungen

- [ ] `composer install --no-dev`, `vendor/bin/phpunit` grün (Core), Captain-Pro-Suite grün (`phpunit`, `phpcs -q`).
- [ ] Core-ZIP mit `bin/build-zip.sh <version>`; enthält keine `tests/`, `docs/`, `vendor/`-Dev-Pakete, `examples/` nur falls gewollt.
- [ ] Pro-ZIP mit `php build/build.php --release --addon=review-captain-pro` (verweigert Platzhalter, Beispielfeatures und „development preview“/„not for sale“ im Readme).
- [ ] `Stable tag`, Plugin-Header-Version und `REVIEW_CAPTAIN_VERSION` bzw. Pro-Version stimmen überein (`bin/check-plugin-meta.php`).
- [ ] POT aktuell, Übersetzungen vollständig (`bin/make-pot.*`; Pro: `php bin/make-pot.php`, `GermanAddressTest`).
- [ ] Keine Secrets, Testschlüssel oder Fixture-Provider aktiv im ZIP (`FixtureProvider` nur in `local`/`development`).
- [ ] Installation auf frischer WordPress-Site mit MySQL 8.0 und MariaDB 10.6 (Schema-Snapshot, `docs/architecture/database.md` Abnahme), Upgrade von der Vorversion, Deinstallation mit/ohne Opt-in.
- [ ] Signatur/Update-Kanal Pro gegen WP License Captain geprüft (RC-058, RC-064).
