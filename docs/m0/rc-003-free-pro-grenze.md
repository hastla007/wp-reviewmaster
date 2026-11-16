# RC-003 – Free/Pro-Grenze

**Status:** **Freigegeben** (Entscheidung der Inhaberin am 16.11.2026)  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §5, §6, §8](../../konzept.md), [RC-002](rc-002-scope-1-0.md), [RC-005](rc-005-entitlements.md)

Kategorien: **Core** (kostenlos, ohne Lizenz) · **Pro 1.0** · **Pro-Folge** (1.1+) · **Ausgeschlossen**.

## Grundregeln

1. Core bleibt ohne Lizenzserver, WooCommerce und externe Dienste vollständig nutzbar.
2. Pro schaltet nur **Automatisierung, API-Zugriff, erweiterte Darstellung und Suite-Verbindungen** frei – niemals Lesen, Export oder Löschen eigener Daten.
3. Öffentliche Verträge (Provider-, Connector-, Suite-Ereignisvertrag) liegen im Core; deren automatisierte Verarbeitung kann Pro sein.

## Zuordnung Konzept §5 „Free – kostenloser Kern“

| # | Funktion | Kategorie | Anmerkung |
|---|---|---|---|
| F1 | WordPress-eigene Reviews/Testimonial-Datensätze anlegen und verwalten | Core | |
| F2 | Externe Review-Links und manuell ausgewählte Auszüge mit Quelle, Link, Datum, Originalbewertung | Core | RC-043 |
| F3 | Sterne auf Originalskala, Quelllogo/-label je externer Bewertung | Core | Logos nur gemäß RC-016 |
| F4 | Review-Sammlung, Such-/Statusfilter, Moderation, manuelle Einträge | Core | |
| F5 | Basis-Grid/List-Widget, Review-Card-Block, Shortcode | Core | |
| F6 | Manuell gepflegte Text-Testimonials, getrennt gekennzeichnet | Core | Bild/Video/Audio = Pro-Folge |
| F7 | Bewertungsprofil-CTA („Auf … bewerten“) | Core | |
| F8 | Lokale Review-Einreichung mit Spam-Schutz, Moderationsqueue, Opt-in | Core | |
| F9 | Basis-Summary nur aus angezeigten Datensätzen, getrennt je Plattform | Core | kein Gesamtscore |
| F10 | Schema-Markup nur für eigene, zulässige Reviews | Core | SEO-Abschnitt |
| F11 | Datenexport/-löschung, Aufbewahrung, Rollen, Übersetzung, Multisite-Trennung | Core | |
| F12 | Keine externen Frontend-Abhängigkeiten ohne Aktivierung | Core | Prinzip |
| F13 | **CSV-Export gefilterter Reviews mit Formelschutz** | Core | Klarstellung, siehe unten |
| F14 | CSV-Import mit Prüflauf, Migrationshilfe | Core | §15 Nr. 3/5, RC-045/046 |
| F15 | Offizielle Embed-Codes (Trustpilot TrustBox, Trusted-Shops-Badge) mit Consent-Gate einfügen | Core | Embed ist kein API-Zugriff |
| F16 | Öffentliche Profil-Links mehrerer Plattformen verwalten | Core | §8.4 |

## Zuordnung Konzept §5 „Pro – separat lizenziertes Add-on“

| # | Funktion | Kategorie | Entitlement |
|---|---|---|---|
| P1 | Offizielle OAuth/API-Verbindungen der fünf Quellen | Pro 1.0 (Google, Apple, Play) / Pro-Folge (Trustpilot, eTrusted) | `review.platform_sync` |
| P2 | Mehrere synchronisierte Profile/Standorte/Shops/Apps | Pro 1.0 | `review.platform_sync` |
| P3 | Zeitgesteuerter Delta-Sync, Jobs, Status, Retry, Ratenbegrenzung | Pro 1.0 | `review.platform_sync` |
| P4 | Plattformübergreifende Review-Inbox mit erweiterten Filtern | Pro 1.0 | `review.platform_sync` (Inbox ist Sicht auf synchronisierte Daten; Basisliste bleibt Core) |
| P5 | Automatische Review-Anfragen, Vorlagen, Verzögerung, Opt-out, Audit | Pro 1.0 | `review.review_requests` |
| P6 | WooCommerce-Integration für Anfragen und Produktzuordnung | Pro 1.0 | `review.review_requests` |
| P7 | Antwort-Hilfen über Plattform-APIs | Pro-Folge | `review.platform_replies` |
| P8 | Zusätzliche Widgets (Karussell, Badge, Masonry, Produkt-/App-Rating, Trustbar, CTA) | Pro 1.0 (Karussell, Badge, Trustbar, Produkt-/App-Rating) / Pro-Folge (Masonry) | `review.advanced_widgets` |
| P9 | Design-Controls, „Mehr laden“ | Pro 1.0 | `review.advanced_widgets` |
| P10 | Analytics | Pro-Folge (Basisreport in 1.0 optional) | `review.analytics` |
| P11 | Social-Proof-Wall und Testimonial-Studio (Bild/Video/Audio) | Pro-Folge (1.2) | `review.social_proof` (neu, siehe RC-005) |
| P12 | Multimedia-Layouts | Pro-Folge (1.2) | `review.social_proof` |
| P13 | Plattform-Bewertungsleiste | Pro 1.0 | `review.advanced_widgets` |
| P14 | Vertrauenssignale/Trustmarks/Awards | Pro-Folge (1.2) | `review.social_proof` |
| P15 | Schwebendes Trust-Badge | Pro-Folge (1.2) | `review.social_proof` |
| P16 | Geplante Reports, erweiterte Moderationsregeln | Pro-Folge | `review.analytics` |
| P17 | Import-Logs, Integrationsdiagnose, Support-Tools für Sync | Pro 1.0 | an zugehöriges Recht gebunden, Diagnose immer lesbar |
| P18 | Captain-Suite-Adapter (Voucher, Pass, Affiliate, Form) | Pro-Folge (1.1) | `review.suite_integrations` |
| P19 | Pro-Manager und Runtime für kundenspezifische Connectoren | Pro-Folge (1.1) | `review.custom_connectors` |
| P20 | White-Label | Ausgeschlossen (kein Startversprechen) | – |

## Konzept §5 „Bewusst nicht im MVP“

Alle acht Punkte sind **Ausgeschlossen**: Scraping, fremde Profile über private Zugänge, Review-Gating, Anreize/Manipulation, Gesamtscore ohne Normalisierung (als Default; erklärter Score bleibt Pro-Folge-Option RC-114), KI-Rezensionen/-Antworten, garantierte Rich Snippets, SaaS-Pflicht für den Core.

## Klarstellung CSV-Export (Widerspruch aufgelöst)

Konzept §5 nennt „CSV-Export mit Spreadsheet-Formel-Schutz“ unter Pro, TODO RC-044 ordnet ihn dem Core (M2) zu, und §6 verlangt, dass Export bei Ablauf möglich bleibt. Entscheidungsvorschlag:

- **Core:** manueller CSV-Export aller lokal gespeicherten Reviews mit Filtern und Formelschutz (RC-044). Damit bleibt Export auch nach Lizenzablauf garantiert.
- **Pro:** geplante/automatische Reports und Analytics-Exporte (`review.analytics`).

Konzept §5 Pro-Liste wird nach Freigabe entsprechend präzisiert.

## Lizenzablauf

Siehe RC-061: Pro-Schreibaktionen, neue Syncs, Anfrageversand und dynamische Pro-Widgets stoppen; gespeicherte Daten bleiben lesbar, exportierbar und löschbar. Pro-Widgets fallen auf das Core-Grid mit den zuletzt zulässig gecachten Daten zurück, solange die Plattform-Cachefrist das erlaubt.

## Offen / Freigabe

- Produktverantwortung bestätigt die Zuordnung, insbesondere F13/F15 (Core) und das neue Recht `review.social_proof`.
