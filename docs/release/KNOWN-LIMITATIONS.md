# Bekannte Einschränkungen – Review Captain 0.1.0-dev / Review Captain Pro 0.1.0

Status: Stand 2026-11-16 (RC-140). Diese Liste ist Teil der Releaseunterlagen und der Go/No-Go-Entscheidung ([RELEASE-CHECKLIST.md](RELEASE-CHECKLIST.md)). Jeder Punkt wird gestrichen, sobald er mit Nachweis erledigt oder als Nichtziel beschlossen ist.

## 1. UNVERIFIED: Plattformangaben

Es stand **kein echtes Konto** einer Plattform zur Verfügung. In der [Provider-Matrix](../providers/matrix.md) ist **keine Zeile bestätigt**; offen sind RC-009 … RC-013 (Zugänge) und RC-132 (Provider-Sandboxen).

| Bereich | Was ungeprüft ist | Quelle |
|---|---|---|
| Google Business Profile | API-Freigabe durch Google, wessen OAuth-Client (Betreiber vs. Captain-App, RC-026), Endpunktversionen und Pfade, Seitengröße/Sortierung, `hasVoiceOfMerchant`, Format übersetzter Kommentare, kein Lösch-Feed, Kontingente, Kosten | matrix.md, Pro `docs/PLATFORMS.md` |
| Google Places (Fallback) | nicht gebaut; Entscheidung bauen oder streichen offen (RC-069) | matrix.md |
| Google Play | `reviews.list` nur ca. 7 Tage, keine App-Liste per Service-Account, minimale Play-Console-Rechte, `maxResults` | Pro `docs/PLATFORMS.md` |
| Trustpilot | Tarif-/Modulabhängigkeit der API, `business-units/find`, private Endpunkte, Client-Credentials-Grant, Paging, Feldnamen, Review-URL, Invitation-API (RC-072), 24-h-Aktualisierungsregel | matrix.md, PLATFORMS.md |
| Trusted Shops / eTrusted | Token-Endpunkt/Audience, `/channels`, `/reviews`-Paging, Feldnamen, keine öffentliche URL je Review, Events-/Invite-API (RC-076), Speicher-/Löschregeln | matrix.md, PLATFORMS.md |
| App Store Connect | `limit`, Sortierung, `include=response`, minimale Key-Rolle, Rate-Limit-Header, kein Permalink, kein Sprachfeld | PLATFORMS.md |
| Alle Plattformen | Rate-Limits und Kosten; Tagesbudget 500 Requests je Profil ist Arbeitsannahme | PLATFORMS.md |
| Anbieter-Widgets (RC-073, RC-077) | Skript-URLs (`widget.trustpilot.com/bootstrap/v5/…`, `widgets.trustedshops.com/js/{TSID}.js`, `integrations.etrusted.com/applications/widget.js/v2`), ID-Formate (Business-Unit-ID, Template-ID, TSID, `wdg-`/`chl-`-IDs), `data-*`-Attribute, Profil-Hosts, Tarifbedingungen; `integrations.etrusted.com` fehlt in der Core-`EmbedPolicy`-Hostliste (ADR offen) | [widget-fallbacks.md](../providers/widget-fallbacks.md), Pro `src/Widgets/ProviderEmbed.php`, Pro `docs/WIDGETS.md` §6 |
| Suite-Plugins | Erkennungskonstanten `FORM_CAPTAIN_VERSION`, `WORKFLOW_CAPTAIN_VERSION`; Verträge von Pass, Affiliate, Workflow Captain fehlen (RC-100 … RC-102, RC-WF-*) | Pro `docs/SUITE.md` |
| Nutzerdoku | Klickpfade und Kosten in `docs/user/platform-setup.md` (OPS-002) | platform-setup.md |

Folge: Kein Plattformadapter darf als „live“ beworben werden, bevor Zugang, Nutzungsrecht, Cache-/Löschregeln und ein echter Verbindungstest geklärt sind (Arbeitsregel in TODO.md).

## 2. Offene Rechts- und Produktfragen

| Thema | Offen | Task |
|---|---|---|
| Datenschutz/Kommunikation | Datenfluss und Versandgrundlage je Zielmarkt (Einwilligung, Opt-out, Aufbewahrung, Löschverantwortung) fachlich/juristisch geprüft | RC-014, RC-135 |
| Medienrechte | Einwilligungs-/Nutzungsrechtstext für Name, Bild, Stimme, Video, Untertitel, Widerruf | RC-015 |
| Marken und Trustmarks | Nutzungsregeln für Plattformlogos, Trustbadges, Awards, Zertifikate; bis dahin keine Anbieterlogos (Text statt Logo) | RC-016 |
| Anbieter-Widgets | Ob TrustBox/Trustbadge im Tarif erlaubt sind, entscheidet der Anbieter; Haftungsausschluss im Admin, juristische Prüfung des Textes offen | RC-073, RC-077 |
| Kombinierter Score | Darstellung plattformübergreifender Werte (nur mit Methode, Opt-in) wettbewerbsrechtlich prüfen | Pro `docs/WIDGETS.md` §3 |
| Lizenz und Pläne | Produkt/Pläne im Lizenzserver, finale Entitlements, Lizenzgrenze für Connectoren | RC-004, RC-005, RC-058, CON-006 |
| Distribution | WordPress.org ja/nein; davon hängen englische Msgids (ADR-0006) und englische `readme.txt` ab | RC-007, RC-143 |
| Rechtstexte in Readmes | AGB-/Datenschutz-Links der Plattformen in der Pro-`readme.txt` vor Verkauf prüfen | RC-140 |
| Einladungs-APIs | Trustpilot/eTrusted-Einladungen nur nach Vertrag; im Code hinter `REVIEW_CAPTAIN_PRO_INVITES_APPROVED` | RC-072, RC-076 |

## 3. Offene Befunde der Sicherheitsprüfung

Aus [docs/security/review-2026-11.md](../security/review-2026-11.md) (RC-134). Keine kritischen oder hohen Befunde; behoben sind SR-01, SR-02, SR-03, SR-06, SR-07. Offen (Stand des Berichts):

| ID | Schweregrad | Einschränkung | Empfehlung |
|---|---|---|---|
| SR-04 | niedrig | Rate-Limit nicht atomar: parallele Requests können das Limit überschreiten | atomar erhöhen vor dem Insert (`wp_cache_incr` bzw. `INSERT … ON DUPLICATE KEY UPDATE`) |
| SR-05 | niedrig | CSV-Formelschutz prüft nur das erste Zeichen; bei `;`-Listentrennzeichen (deutsches Excel) umgehbar | Trennzeichen + Formelauslöser entschärfen oder `;`-Export mit Quotierung / `.xlsx`; mit Importformat abstimmen |
| SR-08 | info | Import-Uploads im Web-Verzeichnis, nur per `.htaccess` geschützt (nginx!) | Speicherort außerhalb Web-Root per Konstante, stündliche Bereinigung, nginx-Regel dokumentieren |
| SR-09 | info | Rate-Limit nutzt `REMOTE_ADDR`; hinter Proxy/CDN teilen sich alle Besucher einen Zähler | Filter `review_captain_client_ip` für vertrauenswürdige Proxys |
| SR-10 | info | Zeitstempel-/Kontextsignaturen nicht an die Site gebunden (Multisite mit gemeinsamen Salts) | `get_current_blog_id()` mitsignieren |
| SR-11 | info | `Crypto::decrypt()` ohne Kontext überspringt die Kontextbindung | Kontext verpflichtend oder `decrypt_unbound()` |
| SR-12 | info (UX) | Rollen-Tab zeigt nicht vergebbare Capabilities als bearbeitbar | Checkboxen deaktivieren, Hinweis |
| SR-13 | info | Exception-Text enthält `$wpdb->last_error` (kann Spaltenwerte enthalten) | nur Fehlercode werfen, `last_error` nur im Debug-Log |

Die Pro-Komponenten (Plattform-Clients, Anfragen, Widgets) wurden in diesem Bericht **nicht** geprüft; eine eigene Sicherheitsprüfung des Add-ons steht aus.

## 4. Fehlende Tests gegen echtes WordPress und MySQL

- Die Core-Suite (`vendor/bin/phpunit`) läuft gegen **SQLite und WordPress-Stubs**, nicht gegen eine echte WordPress-Installation; die Captain-Pro-Suite läuft gegen Stubs (`tests/stubs/`). Abweichungen von `dbDelta`, MySQL-/MariaDB-Typen, Sortierung/Collation, `%i`-Platzhaltern, Transaktionen und Locking sind nicht abgedeckt.
- Nicht durchgeführt: frische Installation auf MySQL 8.0 / MariaDB 10.6 mit Schema-Snapshot (`docs/architecture/database.md`, Abnahme), Upgrade-/Rollback-/Backup-Restore (RC-139), Skalierung mit 100.000 Reviews und 10.000 Jobs (TEC-014), Race-Conditions unter echter Parallelität (TEC-005).
- Nicht durchgeführt: Pluginverbund-E2E mit Pro, WooCommerce (HPOS, Classic/Blocks-Checkout) und Suite-Plugins (RC-131, RC-133, RC-106), echter Lizenzlauf gegen WP License Captain (RC-064).
- Nicht durchgeführt: Browser-Tests (Netzwerkprotokoll „kein Request vor Consent“, ohne JavaScript, Tastatur/Screenreader, Mobile-Layout mit Floating-Badge und Trustbadge; RC-120, RC-137), Page-Cache- und Minify-Verhalten (SEO-Gate §5), Rich-Results-Test (SEO-016).
- Tests der Pro-Plattformadapter und des Job-Handlers brauchen einen Core-Checkout und PHP ≥ 8.5 (MINVER-1) und werden sonst übersprungen.

## 5. Funktionale Einschränkungen dieses Stands

- Quellsprache der Core-Oberfläche ist Deutsch (ADR-0006, Arbeitsannahme); für WordPress.org müssten die Msgids auf Englisch umgestellt werden. Englische Texte (RC-138) fehlen im Core.
- `readme.txt` (Core): „Tested up to: 6.7“ ist nicht gegen aktuelle WordPress-Versionen geprüft; Screenshots sind beschrieben, aber nicht erstellt.
- Page-Builder-Unterstützung nicht festgelegt (RC-119, `docs/decisions/page-builders.md`).
- Trustbadge (Pro) wird nur dort ausgegeben, wo Shortcode/Block steht; keine siteweite Ausgabe, Abstimmung mit dem schwebenden Badge von Hand.
- JS-Strings des Pro-Block-Editors werden nicht übersetzt (keine JSON-Übersetzungen im Build).
- Wettbewerbsfunktionen M7 (Foto-Reviews, Q&A, Mehrprodukt-Formular, Tags/Meldungen, Autorenkorrektur, WhatsApp/QR, Shopping-Feed, weitere Plattformen, KI) sind nicht Teil von 0.1.0; Verschiebebeschluss fehlt.
- Support, Eskalation, API-Änderungsroutine und Verantwortlichkeiten sind nicht besetzt (OPS-003, OPS-005, OPS-009).

## Mindestversionen (MINVER-1)

- PHP 8.5, MySQL 9.7 LTS und MariaDB 12.3 LTS sind im Code und in der CI eingetragen, konnten in der Entwicklungsumgebung aber **nicht lokal** getestet werden (dort nur PHP 8.3 und MariaDB 10.11, keine Pakete für die neuen Versionen, kein Docker). Lokal verifiziert wurden Unit-Tests (PHP 8.3, mit `--ignore-platform-req=php`) und die DB-Integration auf MariaDB 10.11. Für lokale QA gibt es den Testschalter `REVIEW_CAPTAIN_DEV_IGNORE_PHP_MIN` (wirkt nicht auf Produktionsumgebungen, nie unter PHP 8.1). Der erste Lauf der CI-Jobs `PHP 8.5`, `MariaDB 12.3` und `MySQL 9.7` steht aus; ebenso der Test der Inhaberin auf einem echten Host.
- Review Captain Pro muss nachziehen (Pro `Requires PHP` = Core-Minimum, captain-pro Entscheidung B3) – im Pro-Branch noch nicht geändert.

