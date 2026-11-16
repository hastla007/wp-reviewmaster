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

### Statuskennzeichnung

- `[ ]` offen – noch kein belastbares Ergebnis.
- `[~]` Entwurf liegt vor – Ergebnisdokument ist im Repository verlinkt, aber Freigabe durch Produkt/Recht/Shop oder eine externe Aktion (Anbieterzugang, Nutzertest, Vertrag) steht noch aus. Offene Punkte stehen im verlinkten Dokument unter „Offen / Freigabe“.
- `[x]` abgeschlossen – Ergebnis dokumentiert und die genannte Abnahme erfüllt.

## M0 – Umfang festlegen und Freigaben holen

### Produkt- und Veröffentlichungsumfang

- [~] **RC-001 – Produktnamen und Slugs bestätigen.** Ergebnis: verbindliche Namen Review Captain, review-captain und review-captain-pro in Core, Add-on, Textdomain und Build-Dokumentation. → Entwurf: [docs/m0/rc-001-namen-slugs.md](docs/m0/rc-001-namen-slugs.md)
- [~] **RC-002 – 1.0-Scope festschreiben.** Ergebnis: eine Liste „MVP muss enthalten“ und eine Liste „nach 1.0“; insbesondere festlegen, welche der fünf Plattformen in 1.0 nativ synchronisieren und welche zunächst offiziellen Embed-/Profil-Link-Fallback bieten. → Entwurf: [docs/m0/rc-002-scope-1-0.md](docs/m0/rc-002-scope-1-0.md)
- [~] **RC-003 – Free/Pro-Grenze einfrieren.** Ergebnis: jede Funktion aus Abschnitt 5 des Konzepts ist Core, Pro 1.0, Pro-Folgeversion oder ausgeschlossen zugeordnet. → Entwurf: [docs/m0/rc-003-free-pro-grenze.md](docs/m0/rc-003-free-pro-grenze.md)
- [~] **RC-004 – Pro-Planmodell bestätigen.** Ergebnis: solo (1), studio (5) und agency (25 Live-Websites), Supportstufen und Produktpreise sind als Shopentscheidung dokumentiert. → Entwurf: [docs/m0/rc-004-planmodell.md](docs/m0/rc-004-planmodell.md)
- [~] **RC-005 – Lizenz-Entitlements finalisieren.** Ergebnis: Liste der tatsächlich gebauten review.*-Rechte, jeweils genau einer Pro-Funktion zugeordnet; keine leeren oder nur geplanten Rechte im Katalog. → Entwurf: [docs/m0/rc-005-entitlements.md](docs/m0/rc-005-entitlements.md)
- [~] **RC-006 – Kompatibilitätsziele festlegen.** Ergebnis: Mindestversionen WordPress, PHP und optional WooCommerce sowie Zielmatrix für HPOS, Classic/Blocks, Multisite und Page Builder. → Entwurf: [docs/m0/rc-006-kompatibilitaet.md](docs/m0/rc-006-kompatibilitaet.md)
- [~] **RC-007 – Supportumfang und Releasekanäle definieren.** Ergebnis: WordPress.org-Veröffentlichung für Core, Update-/Vertriebskanal für Pro, Supportsprachen, Supportumfang und Reaktionsziele. → Entwurf: [docs/m0/rc-007-support-release.md](docs/m0/rc-007-support-release.md)

### Plattform- und Rechtsfreigaben

- [~] **RC-008 – Provider-Matrix anlegen.** Für Google, Trustpilot, Trusted Shops/eTrusted, App Store Connect und Google Play je Anbieter dokumentieren: Kontotyp/Tarif, API-Freigabe, Authentifizierung, lesbare Felder, Einladungs-/Antwortfunktionen, Limits, Attribution, Cache- und Löschpflicht, Kosten und Fallback. → Entwurf: [docs/m0/rc-008-provider-matrix.md](docs/m0/rc-008-provider-matrix.md)
- [~] **RC-009 – Google-Zugang praktisch freischalten.** Ergebnis: registriertes Google-Cloud-Projekt, aktivierte Business-Profile-API, OAuth-Zustimmungsbildschirm, erforderliche API-Freigabe und Test-Standort. → Entwurf: [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [~] **RC-010 – Trustpilot-Zugang prüfen.** Ergebnis: dokumentierte API-/Partnerberechtigung, Test-Business-Unit, verwendbare Review- und Invitation-Endpunkte sowie Freigabe des konkreten Plugin-Use-Cases. → Entwurf: [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [~] **RC-011 – Trusted-Shops/eTrusted-Zugang prüfen.** Ergebnis: API-Client und Testkanal eines berechtigten Shops; lesende Review-, Einladung- und Widget-Funktionen sind voneinander abgegrenzt. → Entwurf: [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [~] **RC-012 – App-Store-Connect-Testzugang vorbereiten.** Ergebnis: Test-App, Team-/App-Ressourcen-ID, API-Key-Verfahren und minimale Rollen dokumentiert. → Entwurf: [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [~] **RC-013 – Google-Play-Testzugang vorbereiten.** Ergebnis: Test-App/Paket, Play-Console-Zugriff, Service-Account/OAuth-Verfahren und minimaler Scope dokumentiert. → Entwurf: [docs/m0/rc-009-013-anbieterzugaenge.md](docs/m0/rc-009-013-anbieterzugaenge.md)
- [~] **RC-014 – Datenschutz-/Kommunikationsprüfung abschließen.** Ergebnis: Datenfluss und Versandgrundlage je Zielmarkt fachlich geprüft; Consent, Opt-out, Aufbewahrung und Löschverantwortung stehen fest. → Entwurf: [docs/m0/rc-014-datenschutz-kommunikation.md](docs/m0/rc-014-datenschutz-kommunikation.md)
- [~] **RC-015 – Medienrechte prüfen.** Ergebnis: Einwilligungs-/Nutzungsrechtstext und Ablauf für Name, Bild, Stimme, Video, Untertitel, Widerruf und Veröffentlichung sind dokumentiert. → Entwurf: [docs/m0/rc-015-medienrechte.md](docs/m0/rc-015-medienrechte.md)
- [~] **RC-016 – Marken- und Trustmark-Regeln prüfen.** Ergebnis: Vorgaben zur Nutzung jedes Plattformlogos, Trustbadges, Awards und Zertifikatsassets sind in einer Checkliste erfasst. → Entwurf: [docs/m0/rc-016-marken-trustmarks.md](docs/m0/rc-016-marken-trustmarks.md)

**Meilenstein M0 ist erreicht, wenn:** 1.0-Scope, Kompatibilitätsziele, Lizenzmodell und die tatsächlichen Anbieterzugänge dokumentiert sind. Nicht verfügbare APIs werden sichtbar als Fallback geplant, nicht als zugesagte Synchronisierung verkauft.

## M0.5 – Nutzerabläufe und Bildschirmdesign

Diese Aufgaben werden nach dem MVP-Scope-Freeze und vor dem Bau der Admin-Seiten erledigt. Technische Verträge können parallel weiterlaufen.

- [ ] **UX-001 – Zielnutzer und Nutzungsszenarien validieren.** Ergebnis: Gespräche mit Shopbetreibern, lokalen Unternehmen, App-Anbietern und Captain-Suite-Nutzern bestätigen die wichtigsten Abläufe.
- [~] **UX-002 – Nutzerabläufe skizzieren.** Ergebnis: Erstinstallation, Review erfassen, Plattform verbinden, Syncfehler beheben, Widget veröffentlichen, Anfrage konfigurieren und Pro-Lizenz verwalten sind als Flows beschrieben. → Entwurf: [docs/m0-5/ux-002-nutzerablaeufe.md](docs/m0-5/ux-002-nutzerablaeufe.md)
- [~] **UX-003 – Admin-Wireframes erstellen.** Ergebnis: Dashboard, Reviews, Quellen, Profile, Anfragen, Widgets, Testimonials/Trustmarks, Integrationen einschließlich Connector-Manager, Einstellungen, Diagnose und Lizenzseite haben abgestimmte Wireframes. → Entwurf: [docs/m0-5/ux-003-admin-wireframes.md](docs/m0-5/ux-003-admin-wireframes.md)
- [~] **UX-004 – Fehler- und Leerzustände gestalten.** Ergebnis: keine Quelle verbunden, unberechtigter API-Zugang, abgelaufener Token, fehlende Reviews, Lizenz ohne Zugriff und Queuefehler haben verständliche Zustände und konkrete nächste Schritte. → Entwurf: [docs/m0-5/ux-004-fehler-leerzustaende.md](docs/m0-5/ux-004-fehler-leerzustaende.md)
- [~] **UX-005 – Frontend-Widget-Prototypen erstellen.** Ergebnis: Trust-Leiste, Plattform-Scorekarten, Testimonial-Wall, Video-/Audio-Carousel, Auszeichnungsblock und schwebendes Badge sind auf Desktop und Mobil ausgearbeitet. → Entwurf: [docs/prototypes/widgets.html](docs/prototypes/widgets.html)
- [ ] **UX-006 – Prototypen mit Nutzern prüfen.** Ergebnis: Testpersonen schließen „Quelle verbinden“ und „Bewertungsleiste veröffentlichen“ ohne Moderationshilfe ab; beobachtete Blocker sind behoben.
- [~] **UX-007 – Designsystem und Editor-Handoff festlegen.** Ergebnis: Typografie, Farben, Abstände, Sterne, Quellenbadges, Fokuszustände, reduced-motion und Block-Steuerungen sind dokumentiert. → Entwurf: [docs/m0-5/ux-007-designsystem.md](docs/m0-5/ux-007-designsystem.md)
- [~] **UX-008 – Usability-Kennzahlen definieren.** Ergebnis: Zeit bis zur ersten Verbindung, Zeit bis zum ersten Widget und Einrichtungsabbrüche sind ohne unnötige personenbezogene Telemetrie messbar oder bewusst nicht erfasst. → Entwurf: [docs/m0-5/ux-008-usability-kennzahlen.md](docs/m0-5/ux-008-usability-kennzahlen.md)
- [~] **UX-009 – Einstellungsinventar erstellen.** Ergebnis: jede geplante Option ist mit Nutzerfrage, globalem/Profil-/Kampagnen-/Widget-Geltungsbereich, Free/Pro-Recht, sicherem Standardwert, Capability und Speicherort erfasst; doppelte Optionen sind entfernt. → Entwurf: [docs/settings/einstellungsinventar.md](docs/settings/einstellungsinventar.md)
- [~] **UX-010 – Einstellungen-Informationsarchitektur abstimmen.** Ergebnis: Seitennavigation, Tabnamen, Reihenfolge, Gruppierungen und Abgrenzung zu Quellen, Kampagnen, Widgets, Integrationen und Diagnose sind als WP-Admin-Wireframe freigegeben. → Entwurf: [docs/settings/einstellungsvertrag.md](docs/settings/einstellungsvertrag.md)
- [~] **UX-011 – Einfach-/Erweitert-Modus definieren.** Ergebnis: häufige Einstellungen sind sofort sichtbar; seltene technische Optionen liegen in aufklappbaren Erweitert-Bereichen; erforderliche Felder werden abhängig von Quelle, Versandweg und Lizenzstatus eingeblendet. → Entwurf: [docs/settings/einstellungsvertrag.md](docs/settings/einstellungsvertrag.md)
- [~] **UX-012 – Erstkonfiguration und Leerzustände entwerfen.** Ergebnis: Admin wird von Basisdaten und Absender über Quelle/Test bis zu erster Kampagne geführt; Setup-Schritte sind wiederaufnehmbar und lassen sich ohne Datenverlust überspringen. → Entwurf: [docs/settings/einstellungsvertrag.md](docs/settings/einstellungsvertrag.md)
- [~] **UX-013 – Begriffe und Hilfetexte testen.** Ergebnis: Betreiber verstehen Geltungsbereich, Wirkung und Risiko jeder Option; verständliche Beispiele und Statushinweise ersetzen interne Begriffe wie TTL, Backoff oder Entitlement. → Entwurf: [docs/m0-5/ux-013-glossar.md](docs/m0-5/ux-013-glossar.md)

## M1 – Verträge, Datenmodell und Architektur

- [~] **RC-017 – System-of-Record-Regeln dokumentieren.** Ergebnis: WooCommerce besitzt Woo-Produktreviews; Review Captain besitzt lokale allgemeine Reviews, externe Review-Snapshots, Widgets, Testimonials und Anfragen. → Entwurf: [docs/m1/rc-017-system-of-record.md](docs/m1/rc-017-system-of-record.md)
- [~] **RC-018 – Normalisiertes Review-Schema finalisieren.** Ergebnis: Feldliste, Skalen, Sprache, Profil/Quelle, stabile externe ID, Kontext, Cachezeit, Tombstone/Löschung und Sichtbarkeitsstatus sind dokumentiert. → Entwurf: [docs/m1/datenmodell.md](docs/m1/datenmodell.md)
- [~] **RC-019 – Testimonial-Schema finalisieren.** Ergebnis: Text/Bild/Video/Audio-Typen, WordPress-Media-Anhang, Transkript/Untertitel, Kontext, Rechtebeleg, Ablauf, Widerruf und Moderationsstatus sind beschrieben. → Entwurf: [docs/m1/datenmodell.md](docs/m1/datenmodell.md)
- [~] **RC-020 – Provider-Vertrag v1 definieren.** Ergebnis: connect, test, list profiles, sync page/cursor, normalize, delete/tombstone, quota/status und capability discovery sind versioniert und mit Fixture-Beispielen beschrieben. → Entwurf: [docs/m1/vertraege.md](docs/m1/vertraege.md)
- [~] **RC-021 – Ereignisvertrag für Reviews v1 definieren.** Ergebnis: Payload für source, event_id, experience_id, event_type, occurred_at, object reference, recipient reference, locale und Versandberechtigung ist formal festgelegt. → Entwurf: [docs/m1/vertraege.md](docs/m1/vertraege.md)
- [~] **RC-022 – Experience-ID-Ownership je Quelle bestätigen.** Ergebnis: WooCommerce-Kauf, Voucher-Einlösung, Pass-Teilnahme und Form-Captain-Einsendung haben jeweils genau einen ID-Verantwortlichen und dokumentierte Dedupe-Regeln. → Entwurf: [docs/m1/anfragen.md](docs/m1/anfragen.md)
- [~] **RC-023 – Kontaktresolver-Vertrag definieren.** Ergebnis: Empfängerreferenzen können nur zur Versandzeit und mit gültiger Berechtigung in Kontaktangaben aufgelöst werden; unbekannt/widerrufen blockiert Versand. → Entwurf: [docs/m1/vertraege.md](docs/m1/vertraege.md)
- [~] **RC-024 – Anfrage-Lifecycle und Zustände festlegen.** Ergebnis: planned, eligible, sent, delivered/unknown, clicked (nur falls erlaubt), skipped, cancelled, failed, unsubscribed und expired mit Übergängen dokumentiert. → Entwurf: [docs/m1/anfragen.md](docs/m1/anfragen.md)
- [~] **RC-025 – Einladungsregel technisch spezifizieren.** Ergebnis: Unique-Key experience_id + recipient_ref + campaign_id, maximal eine Einladung je Erfahrung/Empfänger und kein zweiter Versand über konkurrierende Suite-Ereignisse. → Entwurf: [docs/m1/anfragen.md](docs/m1/anfragen.md)
- [~] **RC-026 – Authentifizierungsmodell je Provider festlegen.** Ergebnis: Captain OAuth-App vs. Betreibercredential, Scope, Rotation, Token-Widerruf und Verschlüsselungsweg pro Anbieter entschieden. → Entwurf: [docs/m1/auth-und-secrets.md](docs/m1/auth-und-secrets.md)
- [~] **RC-027 – Datenaufbewahrungs- und Löschmatrix festlegen.** Ergebnis: je Quelle Speicherfrist, Refreshintervall, Lösch-/Widerrufspfad, Export, Backup und Entfernung beim Disconnect dokumentiert. → Entwurf: [docs/m1/rc-027-aufbewahrung-loeschung.md](docs/m1/rc-027-aufbewahrung-loeschung.md)
- [~] **RC-028 – Datenbank- und Migrationsplan abnehmen.** Ergebnis: Tabellen/Optionen, Indizes, Versionsnummer, Upgrade-/Rollback-Verhalten und Multisite-Scope sind spezifiziert. → Entwurf: [docs/m1/datenmodell.md](docs/m1/datenmodell.md)
- [~] **RC-029 – Rollen- und Capability-Modell festlegen.** Ergebnis: getrennte Rechte für Reviews moderieren, Quellen verbinden, Anfragen verwalten, Reports sehen und Trustmarks verwalten. → Entwurf: [docs/m1/rc-029-030-rechte-fehlercodes.md](docs/m1/rc-029-030-rechte-fehlercodes.md)
- [~] **RC-030 – Fehlercodes und Integrationsstatus standardisieren.** Ergebnis: inkompatibler Vertrag, Auth-Fehler, Quota, Timeout, entfernte Review, Retry und endgültiger Fehler erhalten dokumentierte Statuscodes. → Entwurf: [docs/m1/rc-029-030-rechte-fehlercodes.md](docs/m1/rc-029-030-rechte-fehlercodes.md)
- [~] **RC-031 – Architektur-Entscheidungsprotokoll einrichten.** Ergebnis: wesentliche Änderungen an Schema, Verträgen, Lizenzrechten und Anbieterzugängen werden mit Datum/Grund versioniert. → Entwurf: [docs/adr/README.md](docs/adr/README.md)

**Meilenstein M1 ist erreicht, wenn:** Core und alle Adapter dieselben Datenverträge, Lifecycle-Regeln und Dedupe-Schlüssel verwenden.

## M1.5 – Technische Betriebs- und Sicherheitsgrundlagen

Diese Infrastrukturentscheidungen müssen vor den davon abhängigen Core-/Pro-Implementierungen abgenommen werden. Allgemeine Aussagen wie „Retry“ oder „verschlüsseln“ reichen für den Produktionsbetrieb nicht aus.

- [~] **TEC-001 – Job-Runner-Vertrag festlegen.** Ergebnis: definiert ist, wann Action Scheduler genutzt wird, wie der Core ohne WooCommerce Jobs ausführt und wie WP-Cron, echter System-Cron und WP-CLI zusammenspielen. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-002 – Produktions-Cron und Verzögerung dokumentieren.** Ergebnis: zeitkritische Syncs/Review-Einladungen funktionieren mit empfohlenem System-Cron; Admin zeigt fällige/überfällige Jobs und keine angeblich präzisen Zeiten bei unzuverlässigem WP-Cron. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-003 – Job-Claiming und Lease implementieren.** Ergebnis: ein Job hat atomaren Besitzer, Ablaufzeit und Recovery nach Prozessabbruch; zwei Worker können denselben Job nicht gleichzeitig ausführen. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-004 – Idempotenz in der Datenbank erzwingen.** Ergebnis: Unique Constraints für externe Review-IDs, Experience-/Recipient-/Campaign-Schlüssel und Provider-Event-IDs verhindern Duplikate auch bei parallelen Hooks und Retry. → Entwurf: [docs/m1/datenmodell.md](docs/m1/datenmodell.md)
- [~] **TEC-005 – Race-Conditions spezifizieren und abnehmen.** Ergebnis: Tests decken gleichzeitig eintreffende Einlösung/Check-in, Refund vs. Einladungsversand, Tokenrefresh und zwei Sync-Läufe derselben Quelle ab. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-006 – Retry- und Dead-letter-Politik pro Fehlerklasse festlegen.** Ergebnis: temporär, permanent, Rate-Limit, Authentifizierungsfehler und Datenfehler haben getrennte Retry-/Pause-/Adminaktionsregeln; Retry-After wird beachtet. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-007 – API-Budgets und Parallelitätslimits implementieren.** Ergebnis: pro Quelle/Profile sind Seitenlimit, tägliches/zeitliches Budget, Backoff und Admin-Warnschwelle einstellbar; ein Sync kann Website oder Anbieterquote nicht ungebremst belasten. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-008 – Tokenverschlüsselung und Schlüsselrotation festlegen.** Ergebnis: Schlüsselquelle, Algorithmus/Nonce-Handling, Rotation, Backup/Wiederherstellung und Verhalten bei fehlendem/ungültigem Schlüssel sind dokumentiert und implementiert; Tokens erscheinen nie in Logs/Exporten. → Entwurf: [docs/m1/auth-und-secrets.md](docs/m1/auth-und-secrets.md)
- [~] **TEC-009 – Multisite- und Site-Isolation absichern.** Ergebnis: Tabellen, Optionen, OAuth-Verbindungen, Warteschlangen, Lizenzstatus und Datenexport sind je Site korrekt isoliert; Netzwerkdeaktivierung/Wiederaktivierung ist definiert. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-010 – REST-/OAuth-Grenzen härten.** Ergebnis: jede Route hat eine explizite permission_callback; Callback prüft State/Nonce, Redirect-URI und Berechtigung; Wiederholung/Replay und unautorisierte Profilwahl werden abgewehrt. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-011 – Cross-site-Schnittstellen explizit begrenzen.** Ergebnis: lokale Suite-Adapter verwenden PHP-Verträge; falls später REST/Webhooks nötig werden, sind Signatur, Timestamp, Replay-Schutz, Scope und Schlüsselrotation Voraussetzung. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-012 – Cache- und CDN-Verhalten spezifizieren.** Ergebnis: Provider-TTL, Pflichtrefresh, Tombstone/Löschung, Seiten-/Objektcache und CDN-Invalidierung sind pro Quelle abgestimmt; personenbezogene Anfrage-/Lizenzdaten werden nie öffentlich gecacht. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-013 – Frontend-Embed-Sicherheitsmodell umsetzen.** Ergebnis: feste Host-Allowlist, SSRF-Schutz, consent-basiertes Laden externer Player/Embeds und sichere Linkattribute verhindern beliebige serverseitige Abrufe und ungefragte Drittanbieterrequests. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-014 – Skalierungsgrenzen und Listenqueries messen.** Ergebnis: Testdaten mit mindestens 100.000 Reviews und 10.000 wartenden/abgeschlossenen Jobs zeigen indizierte Adminsuche, paginierte Exporte und begrenzte Joblaufzeit. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)
- [~] **TEC-015 – Upgrade-/Restore-Reconciliation härten.** Ergebnis: nach Schemaupgrade oder Backup-Restore werden Cursor, externe IDs, Jobleases und widerrufene Tokens geprüft; Wiederherstellung erzeugt keine doppelten Einladungen oder alten API-Schreibaktionen. → Entwurf: [docs/m1-5/technische-grundlagen.md](docs/m1-5/technische-grundlagen.md)

**Meilenstein M1.5 ist erreicht, wenn:** Hintergrundjobs unter Parallelität sicher und wiederholbar laufen, Zugangsdaten kontrolliert wiederherstellbar sind und die Site bei Providerfehlern oder Cache-/Cronproblemen einen nachvollziehbaren Zustand zeigt.

## M2 – Kostenlosen Core bauen

### Plugin-Grundlage

- [ ] **RC-032 – Core-Plugin-Skeleton erstellen.** Ergebnis: Plugin-Header, Namespaces, Loader, Aktivierung/Deaktivierung und Mindestversion-Warnung entsprechen WordPress-Konventionen.
- [ ] **RC-033 – Build-, Coding-Standard- und CI-Grundlage einrichten.** Ergebnis: reproduzierbares Entwicklungs-/Release-ZIP, Syntax-/Coding-Standard-Prüfungen und Plugin-Metadatenprüfung laufen in CI.
- [ ] **RC-034 – Übersetzungsworkflow einrichten.** Ergebnis: Textdomain, POT und de_DE/de_AT Übersetzungsworkflow sind nutzbar; keine sichtbaren Admin-Texte bleiben unübersetzbar.
- [ ] **RC-035 – Versioniertes Datenbankschema implementieren.** Ergebnis: Installation und Upgrade erzeugen Schema idempotent; keine Schemaarbeit auf normalen Frontend-Besuchen.
- [ ] **RC-036 – WP-CLI-/Cron-Wartungspunkte einrichten.** Ergebnis: Datenbereinigung, Diagnose und Reconciliation sind zeitbegrenzt und können per WP-Cron bzw. CLI angestoßen werden.
- [ ] **RC-037 – Rollen, Nonces und Audit-Grundlage implementieren.** Ergebnis: alle schreibenden Adminaktionen prüfen Capability und Nonce; sensible Änderungen hinterlassen datensparsame Audit-Einträge.

### Lokale Reviews und Datenverwaltung

- [ ] **RC-038 – Reviews-Adminseite umsetzen.** Ergebnis: Tabelle, Suche, Filter nach Typ/Quelle/Rating/Status/Zeitraum und paginierte Detailansicht funktionieren.
- [ ] **RC-039 – Lokale Review-Einreichung umsetzen.** Ergebnis: konfigurierbares Formular mit Rating/Text, Validierung, Honeypot, Rate-Limit und Moderationsstatus.
- [ ] **RC-040 – Moderationsworkflow implementieren.** Ergebnis: ein Review kann freigegeben, abgelehnt, archiviert, gemeldet und mit Änderungsgrund protokolliert werden.
- [ ] **RC-041 – Verifizierungsstatus implementieren.** Ergebnis: Kauf verifiziert, Teilnahme bestätigt, extern importiert und ungeprüft sind getrennte Zustände; nur ein Quellenvertrag kann „verifiziert“ setzen.
- [ ] **RC-042 – Eigene Reviews und WooReviews getrennt halten.** Ergebnis: keine importierte Plattformbewertung wird als Woo-Kommentar angelegt oder in native Woo-Sterne eingerechnet.
- [ ] **RC-043 – Manuelle externe Reviews erfassen.** Ergebnis: Eintrag verlangt Plattform, Originalbewertung, erlaubten Text, Datum, Profil-Link und Quellenhinweis.
- [ ] **RC-044 – CSV-Export implementieren.** Ergebnis: Filterexport mit UTF-8, Zeitstempeln, Quellenfeldern und Schutz gegen Spreadsheet-Formelinjektion.
- [ ] **RC-045 – CSV-Import mit Prüflauf implementieren.** Ergebnis: Dry-run zeigt Zeilenfehler und vermutete Duplikate; Import kann nachvollziehbar zurückgerollt werden.
- [ ] **RC-046 – Migration aus WooCommerce und verbreiteten lokalen Review-Plugins spezifizieren.** Ergebnis: Feldmapping, Statusübernahme, Attachment-Behandlung und Grenzen werden in einer Migrationsanleitung erklärt.
- [ ] **RC-047 – Privacy Exporter/Eraser integrieren.** Ergebnis: Reviewdaten, Autorreferenzen, Anfrage-/Consentstatus und Logs können exportiert oder fristgerecht gelöscht werden.
- [ ] **RC-048 – Aufbewahrung und Deinstallation umsetzen.** Ergebnis: Core-Daten bleiben standardmäßig bei Deinstallation erhalten; explizite Löschoption ist verständlich und vollständig.

### Free-Frontend

- [ ] **RC-049 – Review-Card serverseitig rendern.** Ergebnis: Review-Ausgabe funktioniert ohne Pflicht-JavaScript und escaped Nutzerinhalte sicher.
- [ ] **RC-050 – Gutenberg-Review-Block bauen.** Ergebnis: Block bietet Datenquelle, Layout, Rating-/Quellfilter und Vorschau im Editor.
- [ ] **RC-051 – Shortcode-API bauen.** Ergebnis: versionierter Shortcode unterstützt Widget-ID und sichere Basisfilter.
- [ ] **RC-052 – Grid/List-Basislayouts gestalten.** Ergebnis: mobile, responsive Karten mit Quelle, Skala, Datum und Original-Link.
- [ ] **RC-053 – Lokale Text-Testimonial-Ausgabe ergänzen.** Ergebnis: eigene Testimonials werden sichtbar von externen Plattformreviews unterschieden.
- [ ] **RC-054 – Bewertungsprofil-CTA bereitstellen.** Ergebnis: Betreiber kann Originalprofil-Link und neutrale CTA ohne Sterne-Vorwahl setzen.
- [ ] **RC-055 – Schema-Ausgabe begrenzen.** Ergebnis: keine externen Reviews oder selbstbezogenen Unternehmensreviews erzeugen unzulässige AggregateRating-Ausgabe.

**Meilenstein M2 ist erreicht, wenn:** der kostenlose Core ohne Lizenzserver, WooCommerce oder externe Review-API eigenständig installiert und genutzt werden kann.

## M3 – Pro-Add-on und Lizenzierung

- [ ] **RC-056 – Add-on in Captain Pro registrieren.** Ergebnis: Ordner, Slug, Textdomain, Buildkonfiguration und Mindest-Core-Version sind korrekt.
- [ ] **RC-057 – Lizenzclient und Kernel integrieren.** Ergebnis: Export/Bundling folgt Captain-Pro-Namespace- und Buildregeln; Core bleibt frei von Lizenzcode.
- [ ] **RC-058 – Review-Produkt und Pläne in WP License Captain anlegen.** Ergebnis: review-captain-pro, Plan-IDs solo/studio/agency, Site-Limits und Supportlevel stimmen mit Shopkatalog überein.
- [ ] **RC-059 – Entitlement-Katalog aktualisieren.** Ergebnis: nur tatsächlich implementierte review.*-Keys sind auswählbar und je Plan nachvollziehbar vergeben.
- [ ] **RC-060 – Lizenzseite und Statushinweise einbauen.** Ergebnis: Aktivieren, Deaktivieren, Site-Limit, Ablauf, Verlängerung, Updates und Kundenkonto-Link funktionieren.
- [ ] **RC-061 – Lizenzablaufverhalten implementieren.** Ergebnis: neue Pro-Schreibaktionen/Syncs werden gestoppt; vorhandene Reviews bleiben lesbar/exportierbar und werden nicht gelöscht.
- [ ] **RC-062 – Pro-Updater konfigurieren.** Ergebnis: signierte Lizenzprüfung und Updatepakete funktionieren ohne unerlaubte API-Aufrufe bei normalem Frontend-Aufruf.
- [ ] **RC-063 – Pro-Build und Release-ZIP integrieren.** Ergebnis: beide Plugins getrennt verteilbar; Build prüft Produktkey, Version, Host, Schlüssel und Entitlements.
- [ ] **RC-064 – E2E-Lizenzlauf vorbereiten.** Ergebnis: Installieren, Aktivieren, Rechte, Ablauf, Update und Deaktivieren werden gegen den echten Testserver durchgespielt.

**Meilenstein M3 ist erreicht, wenn:** Pro mit gültiger Lizenz freischaltet und Ablauf keine Core-Daten oder Core-Funktion beschädigt.

## M4 – Plattform-Adapter implementieren

Jeder Provider durchläuft dieselbe Reihenfolge: Verbindung → Test → Profilwahl → einmaliger Initialimport → Delta-Sync → Aktualisierung/Löschung → Diagnose → Fallback. Kein Adapter gilt als fertig, nur weil ein einzelner API-Aufruf funktioniert.

### Google Business Profile

- [ ] **RC-065 – OAuth-Verbindung und sichere Callback-Prüfung umsetzen.** Ergebnis: State/Nonce, Redirect, Reconnect und Token-Widerruf funktionieren.
- [ ] **RC-066 – Berechtigte Profile/Standorte auflisten.** Ergebnis: nur vom eingeloggten Konto verwaltete/verifizierte Standorte sind auswählbar.
- [ ] **RC-067 – Reviews paginiert synchronisieren.** Ergebnis: Cursors, Update-Zeit, Zuordnung zu Location und Idempotenz sind robust.
- [ ] **RC-068 – Google-Attribution und Löschregeln umsetzen.** Ergebnis: Quellenlink/-label, Aktualisierungszeit, Fehler- und Cache-Regeln sind sichtbar.
- [ ] **RC-069 – Google-Places-Fallback separat implementieren oder streichen.** Ergebnis: Place-ID, maximaler Umfang, Kosten, Limit und Fallback sind dokumentiert; niemals mit GBP-OAuth-Daten vermischen.

### Trustpilot

- [ ] **RC-070 – Trustpilot-Credential-/OAuth-Flow implementieren.** Ergebnis: API-Recht, Business Unit und Berechtigungen werden vor Synchronisierung geprüft.
- [ ] **RC-071 – Trustpilot-Reviews und Summaries synchronisieren.** Ergebnis: Pagination, erlaubte Cache-Aktualisierung, Löschung und Originalprofil-Link funktionieren.
- [ ] **RC-072 – Trustpilot-Einladungsfähigkeit prüfen.** Ergebnis: nur freigeschaltete offizielle Invitation-Endpunkte werden angeboten; sonst Profil-/Review-Link-Fallback.
- [ ] **RC-073 – TrustBox/Widget-Fallback dokumentieren.** Ergebnis: offizieller Embed-Code lässt sich ohne Tracking-Skript vor Zustimmung nicht automatisch laden.

### Trusted Shops / eTrusted

- [ ] **RC-074 – eTrusted-API-Client verbinden.** Ergebnis: API-Client, Channel, Berechtigung und Tokenrefresh werden geprüft.
- [ ] **RC-075 – eTrusted-Reviews paginiert synchronisieren.** Ergebnis: Service-/Produktreviews werden korrekt typisiert und dem Kanal zugeordnet.
- [ ] **RC-076 – eTrusted-Review-Invite-Modus integrieren.** Ergebnis: Event-/Invite-API läuft nur mit gültigem Vertrag und zugelassenen Datenfeldern.
- [ ] **RC-077 – Trusted-Shops-Widget-Fallback einbauen.** Ergebnis: offizieller Badge-/Embed-Weg ist konfigurierbar, trennbar und im Frontend datenschutzbewusst.

### Apple App Store und Google Play

- [ ] **RC-078 – App Store Connect Credential-Speicher implementieren.** Ergebnis: Team/App/Key-Zuordnung, Rotation und Widerruf funktionieren ohne Geheimnis in Logs.
- [ ] **RC-079 – App-Store-Reviews synchronisieren.** Ergebnis: Territory, Version, Sterne, Reviewer-Alias, Datum und Antwortstatus bleiben korrekt zugeordnet.
- [ ] **RC-080 – Google-Play-Verbindung implementieren.** Ergebnis: Betreiber autorisiert nur eigene Play-Console-App; Rechte sind minimal und widerrufbar.
- [ ] **RC-081 – Play-Reviews synchronisieren.** Ergebnis: Pagination, Übersetzungssprache, App-Version und vorhandene Antwort werden abgeglichen.
- [ ] **RC-082 – Provider-Fehler- und Quota-Verhalten vereinheitlichen.** Ergebnis: 401/403, 404, 429, 5xx, Timeout und Datenlöschung führen in nachvollziehbare Status-/Retry-Wege.
- [ ] **RC-083 – Provider-Sandbox-/Fixture-Suite erstellen.** Ergebnis: jeder Adapter hat Tests für Pagination, Duplikate, Änderungen, Löschung, Quota, Tokenablauf und ungültiges Payload.
- [ ] **RC-084 – Quellenübersicht und Syncdiagnose bauen.** Ergebnis: Profil, letzter Lauf, nächste Ausführung, Anzahl importierter/entfernter Reviews und verständlicher Fehlergrund sind sichtbar.

**Meilenstein M4 ist erreicht, wenn:** jede zum Launch versprochene Plattform einen echten, dokumentierten Verbindungstest plus regelkonformen Fallback bestanden hat.

## M5 – Anfrage-Engine und Captain-Suite

### Review-Anfrage-Engine

- [ ] **RC-085 – Kampagnenverwaltung bauen.** Ergebnis: Auslöser, Zielplattform, Verzögerung, Sprache, Vorlage, Empfängerregel und Aktivierungsstatus sind versioniert gespeichert.
- [ ] **RC-086 – Dauerhafte Versandqueue implementieren.** Ergebnis: Job-Sperre, Retry, Backoff, endgültiger Fehler und manuelles Wiederholen ohne Doppelsendung.
- [ ] **RC-087 – Einladungs-Deduplizierung implementieren.** Ergebnis: Unique-Key aus Erlebnis, Empfänger und Kampagne verhindert doppelte Einladung über konkurrierende Adapter.
- [ ] **RC-088 – Empfänger-Resolver und Berechtigungsprüfung anbinden.** Ergebnis: Kontakt wird erst zum Versandzeitpunkt geladen; unbekannte oder widerrufene Berechtigung stoppt den Versand.
- [ ] **RC-089 – Betreiber-eigenen Mailtransport unterstützen.** Ergebnis: klare Anforderungen an Absenderdomain/SMTP, Testmail, Fehlerstatus, Bounce/Unsubscribe und keine harte Abhängigkeit von stillschweigendem Standardversand.
- [ ] **RC-090 – Opt-out- und Suppression-Liste umsetzen.** Ergebnis: Abmeldelink und siteweite Sperre werden vor jedem Versand geprüft.
- [ ] **RC-091 – Plattformziel und Kampagnenregeln umsetzen.** Ergebnis: Zielplattform ist vorab festgelegt und für alle geeigneten Empfänger gleich; keine Auswahl anhand von Sternebewertung oder Stimmung.
- [ ] **RC-092 – Erinnerungen optional einbauen.** Ergebnis: standardmäßig aus; eine Erinnerung bleibt derselben Experience-/Recipient-Grenze unterworfen und wird protokolliert.
- [ ] **RC-093 – Versandprotokoll und Vorschau bauen.** Ergebnis: Testmodus, Zustände, Skip-Gründe, Templatevorschau und datensparsame Logs funktionieren.
- [ ] **RC-094 – Erinnerungs-/Kampagnenlokalisierung ergänzen.** Ergebnis: Sprache aus Quelle/Empfänger, Fallback und fehlende Übersetzung sind nachvollziehbar.

### Suite-Adapter

- [ ] **RC-095 – WooCommerce-Adapter implementieren.** Ergebnis: konfigurierbare Bestellstatus, Produktmapping, HPOS und Classic-Speicher; Blocks-Checkout wird nicht verändert.
- [ ] **RC-096 – WooCommerce-Mehrprodukt-Anfrage umsetzen.** Ergebnis: eine E-Mail kann mehrere bewertbare Positionen sammeln, ohne native WooReviews zu duplizieren.
- [ ] **RC-097 – Refund-/Stornoausschlüsse anbinden.** Ergebnis: offene Einladung wird bei Refund, Chargeback oder Storno je definierter Kampagnenregel gestoppt.
- [ ] **RC-098 – Voucher-Captain-Vertrag erweitern.** Ergebnis: echte Einlösung/Leistung wird versioniert gemeldet; Ausstellung, Kauf und Guthabenaufladung lösen keine Anfrage aus.
- [ ] **RC-099 – Voucher-Empfängerlogik umsetzen.** Ergebnis: Käufer und Beschenkter werden unterschieden; Einladung geht nur an den zulässigen tatsächlichen Empfänger.
- [ ] **RC-100 – Pass-Captain-Completion-Vertrag ergänzen.** Ergebnis: Check-in/Teilnahme, No-show, Absage und Refund besitzen stabile Ereignisse und Experience-ID.
- [ ] **RC-101 – Ticketkäufer-/Teilnehmerregel umsetzen.** Ergebnis: bei mehreren Tickets wird der definierte tatsächliche Teilnehmer adressiert und Deduplizierung greift je Erlebnis.
- [ ] **RC-102 – Affiliate-Captain-Adapter implementieren.** Ergebnis: nur Kampagnen-/Partnerreferenz und aggregierte Kennzahlen, keine Kunden- oder Reviewtexte.
- [ ] **RC-103 – Form-Captain-Aktion implementieren.** Ergebnis: ausgewählte Form-Captain-Einsendung kann mit explizitem Opt-in als Review-Captain-Entwurf übergeben werden; standardmäßig moderiert, nicht automatisch veröffentlicht.
- [ ] **RC-104 – Form-Captain-Vertragskompatibilität prüfen.** Ergebnis: Form-Captain-Core reicht als Quelle; Review-Captain-Pro-Recht schaltet die Review-Aktion frei; inkompatible Vertragsversion wird sauber angezeigt.
- [ ] **RC-105 – Suite-Integrationsstatus bauen.** Ergebnis: installierte Version, Vertragskompatibilität, letzte Annahme, Queue, Fehler und Verbindungstest je Adapter.
- [ ] **RC-106 – Suite-E2E-Läufe ausführen und dokumentieren.** Ergebnis: Kauf→Gutschein-Einlösung, Ticket→Check-in, Woo-Refund, Formular-Submission, Affiliate-Attribution, Opt-out, Plugin fehlt und Duplicate-Events sind abgedeckt.

**Meilenstein M5 ist erreicht, wenn:** die Einladungsregel durch alle Quellen identisch durchgesetzt wird und kein Plugin fremde interne Tabellen benötigt.

## M6 – Social Proof, Testimonials und Trust-Badges

- [ ] **RC-107 – Testimonial-Bibliothek implementieren.** Ergebnis: Text, Bild, Video und Audio mit Kontext, Sprache, Status und Widerruf sind verwaltbar.
- [ ] **RC-108 – Rechte-/Freigabeprozess umsetzen.** Ergebnis: jede Medienveröffentlichung verlangt belegte Nutzungsrechte für Name, Bild und Stimme; abgelaufene/widerrufene Einwilligung blendet Inhalt aus.
- [ ] **RC-109 – Medien-Upload absichern.** Ergebnis: erlaubte MIME-Typen, Größenlimit, Dateiprüfung, Zugriffskontrolle und sichere Löschung sind implementiert.
- [ ] **RC-110 – Video-/Audio-Ausgabe barrierearm bauen.** Ergebnis: keine Autoplay-Wiedergabe, Tastatursteuerung, Untertitel/Transkript, Posterbild und click-to-load für externe Player.
- [ ] **RC-111 – Trustmark-/Award-Verwaltung bauen.** Ergebnis: Herausgeber, gültig ab/bis, Asset/Embed, Verifizierungslink und Markenrechte sind Pflichtfelder.
- [ ] **RC-112 – Plattform-Bewertungsleiste bauen.** Ergebnis: Score, Originalskala, Anzahl, Quelle, Zeitraum, Datenstand und Link je Plattform sind sichtbar.
- [ ] **RC-113 – Summenanzahl transparent darstellen.** Ergebnis: Count-Summe trägt Label „über ausgewählte Plattformen“, Zeitraum und Hinweis auf fehlende plattformübergreifende Personendeduplizierung.
- [ ] **RC-114 – Plattformübergreifenden Score blockieren oder erklären.** Ergebnis: standardmäßig kein gemeinsamer Score; falls aktiviert, sind Normalisierung, Gewichtung, Quelle und Stichprobe vollständig sichtbar.
- [ ] **RC-115 – Testimonial-Wall/Video-/Audio-Carousel bauen.** Ergebnis: mobile, performante Layouts mit manueller Moderationsauswahl und klarer Quellenart.
- [ ] **RC-116 – Floating Trust-Badge bauen.** Ergebnis: pro Seite/Position konfigurierbar, minimierbar, mobil getestet und kollidiert nicht mit Kauf-, Chat- oder Ticketaktionen.
- [ ] **RC-117 – Page-/Produkt-/Event-Kontextfilter anbinden.** Ergebnis: Editor kann Reviews und Testimonials passend zu Woo-Produkt, Gutschein, Event, App oder Formular auswählen.
- [ ] **RC-118 – Widget-Registry und Embed-Shortcodes finalisieren.** Ergebnis: Blocks, Shortcodes, SSR und Builder-Integrationen geben dieselbe freigegebene Widgetdefinition aus.
- [ ] **RC-119 – Page-Builder-Mindestumfang festlegen.** Ergebnis: Elementor/Divi/andere Builder sind ausdrücklich unterstützt oder als nicht unterstützt dokumentiert.
- [ ] **RC-120 – Layoutkonflikte prüfen.** Ergebnis: Slider, Chat-Bubble, Sticky-Checkout und Trust-Badge überlagern sich auf typischen Mobilbreiten nicht.

**Meilenstein M6 ist erreicht, wenn:** alle Formate korrekt attribuiert, Rechte nachweisbar, zugänglich und ohne externe Autoplay-/Tracking-Überraschungen ausgegeben werden.

## M7 – Wettbewerbsfunktionen nach MVP

Diese Aufgaben starten erst, wenn MVP-Funktionen und API-Rechte stabil sind. Sie blockieren Pro 1.0 nur, wenn sie im Scope-Freeze ausdrücklich als Launchanforderung ausgewählt wurden.

- [ ] **RC-121 – Foto-Reviews mit Moderation ergänzen.** Abnahme: Upload wird vor Veröffentlichung geprüft, datensparsam gespeichert und löschbar.
- [ ] **RC-122 – Produkt-Q&A ergänzen.** Abnahme: Fragen und Antworten sind von Bewertungen getrennt, moderierbar und einem Woo-Produkt zugeordnet.
- [ ] **RC-123 – Review-Formular für mehrere Produkte erweitern.** Abnahme: eine Order-Session kann mehrere Positionen bewerten; keine Doppelreviews.
- [ ] **RC-124 – Review-Tags und Meldungen ergänzen.** Abnahme: interne Tags, öffentliches Melden und Adminprüfung sind verfügbar.
- [ ] **RC-125 – Review-Autorenkorrektur definieren.** Abnahme: Identitätsprüfung, Audit und Versionierung verhindern unbemerkte Änderung freigegebener Reviews.
- [ ] **RC-126 – WhatsApp-Link/QR-Campaign ergänzen.** Abnahme: nur offiziell erlaubter Click-to-Chat oder Anbieter; Feedback-QR wird nie mit Ticket-Einlasscode verwechselt.
- [ ] **RC-127 – Google-Shopping-Review-Feed evaluieren.** Abnahme: Merchant-Center-Freigabe, Herkunftsanforderungen und Feedvalidierung liegen vor; sonst wird das Feature nicht beworben.
- [ ] **RC-128 – Weitere Plattformen priorisieren.** Abnahme: jede neue Plattform hat Nachfragebeleg, offizielle Zugriffsmethode, Nutzungsrecht und Kostenentscheidung.
- [ ] **RC-129 – Sentiment-/KI-Funktionen neu bewerten.** Abnahme: nur als separates Produktdecision-Dokument mit Datenfluss, Einwilligung und menschlicher Kontrolle; kein automatisches Veröffentlichen.

## M8 – Qualität, Sicherheit und Release

- [ ] **RC-130 – Automatisierte Unit-/Integrationstests vervollständigen.** Abnahme: Zustandswechsel, Rechte, Lizenzablauf, Import, Dedupe und Providerfehler sind regressionsgesichert.
- [ ] **RC-131 – Pluginverbund-End-to-End-Testumgebung aufsetzen.** Abnahme: Review Captain Core/Pro plus WooCommerce und Suite-Testplugins lassen sich reproduzierbar installieren.
- [ ] **RC-132 – Provider-Sandboxen durchspielen.** Abnahme: je Launchprovider echte Authentifizierung, Sync, Löschung, Quota und Tokenwiderruf sind protokolliert.
- [ ] **RC-133 – WordPress- und Woo-Kompatibilitätsmatrix abarbeiten.** Abnahme: Minimum und aktuelle Version, HPOS und unterstützte Checkout-Modi bestehen die festgelegten Releaseprüfungen.
- [ ] **RC-134 – Sicherheitsprüfung durchführen.** Abnahme: Nonce/Capability, SQL, escaping, CSRF/XSS/SSRF, Secret-Speicher, CSV-Injection und REST-Berechtigungen sind geprüft.
- [ ] **RC-135 – Datenschutzprüfung durchführen.** Abnahme: Export/Eraser, Einwilligungsstatus, Opt-out, Retention, Löschereignis und externe Services sind dokumentiert.
- [ ] **RC-136 – Performanceprüfung durchführen.** Abnahme: keine API-Calls im öffentlichen Request, Indexe für Admin-Listen, begrenzte Jobs, Cache- und Assetverhalten sind geprüft.
- [ ] **RC-137 – Barrierefreiheit und Mobile QA durchführen.** Abnahme: Tastatur, Screenreader, Kontrast, reduced motion, Medienbedienung und Floating-Badge sind geprüft.
- [ ] **RC-138 – Übersetzungen und Admintexte abnehmen.** Abnahme: Englisch, Deutsch und Deutsch formal sind vollständig und Platzhalter konsistent.
- [ ] **RC-139 – Migration/Backup/Restore prüfen.** Abnahme: Schemaupgrade, Rollback, Backup-Restore, Reconciliation und Consent-/Token-Verhalten sind dokumentiert.
- [ ] **RC-140 – Releaseunterlagen erstellen.** Abnahme: README, WordPress.org-Readme, Datenschutztext, externe Dienste, Plattformsetup, Troubleshooting, Änderungsprotokoll und Supportmatrix sind vorhanden.
- [ ] **RC-141 – Release Candidate bauen.** Abnahme: Core- und Pro-ZIP sind sauber getrennt, keine Dev-Dateien/Secrets, Produktversionen und Signaturen stimmen.
- [ ] **RC-142 – Release Go/No-Go abnehmen.** Abnahme: alle MVP-Aufgaben fertig, echte Provider-/Lizenzläufe bestanden, keine offenen kritischen Datenschutz-/Security-/Datenverlustprobleme.
- [ ] **RC-143 – Core und Pro veröffentlichen.** Abnahme: Core-Release auf dem vereinbarten Verteilungskanal; Pro-ZIP/Update ist mit gültiger Lizenz abrufbar.
- [ ] **RC-144 – Erstes Supportfenster auswerten.** Abnahme: Installations-/Verbindungsprobleme, häufigste Fehlermeldungen und API-Ausfälle fließen in priorisierte Folgeaufgaben.

## M9 – Pilot, Support und Produktbetrieb

Diese Aufgaben liegen nach dem Release Candidate und vor bzw. unmittelbar nach öffentlicher Veröffentlichung.

- [ ] **OPS-001 – Kunden-Onboarding erstellen.** Ergebnis: Installationshilfe, Erststart-Assistent, Verbindungsschritte und Widget-Veröffentlichung sind als kurze, getestete Anleitung verfügbar.
- [ ] **OPS-002 – Plattform-Setup-Dokumentation veröffentlichen.** Ergebnis: pro Quelle gibt es eine aktuelle Anleitung für Kontozugriff, API-Rechte, Tokens, Profile, Kosten/Limit, Fehlerbehebung und Disconnect.
- [ ] **OPS-003 – Support- und Eskalationsprozess festlegen.** Ergebnis: Zuständigkeit für Pluginfehler, Lizenzfehler und Anbieter-API-Ausfall sowie Antwort-/Eskalationswege sind dokumentiert.
- [ ] **OPS-004 – Betriebs- und Incident-Runbook erstellen.** Ergebnis: Token-Leak, fehlerhafter Sync, Datenlöschanforderung, API-Ausfall, Update-Rollback und möglicher Datenverlust haben klare Schritte.
- [ ] **OPS-005 – API-Änderungsroutine einrichten.** Ergebnis: verantwortliche Person, regelmäßige Prüfung offizieller Änderungsmitteilungen und Prozess zum Aktualisieren/Deaktivieren veralteter Adapter sind benannt.
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

## Kundenspezifische Projekt-Connectoren

Diese Aufgaben ergänzen die offiziellen Captain-Suite-Adapter um eine stabile Erweiterungsmöglichkeit für kundeneigene Buchungs-, CRM-, ERP-, Mitglieder-, Kurs- und Branchensysteme. Lokale PHP-Connectoren gelten nur innerhalb derselben WordPress-Installation; andere Sites/Systeme verwenden signierte REST-/Webhook-Verträge.

### Phase A – Contract und Erweiterbarkeit

- [~] **CON-001 – Connector-Use-Cases und Grenzen festlegen.** Ergebnis: Experience Source, Recipient Resolver, Review Import Source, Request Transport und Context Provider sind getrennte optionale Fähigkeiten; Suite-Adapter, lokale Connectoren und externe Webhooks/REST sind klar abgegrenzt. → Entwurf: [docs/connectors/connector-vertrag.md](docs/connectors/connector-vertrag.md)
- [~] **CON-002 – Versionierten Connector-Hauptvertrag spezifizieren.** Ergebnis: Registry-Hook, Interface-Metadaten (key, label, version, contract version, capabilities), eindeutige Connector-ID, Major-Kompatibilität und Deprecation-Frist sind dokumentiert. Vorschlag für den Hook: wp_review_captain_connectors_v1. → Entwurf: [docs/connectors/connector-vertrag.md](docs/connectors/connector-vertrag.md)
- [~] **CON-003 – Capability-Verträge einzeln definieren.** Ergebnis: ExperienceSource, RecipientResolver, ReviewSource, RequestTransport und ContextProvider haben minimale typisierte Methoden und Fixture-Payloads; ein Connector muss nur seine Fähigkeiten implementieren. → Entwurf: [docs/connectors/connector-vertrag.md](docs/connectors/connector-vertrag.md)
- [~] **CON-004 – Fachliche Event-Payloads vertraglich festlegen.** Ergebnis: Experience-ID, Event-ID, Source-Key, Objekt-Referenz, Recipient-Referenz, Locale, occurred_at, erlaubter Consent-/Versandstatus und Idempotency-Key sind Pflicht; Kontakt- und Reviewtexte werden nur über ausdrücklich erforderliche Fähigkeiten übertragen. → Entwurf: [docs/connectors/connector-vertrag.md](docs/connectors/connector-vertrag.md)
- [~] **CON-005 – Connector-Sicherheits- und Datenmatrix erstellen.** Ergebnis: jede Fähigkeit hat minimale Datenfelder, Capabilities, Consent-/Berechtigungsprüfung, Retention/Erase, Logging-Allowlist, Retry-Klassifikation und Disconnect-Verhalten. Das Framework ist als PHP-Code ausdrücklich keine Sandbox. → Entwurf: [docs/connectors/connector-vertrag.md](docs/connectors/connector-vertrag.md)
- [~] **CON-006 – Lizenzgrenze finalisieren.** Ergebnis: öffentlicher Contract und Basis-Registry bleiben im kostenlosen Core; generischer Pro-Manager und automatisierte Connector-Runtime nutzen review.custom_connectors; offizielle Captain-Adapter nutzen review.suite_integrations. → Entwurf: [docs/connectors/connector-vertrag.md](docs/connectors/connector-vertrag.md)
- [~] **CON-007 – Vertrags- und Kompatibilitätstests vorbereiten.** Ergebnis: Contract-Tests prüfen Pflichtmetadaten, unbekannte Major-Versionen, fehlende Capabilities, ungültige Payloads, Fehlercodes, Idempotenz und wiederholte Events. → Entwurf: [docs/connectors/connector-vertrag.md](docs/connectors/connector-vertrag.md)

### Phase B – Core Registry und Pro-Verwaltung

- [ ] **CON-008 – Core-Registry und Contract-Validator implementieren.** Ergebnis: registrierte Connectoren werden dedupliziert, validiert und ohne Fatal Error bei inkompatibler Version als pausiert ausgewiesen.
- [ ] **CON-009 – Jobrunner-Erweiterung implementieren.** Ergebnis: versionierte Connectorjob-Typen laufen mit atomarem Claim/Lease, Idempotency-Key, Retry/Backoff, Dead-Letter und Hook für Connectorfehler (Vorschlag wp_review_captain_process_job_v1); keine Connector-Jobverarbeitung im Frontend-Request.
- [ ] **CON-010 – Pro-Manager-Seite gestalten und bauen.** Ergebnis: Statuskarten zeigen Entwickler, Version, Fähigkeiten, Contract-Kompatibilität, Aktivstatus, letzte Ausführung, wartende Jobs und sanitisierten Fehler; Test, Pause, Disconnect und Wiederholen sind capability-/nonce-geschützt.
- [ ] **CON-011 – Schema-basierte Connector-Konfiguration bereitstellen.** Ergebnis: Felder sind typisiert und validiert; Secrets werden maskiert/verschlüsselt gespeichert und erscheinen weder in Logs noch Diagnoseexporten; keine frei ausführbaren PHP-Felder.
- [ ] **CON-012 – Dry-Run und Diagnose hinzufügen.** Ergebnis: Test-Fixture zeigt normalisierte Payload, Mapping, Eligibility-/Consent-/Dedupe-Entscheidung und erwartete Aktion, ohne reale E-Mail oder externe Schreibaktion auszulösen.
- [ ] **CON-013 – Error-/Health-Vertrag umsetzen.** Ergebnis: stabile Fehlercodes für Authentifizierung, Rate Limit, Timeout, inkompatiblen Vertrag, ungültige Payload, temporären und permanenten Fehler; Status enthält nächste sinnvolle Admin-Aktion.
- [ ] **CON-014 – Inkompatibilität und Plugin-Lifecycle behandeln.** Ergebnis: Deaktivierung oder Entfernung eines Quell-Plugins pausiert offene Connectorjobs und zeigt source_unavailable; erneute Aktivierung validiert Vertrag, Consent und Fristen vor einem Resume.

### Phase C – Referenz-Connector, Developer Kit und Abnahme

- [ ] **CON-015 – Beispiel-Connector bauen.** Ergebnis: separates Demo-Plugin emittiert ein abgeschlossenes Buchungs-/Kurs-Erlebnis über den öffentlichen Vertrag; es verwendet keine Core-Änderung oder fremden Datenbankzugriffe.
- [ ] **CON-016 – Ende-zu-Ende-Fluss abnehmen.** Ergebnis: abgeschlossenes Erlebnis wird genau einmal normalisiert und geplant; Duplikat, Refund, Widerruf, Opt-out, fehlender Kontakt und nicht erlaubte Kommunikation blockieren korrekt.
- [ ] **CON-017 – Connector-SDK-Skelett veröffentlichungsfähig machen.** Ergebnis: Plugin-Header, Beispielklasse, Capability-Manifeste, Beispielkonfiguration, Job-Handler und lokale Entwicklungsfixtures lassen sich als Ausgangspunkt kopieren.
- [ ] **CON-018 – Developer-Dokumentation veröffentlichen.** Ergebnis: Setup, Contract-Versionierung, Datenmapping, Rechte, Consent, Empfängerresolver, Jobs, Fehlerbehandlung, Tests, Datenschutz und Releasekompatibilität sind mit vollständigen Beispielen beschrieben.
- [ ] **CON-019 – Connector-Release-Gate ergänzen.** Ergebnis: kein Connector gilt als Pro-1.0-fertig, bevor Registry, Contract-Tests, Demo-Connector, Health-/Pause-Verhalten, Datenschutzprüfung und erfolgreiche Dedupe-/Retry-Abnahme bestehen.

**Connector-Meilenstein ist erreicht, wenn:** eine Drittanbieter-Erweiterung ohne Core-Patch installiert werden kann, über den öffentlichen Vertrag ein reales abgeschlossenes Erlebnis meldet und Review Captain Regeln, Deduplizierung, Berechtigungen, Queue und Diagnose vollständig kontrolliert.

## Workflow Captain Provider-Vertrag

- [~] **RC-WF-01 – Öffentlichen Providervertrag v1 mit Schema-Version, stabiler Event-ID, Capability, Entitlement, Payload-Allowlist, Idempotency-Key und Deprecation-Regel dokumentieren.** → Entwurf: [docs/connectors/workflow-captain-vertrag.md](docs/connectors/workflow-captain-vertrag.md)
- [ ] **RC-WF-02 – Post-Commit-Events für Anfrage geplant/übersprungen/gesendet/fehlgeschlagen, Erinnerung, Antwort, Review erstellt/moderiert/veröffentlicht/zurückgezogen und Opt-out bereitstellen; keine Reviewtexte, E-Mail oder Tokens in Logs.**
- [ ] **RC-WF-03 – Services zum Planen/Abbrechen zulässiger Anfragen, Erstellen genehmigter Entwürfe und Lesen datensparsamer Aggregate anbieten.** Workflow darf Einladungsberechtigung, Consent, Opt-out, Suppression, Frequenzlimits, Plattformziel oder Moderation nicht umgehen.
- [ ] **RC-WF-04 – Suite-Trigger und erweiterte Actions an Review-Pro-Recht binden, native Queue als Standard behalten und explizite Ownership-Übergabe unterstützen; parallele Woo/Pass/Voucher/Workflow-Einladungen deduplizieren.**
- [ ] **RC-WF-05 – Review-Admin um Workflow-Verbindung, Contract-Version, aktive Übergabe, Eventannahme, Ablehnungsgrund und Retrystatus ergänzen; fehlendes/inkompatibles Workflow lässt Review Core normal laufen.**
- [ ] **RC-WF-06 – Pass-Check-in und qualifizierte Voucher-Leistung dürfen Anfrage planen; Refund, Absage, No-show, Consent fehlt, Opt-out und Duplikat blockieren.** Form-Entry darf nur explizit gemappten pending/unverified Entwurf übergeben.

**Abnahme Workflow-Vertrag:** Workflow kann Review-Regeln, Moderation und Einwilligung nicht umgehen; höchstens eine Einladung je Experience/Empfänger/Kampagne.


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

- [~] **SET-001 – Einstellungsinventar aus Konzept und Plugin-Flows ableiten.** Ergebnis: jede existierende/geplante Option ist einer Admin-Seite und einem Geltungsbereich zugeordnet; Einladungsziel, Quelle, Kampagne oder Widget hat genau einen fachlichen Besitzer. → Entwurf: [docs/settings/einstellungsinventar.md](docs/settings/einstellungsinventar.md)
- [~] **SET-002 – Einstellungsbaum und Tab-Reihenfolge finalisieren.** Ergebnis: Allgemein, Quellen & Synchronisierung, Anfragen & Zustellung, Einreichungen & Moderation, Darstellung, Datenschutz & Aufbewahrung, Zugriff & Rollen und Erweitert sind wireframed; Diagnose bleibt read-only separat. → Entwurf: [docs/settings/einstellungsvertrag.md](docs/settings/einstellungsvertrag.md)
- [~] **SET-003 – Optionsschema und Migration definieren.** Ergebnis: Optionsschlüssel, Typ, Validierung, Default, Site-/Network-Scope, Datenschutzklassifikation, Entitlement, UI-Geltungsbereich und Versionsmigration sind dokumentiert. → Entwurf: [docs/settings/einstellungsvertrag.md](docs/settings/einstellungsvertrag.md)
- [~] **SET-004 – Inheritance und Overrides spezifizieren.** Ergebnis: globaler Standard → Profil/Kampagne/Formular/Widget ist eindeutig; ungültige Overrides fallen kontrolliert zurück und zeigen, welcher Wert wirksam ist. → Entwurf: [docs/settings/einstellungsvertrag.md](docs/settings/einstellungsvertrag.md)
- [~] **SET-005 – Safe Defaults und Reset-Semantik festlegen.** Ergebnis: fehlende Consent-/Kontaktberechtigung blockiert Versand, Erinnerungen sind aus, externe Skripte sind aus, neue Reviews warten auf Moderation; Reset löscht keine Reviewdaten und Datenlöschung verlangt einen getrennten, bestätigten Weg. → Entwurf: [docs/settings/einstellungsvertrag.md](docs/settings/einstellungsvertrag.md)
- [~] **SET-006 – Einstellungs- und Pro-Rechte-Matrix erstellen.** Ergebnis: pro Feld ist festgelegt, ob Core oder Pro es besitzt; kein Free-Feld wird nach Lizenzablauf verändert oder geleert. → Entwurf: [docs/settings/einstellungsvertrag.md](docs/settings/einstellungsvertrag.md)

#### Phase B – WP-Admin UX und Umsetzung

- [ ] **SET-007 – Einstellungsseite mit Tabs und Abschnittsnavigation bauen.** Ergebnis: konsistente WordPress-native Formulare, Beschreibungen, Save-Status, Feldvalidierung und mobile Adminansicht; keine Optionswand auf einer langen Seite.
- [ ] **SET-008 – Setup-Assistent und Erststart-Checkliste bauen.** Ergebnis: Marke/Locale, Verbindung, Test, Anfragepolitik und erstes Widget können einzeln erledigt, später fortgesetzt oder übersprungen werden; keine externe Verbindung wird automatisch aktiviert.
- [ ] **SET-009 – Progressive Disclosure und Kontextfelder implementieren.** Ergebnis: Provider-/Versandweg-spezifische Felder erscheinen erst bei Bedarf; Erweitert-Optionen sind eingeklappt, Pro-Sperren und Abhängigkeiten verständlich.
- [ ] **SET-010 – Allgemeine Website-Defaults implementieren.** Ergebnis: Standardsprache, Zeitzone, Marken-/Absenderidentität und Standardanzeige sind zentral definiert und in Vorschauen erkennbar.
- [ ] **SET-011 – Quellen- und Sync-Defaults umsetzen.** Ergebnis: globale Jobfenster/-limits, Quota- und Fehlerpause sind einstellbar; individuelle Credentials, Profile und Anbieterscopes bleiben ausschließlich auf der Quellen-Seite.
- [ ] **SET-012 – Globale Anfrage- und Versandrichtlinien umsetzen.** Ergebnis: Absender/Testmail, Versandzeitfenster, globale Frequenzgrenze, Erinnerungsstandard, Suppression/Opt-out und Bounce-/Fehlerstatus sind vorhanden; Kampagnenwerte werden an der Kampagne gepflegt.
- [ ] **SET-013 – Einreichungs- und Moderationsdefaults umsetzen.** Ergebnis: neue lokale Reviews sind standardmäßig moderiert; Spam- und Uploadschutz sowie Formular-Standardfelder lassen sich zentral konfigurieren.
- [ ] **SET-014 – globale Darstellungsdefaults umsetzen.** Ergebnis: Sterne, Attribution, Datum, Avatar/Autor, leere Zustände und Barrierefreiheitspräferenzen bilden die Voreinstellung neuer Widgets; externe Quellenangabe kann nicht entfernt werden.
- [ ] **SET-015 – Datenschutz- und Aufbewahrungsseite umsetzen.** Ergebnis: Fristen für Snapshots, Anfragen, Logs und Medien sind getrennt; Export, Eraser, Disconnect, Settings-Reset und vollständige Datenlöschung haben klare getrennte Folgen.
- [ ] **SET-016 – Rollen- und Multisite-Einstellungen umsetzen.** Ergebnis: Verbindung, Moderation, Anfrageversand, Datenschutz und Einstellungen sind getrennt berechtigt; Netzwerk- vs. Site-Einstellungen und Datenisolation sind klar.
- [ ] **SET-017 – Erweitert- und Diagnosebereiche implementieren.** Ergebnis: Cron-/Queuezustand, Jobbudget, begrenzte Debugfunktion und Konfigurationsbackup sind zugänglich; Diagnoseexport enthält keine Secrets oder unnötige PII.
- [ ] **SET-018 – Einstellungsimport/-export absichern.** Ergebnis: Schema-Version, Preview, Validierung und Rollback sind vorhanden; Secrets und personenbezogene Daten werden nicht exportiert, Credentials müssen nach Import erneut verbunden werden.
- [ ] **SET-019 – Änderungshistorie und Audit-Semantik festlegen.** Ergebnis: sicherheits- und versandrelevante Änderungen (z. B. Absender, Frequenz, Berechtigung, Aufbewahrung) sind mit Nutzer/Zeitpunkt nachvollziehbar, ohne Secrets oder Reviewtext zu protokollieren.

#### Phase C – Validierung und Release

- [ ] **SET-020 – Einstellungs-Usability testen.** Ergebnis: Betreiber finden Quellenverbindung, Kampagnenpolitik, Datenschutzfrist und Rollen ohne Support; überflüssige oder doppelte Felder werden entfernt.
- [ ] **SET-021 – Rollen-/Nonce-/Multisite-Sicherheit abnehmen.** Ergebnis: unberechtigte Nutzer können keine Einstellungen lesen/ändern; Site-Konfiguration bleibt in Multisite korrekt isoliert.
- [ ] **SET-022 – Defaults und Migration regressionsprüfen.** Ergebnis: Neuinstallation, Upgrade, fehlende Optionen, Import, Lizenzablauf und Wiederherstellung behalten dokumentierte Defaults und verändern keine Core-Daten unerwartet.
- [ ] **SET-023 – Einstellungsdokumentation erstellen.** Ergebnis: jede Einstellung erklärt Zweck, Scope, Standard, Folgen, Datenschutzbezug und erforderliche Anbieter-/Lizenzvoraussetzungen.
- [ ] **SET-024 – Einstellungs-Release-Gate ergänzen.** Ergebnis: keine 1.0-Einstellung bleibt ohne Default, Validierung, Rechteprüfung, Migration, Export-/Löschregel und verständlichen Hilfetext.

## Yoast SEO-Kompatibilität und Aufgaben

### Phase A – Kompatibilität und Regeln

- [~] **SEO-001 – Yoast-/SEO-Plugin-Kompatibilitätsmatrix festlegen.** Ergebnis: Mindestversion für Yoast SEO, Verhalten mit Free/Premium, WooCommerce-/Yoast-Erweiterungen, Gutenberg, Caching/Minifizierung und konkurrierenden SEO-/Schema-Plugins sind festgelegt. → Entwurf: [docs/seo/seo-regeln.md](docs/seo/seo-regeln.md)
- [~] **SEO-002 – SEO-Ausgabebesitz dokumentieren.** Ergebnis: Yoast besitzt Title, Description, Canonical, Robots, Open Graph, Sitemap und Basisschema; Review Captain gibt keine konkurrierenden Head-Tags aus. → Entwurf: [docs/seo/seo-regeln.md](docs/seo/seo-regeln.md)
- [~] **SEO-003 – Schema-Eignungsmatrix erstellen.** Ergebnis: externe Reviews, lokale Unternehmensreviews, eigene Produktreviews, Kurs-/Eventreviews und gemischte Widgets sind anhand aktueller Google-Regeln freigegeben oder ausgeschlossen. → Entwurf: [docs/seo/seo-regeln.md](docs/seo/seo-regeln.md)
- [~] **SEO-004 – URL-/Indexierungsregeln spezifizieren.** Ergebnis: CPT/Custom-Table-Entscheidung, Indexierbarkeit, Archive, Filter, Parameter, Pagination, Canonical und Sitemap-Verhalten sind entschieden. → Entwurf: [docs/seo/seo-regeln.md](docs/seo/seo-regeln.md)

### Phase B – Yoast-Integration

- [ ] **SEO-005 – Yoast-Erkennung und Versionsprüfung umsetzen.** Ergebnis: Yoast wird ohne direkte Klassen-/Tabellenabhängigkeit erkannt; inkompatible oder fehlende Version fällt sicher auf sichtbare Widgets ohne Schema zurück.
- [ ] **SEO-006 – Offizielle Yoast-Schema-API anbinden.** Ergebnis: bedingter Schema-Graph-Piece wird registriert, mit WebPage/Canonical verbunden und nutzt vorhandene @id-Knoten statt Duplikate zu erzeugen.
- [ ] **SEO-007 – Schema nur bei sichtbarem Block-/Widgetinhalt ausgeben.** Ergebnis: serverseitig gerenderte Review-Blöcke und Shortcodes steuern die Ausgabe; leere oder unsichtbare Widgets erzeugen kein Review-Markup.
- [ ] **SEO-008 – WooCommerce-Produkt-Schema konfliktfrei integrieren.** Ergebnis: vorhandene Yoast-/WooCommerce-Product-Knoten werden erkannt; es entsteht kein zweites Product- oder AggregateRating-Objekt.
- [ ] **SEO-009 – Unzulässige Quellen technisch sperren.** Ergebnis: importierte Fremdplattformreviews, eigene Organization-/LocalBusiness-Ratings und plattformübergreifende Gesamtscores gelangen nie in Review-Schema.
- [ ] **SEO-010 – Schema-Fallback und Plugin-Konflikte implementieren.** Ergebnis: ohne Yoast wird nur dann eigenes JSON-LD gerendert, wenn kein konkurrierender Schema-Besitzer aktiv ist und die Eignungsprüfung bestanden ist.
- [ ] **SEO-011 – SEO-Metadaten unangetastet lassen.** Ergebnis: keine Review-Captain-Ausgabe dupliziert oder überschreibt Yoast-Titel, Beschreibung, Robots, Canonical, Open Graph oder Indexables.

### Phase C – Admin, Indexierung und Abnahme

- [ ] **SEO-012 – SEO-&-Yoast-Statusseite bauen.** Ergebnis: Version, Graph-Integration, erkannte Konflikte, blockierte Markups mit Grund und Hilfelinks sind verständlich sichtbar.
- [ ] **SEO-013 – Widget- und Schema-Hinweise ergänzen.** Ergebnis: externe Reviews, Produktreviews und Unternehmensreviews erklären ihre Schema-Berechtigung ohne Rich-Result-Versprechen.
- [ ] **SEO-014 – Noindex-, Sitemap- und Canonical-Regeln umsetzen.** Ergebnis: interne Reviewarchive, Filter-/Sortierparameter, Vorschau, Admin, Connectoren und AJAX-Routen sind nicht indexierbar; Nutzerseiten behalten Yoast-Kontrolle.
- [ ] **SEO-015 – Crawlbare Server-Rendering-Ausgabe sicherstellen.** Ergebnis: sichtbare Reviewtexte, Quellen und Ratings sind ohne Client-JavaScript lesbar; externe Ressourcen bleiben consent-gesteuert.
- [ ] **SEO-016 – Strukturierte Daten und Yoast-Konflikte abnehmen.** Ergebnis: Szenarien decken Review Captain allein, Yoast SEO, WooCommerce-Produkt, Fremdreviews, lokale Unternehmensreviews, zusätzliche Schema-Plugins und noindex-Seiten ab.
- [ ] **SEO-017 – SEO-Dokumentation und Release-Checkliste veröffentlichen.** Ergebnis: Markupgrenzen, Yoast-Konfiguration, Rich-Results-Erwartung und Fehlerdiagnose sind dokumentiert und vor Release verifiziert.

## Änderungsverlauf

- 2026-11-15: Erste ausführbare Aufgabenliste aus dem Review-Captain-Konzept erstellt.
- 2026-11-16: Nacharbeit der Wettbewerber-Anonymisierung (Konzept §3, RC-046), einheitliches ID-Format für RC-WF, Statuskennzeichnung `[~]` eingeführt; M0-Entwürfe RC-001 bis RC-016 unter [docs/m0](docs/m0/README.md) angelegt.
- 2026-11-16: Entwürfe für M0.5 (UX-002–005, UX-007–013), M1 (RC-017–031), M1.5 (TEC-001–015, Spezifikation), Connector-Vertrag (CON-001–007), Einstellungsvertrag (SET-001–006), SEO-Regeln (SEO-001–004) und Workflow-Vertrag (RC-WF-01) ergänzt. Implementierungs-/Messanteile von TEC-003, TEC-008, TEC-013, TEC-014 folgen mit dem Core-Code.
