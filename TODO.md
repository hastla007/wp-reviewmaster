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

- [ ] **RC-001 – Produktnamen und Slugs bestätigen.** Ergebnis: verbindliche Namen Review Captain, review-captain und review-captain-pro in Core, Add-on, Textdomain und Build-Dokumentation.
- [ ] **RC-002 – 1.0-Scope festschreiben.** Ergebnis: eine Liste „MVP muss enthalten“ und eine Liste „nach 1.0“; insbesondere festlegen, welche der fünf Plattformen in 1.0 nativ synchronisieren und welche zunächst offiziellen Embed-/Profil-Link-Fallback bieten.
- [ ] **RC-003 – Free/Pro-Grenze einfrieren.** Ergebnis: jede Funktion aus Abschnitt 5 des Konzepts ist Core, Pro 1.0, Pro-Folgeversion oder ausgeschlossen zugeordnet.
- [ ] **RC-004 – Pro-Planmodell bestätigen.** Ergebnis: solo (1), studio (5) und agency (25 Live-Websites), Supportstufen und Produktpreise sind als Shopentscheidung dokumentiert.
- [ ] **RC-005 – Lizenz-Entitlements finalisieren.** Ergebnis: Liste der tatsächlich gebauten review.*-Rechte, jeweils genau einer Pro-Funktion zugeordnet; keine leeren oder nur geplanten Rechte im Katalog.
- [ ] **RC-006 – Kompatibilitätsziele festlegen.** Ergebnis: Mindestversionen WordPress, PHP und optional WooCommerce sowie Zielmatrix für HPOS, Classic/Blocks, Multisite und Page Builder.
- [ ] **RC-007 – Supportumfang und Releasekanäle definieren.** Ergebnis: WordPress.org-Veröffentlichung für Core, Update-/Vertriebskanal für Pro, Supportsprachen, Supportumfang und Reaktionsziele.

### Plattform- und Rechtsfreigaben

- [ ] **RC-008 – Provider-Matrix anlegen.** Für Google, Trustpilot, Trusted Shops/eTrusted, App Store Connect und Google Play je Anbieter dokumentieren: Kontotyp/Tarif, API-Freigabe, Authentifizierung, lesbare Felder, Einladungs-/Antwortfunktionen, Limits, Attribution, Cache- und Löschpflicht, Kosten und Fallback.
- [ ] **RC-009 – Google-Zugang praktisch freischalten.** Ergebnis: registriertes Google-Cloud-Projekt, aktivierte Business-Profile-API, OAuth-Zustimmungsbildschirm, erforderliche API-Freigabe und Test-Standort.
- [ ] **RC-010 – Trustpilot-Zugang prüfen.** Ergebnis: dokumentierte API-/Partnerberechtigung, Test-Business-Unit, verwendbare Review- und Invitation-Endpunkte sowie Freigabe des konkreten Plugin-Use-Cases.
- [ ] **RC-011 – Trusted-Shops/eTrusted-Zugang prüfen.** Ergebnis: API-Client und Testkanal eines berechtigten Shops; lesende Review-, Einladung- und Widget-Funktionen sind voneinander abgegrenzt.
- [ ] **RC-012 – App-Store-Connect-Testzugang vorbereiten.** Ergebnis: Test-App, Team-/App-Ressourcen-ID, API-Key-Verfahren und minimale Rollen dokumentiert.
- [ ] **RC-013 – Google-Play-Testzugang vorbereiten.** Ergebnis: Test-App/Paket, Play-Console-Zugriff, Service-Account/OAuth-Verfahren und minimaler Scope dokumentiert.
- [ ] **RC-014 – Datenschutz-/Kommunikationsprüfung abschließen.** Ergebnis: Datenfluss und Versandgrundlage je Zielmarkt fachlich geprüft; Consent, Opt-out, Aufbewahrung und Löschverantwortung stehen fest.
- [ ] **RC-015 – Medienrechte prüfen.** Ergebnis: Einwilligungs-/Nutzungsrechtstext und Ablauf für Name, Bild, Stimme, Video, Untertitel, Widerruf und Veröffentlichung sind dokumentiert.
- [ ] **RC-016 – Marken- und Trustmark-Regeln prüfen.** Ergebnis: Vorgaben zur Nutzung jedes Plattformlogos, Trustbadges, Awards und Zertifikatsassets sind in einer Checkliste erfasst.

**Meilenstein M0 ist erreicht, wenn:** 1.0-Scope, Kompatibilitätsziele, Lizenzmodell und die tatsächlichen Anbieterzugänge dokumentiert sind. Nicht verfügbare APIs werden sichtbar als Fallback geplant, nicht als zugesagte Synchronisierung verkauft.

## M0.5 – Nutzerabläufe und Bildschirmdesign

Diese Aufgaben werden nach dem MVP-Scope-Freeze und vor dem Bau der Admin-Seiten erledigt. Technische Verträge können parallel weiterlaufen.

- [ ] **UX-001 – Zielnutzer und Nutzungsszenarien validieren.** Ergebnis: Gespräche mit Shopbetreibern, lokalen Unternehmen, App-Anbietern und Captain-Suite-Nutzern bestätigen die wichtigsten Abläufe.
- [ ] **UX-002 – Nutzerabläufe skizzieren.** Ergebnis: Erstinstallation, Review erfassen, Plattform verbinden, Syncfehler beheben, Widget veröffentlichen, Anfrage konfigurieren und Pro-Lizenz verwalten sind als Flows beschrieben.
- [ ] **UX-003 – Admin-Wireframes erstellen.** Ergebnis: Dashboard, Reviews, Quellen, Profile, Anfragen, Widgets, Testimonials/Trustmarks, Integrationen, Einstellungen, Diagnose und Lizenzseite haben abgestimmte Wireframes.
- [ ] **UX-004 – Fehler- und Leerzustände gestalten.** Ergebnis: keine Quelle verbunden, unberechtigter API-Zugang, abgelaufener Token, fehlende Reviews, Lizenz ohne Zugriff und Queuefehler haben verständliche Zustände und konkrete nächste Schritte.
- [ ] **UX-005 – Frontend-Widget-Prototypen erstellen.** Ergebnis: Trust-Leiste, Plattform-Scorekarten, Testimonial-Wall, Video-/Audio-Carousel, Auszeichnungsblock und schwebendes Badge sind auf Desktop und Mobil ausgearbeitet.
- [ ] **UX-006 – Prototypen mit Nutzern prüfen.** Ergebnis: Testpersonen schließen „Quelle verbinden“ und „Bewertungsleiste veröffentlichen“ ohne Moderationshilfe ab; beobachtete Blocker sind behoben.
- [ ] **UX-007 – Designsystem und Editor-Handoff festlegen.** Ergebnis: Typografie, Farben, Abstände, Sterne, Quellenbadges, Fokuszustände, reduced-motion und Block-Steuerungen sind dokumentiert.
- [ ] **UX-008 – Usability-Kennzahlen definieren.** Ergebnis: Zeit bis zur ersten Verbindung, Zeit bis zum ersten Widget und Einrichtungsabbrüche sind ohne unnötige personenbezogene Telemetrie messbar oder bewusst nicht erfasst.

## M1 – Verträge, Datenmodell und Architektur

- [ ] **RC-017 – System-of-Record-Regeln dokumentieren.** Ergebnis: WooCommerce besitzt Woo-Produktreviews; Review Captain besitzt lokale allgemeine Reviews, externe Review-Snapshots, Widgets, Testimonials und Anfragen.
- [ ] **RC-018 – Normalisiertes Review-Schema finalisieren.** Ergebnis: Feldliste, Skalen, Sprache, Profil/Quelle, stabile externe ID, Kontext, Cachezeit, Tombstone/Löschung und Sichtbarkeitsstatus sind dokumentiert.
- [ ] **RC-019 – Testimonial-Schema finalisieren.** Ergebnis: Text/Bild/Video/Audio-Typen, WordPress-Media-Anhang, Transkript/Untertitel, Kontext, Rechtebeleg, Ablauf, Widerruf und Moderationsstatus sind beschrieben.
- [ ] **RC-020 – Provider-Vertrag v1 definieren.** Ergebnis: connect, test, list profiles, sync page/cursor, normalize, delete/tombstone, quota/status und capability discovery sind versioniert und mit Fixture-Beispielen beschrieben.
- [ ] **RC-021 – Ereignisvertrag für Reviews v1 definieren.** Ergebnis: Payload für source, event_id, experience_id, event_type, occurred_at, object reference, recipient reference, locale und Versandberechtigung ist formal festgelegt.
- [ ] **RC-022 – Experience-ID-Ownership je Quelle bestätigen.** Ergebnis: WooCommerce-Kauf, Voucher-Einlösung, Pass-Teilnahme und Form-Captain-Einsendung haben jeweils genau einen ID-Verantwortlichen und dokumentierte Dedupe-Regeln.
- [ ] **RC-023 – Kontaktresolver-Vertrag definieren.** Ergebnis: Empfängerreferenzen können nur zur Versandzeit und mit gültiger Berechtigung in Kontaktangaben aufgelöst werden; unbekannt/widerrufen blockiert Versand.
- [ ] **RC-024 – Anfrage-Lifecycle und Zustände festlegen.** Ergebnis: planned, eligible, sent, delivered/unknown, clicked (nur falls erlaubt), skipped, cancelled, failed, unsubscribed und expired mit Übergängen dokumentiert.
- [ ] **RC-025 – Einladungsregel technisch spezifizieren.** Ergebnis: Unique-Key experience_id + recipient_ref + campaign_id, maximal eine Einladung je Erfahrung/Empfänger und kein zweiter Versand über konkurrierende Suite-Ereignisse.
- [ ] **RC-026 – Authentifizierungsmodell je Provider festlegen.** Ergebnis: Captain OAuth-App vs. Betreibercredential, Scope, Rotation, Token-Widerruf und Verschlüsselungsweg pro Anbieter entschieden.
- [ ] **RC-027 – Datenaufbewahrungs- und Löschmatrix festlegen.** Ergebnis: je Quelle Speicherfrist, Refreshintervall, Lösch-/Widerrufspfad, Export, Backup und Entfernung beim Disconnect dokumentiert.
- [ ] **RC-028 – Datenbank- und Migrationsplan abnehmen.** Ergebnis: Tabellen/Optionen, Indizes, Versionsnummer, Upgrade-/Rollback-Verhalten und Multisite-Scope sind spezifiziert.
- [ ] **RC-029 – Rollen- und Capability-Modell festlegen.** Ergebnis: getrennte Rechte für Reviews moderieren, Quellen verbinden, Anfragen verwalten, Reports sehen und Trustmarks verwalten.
- [ ] **RC-030 – Fehlercodes und Integrationsstatus standardisieren.** Ergebnis: inkompatibler Vertrag, Auth-Fehler, Quota, Timeout, entfernte Review, Retry und endgültiger Fehler erhalten dokumentierte Statuscodes.
- [ ] **RC-031 – Architektur-Entscheidungsprotokoll einrichten.** Ergebnis: wesentliche Änderungen an Schema, Verträgen, Lizenzrechten und Anbieterzugängen werden mit Datum/Grund versioniert.

**Meilenstein M1 ist erreicht, wenn:** Core und alle Adapter dieselben Datenverträge, Lifecycle-Regeln und Dedupe-Schlüssel verwenden.

## M1.5 – Technische Betriebs- und Sicherheitsgrundlagen

Diese Infrastrukturentscheidungen müssen vor den davon abhängigen Core-/Pro-Implementierungen abgenommen werden. Allgemeine Aussagen wie „Retry“ oder „verschlüsseln“ reichen für den Produktionsbetrieb nicht aus.

- [ ] **TEC-001 – Job-Runner-Vertrag festlegen.** Ergebnis: definiert ist, wann Action Scheduler genutzt wird, wie der Core ohne WooCommerce Jobs ausführt und wie WP-Cron, echter System-Cron und WP-CLI zusammenspielen.
- [ ] **TEC-002 – Produktions-Cron und Verzögerung dokumentieren.** Ergebnis: zeitkritische Syncs/Review-Einladungen funktionieren mit empfohlenem System-Cron; Admin zeigt fällige/überfällige Jobs und keine angeblich präzisen Zeiten bei unzuverlässigem WP-Cron.
- [ ] **TEC-003 – Job-Claiming und Lease implementieren.** Ergebnis: ein Job hat atomaren Besitzer, Ablaufzeit und Recovery nach Prozessabbruch; zwei Worker können denselben Job nicht gleichzeitig ausführen.
- [ ] **TEC-004 – Idempotenz in der Datenbank erzwingen.** Ergebnis: Unique Constraints für externe Review-IDs, Experience-/Recipient-/Campaign-Schlüssel und Provider-Event-IDs verhindern Duplikate auch bei parallelen Hooks und Retry.
- [ ] **TEC-005 – Race-Conditions spezifizieren und abnehmen.** Ergebnis: Tests decken gleichzeitig eintreffende Einlösung/Check-in, Refund vs. Einladungsversand, Tokenrefresh und zwei Sync-Läufe derselben Quelle ab.
- [ ] **TEC-006 – Retry- und Dead-letter-Politik pro Fehlerklasse festlegen.** Ergebnis: temporär, permanent, Rate-Limit, Authentifizierungsfehler und Datenfehler haben getrennte Retry-/Pause-/Adminaktionsregeln; Retry-After wird beachtet.
- [ ] **TEC-007 – API-Budgets und Parallelitätslimits implementieren.** Ergebnis: pro Quelle/Profile sind Seitenlimit, tägliches/zeitliches Budget, Backoff und Admin-Warnschwelle einstellbar; ein Sync kann Website oder Anbieterquote nicht ungebremst belasten.
- [ ] **TEC-008 – Tokenverschlüsselung und Schlüsselrotation festlegen.** Ergebnis: Schlüsselquelle, Algorithmus/Nonce-Handling, Rotation, Backup/Wiederherstellung und Verhalten bei fehlendem/ungültigem Schlüssel sind dokumentiert und implementiert; Tokens erscheinen nie in Logs/Exporten.
- [ ] **TEC-009 – Multisite- und Site-Isolation absichern.** Ergebnis: Tabellen, Optionen, OAuth-Verbindungen, Warteschlangen, Lizenzstatus und Datenexport sind je Site korrekt isoliert; Netzwerkdeaktivierung/Wiederaktivierung ist definiert.
- [ ] **TEC-010 – REST-/OAuth-Grenzen härten.** Ergebnis: jede Route hat eine explizite permission_callback; Callback prüft State/Nonce, Redirect-URI und Berechtigung; Wiederholung/Replay und unautorisierte Profilwahl werden abgewehrt.
- [ ] **TEC-011 – Cross-site-Schnittstellen explizit begrenzen.** Ergebnis: lokale Suite-Adapter verwenden PHP-Verträge; falls später REST/Webhooks nötig werden, sind Signatur, Timestamp, Replay-Schutz, Scope und Schlüsselrotation Voraussetzung.
- [ ] **TEC-012 – Cache- und CDN-Verhalten spezifizieren.** Ergebnis: Provider-TTL, Pflichtrefresh, Tombstone/Löschung, Seiten-/Objektcache und CDN-Invalidierung sind pro Quelle abgestimmt; personenbezogene Anfrage-/Lizenzdaten werden nie öffentlich gecacht.
- [ ] **TEC-013 – Frontend-Embed-Sicherheitsmodell umsetzen.** Ergebnis: feste Host-Allowlist, SSRF-Schutz, consent-basiertes Laden externer Player/Embeds und sichere Linkattribute verhindern beliebige serverseitige Abrufe und ungefragte Drittanbieterrequests.
- [ ] **TEC-014 – Skalierungsgrenzen und Listenqueries messen.** Ergebnis: Testdaten mit mindestens 100.000 Reviews und 10.000 wartenden/abgeschlossenen Jobs zeigen indizierte Adminsuche, paginierte Exporte und begrenzte Joblaufzeit.
- [ ] **TEC-015 – Upgrade-/Restore-Reconciliation härten.** Ergebnis: nach Schemaupgrade oder Backup-Restore werden Cursor, externe IDs, Jobleases und widerrufene Tokens geprüft; Wiederherstellung erzeugt keine doppelten Einladungen oder alten API-Schreibaktionen.

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
- [ ] **RC-046 – Migration aus WooCommerce, CusRev und Site Reviews spezifizieren.** Ergebnis: Feldmapping, Statusübernahme, Attachment-Behandlung und Grenzen werden in einer Migrationsanleitung erklärt.
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

## Änderungsverlauf

- 2026-11-15: Erste ausführbare Aufgabenliste aus dem Review-Captain-Konzept erstellt.
