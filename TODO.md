# Review Captain – ausführliche Aufgabenliste

Diese Liste macht das Produktkonzept in umsetzbare Arbeitspakete. Aufgaben sind in Abhängigkeitsreihenfolge sortiert. Ein Task gilt erst als abgeschlossen, wenn sein Ergebnis dokumentiert und die genannte Abnahme erfüllt ist. Ändert sich eine Anbieterregel oder ein Suite-Vertrag, wird der betroffene Task wieder geöffnet.

**Projektgrundlage:** [Produktkonzept](konzept.md)  
**Kern-Repository:** hastla007/wp-reviewmaster  
**Pro-Add-on:** hastla007/captain-pro/addons/review-captain-pro  
**Lizenzserver:** hastla007/wp-license  
**Produktkey:** review-captain-pro (Kataloganlage noch erforderlich)  
**Add-on-Rechtepräfix:** review.

## Arbeitsregeln

- Pro-Aufgaben bauen auf dem kostenlosen Review-Captain-Core auf. Kein Pro-Feature darf den Core lizenzpflichtig machen.
- Plattformdaten ausschließlich über dokumentierte Anbieter-APIs, offiziell erlaubte Widgets/Embeds oder manuelle Einträge; kein Scraping.
- Core- und Pro-Repository bleiben getrennt. Captain-Pro-Bibliotheken werden nach dem bestehenden Build-Verfahren eingebunden.
- Jede Cross-Plugin-Aktion erhält eine stabile ID, ist wiederholbar ohne Dopplung und protokolliert Fehler ohne Zugangsdaten oder unnötige personenbezogene Daten.
- Erst Live-Release eines Plattformadapters, wenn Zugang, Nutzungsrecht, Cache-/Löschregeln und ein echter Verbindungstest geklärt sind.

## M0 – Umfang festlegen und Freigaben holen

### Produkt- und Veröffentlichungsumfang

- [x] **RC-001 – Produktnamen und Slugs bestätigen.** Ergebnis: verbindliche Namen Review Captain, review-captain und review-captain-pro in Core, Add-on, Textdomain und Build-Dokumentation. **Entwurf zur Freigabe:** [docs/m0/rc-001-namen-slugs.md](docs/m0/rc-001-namen-slugs.md) *Entscheidung:* freigegeben (Inhaberin, 16.11.2026).
- [x] **RC-002 – 1.0-Scope festschreiben.** Ergebnis: eine Liste „MVP muss enthalten“ und eine Liste „nach 1.0“; insbesondere festlegen, welche der fünf Plattformen in 1.0 nativ synchronisieren und welche zunächst offiziellen Embed-/Profil-Link-Fallback bieten. **Entwurf zur Freigabe:** [docs/m0/rc-002-scope-1-0.md](docs/m0/rc-002-scope-1-0.md) *Entscheidung:* freigegeben (Inhaberin, 16.11.2026), ergänzt: alle sechs Pro-Rechte in 1.0 (RC-005).
- [x] **RC-003 – Free/Pro-Grenze einfrieren.** Ergebnis: jede Funktion aus Abschnitt 5 des Konzepts ist Core, Pro 1.0, Pro-Folgeversion oder ausgeschlossen zugeordnet. **Entwurf zur Freigabe:** [docs/m0/rc-003-free-pro-grenze.md](docs/m0/rc-003-free-pro-grenze.md) *Entscheidung:* freigegeben (Inhaberin, 16.11.2026).
- [ ] **RC-004 – Pro-Planmodell bestätigen.** Ergebnis: single (1), business (5) und agency (25 Live-Websites; suiteweit am 16.11.2026 festgelegt, Bestätigung für Review offen), Supportstufen und Produktpreise sind als Shopentscheidung dokumentiert. **Entwurf zur Freigabe:** [docs/m0/rc-004-planmodell.md](docs/m0/rc-004-planmodell.md) *Stand 16.11.2026:* Planstruktur single/business/agency freigegeben; Preise legt die Inhaberin später fest.
- [x] **RC-005 – Lizenz-Entitlements finalisieren.** Ergebnis: Liste der tatsächlich gebauten review.*-Rechte, jeweils genau einer Pro-Funktion zugeordnet; keine leeren oder nur geplanten Rechte im Katalog. **Entwurf zur Freigabe:** [docs/m0/rc-005-entitlements.md](docs/m0/rc-005-entitlements.md) *Entscheidung:* freigegeben (Inhaberin, 16.11.2026): alle sechs gebauten Rechte ab Pro 1.0; Umsetzung `hold` entfernen im Pro-Repository offen.
- [x] **RC-006 – Kompatibilitätsziele festlegen.** Ergebnis: Mindestversionen WordPress, PHP und optional WooCommerce sowie Zielmatrix für HPOS, Classic/Blocks, Multisite und Page Builder. **Entwurf zur Freigabe:** [docs/m0/rc-006-kompatibilitaet.md](docs/m0/rc-006-kompatibilitaet.md) *Entscheidung:* freigegeben (Inhaberin, 16.11.2026); Versionsnummern am Feature-Freeze.
- [x] **RC-007 – Supportumfang und Releasekanäle definieren.** Ergebnis: WordPress.org-Veröffentlichung für Core, Update-/Vertriebskanal für Pro, Supportsprachen, Supportumfang und Reaktionsziele. **Entwurf zur Freigabe:** [docs/m0/rc-007-support-release.md](docs/m0/rc-007-support-release.md) *Entscheidung:* freigegeben (Inhaberin, 16.11.2026); Adressen unter OPS-009.

### Plattform- und Rechtsfreigaben

- [x] **RC-008 – Provider-Matrix anlegen.** Für Google, Trustpilot, Trusted Shops/eTrusted, App Store Connect und Google Play je Anbieter dokumentieren: Kontotyp/Tarif, API-Freigabe, Authentifizierung, lesbare Felder, Einladungs-/Antwortfunktionen, Limits, Attribution, Cache- und Löschpflicht, Kosten und Fallback.
- [ ] **RC-009 – Google-Zugang praktisch freischalten.** Ergebnis: registriertes Google-Cloud-Projekt, aktivierte Business-Profile-API, OAuth-Zustimmungsbildschirm, erforderliche API-Freigabe und Test-Standort. **Entwurf zur Freigabe:** [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [ ] **RC-010 – Trustpilot-Zugang prüfen.** Ergebnis: dokumentierte API-/Partnerberechtigung, Test-Business-Unit, verwendbare Review- und Invitation-Endpunkte sowie Freigabe des konkreten Plugin-Use-Cases. **Entwurf zur Freigabe:** [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [ ] **RC-011 – Trusted-Shops/eTrusted-Zugang prüfen.** Ergebnis: API-Client und Testkanal eines berechtigten Shops; lesende Review-, Einladung- und Widget-Funktionen sind voneinander abgegrenzt. **Entwurf zur Freigabe:** [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [ ] **RC-012 – App-Store-Connect-Testzugang vorbereiten.** Ergebnis: Test-App, Team-/App-Ressourcen-ID, API-Key-Verfahren und minimale Rollen dokumentiert. **Entwurf zur Freigabe:** [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [ ] **RC-013 – Google-Play-Testzugang vorbereiten.** Ergebnis: Test-App/Paket, Play-Console-Zugriff, Service-Account/OAuth-Verfahren und minimaler Scope dokumentiert. **Entwurf zur Freigabe:** [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [ ] **RC-014 – Datenschutz-/Kommunikationsprüfung abschließen.** Ergebnis: Datenfluss und Versandgrundlage je Zielmarkt fachlich geprüft; Consent, Opt-out, Aufbewahrung und Löschverantwortung stehen fest. **Entwurf zur Freigabe:** [docs/m0/rc-014-datenschutz-kommunikation.md](docs/m0/rc-014-datenschutz-kommunikation.md)
- [ ] **RC-015 – Medienrechte prüfen.** Ergebnis: Einwilligungs-/Nutzungsrechtstext und Ablauf für Name, Bild, Stimme, Video, Untertitel, Widerruf und Veröffentlichung sind dokumentiert. **Entwurf zur Freigabe:** [docs/m0/rc-015-medienrechte.md](docs/m0/rc-015-medienrechte.md)
- [ ] **RC-016 – Marken- und Trustmark-Regeln prüfen.** Ergebnis: Vorgaben zur Nutzung jedes Plattformlogos, Trustbadges, Awards und Zertifikatsassets sind in einer Checkliste erfasst. **Entwurf zur Freigabe:** [docs/m0/rc-016-marken-trustmarks.md](docs/m0/rc-016-marken-trustmarks.md)

**Meilenstein M0 ist erreicht, wenn:** 1.0-Scope, Kompatibilitätsziele, Lizenzmodell und die tatsächlichen Anbieterzugänge dokumentiert sind. Nicht verfügbare APIs werden sichtbar als Fallback geplant, nicht als zugesagte Synchronisierung verkauft.

## M0.5 – Nutzerabläufe und Bildschirmdesign

Diese Aufgaben werden nach dem MVP-Scope-Freeze und vor dem Bau der Admin-Seiten erledigt. Technische Verträge können parallel weiterlaufen.

- [ ] **UX-001 – Zielnutzer und Nutzungsszenarien validieren.** Ergebnis: Gespräche mit Shopbetreibern, lokalen Unternehmen, App-Anbietern und Captain-Suite-Nutzern bestätigen die wichtigsten Abläufe.
- [x] **UX-002 – Nutzerabläufe skizzieren.** Ergebnis: Erstinstallation, Review erfassen, Plattform verbinden, Syncfehler beheben, Widget veröffentlichen, Anfrage konfigurieren und Pro-Lizenz verwalten sind als Flows beschrieben.
- [x] **UX-003 – Admin-Wireframes erstellen.** Ergebnis: Dashboard, Reviews, Quellen, Profile, Anfragen, Widgets, Testimonials/Trustmarks, Integrationen einschließlich Connector-Manager, Einstellungen, Diagnose und Lizenzseite haben abgestimmte Wireframes.
- [x] **UX-004 – Fehler- und Leerzustände gestalten.** Ergebnis: keine Quelle verbunden, unberechtigter API-Zugang, abgelaufener Token, fehlende Reviews, Lizenz ohne Zugriff und Queuefehler haben verständliche Zustände und konkrete nächste Schritte.
- [x] **UX-005 – Frontend-Widget-Prototypen erstellen.** Ergebnis: Trust-Leiste, Plattform-Scorekarten, Testimonial-Wall, Video-/Audio-Carousel, Auszeichnungsblock und schwebendes Badge sind auf Desktop und Mobil ausgearbeitet.
- [ ] **UX-006 – Prototypen mit Nutzern prüfen.** Ergebnis: Testpersonen schließen „Quelle verbinden“ und „Bewertungsleiste veröffentlichen“ ohne Moderationshilfe ab; beobachtete Blocker sind behoben.
- [x] **UX-007 – Designsystem und Editor-Handoff festlegen.** Ergebnis: Typografie, Farben, Abstände, Sterne, Quellenbadges, Fokuszustände, reduced-motion und Block-Steuerungen sind dokumentiert.
- [x] **UX-008 – Usability-Kennzahlen definieren.** Ergebnis: Zeit bis zur ersten Verbindung, Zeit bis zum ersten Widget und Einrichtungsabbrüche sind ohne unnötige personenbezogene Telemetrie messbar oder bewusst nicht erfasst.
- [x] **UX-009 – Einstellungsinventar erstellen.** Ergebnis: jede geplante Option ist mit Nutzerfrage, globalem/Profil-/Kampagnen-/Widget-Geltungsbereich, Free/Pro-Recht, sicherem Standardwert, Capability und Speicherort erfasst; doppelte Optionen sind entfernt.
- [x] **UX-010 – Einstellungen-Informationsarchitektur abstimmen.** Ergebnis: Seitennavigation, Tabnamen, Reihenfolge, Gruppierungen und Abgrenzung zu Quellen, Kampagnen, Widgets, Integrationen und Diagnose sind als WP-Admin-Wireframe freigegeben.
- [x] **UX-011 – Einfach-/Erweitert-Modus definieren.** Ergebnis: häufige Einstellungen sind sofort sichtbar; seltene technische Optionen liegen in aufklappbaren Erweitert-Bereichen; erforderliche Felder werden abhängig von Quelle, Versandweg und Lizenzstatus eingeblendet.
- [x] **UX-012 – Erstkonfiguration und Leerzustände entwerfen.** Ergebnis: Admin wird von Basisdaten und Absender über Quelle/Test bis zu erster Kampagne geführt; Setup-Schritte sind wiederaufnehmbar und lassen sich ohne Datenverlust überspringen.
- [ ] **UX-013 – Begriffe und Hilfetexte testen.** Ergebnis: Betreiber verstehen Geltungsbereich, Wirkung und Risiko jeder Option; verständliche Beispiele und Statushinweise ersetzen interne Begriffe wie TTL, Backoff oder Entitlement. **Glossar-Entwurf:** [docs/ux/glossary.md](docs/ux/glossary.md); Verständnistest offen

## M1 – Verträge, Datenmodell und Architektur

- [x] **RC-017 – System-of-Record-Regeln dokumentieren.** Ergebnis: WooCommerce besitzt Woo-Produktreviews; Review Captain besitzt lokale allgemeine Reviews, externe Review-Snapshots, Widgets, Testimonials und Anfragen.
- [x] **RC-018 – Normalisiertes Review-Schema finalisieren.** Ergebnis: Feldliste, Skalen, Sprache, Profil/Quelle, stabile externe ID, Kontext, Cachezeit, Tombstone/Löschung und Sichtbarkeitsstatus sind dokumentiert.
- [x] **RC-019 – Testimonial-Schema finalisieren.** Ergebnis: Text/Bild/Video/Audio-Typen, WordPress-Media-Anhang, Transkript/Untertitel, Kontext, Rechtebeleg, Ablauf, Widerruf und Moderationsstatus sind beschrieben.
- [x] **RC-020 – Provider-Vertrag v1 definieren.** Ergebnis: connect, test, list profiles, sync page/cursor, normalize, delete/tombstone, quota/status und capability discovery sind versioniert und mit Fixture-Beispielen beschrieben.
- [x] **RC-021 – Ereignisvertrag für Reviews v1 definieren.** Ergebnis: Payload für source, event_id, experience_id, event_type, occurred_at, object reference, recipient reference, locale und Versandberechtigung ist formal festgelegt.
- [x] **RC-022 – Experience-ID-Ownership je Quelle bestätigen.** Ergebnis: WooCommerce-Kauf, Voucher-Einlösung, Pass-Teilnahme und Form-Captain-Einsendung haben jeweils genau einen ID-Verantwortlichen und dokumentierte Dedupe-Regeln. *Abgleich 16.11.2026:* Format Core ≠ Pro bei Pass/Voucher/Form; [Details](docs/contracts/suite-abgleich-2026-11-16.md). *Entscheidung:* Core-Format mit Site-ID gilt (Inhaberin, 16.11.2026); Pro passt Quellschlüssel an (Pro-Repository).
- [x] **RC-023 – Kontaktresolver-Vertrag definieren.** Ergebnis: Empfängerreferenzen können nur zur Versandzeit und mit gültiger Berechtigung in Kontaktangaben aufgelöst werden; unbekannt/widerrufen blockiert Versand.
- [x] **RC-024 – Anfrage-Lifecycle und Zustände festlegen.** Ergebnis: planned, eligible, sent, delivered/unknown, clicked (nur falls erlaubt), skipped, cancelled, failed, unsubscribed und expired mit Übergängen dokumentiert.
- [x] **RC-025 – Einladungsregel technisch spezifizieren.** Ergebnis: Unique-Key experience_id + recipient_ref + campaign_id, maximal eine Einladung je Erfahrung/Empfänger und kein zweiter Versand über konkurrierende Suite-Ereignisse.
- [x] **RC-026 – Authentifizierungsmodell je Provider festlegen.** Ergebnis: Captain OAuth-App vs. Betreibercredential, Scope, Rotation, Token-Widerruf und Verschlüsselungsweg pro Anbieter entschieden. **Vorschlag zur Entscheidung:** [ADR-0008](docs/adr/0008-auth-modell-betreiber-credential.md) *Entscheidung:* Captain-OAuth-App für Google Business Profile bereits in 1.0, übrige Anbieter Betreiber-Zugänge (Inhaberin, 16.11.2026; ADR-0008 abgelehnt, Umsetzung RC-026A).
- [ ] **RC-026A – Captain-OAuth-App für Google Business Profile bauen.** Ergebnis: OAuth-Broker ohne Token-Speicherung, signierte Site↔Broker-Kommunikation, AVV, Kontingentkonzept, Google-Verifizierung abgeschlossen; Betreiber-Zugang bleibt Alternative ([ADR-0008](docs/adr/0008-auth-modell-betreiber-credential.md)).
- [x] **RC-027 – Datenaufbewahrungs- und Löschmatrix festlegen.** Ergebnis: je Quelle Speicherfrist, Refreshintervall, Lösch-/Widerrufspfad, Export, Backup und Entfernung beim Disconnect dokumentiert.
- [x] **RC-028 – Datenbank- und Migrationsplan abnehmen.** Ergebnis: Tabellen/Optionen, Indizes, Versionsnummer, Upgrade-/Rollback-Verhalten und Multisite-Scope sind spezifiziert.
- [x] **RC-029 – Rollen- und Capability-Modell festlegen.** Ergebnis: getrennte Rechte für Reviews moderieren, Quellen verbinden, Anfragen verwalten, Reports sehen und Trustmarks verwalten.
- [x] **RC-030 – Fehlercodes und Integrationsstatus standardisieren.** Ergebnis: inkompatibler Vertrag, Auth-Fehler, Quota, Timeout, entfernte Review, Retry und endgültiger Fehler erhalten dokumentierte Statuscodes.
- [x] **RC-031 – Architektur-Entscheidungsprotokoll einrichten.** Ergebnis: wesentliche Änderungen an Schema, Verträgen, Lizenzrechten und Anbieterzugängen werden mit Datum/Grund versioniert.

**Meilenstein M1 ist erreicht, wenn:** Core und alle Adapter dieselben Datenverträge, Lifecycle-Regeln und Dedupe-Schlüssel verwenden.

## M1.5 – Technische Betriebs- und Sicherheitsgrundlagen

Diese Infrastrukturentscheidungen müssen vor den davon abhängigen Core-/Pro-Implementierungen abgenommen werden. Allgemeine Aussagen wie „Retry“ oder „verschlüsseln“ reichen für den Produktionsbetrieb nicht aus.

- [x] **TEC-001 – Job-Runner-Vertrag festlegen.** Ergebnis: definiert ist, wann Action Scheduler genutzt wird, wie der Core ohne WooCommerce Jobs ausführt und wie WP-Cron, echter System-Cron und WP-CLI zusammenspielen.
- [x] **TEC-002 – Produktions-Cron und Verzögerung dokumentieren.** Ergebnis: zeitkritische Syncs/Review-Einladungen funktionieren mit empfohlenem System-Cron; Admin zeigt fällige/überfällige Jobs und keine angeblich präzisen Zeiten bei unzuverlässigem WP-Cron.
- [x] **TEC-003 – Job-Claiming und Lease implementieren.** Ergebnis: ein Job hat atomaren Besitzer, Ablaufzeit und Recovery nach Prozessabbruch; zwei Worker können denselben Job nicht gleichzeitig ausführen.
- [x] **TEC-004 – Idempotenz in der Datenbank erzwingen.** Ergebnis: Unique Constraints für externe Review-IDs, Experience-/Recipient-/Campaign-Schlüssel und Provider-Event-IDs verhindern Duplikate auch bei parallelen Hooks und Retry.
- [x] **TEC-005 – Race-Conditions spezifizieren und abnehmen.** _(Core-Tests `tests/Unit/Races/` für R-01/R-05 (Core-Teil), R-06, R-10, R-11, R-12, Import-Rollback, Eraser; Zuordnung und behobene Fehler in `docs/contracts/race-conditions.md`. Core-Anteil am 16.11.2026 mit 8 und 16 parallelen Prozessen auf MariaDB 10.11 abgenommen: [docs/qa/2026-11-race-mariadb.md](docs/qa/2026-11-race-mariadb.md), `tests/e2e/race-mariadb.sh`. Offen: Pro-Anteil R-02–R-04/R-07–R-09 im Pro-Repository; Lauf auf MySQL 8.)_ Ergebnis: Tests decken gleichzeitig eintreffende Einlösung/Check-in, Refund vs. Einladungsversand, Tokenrefresh und zwei Sync-Läufe derselben Quelle ab.
- [x] **TEC-006 – Retry- und Dead-letter-Politik pro Fehlerklasse festlegen.** Ergebnis: temporär, permanent, Rate-Limit, Authentifizierungsfehler und Datenfehler haben getrennte Retry-/Pause-/Adminaktionsregeln; Retry-After wird beachtet.
- [x] **TEC-007 – API-Budgets und Parallelitätslimits implementieren.** Ergebnis: pro Quelle/Profile sind Seitenlimit, tägliches/zeitliches Budget, Backoff und Admin-Warnschwelle einstellbar; ein Sync kann Website oder Anbieterquote nicht ungebremst belasten.
- [x] **TEC-008 – Tokenverschlüsselung und Schlüsselrotation festlegen.** Ergebnis: Schlüsselquelle, Algorithmus/Nonce-Handling, Rotation, Backup/Wiederherstellung und Verhalten bei fehlendem/ungültigem Schlüssel sind dokumentiert und implementiert; Tokens erscheinen nie in Logs/Exporten.
- [x] **TEC-009 – Multisite- und Site-Isolation absichern.** Ergebnis: Tabellen, Optionen, OAuth-Verbindungen, Warteschlangen, Lizenzstatus und Datenexport sind je Site korrekt isoliert; Netzwerkdeaktivierung/Wiederaktivierung ist definiert.
- [x] **TEC-010 – REST-/OAuth-Grenzen härten.** Ergebnis: jede Route hat eine explizite permission_callback; Callback prüft State/Nonce, Redirect-URI und Berechtigung; Wiederholung/Replay und unautorisierte Profilwahl werden abgewehrt.
- [x] **TEC-011 – Cross-site-Schnittstellen explizit begrenzen.** Ergebnis: lokale Suite-Adapter verwenden PHP-Verträge; falls später REST/Webhooks nötig werden, sind Signatur, Timestamp, Replay-Schutz, Scope und Schlüsselrotation Voraussetzung.
- [x] **TEC-012 – Cache- und CDN-Verhalten spezifizieren.** Ergebnis: Provider-TTL, Pflichtrefresh, Tombstone/Löschung, Seiten-/Objektcache und CDN-Invalidierung sind pro Quelle abgestimmt; personenbezogene Anfrage-/Lizenzdaten werden nie öffentlich gecacht.
- [x] **TEC-013 – Frontend-Embed-Sicherheitsmodell umsetzen.** Ergebnis: feste Host-Allowlist, SSRF-Schutz, consent-basiertes Laden externer Player/Embeds und sichere Linkattribute verhindern beliebige serverseitige Abrufe und ungefragte Drittanbieterrequests.
- [x] **TEC-014 – Skalierungsgrenzen und Listenqueries messen.** Ergebnis: Testdaten mit mindestens 100.000 Reviews und 10.000 wartenden/abgeschlossenen Jobs zeigen indizierte Adminsuche, paginierte Exporte und begrenzte Joblaufzeit. _(Erledigt 16.11.2026 auf MariaDB 10.11 mit 100.000 Reviews und 10.000 Jobs: [docs/qa/2026-11-tec-014-mariadb.md](docs/qa/2026-11-tec-014-mariadb.md); Schema v3 mit Index `status_sorted` und Keyset-Export, [ADR-0010](docs/adr/0010-sortierspalte-sorted-at.md).)_
- [x] **TEC-015 – Upgrade-/Restore-Reconciliation härten.** Ergebnis: nach Schemaupgrade oder Backup-Restore werden Cursor, externe IDs, Jobleases und widerrufene Tokens geprüft; Wiederherstellung erzeugt keine doppelten Einladungen oder alten API-Schreibaktionen.

**Meilenstein M1.5 ist erreicht, wenn:** Hintergrundjobs unter Parallelität sicher und wiederholbar laufen, Zugangsdaten kontrolliert wiederherstellbar sind und die Site bei Providerfehlern oder Cache-/Cronproblemen einen nachvollziehbaren Zustand zeigt.

## M2 – Kostenlosen Core bauen

### Plugin-Grundlage

- [x] **RC-032 – Core-Plugin-Skeleton erstellen.** Ergebnis: Plugin-Header, Namespaces, Loader, Aktivierung/Deaktivierung und Mindestversion-Warnung entsprechen WordPress-Konventionen.
- [x] **RC-033 – Build-, Coding-Standard- und CI-Grundlage einrichten.** Ergebnis: reproduzierbares Entwicklungs-/Release-ZIP, Syntax-/Coding-Standard-Prüfungen und Plugin-Metadatenprüfung laufen in CI.
- [x] **RC-034 – Übersetzungsworkflow einrichten.** Ergebnis: Textdomain, POT und de_DE/de_AT Übersetzungsworkflow sind nutzbar; keine sichtbaren Admin-Texte bleiben unübersetzbar.
- [x] **RC-035 – Versioniertes Datenbankschema implementieren.** Ergebnis: Installation und Upgrade erzeugen Schema idempotent; keine Schemaarbeit auf normalen Frontend-Besuchen.
- [x] **RC-036 – WP-CLI-/Cron-Wartungspunkte einrichten.** Ergebnis: Datenbereinigung, Diagnose und Reconciliation sind zeitbegrenzt und können per WP-Cron bzw. CLI angestoßen werden.
- [x] **RC-037 – Rollen, Nonces und Audit-Grundlage implementieren.** Ergebnis: alle schreibenden Adminaktionen prüfen Capability und Nonce; sensible Änderungen hinterlassen datensparsame Audit-Einträge.

### Lokale Reviews und Datenverwaltung

- [x] **RC-038 – Reviews-Adminseite umsetzen.** Ergebnis: Tabelle, Suche, Filter nach Typ/Quelle/Rating/Status/Zeitraum und paginierte Detailansicht funktionieren.
- [x] **RC-039 – Lokale Review-Einreichung umsetzen.** Ergebnis: konfigurierbares Formular mit Rating/Text, Validierung, Honeypot, Rate-Limit und Moderationsstatus.
- [x] **RC-040 – Moderationsworkflow implementieren.** Ergebnis: ein Review kann freigegeben, abgelehnt, archiviert, gemeldet und mit Änderungsgrund protokolliert werden.
- [x] **RC-041 – Verifizierungsstatus implementieren.** Ergebnis: Kauf verifiziert, Teilnahme bestätigt, extern importiert und ungeprüft sind getrennte Zustände; nur ein Quellenvertrag kann „verifiziert“ setzen.
- [x] **RC-042 – Eigene Reviews und WooReviews getrennt halten.** Ergebnis: keine importierte Plattformbewertung wird als Woo-Kommentar angelegt oder in native Woo-Sterne eingerechnet.
- [x] **RC-043 – Manuelle externe Reviews erfassen.** Ergebnis: Eintrag verlangt Plattform, Originalbewertung, erlaubten Text, Datum, Profil-Link und Quellenhinweis.
- [x] **RC-044 – CSV-Export implementieren.** Ergebnis: Filterexport mit UTF-8, Zeitstempeln, Quellenfeldern und Schutz gegen Spreadsheet-Formelinjektion.
- [x] **RC-045 – CSV-Import mit Prüflauf implementieren.** Ergebnis: Dry-run zeigt Zeilenfehler und vermutete Duplikate; Import kann nachvollziehbar zurückgerollt werden.
- [x] **RC-046 – Migration aus WooCommerce, bestehenden lokalen Review-Systemen und bestehenden lokalen Review-Systemen spezifizieren.** Ergebnis: Feldmapping, Statusübernahme, Attachment-Behandlung und Grenzen werden in einer Migrationsanleitung erklärt.
- [x] **RC-047 – Privacy Exporter/Eraser integrieren.** Ergebnis: Reviewdaten, Autorreferenzen, Anfrage-/Consentstatus und Logs können exportiert oder fristgerecht gelöscht werden.
- [x] **RC-048 – Aufbewahrung und Deinstallation umsetzen.** Ergebnis: Core-Daten bleiben standardmäßig bei Deinstallation erhalten; explizite Löschoption ist verständlich und vollständig.

### Free-Frontend

- [x] **RC-049 – Review-Card serverseitig rendern.** Ergebnis: Review-Ausgabe funktioniert ohne Pflicht-JavaScript und escaped Nutzerinhalte sicher.
- [x] **RC-050 – Gutenberg-Review-Block bauen.** Ergebnis: Block bietet Datenquelle, Layout, Rating-/Quellfilter und Vorschau im Editor.
- [x] **RC-051 – Shortcode-API bauen.** Ergebnis: versionierter Shortcode unterstützt Widget-ID und sichere Basisfilter.
- [x] **RC-052 – Grid/List-Basislayouts gestalten.** Ergebnis: mobile, responsive Karten mit Quelle, Skala, Datum und Original-Link.
- [x] **RC-053 – Lokale Text-Testimonial-Ausgabe ergänzen.** Ergebnis: eigene Testimonials werden sichtbar von externen Plattformreviews unterschieden.
- [x] **RC-054 – Bewertungsprofil-CTA bereitstellen.** Ergebnis: Betreiber kann Originalprofil-Link und neutrale CTA ohne Sterne-Vorwahl setzen.
- [x] **RC-055 – Schema-Ausgabe begrenzen.** Ergebnis: keine externen Reviews oder selbstbezogenen Unternehmensreviews erzeugen unzulässige AggregateRating-Ausgabe.

**Meilenstein M2 ist erreicht, wenn:** der kostenlose Core ohne Lizenzserver, WooCommerce oder externe Review-API eigenständig installiert und genutzt werden kann.

## M3 – Pro-Add-on und Lizenzierung

- [x] **RC-056 – Add-on in Captain Pro registrieren.** Ergebnis: Ordner, Slug, Textdomain, Buildkonfiguration und Mindest-Core-Version sind korrekt.
- [x] **RC-057 – Lizenzclient und Kernel integrieren.** Ergebnis: Export/Bundling folgt Captain-Pro-Namespace- und Buildregeln; Core bleibt frei von Lizenzcode.
- [ ] **RC-058 – Review-Produkt und Pläne in WP License Captain anlegen.** Ergebnis: review-captain-pro, Plan-IDs single/business/agency, Site-Limits und Supportlevel stimmen mit Shopkatalog überein.
- [x] **RC-059 – Entitlement-Katalog aktualisieren.** Ergebnis: nur tatsächlich implementierte review.*-Keys sind auswählbar und je Plan nachvollziehbar vergeben.
- [x] **RC-060 – Lizenzseite und Statushinweise einbauen.** Ergebnis: Aktivieren, Deaktivieren, Site-Limit, Ablauf, Verlängerung, Updates und Kundenkonto-Link funktionieren.
- [x] **RC-061 – Lizenzablaufverhalten implementieren.** Ergebnis: neue Pro-Schreibaktionen/Syncs werden gestoppt; vorhandene Reviews bleiben lesbar/exportierbar und werden nicht gelöscht.
- [x] **RC-062 – Pro-Updater konfigurieren.** Ergebnis: signierte Lizenzprüfung und Updatepakete funktionieren ohne unerlaubte API-Aufrufe bei normalem Frontend-Aufruf.
- [x] **RC-063 – Pro-Build und Release-ZIP integrieren.** Ergebnis: beide Plugins getrennt verteilbar; Build prüft Produktkey, Version, Host, Schlüssel und Entitlements.
- [ ] **RC-064 – E2E-Lizenzlauf vorbereiten.** Ergebnis: Installieren, Aktivieren, Rechte, Ablauf, Update und Deaktivieren werden gegen den echten Testserver durchgespielt.

**Meilenstein M3 ist erreicht, wenn:** Pro mit gültiger Lizenz freischaltet und Ablauf keine Core-Daten oder Core-Funktion beschädigt.

## M4 – Plattform-Adapter implementieren

Jeder Provider durchläuft dieselbe Reihenfolge: Verbindung → Test → Profilwahl → einmaliger Initialimport → Delta-Sync → Aktualisierung/Löschung → Diagnose → Fallback. Kein Adapter gilt als fertig, nur weil ein einzelner API-Aufruf funktioniert.

### Google Business Profile

- [x] **RC-065 – OAuth-Verbindung und sichere Callback-Prüfung umsetzen.** Ergebnis: State/Nonce, Redirect, Reconnect und Token-Widerruf funktionieren.
- [x] **RC-066 – Berechtigte Profile/Standorte auflisten.** Ergebnis: nur vom eingeloggten Konto verwaltete/verifizierte Standorte sind auswählbar.
- [x] **RC-067 – Reviews paginiert synchronisieren.** Ergebnis: Cursors, Update-Zeit, Zuordnung zu Location und Idempotenz sind robust.
- [x] **RC-068 – Google-Attribution und Löschregeln umsetzen.** Ergebnis: Quellenlink/-label, Aktualisierungszeit, Fehler- und Cache-Regeln sind sichtbar.
- [ ] **RC-069 – Google-Places-Fallback separat implementieren oder streichen.** Ergebnis: Place-ID, maximaler Umfang, Kosten, Limit und Fallback sind dokumentiert; niemals mit GBP-OAuth-Daten vermischen.

### Trustpilot

- [x] **RC-070 – Trustpilot-Credential-/OAuth-Flow implementieren.** Ergebnis: API-Recht, Business Unit und Berechtigungen werden vor Synchronisierung geprüft.
- [x] **RC-071 – Trustpilot-Reviews und Summaries synchronisieren.** Ergebnis: Pagination, erlaubte Cache-Aktualisierung, Löschung und Originalprofil-Link funktionieren.
- [ ] **RC-072 – Trustpilot-Einladungsfähigkeit prüfen.** Ergebnis: nur freigeschaltete offizielle Invitation-Endpunkte werden angeboten; sonst Profil-/Review-Link-Fallback.
- [x] **RC-073 – TrustBox/Widget-Fallback dokumentieren.** Ergebnis: offizieller Embed-Code lässt sich ohne Tracking-Skript vor Zustimmung nicht automatisch laden.

### Trusted Shops / eTrusted

- [x] **RC-074 – eTrusted-API-Client verbinden.** Ergebnis: API-Client, Channel, Berechtigung und Tokenrefresh werden geprüft.
- [x] **RC-075 – eTrusted-Reviews paginiert synchronisieren.** Ergebnis: Service-/Produktreviews werden korrekt typisiert und dem Kanal zugeordnet.
- [ ] **RC-076 – eTrusted-Review-Invite-Modus integrieren.** Ergebnis: Event-/Invite-API läuft nur mit gültigem Vertrag und zugelassenen Datenfeldern.
- [ ] **RC-077 – Trusted-Shops-Widget-Fallback einbauen.** Ergebnis: offizieller Badge-/Embed-Weg ist konfigurierbar, trennbar und im Frontend datenschutzbewusst.

### Apple App Store und Google Play

- [x] **RC-078 – App Store Connect Credential-Speicher implementieren.** Ergebnis: Team/App/Key-Zuordnung, Rotation und Widerruf funktionieren ohne Geheimnis in Logs.
- [x] **RC-079 – App-Store-Reviews synchronisieren.** Ergebnis: Territory, Version, Sterne, Reviewer-Alias, Datum und Antwortstatus bleiben korrekt zugeordnet.
- [x] **RC-080 – Google-Play-Verbindung implementieren.** Ergebnis: Betreiber autorisiert nur eigene Play-Console-App; Rechte sind minimal und widerrufbar.
- [x] **RC-081 – Play-Reviews synchronisieren.** Ergebnis: Pagination, Übersetzungssprache, App-Version und vorhandene Antwort werden abgeglichen.
- [x] **RC-082 – Provider-Fehler- und Quota-Verhalten vereinheitlichen.** Ergebnis: 401/403, 404, 429, 5xx, Timeout und Datenlöschung führen in nachvollziehbare Status-/Retry-Wege.
- [x] **RC-083 – Provider-Sandbox-/Fixture-Suite erstellen.** Ergebnis: jeder Adapter hat Tests für Pagination, Duplikate, Änderungen, Löschung, Quota, Tokenablauf und ungültiges Payload.
- [x] **RC-084 – Quellenübersicht und Syncdiagnose bauen.** Ergebnis: Profil, letzter Lauf, nächste Ausführung, Anzahl importierter/entfernter Reviews und verständlicher Fehlergrund sind sichtbar.

**Meilenstein M4 ist erreicht, wenn:** jede zum Launch versprochene Plattform einen echten, dokumentierten Verbindungstest plus regelkonformen Fallback bestanden hat.

## M5 – Anfrage-Engine und Captain-Suite

### Review-Anfrage-Engine

- [x] **RC-085 – Kampagnenverwaltung bauen.** Ergebnis: Auslöser, Zielplattform, Verzögerung, Sprache, Vorlage, Empfängerregel und Aktivierungsstatus sind versioniert gespeichert.
- [x] **RC-086 – Dauerhafte Versandqueue implementieren.** Ergebnis: Job-Sperre, Retry, Backoff, endgültiger Fehler und manuelles Wiederholen ohne Doppelsendung.
- [x] **RC-087 – Einladungs-Deduplizierung implementieren.** Ergebnis: Unique-Key aus Erlebnis, Empfänger und Kampagne verhindert doppelte Einladung über konkurrierende Adapter.
- [x] **RC-088 – Empfänger-Resolver und Berechtigungsprüfung anbinden.** Ergebnis: Kontakt wird erst zum Versandzeitpunkt geladen; unbekannte oder widerrufene Berechtigung stoppt den Versand.
- [x] **RC-089 – Betreiber-eigenen Mailtransport unterstützen.** Ergebnis: klare Anforderungen an Absenderdomain/SMTP, Testmail, Fehlerstatus, Bounce/Unsubscribe und keine harte Abhängigkeit von stillschweigendem Standardversand.
- [x] **RC-090 – Opt-out- und Suppression-Liste umsetzen.** Ergebnis: Abmeldelink und siteweite Sperre werden vor jedem Versand geprüft.
- [x] **RC-091 – Plattformziel und Kampagnenregeln umsetzen.** Ergebnis: Zielplattform ist vorab festgelegt und für alle geeigneten Empfänger gleich; keine Auswahl anhand von Sternebewertung oder Stimmung.
- [x] **RC-092 – Erinnerungen optional einbauen.** Ergebnis: standardmäßig aus; eine Erinnerung bleibt derselben Experience-/Recipient-Grenze unterworfen und wird protokolliert.
- [x] **RC-093 – Versandprotokoll und Vorschau bauen.** Ergebnis: Testmodus, Zustände, Skip-Gründe, Templatevorschau und datensparsame Logs funktionieren.
- [x] **RC-094 – Erinnerungs-/Kampagnenlokalisierung ergänzen.** Ergebnis: Sprache aus Quelle/Empfänger, Fallback und fehlende Übersetzung sind nachvollziehbar.

### Suite-Adapter

- [x] **RC-095 – WooCommerce-Adapter implementieren.** Ergebnis: konfigurierbare Bestellstatus, Produktmapping, HPOS und Classic-Speicher; Blocks-Checkout wird nicht verändert.
- [x] **RC-096 – WooCommerce-Mehrprodukt-Anfrage umsetzen.** Ergebnis: eine E-Mail kann mehrere bewertbare Positionen sammeln, ohne native WooReviews zu duplizieren.
- [x] **RC-097 – Refund-/Stornoausschlüsse anbinden.** Ergebnis: offene Einladung wird bei Refund, Chargeback oder Storno je definierter Kampagnenregel gestoppt.
- [x] **RC-098 – Voucher-Captain-Vertrag erweitern.** Ergebnis: echte Einlösung/Leistung wird versioniert gemeldet; Ausstellung, Kauf und Guthabenaufladung lösen keine Anfrage aus.
- [x] **RC-099 – Voucher-Empfängerlogik umsetzen.** Ergebnis: Käufer und Beschenkter werden unterschieden; Einladung geht nur an den zulässigen tatsächlichen Empfänger.
- [ ] **RC-100 – Pass-Captain-Completion-Vertrag ergänzen.** Ergebnis: Check-in/Teilnahme, No-show, Absage und Refund besitzen stabile Ereignisse und Experience-ID. *Abgleich 16.11.2026:* auf Pass-`main` fehlen Termin-ID, Teilnahme-/No-show-/Eventende-Hooks; [Details](docs/contracts/suite-abgleich-2026-11-16.md).
- [ ] **RC-101 – Ticketkäufer-/Teilnehmerregel umsetzen.** Ergebnis: bei mehreren Tickets wird der definierte tatsächliche Teilnehmer adressiert und Deduplizierung greift je Erlebnis. *Abgleich 16.11.2026:* kein Resolver auf Pass-`main`; [Details](docs/contracts/suite-abgleich-2026-11-16.md).
- [x] **RC-102 – Affiliate-Captain-Adapter implementieren.** Ergebnis: nur Kampagnen-/Partnerreferenz und aggregierte Kennzahlen, keine Kunden- oder Reviewtexte. *Abgleich 16.11.2026:* Vertrag 1.4.0 auf `main` reicht, keine Blocker; [Details](docs/contracts/suite-abgleich-2026-11-16.md). *Abgenommen 16.11.2026:* Pro `Suite/Adapters/AffiliateAdapter.php` + `AffiliateReport.php`: nur lesend über `wpac_get_commissions()` (Vertrag ≥ 1.3.0, `main` hat 1.4.0), nur aggregierte Kennzahlen je Partner, keine Käuferdaten, kein Auslöser.
- [x] **RC-103 – Form-Captain-Aktion implementieren.** Ergebnis: ausgewählte Form-Captain-Einsendung kann mit explizitem Opt-in als Review-Captain-Entwurf übergeben werden; standardmäßig moderiert, nicht automatisch veröffentlicht.
- [x] **RC-104 – Form-Captain-Vertragskompatibilität prüfen.** Ergebnis: Form-Captain-Core reicht als Quelle; Review-Captain-Pro-Recht schaltet die Review-Aktion frei; inkompatible Vertragsversion wird sauber angezeigt. *Abgleich 16.11.2026:* Form-`main` erfüllt den Vertrag; offen: Multisite-Schlüssel im Pro-Adapter; [Details](docs/contracts/suite-abgleich-2026-11-16.md). *Abgenommen 16.11.2026:* Pro `Suite/Form/ReviewAction.php` registriert über `form_captain_register_action_v1` (Vertrag `^1.0`, Form-`main` 1.0.0) und ist an `review.suite_integrations` gebunden; `Suite/StatusPage.php` zeigt Vertragszustand (nicht installiert/ausstehend/unlizenziert/aktiv). Folgeaufgabe Multisite-Schlüssel unter RC-022.
- [x] **RC-105 – Suite-Integrationsstatus bauen.** Ergebnis: installierte Version, Vertragskompatibilität, letzte Annahme, Queue, Fehler und Verbindungstest je Adapter.
- [ ] **RC-106 – Suite-E2E-Läufe ausführen und dokumentieren.** Ergebnis: Kauf→Gutschein-Einlösung, Ticket→Check-in, Woo-Refund, Formular-Submission, Affiliate-Attribution, Opt-out, Plugin fehlt und Duplicate-Events sind abgedeckt.

**Meilenstein M5 ist erreicht, wenn:** die Einladungsregel durch alle Quellen identisch durchgesetzt wird und kein Plugin fremde interne Tabellen benötigt.

## M6 – Social Proof, Testimonials und Trust-Badges

- [x] **RC-107 – Testimonial-Bibliothek implementieren.** Ergebnis: Text, Bild, Video und Audio mit Kontext, Sprache, Status und Widerruf sind verwaltbar.
- [x] **RC-108 – Rechte-/Freigabeprozess umsetzen.** Ergebnis: jede Medienveröffentlichung verlangt belegte Nutzungsrechte für Name, Bild und Stimme; abgelaufene/widerrufene Einwilligung blendet Inhalt aus.
- [x] **RC-109 – Medien-Upload absichern.** Ergebnis: erlaubte MIME-Typen, Größenlimit, Dateiprüfung, Zugriffskontrolle und sichere Löschung sind implementiert.
- [x] **RC-110 – Video-/Audio-Ausgabe barrierearm bauen.** Ergebnis: keine Autoplay-Wiedergabe, Tastatursteuerung, Untertitel/Transkript, Posterbild und click-to-load für externe Player.
- [x] **RC-111 – Trustmark-/Award-Verwaltung bauen.** Ergebnis: Herausgeber, gültig ab/bis, Asset/Embed, Verifizierungslink und Markenrechte sind Pflichtfelder.
- [x] **RC-112 – Plattform-Bewertungsleiste bauen.** Ergebnis: Score, Originalskala, Anzahl, Quelle, Zeitraum, Datenstand und Link je Plattform sind sichtbar.
- [x] **RC-113 – Summenanzahl transparent darstellen.** Ergebnis: Count-Summe trägt Label „über ausgewählte Plattformen“, Zeitraum und Hinweis auf fehlende plattformübergreifende Personendeduplizierung.
- [x] **RC-114 – Plattformübergreifenden Score blockieren oder erklären.** Ergebnis: standardmäßig kein gemeinsamer Score; falls aktiviert, sind Normalisierung, Gewichtung, Quelle und Stichprobe vollständig sichtbar.
- [x] **RC-115 – Testimonial-Wall/Video-/Audio-Carousel bauen.** Ergebnis: mobile, performante Layouts mit manueller Moderationsauswahl und klarer Quellenart.
- [x] **RC-116 – Floating Trust-Badge bauen.** Ergebnis: pro Seite/Position konfigurierbar, minimierbar, mobil getestet und kollidiert nicht mit Kauf-, Chat- oder Ticketaktionen.
- [x] **RC-117 – Page-/Produkt-/Event-Kontextfilter anbinden.** Ergebnis: Editor kann Reviews und Testimonials passend zu Woo-Produkt, Gutschein, Event, App oder Formular auswählen.
- [x] **RC-118 – Widget-Registry und Embed-Shortcodes finalisieren.** Ergebnis: Blocks, Shortcodes, SSR und Builder-Integrationen geben dieselbe freigegebene Widgetdefinition aus.
- [x] **RC-119 – Page-Builder-Mindestumfang festlegen.** Ergebnis: Elementor/Divi/andere Builder sind ausdrücklich unterstützt oder als nicht unterstützt dokumentiert. *Stand 16.11.2026:* Entscheidungsvorlage [page-builders.md](docs/decisions/page-builders.md) (Vorschlag: Shortcode-Fallback, keine nativen Module in 1.0) – Produktentscheidung offen. *Entscheidung:* natives Elementor-Widget in 1.0, andere Builder per Shortcode (Inhaberin, 16.11.2026; Umsetzung RC-119A).
- [ ] **RC-119A – Elementor-Widget bauen.** Ergebnis: natives Elementor-Widget als Hülle um `widget-v1` (SSR, kein Extra-JS), nur bei aktivem Elementor geladen, mit Editor-Vorschau und Tests ([Entscheidung](docs/decisions/page-builders.md)). *Stand 16.11.2026:* gebaut (`src/Integrations/Elementor/`), Unit-Tests mit Elementor-Platzhaltern grün; offen: Smoke-Test im echten Elementor (in der Sandbox nicht installierbar).
- [ ] **RC-120 – Layoutkonflikte prüfen.** Ergebnis: Slider, Chat-Bubble, Sticky-Checkout und Trust-Badge überlagern sich auf typischen Mobilbreiten nicht. *Stand 16.11.2026:* Core-Widgets ohne horizontales Scrollen/Überlagerung auf iPhone 13/Pixel 7 ([Nachprüfung](docs/qa/2026-11-16-nachpruefung.md)); offen: Pro-Floating-Badge (F-05), Slider/Chat-Bubble/Sticky-Checkout mit echten Fremd-Plugins.

**Meilenstein M6 ist erreicht, wenn:** alle Formate korrekt attribuiert, Rechte nachweisbar, zugänglich und ohne externe Autoplay-/Tracking-Überraschungen ausgegeben werden.

## M7 – Wettbewerbsfunktionen nach MVP

Diese Aufgaben starten erst, wenn MVP-Funktionen und API-Rechte stabil sind. Sie blockieren Pro 1.0 nur, wenn sie im Scope-Freeze ausdrücklich als Launchanforderung ausgewählt wurden.

- [x] **RC-121 – Foto-Reviews mit Moderation ergänzen.** _(Core, Schema v2, Schalter `submissions.photos_enabled`/`display.show_photos`, Standard aus; `src/Media/PhotoStore.php`.)_ Abnahme: Upload wird vor Veröffentlichung geprüft, datensparsam gespeichert und löschbar.
- [x] **RC-122 – Produkt-Q&A ergänzen.** _(Core, Tabelle `questions`, `[review_captain_qa]`, Schalter `submissions.qa_enabled`, Standard aus.)_ Abnahme: Fragen und Antworten sind von Bewertungen getrennt, moderierbar und einem Woo-Produkt zugeordnet.
- [x] **RC-123 – Review-Formular für mehrere Produkte erweitern.** _(Core: `context_ids` + UNIQUE `submission_key`, Schalter `submissions.multi_product_enabled`, Standard aus; Kaufverifizierung je Bestellung bleibt Pro.)_ Abnahme: eine Order-Session kann mehrere Positionen bewerten; keine Doppelreviews.
- [x] **RC-124 – Review-Tags und Meldungen ergänzen.** _(Core: `review_tags`, `review_reports`, Schalter `tags_enabled`/`reports_enabled`, Standard aus.)_ Abnahme: interne Tags, öffentliches Melden und Adminprüfung sind verfügbar.
- [x] **RC-125 – Review-Autorenkorrektur definieren.** _(Spezifikation: `docs/decisions/author-correction.md` – Entscheidung des Inhabers offen.)_ Abnahme: Identitätsprüfung, Audit und Versionierung verhindern unbemerkte Änderung freigegebener Reviews. *Entscheidung:* Autorenkorrektur nach Vorlage bereits in 1.0 (Inhaberin, 16.11.2026; Umsetzung RC-125A).
- [ ] **RC-125A – Autorenkorrektur umsetzen.** Ergebnis: Einmal-Link/angemeldete Nutzer, `review_revisions` (Schema v4), Moderations-Diff, „bearbeitet am“, sofortige Rücknahme, Audit, Export/Eraser inkl. Fassungen, Tests ([Entscheidung](docs/decisions/author-correction.md)).
- [x] **RC-126 – WhatsApp-Link/QR-Campaign ergänzen.** _(Core: eigener QR-Encoder (SVG, „Feedback geben – Kein Ticket“), wa.me-Generator, Schalter `submissions.feedback_campaigns_enabled`, Standard aus.)_ Abnahme: nur offiziell erlaubter Click-to-Chat oder Anbieter; Feedback-QR wird nie mit Ticket-Einlasscode verwechselt.
- [x] **RC-127 – Google-Shopping-Review-Feed evaluieren.** _(Vorlage `docs/decisions/google-shopping-feed.md`; Merchant-Center-Freigabe offen, Feature wird nicht beworben.)_ Abnahme: Merchant-Center-Freigabe, Herkunftsanforderungen und Feedvalidierung liegen vor; sonst wird das Feature nicht beworben. *Entscheidung:* später als Pro-Funktion nach Pilotbedarf, nicht im Core (Inhaberin, 16.11.2026).
- [x] **RC-128 – Weitere Plattformen priorisieren.** _(Kriterienkatalog + Kandidaten: `docs/decisions/weitere-plattformen.md`; Nachfragebelege/Kostenentscheidung offen.)_ Abnahme: jede neue Plattform hat Nachfragebeleg, offizielle Zugriffsmethode, Nutzungsrecht und Kostenentscheidung. *Entscheidung:* Kriterien K1–K4 und Reihenfolge angenommen (Inhaberin, 16.11.2026).
- [x] **RC-129 – Sentiment-/KI-Funktionen neu bewerten.** _(Vorlage `docs/decisions/ki-funktionen.md`, Empfehlung: kein Auto-Publish, kein KI-Feature in Core 1.x.)_ Abnahme: nur als separates Produktdecision-Dokument mit Datenfluss, Einwilligung und menschlicher Kontrolle; kein automatisches Veröffentlichen. *Entscheidung:* keine KI-Funktionen, auch nicht in Pro (Inhaberin, 16.11.2026).

## M8 – Qualität, Sicherheit und Release

- [x] **RC-130 – Automatisierte Unit-/Integrationstests vervollständigen.** Abnahme: Zustandswechsel, Rechte, Lizenzablauf, Import, Dedupe und Providerfehler sind regressionsgesichert.
- [x] **RC-131 – Pluginverbund-End-to-End-Testumgebung aufsetzen.** Abnahme: Review Captain Core/Pro plus WooCommerce und Suite-Testplugins lassen sich reproduzierbar installieren. *Stand 16.11.2026:* Core+Pro+WooCommerce 5.9.1 laufen gemeinsam, Bestellung→Anfrage→Erstattung bestanden ([Protokoll](docs/qa/2026-11-16-woo-pro.md)); offen: aktuelle Woo-Version/HPOS, Suite-Plugins mit Review-Verträgen, echte Lizenz. *Abgenommen 16.11.2026:* `tests/e2e/setup.sh`, `deploy-plugins.sh`, `install-woo.sh`, `deploy-suite.sh`: Core, Pro, WooCommerce, Yoast, Form/Pass/Voucher/Affiliate Captain reproduzierbar installiert und gleichzeitig aktiv (16.11.2026).
- [ ] **RC-132 – Provider-Sandboxen durchspielen.** Abnahme: je Launchprovider echte Authentifizierung, Sync, Löschung, Quota und Tokenwiderruf sind protokolliert.
- [ ] **RC-133 – WordPress- und Woo-Kompatibilitätsmatrix abarbeiten.** Abnahme: Minimum und aktuelle Version, HPOS und unterstützte Checkout-Modi bestehen die festgelegten Releaseprüfungen. *Stand 16.11.2026:* geprüft: WP 6.9.11 + Woo 5.9.1 + Yoast 27.0 ([SEO-016](docs/qa/2026-11-seo-016.md), [Woo/Pro](docs/qa/2026-11-16-woo-pro.md)); offen: aktuelle Woo-Version mit HPOS/Checkout-Block, ältere WP-Versionen laut RC-006.
- [x] **RC-134 – Sicherheitsprüfung durchführen.** Abnahme: Nonce/Capability, SQL, escaping, CSRF/XSS/SSRF, Secret-Speicher, CSV-Injection und REST-Berechtigungen sind geprüft.
- [x] **RC-135 – Datenschutzprüfung durchführen.** Abnahme: Export/Eraser, Einwilligungsstatus, Opt-out, Retention, Löschereignis und externe Services sind dokumentiert. *Stand 16.11.2026:* Export/Eraser im Browser bestanden ([Nachprüfung](docs/qa/2026-11-16-nachpruefung.md)), technische Checkliste [privacy-checklist.md](docs/ops/privacy-checklist.md); offen: rechtliche Bewertung (RC-014).
- [x] **RC-136 – Performanceprüfung durchführen.** Abnahme: keine API-Calls im öffentlichen Request, Indexe für Admin-Listen, begrenzte Jobs, Cache- und Assetverhalten sind geprüft. *Stand 16.11.2026:* keine externen Requests im Frontend nachgewiesen, Indexe/Skalierung auf MariaDB gemessen ([Nachprüfung](docs/qa/2026-11-16-nachpruefung.md), [TEC-014](docs/qa/2026-11-tec-014-mariadb.md)); `summary_by_platform` gecacht; offen: Fragment-Cache Widget-HTML, Admin-Suche.
- [ ] **RC-137 – Barrierefreiheit und Mobile QA durchführen.** Abnahme: Tastatur, Screenreader, Kontrast, reduced motion, Medienbedienung und Floating-Badge sind geprüft. *Stand 16.11.2026:* Core-Seiten ohne axe-Verstoß durch Review Captain, F-06/F-07 behoben und bestätigt ([Nachprüfung](docs/qa/2026-11-16-nachpruefung.md)); offen: Screenreader manuell, Pro-Widgets/Floating-Badge (F-05).
- [x] **RC-138 – Übersetzungen und Admintexte abnehmen.** Abnahme: Englisch, Deutsch und Deutsch formal sind vollständig und Platzhalter konsistent. *Stand 16.11.2026:* Quellsprache Deutsch (du); `en_US` und `de_DE_formal` vollständig (936 Texte, Platzhalter geprüft), MO/JSON über `bin/compile-translations.php`, CI-Prüfung `--check`. Offen: sprachliche Abnahme durch Muttersprachler; Frontend-Quelltexte sind teils schon in der Sie-Form (Entscheidung, ob de_DE im Frontend duzen soll); WordPress.org verlangt für translate.wordpress.org englische Quelltexte (vor Veröffentlichung klären, RC-140).
- [x] **RC-139 – Migration/Backup/Restore prüfen.** Abnahme: Schemaupgrade, Rollback, Backup-Restore, Reconciliation und Consent-/Token-Verhalten sind dokumentiert. *Stand 16.11.2026:* Backup/Restore + Reconciliation bestanden (keine Duplikate), Schema-Upgrade v2→v3 auf MariaDB bestanden (`tests/e2e/ci-db.sh`); offen: Upgrade auf MariaDB 12.3/MySQL 9.7 in CI, Consent-/Token-Verhalten mit echten Anbietern.
- [x] **RC-140 – Releaseunterlagen erstellen.** Abnahme: README, WordPress.org-Readme, Datenschutztext, externe Dienste, Plattformsetup, Troubleshooting, Änderungsprotokoll und Supportmatrix sind vorhanden. *Stand 16.11.2026:* vorhanden: README, readme.txt (inkl. External services), Datenschutztext (`wp_add_privacy_policy_content`), Plattform-Setup (Entwurf), [Fehlerbehebung](docs/user/troubleshooting.md), CHANGELOG, Supportmatrix (RC-007); offen: Freigaben der Entwürfe, englische readme.txt für WordPress.org.
- [ ] **RC-141 – Release Candidate bauen.** Abnahme: Core- und Pro-ZIP sind sauber getrennt, keine Dev-Dateien/Secrets, Produktversionen und Signaturen stimmen. *Stand 16.11.2026:* Core-ZIP baut sauber (137 Dateien, 440 KB, keine Tests/Docs/Vendor/Secrets); offen: Pro-ZIP mit Signatur, Versionsnummer 1.0.0-rc statt 0.1.0-dev nach Go.
- [ ] **RC-142 – Release Go/No-Go abnehmen.** Abnahme: alle MVP-Aufgaben fertig, echte Provider-/Lizenzläufe bestanden, keine offenen kritischen Datenschutz-/Security-/Datenverlustprobleme.
- [ ] **RC-143 – Core und Pro veröffentlichen.** Abnahme: Core-Release auf dem vereinbarten Verteilungskanal; Pro-ZIP/Update ist mit gültiger Lizenz abrufbar.
- [ ] **RC-144 – Erstes Supportfenster auswerten.** Abnahme: Installations-/Verbindungsprobleme, häufigste Fehlermeldungen und API-Ausfälle fließen in priorisierte Folgeaufgaben.

## M9 – Pilot, Support und Produktbetrieb

Diese Aufgaben liegen nach dem Release Candidate und vor bzw. unmittelbar nach öffentlicher Veröffentlichung.

- [x] **OPS-001 – Kunden-Onboarding erstellen.** Ergebnis: Installationshilfe, Erststart-Assistent, Verbindungsschritte und Widget-Veröffentlichung sind als kurze, getestete Anleitung verfügbar.
- [ ] **OPS-002 – Plattform-Setup-Dokumentation veröffentlichen.** Ergebnis: pro Quelle gibt es eine aktuelle Anleitung für Kontozugriff, API-Rechte, Tokens, Profile, Kosten/Limit, Fehlerbehebung und Disconnect. *Stand 16.11.2026:* Entwurf [platform-setup.md](docs/user/platform-setup.md); Klickpfade/Kosten erst nach Testzugängen (RC-009…RC-013).
- [ ] **OPS-003 – Support- und Eskalationsprozess festlegen.** Ergebnis: Zuständigkeit für Pluginfehler, Lizenzfehler und Anbieter-API-Ausfall sowie Antwort-/Eskalationswege sind dokumentiert. *Stand 16.11.2026:* Prozess dokumentiert in [support-process.md](docs/ops/support-process.md); offen: Personen/Kontakte (OPS-009), Bestätigung Reaktionsziele (RC-007).
- [x] **OPS-004 – Betriebs- und Incident-Runbook erstellen.** Ergebnis: Token-Leak, fehlerhafter Sync, Datenlöschanforderung, API-Ausfall, Update-Rollback und möglicher Datenverlust haben klare Schritte.
- [ ] **OPS-005 – API-Änderungsroutine einrichten.** Ergebnis: verantwortliche Person, regelmäßige Prüfung offizieller Änderungsmitteilungen und Prozess zum Aktualisieren/Deaktivieren veralteter Adapter sind benannt. *Stand 16.11.2026:* Routine dokumentiert in [api-change-routine.md](docs/ops/api-change-routine.md); offen: verantwortliche Person und Vertretung benennen.
- [ ] **OPS-006 – Pilot-Websites auswählen.** Ergebnis: kleine Pilotgruppe testet echte Verbindungen, Migration, Einladungen, Widgets und Supportabläufe vor breitem Verkauf.
- [ ] **OPS-007 – Pilotfeedback abarbeiten.** Ergebnis: kritische Einrichtungs- und Datenprobleme sind behoben; offene Wünsche sind priorisiert statt stillschweigend Teil des Releases zu werden.
- [ ] **OPS-008 – Release-Nachbeobachtung durchführen.** Ergebnis: in den ersten 30 Tagen werden nur datensparsame Betriebskennzahlen, Supportfälle, Syncfehler und Updateprobleme ausgewertet; Folgeaufgaben sind erfasst.
- [ ] **OPS-009 – Wartungs- und Supportverantwortung bestätigen.** Ergebnis: Anbieterzugänge, OAuth-App-Kontakt, Lizenzserverkontakt, Security-E-Mail und Release-Verantwortliche bleiben dauerhaft betreut.

## Release-Gates

### Core v1.0

- M0, M0.5, M1, M1.5 und M2 abgeschlossen.
- Core funktioniert ohne Pro, WooCommerce und externe Konten.
- Datenschutzexport/-löschung, Moderation, sichere Frontend-Ausgabe und Release-ZIP bestehen die freigegebenen Prüfungen.

### Pro v1.0

- M1.5 und M3 abgeschlossen.
- Mindestens die im Scope-Freeze für 1.0 zugesagten Provider haben echte Testverbindungen und dokumentierten Fallback.
- Einladungsqueue, Einladungsgrenze, Opt-out, Authentifizierung, Sync-Diagnose und Medien-/Widget-Funktionen im vereinbarten 1.0-Scope sind abgeschlossen.
- Lizenzablauf entzieht nur Add-on-Funktionen und löscht keine Reviews.

### Gesamtprojekt fertig

- Alle verpflichtenden Core-/Pro-Aufgaben aus M0–M8 abgeschlossen oder als bewusste Nichtziele beschlossen.
- Unterstützte Plattformen, APIs, Tarife und Lizenzen sind aktuell dokumentiert.
- Backups, Updates, Datenschutzlöschung, Fehlerdiagnose und Supportbetrieb sind handhabbar.
- Offene Wettbewerbsfunktionen aus M7 sind entweder abgeschlossen oder mit Priorität/Begründung in die nächste Version verschoben.

## Änderungsverlauf

- 2026-11-15: Erste ausführbare Aufgabenliste aus dem Review-Captain-Konzept erstellt.
- 2026-11-16: Schema v2: RC-WF-03-Core-Services, TEC-005-Core-Tests (4 Race-Fehler behoben), M7-Funktionen RC-121–124/126 (standardmäßig aus), Entscheidungsvorlagen RC-125/127/128/129.
- 2026-11-16: Free Core implementiert (src/, 296 Tests), Spezifikationen unter docs/, Sicherheitsprüfung (docs/security/review-2026-11.md). Erledigte Punkte abgehakt; offen sind Produktentscheidungen, externe Zugänge/Prüfungen und alle Pro-/Suite-Aufgaben (anderes Repository).


## Kundenspezifische Projekt-Connectoren

Diese Aufgaben ergänzen die offiziellen Captain-Suite-Adapter um eine stabile Erweiterungsmöglichkeit für kundeneigene Buchungs-, CRM-, ERP-, Mitglieder-, Kurs- und Branchensysteme. Lokale PHP-Connectoren gelten nur innerhalb derselben WordPress-Installation; andere Sites/Systeme verwenden signierte REST-/Webhook-Verträge.

### Phase A – Contract und Erweiterbarkeit

- [x] **CON-001 – Connector-Use-Cases und Grenzen festlegen.** Ergebnis: Experience Source, Recipient Resolver, Review Import Source, Request Transport und Context Provider sind getrennte optionale Fähigkeiten; Suite-Adapter, lokale Connectoren und externe Webhooks/REST sind klar abgegrenzt.
- [x] **CON-002 – Versionierten Connector-Hauptvertrag spezifizieren.** Ergebnis: Registry-Hook, Interface-Metadaten (key, label, version, contract version, capabilities), eindeutige Connector-ID, Major-Kompatibilität und Deprecation-Frist sind dokumentiert. Vorschlag für den Hook: wp_review_captain_connectors_v1.
- [x] **CON-003 – Capability-Verträge einzeln definieren.** Ergebnis: ExperienceSource, RecipientResolver, ReviewSource, RequestTransport und ContextProvider haben minimale typisierte Methoden und Fixture-Payloads; ein Connector muss nur seine Fähigkeiten implementieren.
- [x] **CON-004 – Fachliche Event-Payloads vertraglich festlegen.** Ergebnis: Experience-ID, Event-ID, Source-Key, Objekt-Referenz, Recipient-Referenz, Locale, occurred_at, erlaubter Consent-/Versandstatus und Idempotency-Key sind Pflicht; Kontakt- und Reviewtexte werden nur über ausdrücklich erforderliche Fähigkeiten übertragen.
- [x] **CON-005 – Connector-Sicherheits- und Datenmatrix erstellen.** Ergebnis: jede Fähigkeit hat minimale Datenfelder, Capabilities, Consent-/Berechtigungsprüfung, Retention/Erase, Logging-Allowlist, Retry-Klassifikation und Disconnect-Verhalten. Das Framework ist als PHP-Code ausdrücklich keine Sandbox.
- [x] **CON-006 – Lizenzgrenze finalisieren.** Ergebnis: öffentlicher Contract und Basis-Registry bleiben im kostenlosen Core; generischer Pro-Manager und automatisierte Connector-Runtime nutzen review.custom_connectors; offizielle Captain-Adapter nutzen review.suite_integrations. **Vorschlag zur Entscheidung:** [ADR-0009](docs/adr/0009-connector-lizenzgrenze.md) *Entscheidung:* ADR-0009 angenommen (Inhaberin, 16.11.2026).
- [x] **CON-007 – Vertrags- und Kompatibilitätstests vorbereiten.** Ergebnis: Contract-Tests prüfen Pflichtmetadaten, unbekannte Major-Versionen, fehlende Capabilities, ungültige Payloads, Fehlercodes, Idempotenz und wiederholte Events.

### Phase B – Core Registry und Pro-Verwaltung

- [x] **CON-008 – Core-Registry und Contract-Validator implementieren.** Ergebnis: registrierte Connectoren werden dedupliziert, validiert und ohne Fatal Error bei inkompatibler Version als pausiert ausgewiesen.
- [x] **CON-009 – Jobrunner-Erweiterung implementieren.** Ergebnis: versionierte Connectorjob-Typen laufen mit atomarem Claim/Lease, Idempotency-Key, Retry/Backoff, Dead-Letter und Hook für Connectorfehler (Vorschlag wp_review_captain_process_job_v1); keine Connector-Jobverarbeitung im Frontend-Request.
- [x] **CON-010 – Pro-Manager-Seite gestalten und bauen.** Ergebnis: Statuskarten zeigen Entwickler, Version, Fähigkeiten, Contract-Kompatibilität, Aktivstatus, letzte Ausführung, wartende Jobs und sanitisierten Fehler; Test, Pause, Disconnect und Wiederholen sind capability-/nonce-geschützt.
- [x] **CON-011 – Schema-basierte Connector-Konfiguration bereitstellen.** Ergebnis: Felder sind typisiert und validiert; Secrets werden maskiert/verschlüsselt gespeichert und erscheinen weder in Logs noch Diagnoseexporten; keine frei ausführbaren PHP-Felder.
- [x] **CON-012 – Dry-Run und Diagnose hinzufügen.** Ergebnis: Test-Fixture zeigt normalisierte Payload, Mapping, Eligibility-/Consent-/Dedupe-Entscheidung und erwartete Aktion, ohne reale E-Mail oder externe Schreibaktion auszulösen.
- [x] **CON-013 – Error-/Health-Vertrag umsetzen.** Ergebnis: stabile Fehlercodes für Authentifizierung, Rate Limit, Timeout, inkompatiblen Vertrag, ungültige Payload, temporären und permanenten Fehler; Status enthält nächste sinnvolle Admin-Aktion.
- [x] **CON-014 – Inkompatibilität und Plugin-Lifecycle behandeln.** Ergebnis: Deaktivierung oder Entfernung eines Quell-Plugins pausiert offene Connectorjobs und zeigt source_unavailable; erneute Aktivierung validiert Vertrag, Consent und Fristen vor einem Resume.

### Phase C – Referenz-Connector, Developer Kit und Abnahme

- [x] **CON-015 – Beispiel-Connector bauen.** Ergebnis: separates Demo-Plugin emittiert ein abgeschlossenes Buchungs-/Kurs-Erlebnis über den öffentlichen Vertrag; es verwendet keine Core-Änderung oder fremden Datenbankzugriffe.
- [ ] **CON-016 – Ende-zu-Ende-Fluss abnehmen.** Ergebnis: abgeschlossenes Erlebnis wird genau einmal normalisiert und geplant; Duplikat, Refund, Widerruf, Opt-out, fehlender Kontakt und nicht erlaubte Kommunikation blockieren korrekt. *Stand 16.11.2026:* blockiert durch Pro-Befund F-04 (`poll_experiences()` ohne Aufrufer); Woo-Weg (Erlebnis → Anfrage, Duplikat, Erstattung) bestanden ([Woo/Pro](docs/qa/2026-11-16-woo-pro.md)).
- [x] **CON-017 – Connector-SDK-Skelett veröffentlichungsfähig machen.** Ergebnis: Plugin-Header, Beispielklasse, Capability-Manifeste, Beispielkonfiguration, Job-Handler und lokale Entwicklungsfixtures lassen sich als Ausgangspunkt kopieren.
- [x] **CON-018 – Developer-Dokumentation veröffentlichen.** Ergebnis: Setup, Contract-Versionierung, Datenmapping, Rechte, Consent, Empfängerresolver, Jobs, Fehlerbehandlung, Tests, Datenschutz und Releasekompatibilität sind mit vollständigen Beispielen beschrieben.
- [x] **CON-019 – Connector-Release-Gate ergänzen.** Ergebnis: kein Connector gilt als Pro-1.0-fertig, bevor Registry, Contract-Tests, Demo-Connector, Health-/Pause-Verhalten, Datenschutzprüfung und erfolgreiche Dedupe-/Retry-Abnahme bestehen.

**Connector-Meilenstein ist erreicht, wenn:** eine Drittanbieter-Erweiterung ohne Core-Patch installiert werden kann, über den öffentlichen Vertrag ein reales abgeschlossenes Erlebnis meldet und Review Captain Regeln, Deduplizierung, Berechtigungen, Queue und Diagnose vollständig kontrolliert.

## Workflow Captain Provider-Vertrag

- [x] RC-WF-01 – Öffentlichen Providervertrag v1 mit Schema-Version, stabiler Event-ID, Capability, Entitlement, Payload-Allowlist, Idempotency-Key und Deprecation-Regel dokumentieren.
- [x] RC-WF-02 – Post-Commit-Events für Anfrage geplant/übersprungen/gesendet/fehlgeschlagen, Erinnerung, Antwort, Review erstellt/moderiert/veröffentlicht/zurückgezogen und Opt-out bereitstellen; keine Reviewtexte, E-Mail oder Tokens in Logs.
- [x] RC-WF-03 – Services zum Planen/Abbrechen zulässiger Anfragen, Erstellen genehmigter Entwürfe und Lesen datensparsamer Aggregate anbieten. Workflow darf Einladungsberechtigung, Consent, Opt-out, Suppression, Frequenzlimits, Plattformziel oder Moderation nicht umgehen. _(Core: `ReviewCaptain\Workflow\WorkflowService` (Service `workflow`): `create_draft`, `cancel_draft`, `aggregates`, `request_capabilities`; Planen/Abbrechen delegiert an den Pro-Planer über `review_captain_request_planner_v1`.)_
- [x] RC-WF-04 – Suite-Trigger und erweiterte Actions an Review-Pro-Recht binden, native Queue als Standard behalten und explizite Ownership-Übergabe unterstützen; parallele Woo/Pass/Voucher/Workflow-Einladungen deduplizieren.
- [x] RC-WF-05 – Review-Admin um Workflow-Verbindung, Contract-Version, aktive Übergabe, Eventannahme, Ablehnungsgrund und Retrystatus ergänzen; fehlendes/inkompatibles Workflow lässt Review Core normal laufen.
- [x] RC-WF-06 – Pass-Check-in und qualifizierte Voucher-Leistung dürfen Anfrage planen; Refund, Absage, No-show, Consent fehlt, Opt-out und Duplikat blockieren. Form-Entry darf nur explizit gemappten pending/unverified Entwurf übergeben.
- Abnahme: Workflow kann Review-Regeln, Moderation und Einwilligung nicht umgehen; höchstens eine Einladung je Experience/Empfänger/Kampagne.


## Plugin-Einstellungen: Prioritäten, Sortierung und Umsetzung

Die Einstellungsseite soll globale Standardwerte und Richtlinien enthalten. Objekte werden an ihren jeweiligen Verwaltungsseiten gepflegt: API-Konten und Profile unter **Quellen**, einzelne Kampagnen unter **Anfragen**, Widgets unter **Widgets**, Connectorverbindungen unter **Integrationen**. Dadurch werden dieselben Felder nicht an mehreren Stellen konfiguriert.

### Empfohlene WP-Admin-Struktur

| Reihenfolge | Einstellungsgruppe | Darin enthalten | Gehört nicht hierher |
|---|---|---|---|
| 1 | **Allgemein** | Firmen-/Markenname für Vorlagen, Standardsprache, Zeitzone, Datumsformat, Standards für neue Reviews | individuelle Widgetgestaltung |
| 2 | **Quellen & Synchronisierung** | globale Sync-Zeitfenster, Mengen-/Rückblickgrenzen, Fehlerpause, Cache-/Löschverhalten; jeweilige API-Schlüssel und Profile verbleiben bei Quellen | Quellprofile oder manuelle Reviewpflege |
| 3 | **Anfragen & Zustellung** | globaler Absender, Testempfänger, Sendezeitfenster, globale Frequenzgrenzen, Erinnerungsstandard, Versandstatus/Bounce-Verhalten, siteweite Abmeldesperre | einzelne Kampagnen und Kampagnenvorlagen |
| 4 | **Einreichungen & Moderation** | Standardstatus neuer lokaler Reviews, Spam-Schutz, erlaubte Uploadtypen/-größen, Melden und Moderationshinweise | Moderation einzelner Reviews |
| 5 | **Darstellung** | globale Voreinstellungen für Sterne, Quellenlabel, Datum, Autor-/Avataranzeige, leere Zustände und Barrierefreiheit | einzelne Widgetdefinitionen |
| 6 | **Datenschutz & Aufbewahrung** | Aufbewahrung getrennt nach Review-Snapshots, Einladungsdaten, Job-/Fehlerlogs und Medien; Export-/Löschverhalten; Datenschutzhinweis | Zugangsdaten oder Lizenzstatus |
| 7 | **Zugriff & Rollen** | Rollen-/Capability-Zuordnung für Quellenverbindungen, Reviews, Kampagnen, Datenschutz und globale Einstellungen; Multisite-Scope | Nutzer- und Rollenverwaltung außerhalb des Plugins |
| 8 | **Erweitert** | Jobrunner-/Cron-Hinweise, Zeitlimits, begrenzte Debugoption, Im-/Export der Konfiguration, Deinstallations-/Datenlöschwahl | reguläre tägliche Bedienoptionen |
| – | **Diagnose (separate Seite, read-only)** | Contract-/Plugin-Kompatibilität, letzte Jobs, Quoten, Tokenablauf ohne Secret, API-Fehler, Cron-/Queue-Gesundheit | editierbare Fachoptionen |
| – | **Setup-Assistent (geführter Ablauf)** | notwendige Basisangaben und erster Verbindungstest mit sicheren Standardwerten | vollständige technische Konfiguration |

**Menüempfehlung:** Dashboard, Reviews, Anfragen, Widgets, Quellen, Integrationen, Einstellungen, Diagnose und Lizenz. Unter „Einstellungen“ eine konsistente Tab-Navigation; keine zusätzlichen überladenen WordPress-Untermenüs für seltene Optionen.

### Einstellungen, die fachlich ergänzt bzw. präzisiert werden müssen

- **Geltungsbereich:** Jede Option benennt, ob sie global, pro Plattformprofil, pro Kampagne, pro Review-Formular oder pro Widget gilt. Vererbung und Override-Reihenfolge werden sichtbar angezeigt.
- **Sichere Anfrage-Defaults:** eine Einladung pro tatsächlichem Erlebnis und Empfänger, keine Einladung bei fehlender/unbekannter Versandberechtigung, keine Bewertungsauswahl als Routingkriterium, Erinnerungen standardmäßig aus, globale Frequenz-/Cooldown-Grenze und siteweite Opt-out-Sperre.
- **Versandbetrieb:** Absenderprüfung/Testmail, Versandfenster in Website-Zeitzone, tägliche Obergrenze, Retry-/Bounce-/Beschwerdeverhalten und verständliche Warnung, wenn die Absenderdomain nicht eingerichtet ist.
- **Quellenbetrieb:** letzte erfolgreiche Synchronisierung, manuelles Sync-Limit, Provider-Ratenlimit, Cache-/Refreshgrenze je Plattform, Verhalten bei gelöschten Reviews und Pause nach Authentifizierungs-/Quota-Fehlern.
- **Datenschutz:** getrennte Fristen für externe Review-Caches, Empfänger-/Einladungsdaten, Protokolle und hochgeladene Medien; sichere Standardfrist statt unbegrenzter Speicherung; Export, Eraser, Disconnect und Datenlöschung nach Produktdeinstallation getrennt behandeln.
- **Zugriff:** getrennte Capabilities für Quellen verbinden/trennen, Reviews moderieren, Kampagnen versenden, Daten exportieren/löschen und globale Einstellungen ändern; Multisite ausdrücklich festlegen.
- **Sicherheit und Wiederherstellung:** Secret-Felder maskieren, bei Export/Import standardmäßig ausschließen; getrennte „Verbindung trennen“, „Einstellungen zurücksetzen“ und „alle Plugin-Daten löschen“-Aktionen mit Bestätigung.
- **Darstellung und Barrierefreiheit:** Quellenattribution nicht abschaltbar für externe Reviews; leere-/Fehlerzustände, Tastaturbedienung, Kontrast, reduced motion und externe Medien/Consent als klare Defaults.
- **Free/Pro-Klarheit:** gesperrte Pro-Optionen erklären die Funktion und Lizenzvoraussetzung, bleiben aber sichtbar und verändern Core-Daten oder Einstellungen beim Ablauf nicht.

### Umsetzungs-Tasks

#### Phase A – Informationsarchitektur und Einstellungsvertrag

- [x] **SET-001 – Einstellungsinventar aus Konzept und Plugin-Flows ableiten.** Ergebnis: jede existierende/geplante Option ist einer Admin-Seite und einem Geltungsbereich zugeordnet; Einladungsziel, Quelle, Kampagne oder Widget hat genau einen fachlichen Besitzer.
- [x] **SET-002 – Einstellungsbaum und Tab-Reihenfolge finalisieren.** Ergebnis: Allgemein, Quellen & Synchronisierung, Anfragen & Zustellung, Einreichungen & Moderation, Darstellung, Datenschutz & Aufbewahrung, Zugriff & Rollen und Erweitert sind wireframed; Diagnose bleibt read-only separat.
- [x] **SET-003 – Optionsschema und Migration definieren.** Ergebnis: Optionsschlüssel, Typ, Validierung, Default, Site-/Network-Scope, Datenschutzklassifikation, Entitlement, UI-Geltungsbereich und Versionsmigration sind dokumentiert.
- [x] **SET-004 – Inheritance und Overrides spezifizieren.** Ergebnis: globaler Standard → Profil/Kampagne/Formular/Widget ist eindeutig; ungültige Overrides fallen kontrolliert zurück und zeigen, welcher Wert wirksam ist.
- [x] **SET-005 – Safe Defaults und Reset-Semantik festlegen.** Ergebnis: fehlende Consent-/Kontaktberechtigung blockiert Versand, Erinnerungen sind aus, externe Skripte sind aus, neue Reviews warten auf Moderation; Reset löscht keine Reviewdaten und Datenlöschung verlangt einen getrennten, bestätigten Weg.
- [x] **SET-006 – Einstellungs- und Pro-Rechte-Matrix erstellen.** Ergebnis: pro Feld ist festgelegt, ob Core oder Pro es besitzt; kein Free-Feld wird nach Lizenzablauf verändert oder geleert.

#### Phase B – WP-Admin UX und Umsetzung

- [x] **SET-007 – Einstellungsseite mit Tabs und Abschnittsnavigation bauen.** Ergebnis: konsistente WordPress-native Formulare, Beschreibungen, Save-Status, Feldvalidierung und mobile Adminansicht; keine Optionswand auf einer langen Seite.
- [x] **SET-008 – Setup-Assistent und Erststart-Checkliste bauen.** Ergebnis: Marke/Locale, Verbindung, Test, Anfragepolitik und erstes Widget können einzeln erledigt, später fortgesetzt oder übersprungen werden; keine externe Verbindung wird automatisch aktiviert.
- [x] **SET-009 – Progressive Disclosure und Kontextfelder implementieren.** Ergebnis: Provider-/Versandweg-spezifische Felder erscheinen erst bei Bedarf; Erweitert-Optionen sind eingeklappt, Pro-Sperren und Abhängigkeiten verständlich.
- [x] **SET-010 – Allgemeine Website-Defaults implementieren.** Ergebnis: Standardsprache, Zeitzone, Marken-/Absenderidentität und Standardanzeige sind zentral definiert und in Vorschauen erkennbar.
- [x] **SET-011 – Quellen- und Sync-Defaults umsetzen.** Ergebnis: globale Jobfenster/-limits, Quota- und Fehlerpause sind einstellbar; individuelle Credentials, Profile und Anbieterscopes bleiben ausschließlich auf der Quellen-Seite.
- [x] **SET-012 – Globale Anfrage- und Versandrichtlinien umsetzen.** Ergebnis: Absender/Testmail, Versandzeitfenster, globale Frequenzgrenze, Erinnerungsstandard, Suppression/Opt-out und Bounce-/Fehlerstatus sind vorhanden; Kampagnenwerte werden an der Kampagne gepflegt.
- [x] **SET-013 – Einreichungs- und Moderationsdefaults umsetzen.** Ergebnis: neue lokale Reviews sind standardmäßig moderiert; Spam- und Uploadschutz sowie Formular-Standardfelder lassen sich zentral konfigurieren.
- [x] **SET-014 – globale Darstellungsdefaults umsetzen.** Ergebnis: Sterne, Attribution, Datum, Avatar/Autor, leere Zustände und Barrierefreiheitspräferenzen bilden die Voreinstellung neuer Widgets; externe Quellenangabe kann nicht entfernt werden.
- [x] **SET-015 – Datenschutz- und Aufbewahrungsseite umsetzen.** Ergebnis: Fristen für Snapshots, Anfragen, Logs und Medien sind getrennt; Export, Eraser, Disconnect, Settings-Reset und vollständige Datenlöschung haben klare getrennte Folgen.
- [x] **SET-016 – Rollen- und Multisite-Einstellungen umsetzen.** Ergebnis: Verbindung, Moderation, Anfrageversand, Datenschutz und Einstellungen sind getrennt berechtigt; Netzwerk- vs. Site-Einstellungen und Datenisolation sind klar.
- [x] **SET-017 – Erweitert- und Diagnosebereiche implementieren.** Ergebnis: Cron-/Queuezustand, Jobbudget, begrenzte Debugfunktion und Konfigurationsbackup sind zugänglich; Diagnoseexport enthält keine Secrets oder unnötige PII.
- [x] **SET-018 – Einstellungsimport/-export absichern.** Ergebnis: Schema-Version, Preview, Validierung und Rollback sind vorhanden; Secrets und personenbezogene Daten werden nicht exportiert, Credentials müssen nach Import erneut verbunden werden.
- [x] **SET-019 – Änderungshistorie und Audit-Semantik festlegen.** Ergebnis: sicherheits- und versandrelevante Änderungen (z. B. Absender, Frequenz, Berechtigung, Aufbewahrung) sind mit Nutzer/Zeitpunkt nachvollziehbar, ohne Secrets oder Reviewtext zu protokollieren.

#### Phase C – Validierung und Release

- [ ] **SET-020 – Einstellungs-Usability testen.** Ergebnis: Betreiber finden Quellenverbindung, Kampagnenpolitik, Datenschutzfrist und Rollen ohne Support; überflüssige oder doppelte Felder werden entfernt.
- [x] **SET-021 – Rollen-/Nonce-/Multisite-Sicherheit abnehmen.** Ergebnis: unberechtigte Nutzer können keine Einstellungen lesen/ändern; Site-Konfiguration bleibt in Multisite korrekt isoliert.
- [x] **SET-022 – Defaults und Migration regressionsprüfen.** Ergebnis: Neuinstallation, Upgrade, fehlende Optionen, Import, Lizenzablauf und Wiederherstellung behalten dokumentierte Defaults und verändern keine Core-Daten unerwartet.
- [x] **SET-023 – Einstellungsdokumentation erstellen.** Ergebnis: jede Einstellung erklärt Zweck, Scope, Standard, Folgen, Datenschutzbezug und erforderliche Anbieter-/Lizenzvoraussetzungen.
- [x] **SET-024 – Einstellungs-Release-Gate ergänzen.** Ergebnis: keine 1.0-Einstellung bleibt ohne Default, Validierung, Rechteprüfung, Migration, Export-/Löschregel und verständlichen Hilfetext.

## Yoast SEO-Kompatibilität und Aufgaben

### Phase A – Kompatibilität und Regeln

- [x] **SEO-001 – Yoast-/SEO-Plugin-Kompatibilitätsmatrix festlegen.** Ergebnis: Mindestversion für Yoast SEO, Verhalten mit Free/Premium, WooCommerce-/Yoast-Erweiterungen, Gutenberg, Caching/Minifizierung und konkurrierenden SEO-/Schema-Plugins sind festgelegt. *Stand 16.11.2026:* Matrix v1 ([compatibility-matrix.md](docs/seo/compatibility-matrix.md)), umgesetzt mit Yoast-Mindestversion 14.0 und getestet mit Yoast 27.0 ([SEO-016-Protokoll](docs/qa/2026-11-seo-016.md)); offen nur die Freigabe der Mindestversionen (Abschnitt „Offene Entscheidungen“). *Entscheidung:* Yoast-Mindestversion = aktuelle Major − 2 (derzeit 25.0, im Code), Schema Pro gilt als Besitzer (Inhaberin, 16.11.2026).
- [x] **SEO-002 – SEO-Ausgabebesitz dokumentieren.** Ergebnis: Yoast besitzt Title, Description, Canonical, Robots, Open Graph, Sitemap und Basisschema; Review Captain gibt keine konkurrierenden Head-Tags aus.
- [x] **SEO-003 – Schema-Eignungsmatrix erstellen.** Ergebnis: externe Reviews, lokale Unternehmensreviews, eigene Produktreviews, Kurs-/Eventreviews und gemischte Widgets sind anhand aktueller Google-Regeln freigegeben oder ausgeschlossen.
- [x] **SEO-004 – URL-/Indexierungsregeln spezifizieren.** Ergebnis: CPT/Custom-Table-Entscheidung, Indexierbarkeit, Archive, Filter, Parameter, Pagination, Canonical und Sitemap-Verhalten sind entschieden.

### Phase B – Yoast-Integration

- [x] **SEO-005 – Yoast-Erkennung und Versionsprüfung umsetzen.** Ergebnis: Yoast wird ohne direkte Klassen-/Tabellenabhängigkeit erkannt; inkompatible oder fehlende Version fällt sicher auf sichtbare Widgets ohne Schema zurück.
- [x] **SEO-006 – Offizielle Yoast-Schema-API anbinden.** Ergebnis: bedingter Schema-Graph-Piece wird registriert, mit WebPage/Canonical verbunden und nutzt vorhandene @id-Knoten statt Duplikate zu erzeugen.
- [x] **SEO-007 – Schema nur bei sichtbarem Block-/Widgetinhalt ausgeben.** Ergebnis: serverseitig gerenderte Review-Blöcke und Shortcodes steuern die Ausgabe; leere oder unsichtbare Widgets erzeugen kein Review-Markup.
- [x] **SEO-008 – WooCommerce-Produkt-Schema konfliktfrei integrieren.** Ergebnis: vorhandene Yoast-/WooCommerce-Product-Knoten werden erkannt; es entsteht kein zweites Product- oder AggregateRating-Objekt.
- [x] **SEO-009 – Unzulässige Quellen technisch sperren.** Ergebnis: importierte Fremdplattformreviews, eigene Organization-/LocalBusiness-Ratings und plattformübergreifende Gesamtscores gelangen nie in Review-Schema.
- [x] **SEO-010 – Schema-Fallback und Plugin-Konflikte implementieren.** Ergebnis: ohne Yoast wird nur dann eigenes JSON-LD gerendert, wenn kein konkurrierender Schema-Besitzer aktiv ist und die Eignungsprüfung bestanden ist.
- [x] **SEO-011 – SEO-Metadaten unangetastet lassen.** Ergebnis: keine Review-Captain-Ausgabe dupliziert oder überschreibt Yoast-Titel, Beschreibung, Robots, Canonical, Open Graph oder Indexables.

### Phase C – Admin, Indexierung und Abnahme

- [x] **SEO-012 – SEO-&-Yoast-Statusseite bauen.** Ergebnis: Version, Graph-Integration, erkannte Konflikte, blockierte Markups mit Grund und Hilfelinks sind verständlich sichtbar.
- [x] **SEO-013 – Widget- und Schema-Hinweise ergänzen.** Ergebnis: externe Reviews, Produktreviews und Unternehmensreviews erklären ihre Schema-Berechtigung ohne Rich-Result-Versprechen.
- [x] **SEO-014 – Noindex-, Sitemap- und Canonical-Regeln umsetzen.** Ergebnis: interne Reviewarchive, Filter-/Sortierparameter, Vorschau, Admin, Connectoren und AJAX-Routen sind nicht indexierbar; Nutzerseiten behalten Yoast-Kontrolle.
- [x] **SEO-015 – Crawlbare Server-Rendering-Ausgabe sicherstellen.** Ergebnis: sichtbare Reviewtexte, Quellen und Ratings sind ohne Client-JavaScript lesbar; externe Ressourcen bleiben consent-gesteuert.
- [ ] **SEO-016 – Strukturierte Daten und Yoast-Konflikte abnehmen.** Ergebnis: Szenarien decken Review Captain allein, Yoast SEO, WooCommerce-Produkt, Fremdreviews, lokale Unternehmensreviews, zusätzliche Schema-Plugins und noindex-Seiten ab. *Stand 16.11.2026:* 14 von 15 Checklisten-Szenarien + Indexierung in echtem WordPress bestanden, inkl. WooCommerce 5.9.1 ([Protokoll](docs/qa/2026-11-seo-016.md)); offen: Yoast Premium, aktuelle WooCommerce-Version/Yoast WooCommerce SEO, echte Fremd-SEO-Plugins, Rich-Results-Test.
- [x] **SEO-017 – SEO-Dokumentation und Release-Checkliste veröffentlichen.** Ergebnis: Markupgrenzen, Yoast-Konfiguration, Rich-Results-Erwartung und Fehlerdiagnose sind dokumentiert und vor Release verifiziert.
- 2026-11-16: Basis-Entscheidung: Core-Stand `claude/sleepy-gates-6mm217` gilt; M0-Entwürfe (docs/m0) übernommen. TEC-014 auf MariaDB mit 100.000 Reviews gemessen, Schema v3 (`sorted_at`, Index `status_sorted`) und Keyset-Export: Widget-Seiten 189→108 ms, CSV-Export 18,7→1,4 s. ADR-Vorschläge 0008–0010.

## Mindestversionen: neueste PHP-, MySQL- und MariaDB-Version (Entscheidung der Inhaberin, 16.11.2026)

- [ ] **MINVER-1 — Auf die neuesten Versionen umstellen und testen.** Die Inhaberin hat am 16.11.2026 für alle Captain-Plugins entschieden: Mindestversionen sind die jeweils neuesten – **PHP 8.5** (PHP 8.6 erscheint am 19.11.2026 und kommt in die Test-Matrix), **MySQL 9.7 (LTS)** und **MariaDB 12.3 (LTS)**.
  - *Erledigt (Core, 16.11.2026):* Plugin-Header `Requires PHP: 8.5`, Laufzeitprüfung (`REVIEW_CAPTAIN_MIN_PHP`), `readme.txt`, `composer.json`/Lock, PHPCS `testVersion 8.5-`, `src/Support/Requirements.php` mit DB-Prüfung (Warnung statt Sperre in `wp review-captain doctor` und Diagnose-Systeminfo), CI: Unit-Matrix 8.5 (+8.6 experimentell), PHPCS auf 8.5, Integrationsjobs MariaDB 12.3 und MySQL 9.7 (`tests/e2e/ci-db.sh`), Changelog, [RC-006](docs/m0/rc-006-kompatibilitaet.md). PHPStan ist im Core nicht eingerichtet.
  - *Offen:* erster grüner CI-Lauf auf PHP 8.5/8.6, MariaDB 12.3, MySQL 9.7 (lokal nicht verfügbar, siehe [KNOWN-LIMITATIONS](docs/release/KNOWN-LIMITATIONS.md)); Review Captain Pro anpassen (Pro-Branch, Absprache mit der Pro-Sitzung).
  - *Test (Inhaberin):* auf einem echten Host mit PHP 8.5/8.6 und MySQL 9.7 bzw. MariaDB 12.3 installieren, aktualisieren und die Hauptabläufe durchspielen.
  - *Vorbild:* Affiliate Captain (`hastla007/wpaffiliatemaster`, Aufgaben PHP-1/PHP-2).
