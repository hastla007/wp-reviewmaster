# RC-002 – 1.0-Scope

**Status:** Entwurf – Freigabe offen  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §4, §5, §13, §15](../../konzept.md), [TODO.md M2–M7](../../TODO.md)

## Gefundener Widerspruch im Konzept

- §13 (MVP-Empfehlung) verschiebt Trustpilot, Trusted Shops und WooCommerce-Anfrageautomationen nach Pro 1.x.
- §15 Nr. 1 empfiehlt dagegen, dass Pro 1.0 **alle fünf Plattformen** mit einem dokumentierten offiziellen Weg unterstützt (Sync, Widget/Embed oder Profil-Link).
- §15 Nr. 3 priorisiert für 1.0 Migration, Verifizierungslabel und mehrsprachige/gebündelte Anfragen – setzt also eine Anfrage-Engine in 1.0 voraus.

Dieser Entwurf löst den Widerspruch so auf: **alle fünf Plattformen sind in 1.0 sichtbar unterstützt, aber nur drei synchronisieren nativ**; die Anfrage-Engine kommt mit dem WooCommerce-Auslöser in 1.0, die übrigen Suite-Adapter in 1.1.

## Plattformen in 1.0

| Plattform | 1.0-Weg | Begründung | Hochstufung auf nativen Sync |
|---|---|---|---|
| Google Business Profile | **Nativer Sync (Pro)** über OAuth für eigene, verifizierte Standorte | Zugang ist an das eigene Konto gebunden, kein Partnertarif nötig; API-Freischaltung durch Google ist aber Voraussetzung (RC-009). | – |
| Apple App Store | **Nativer Sync (Pro)**, read-only, eigene Apps | Zugang über App-Store-Connect-API-Key des Betreibers, kein Zusatztarif. | – |
| Google Play | **Nativer Sync (Pro)**, read-only, eigene Apps | Zugang über Play Console des Betreibers; Einschränkung der API auf aktuelle Reviews beachten (siehe RC-008). | – |
| Trustpilot | **Offizielles Widget/Profil-Link (Core + Pro)**; manuelle Kuratierung im Core | API-Recht hängt von Tarif/Partnerfreigabe ab (RC-010). | 1.x, sobald Test-Business-Unit mit API-Recht vorliegt und RC-010 abgenommen ist. |
| Trusted Shops / eTrusted | **Offizielles Widget/Trustbadge/Profil-Link (Core + Pro)**; manuelle Kuratierung im Core | API-Client nur für berechtigte Händler (RC-011). | 1.x, sobald berechtigter Testkanal vorliegt und RC-011 abgenommen ist. |

Fällt die Google-API-Freischaltung bis zum Feature-Freeze aus, wird Google in 1.0 ebenfalls als Profil-Link/manuelle Kuratierung ausgeliefert. Kein Verkaufstext verspricht nativen Sync, bevor RC-132 für die Plattform bestanden ist.

## MVP muss enthalten

### Core 1.0 (kostenlos)

- Plugin-Grundlage, Schema, Rollen/Capabilities, Audit, Übersetzungen (RC-032 – RC-037).
- Lokale Reviews: Adminliste, Einreichungsformular mit Spam-Schutz, Moderation, Verifizierungsstatus, Trennung von WooCommerce-Reviews, manuelle externe Reviews (RC-038 – RC-043).
- CSV-Export mit Formelschutz (RC-044) – siehe Grenzziehung in RC-003.
- CSV-Import mit Prüflauf und Migrationsanleitung für WooCommerce und verbreitete lokale Review-Plugins (RC-045, RC-046).
- Privacy Exporter/Eraser, Aufbewahrung, Deinstallation (RC-047, RC-048).
- SSR-Review-Card, Gutenberg-Block, Shortcode, Grid/List, Text-Testimonials, Profil-CTA, begrenzte Schema-Ausgabe (RC-049 – RC-055).
- Öffentlicher Provider- und Connector-Vertrag sowie Basis-Registry (RC-020, CON-002, CON-008).
- Einstellungsseite, Setup-Assistent und Diagnose im Core-Umfang (SET-007 – SET-017, soweit Core).
- Yoast-Kompatibilität ohne konkurrierende Head-/Schema-Ausgabe (SEO-001 – SEO-011, Core-Anteil).

### Pro 1.0

- Lizenz-/Updater-Integration (M3).
- Native Adapter Google Business Profile, App Store Connect, Google Play inkl. Sync-Diagnose, Fehler-/Quota-Verhalten, Fixture-Suite (RC-065 – RC-068, RC-078 – RC-084).
- Offizielle Widget-/Embed-Fallbacks Trustpilot und Trusted Shops mit Consent-Steuerung (RC-073, RC-077).
- Mehrere Profile/Apps pro Website.
- Anfrage-Engine: Kampagnen, Queue, Dedupe, Resolver, eigener Mailtransport, Opt-out, Vorschau, Lokalisierung (RC-085 – RC-094; Erinnerungen RC-092 standardmäßig aus).
- WooCommerce-Adapter inkl. Mehrprodukt-Anfrage und Refund-Ausschluss (RC-095 – RC-097).
- Pro-Widgets: Carousel, Badge/Score, Plattform-Bewertungsleiste mit transparenter Summe (RC-112 – RC-114, RC-118).

## Nach 1.0

| Version | Inhalt | Bedingung |
|---|---|---|
| Pro 1.1 | Suite-Adapter Voucher, Pass, Affiliate, Form Captain (RC-098 – RC-106), Workflow-Captain-Vertrag (RC-WF-01 – 06), Connector-Pro-Manager (CON-009 – CON-019) | Verträge der Quellplugins stabil und abgenommen (RC-022). |
| Pro 1.1/1.2 | Trustpilot- und eTrusted-Sync, ggf. Einladungs-APIs (RC-070 – RC-076) | Anbieterzugang bestätigt (RC-010, RC-011). |
| Pro 1.2 | Testimonial-Studio mit Bild/Video/Audio, Trustmarks/Awards, Floating Badge, Testimonial-Wall (RC-107 – RC-111, RC-115 – RC-117) | Medienrechte-Ablauf (RC-015) und Markenregeln (RC-016) freigegeben. |
| später | M7-Funktionen (Foto-Reviews, Q&A, Tags/Melden, Autorenkorrektur, WhatsApp/QR, Shopping-Feed, weitere Plattformen), Antwortfunktionen, Analytics-Ausbau | Nachfrage, API-Rechte, gesonderte Produktentscheidung. |

Ausdrücklich nicht in 1.0: Places-API-Fallback (RC-069, Entscheidung „streichen“ empfohlen, siehe RC-008), Antworten auf Plattform-Reviews, KI-/Sentiment-Funktionen, plattformübergreifender Gesamtscore (standardmäßig aus, RC-114).

## Offen / Freigabe

- Produktverantwortung bestätigt die Plattformzuordnung und die Verschiebung der Suite-Adapter auf 1.1.
- Alternative bei Bedarf: Suite-Adapter in 1.0, dafür WooCommerce-Mehrprodukt-Anfrage auf 1.1 – verlängert den 1.0-Pfad wegen der Abhängigkeit von Vertragsänderungen in Voucher/Pass Captain.
- Konzept §13 wird nach Freigabe an diese Tabelle angepasst.
