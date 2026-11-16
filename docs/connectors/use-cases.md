# CON-001 – Connector-Use-Cases und Grenzen

Status: Spezifikation v1, Stand 2026-11-16. Technische Details: [contract-v1.md](contract-v1.md).

## Ziel

Review Captain soll kundenspezifische WordPress-Projekte (Buchungs-, CRM-, ERP-, Mitglieder-, Kurs- oder Branchenlösungen) anbinden, ohne dass der Core geändert wird. Ein Connector ist ein **Adapter**, kein alternatives Review-System: Review Captain bleibt Eigentümer von Review-Anfragen, Reviews, Moderation, Quellenzuordnung, Deduplizierung, Suppression und Widgets.

## Abgrenzung der Integrationsarten

| Art | Wer pflegt | Einsatz | Technischer Weg | Lizenz (siehe [licensing.md](licensing.md)) |
|---|---|---|---|---|
| **Suite-Adapter** | Review Captain (offiziell, getestet) | WooCommerce, Voucher Captain, Pass Captain, Affiliate Captain, Form Captain | Versionierte Ereignis- und Service-Verträge der jeweiligen Suite-Plugins; im Review-Captain-Pro-Add-on enthalten | `review.suite_integrations` |
| **Lokaler Projekt-Connector** | Drittanbieter / Agentur / Kunde | Individuelle Software **auf derselben WordPress-Installation** | PHP-Vertrag `review_captain_connectors_v1` + Capability-Interfaces, asynchron über den Review-Captain-Jobrunner | Vertrag frei; Runtime `review.custom_connectors` |
| **Externe Verbindung** | Review Captain (Endpunkt) + Fremdsystem | Eigenständige Systeme oder andere WordPress-Installationen | Signierte Webhooks bzw. scoped REST-API (HMAC, Timestamp, Nonce, Replay-Schutz, Rotation) – **nicht Teil von Contract v1** | eigenes Integrations-Entitlement (offen) |

Regeln:

- PHP-Hooks gelten nur innerhalb derselben WordPress-Installation. Ein lokaler Connector darf keine Netzwerkaufrufe als „lokale Hooks“ tarnen (z. B. eine Remote-Site per HTTP abfragen und so tun, als sei sie lokal). Für Remote-Quellen ist der Webhook-/REST-Weg vorgesehen.
- Ein Connector darf seine **eigenen** Daten bzw. die Daten des Systems lesen, für das er gebaut ist. Er liest **keine** Review-Captain-Tabellen direkt und schreibt nicht hinein; der Datenaustausch läuft ausschließlich über die Rückgabewerte der Capability-Methoden.
- Suite-Plugins der Captain Suite werden nicht als Custom Connector nachgebaut; dafür existieren offizielle Suite-Adapter.

## Fähigkeiten (Capabilities)

Jeder Connector deklariert in `capabilities()` eine oder mehrere Fähigkeiten und implementiert **nur** die dazugehörigen Interfaces. Der Admin zeigt ausschließlich Aktionen, die tatsächlich deklariert **und** implementiert sind.

| Capability-Wert | Interface | Richtung | Zweck |
|---|---|---|---|
| `experience_source` | `ExperienceSourceInterface` | Kundensystem → Review Captain | meldet abgeschlossene, abgesagte oder erstattete Erlebnisse |
| `recipient_resolver` | `RecipientResolverInterface` | Kundensystem → Review Captain (on demand) | löst eine site-lokale Empfängerreferenz unmittelbar vor dem Versand in minimale Kontaktdaten auf |
| `review_source` | `ReviewSourceInterface` | Kundensystem → Review Captain | liefert kundeneigene Reviews im gemeinsamen Schema |
| `request_transport` | `RequestTransportInterface` | Review Captain → Kundensystem | übergibt eine bereits geprüfte Review-Anfrage an einen kundeneigenen Versandweg |
| `context_provider` | `ContextProviderInterface` | Kundensystem → Review Captain (on demand) | liefert freigegebene Bezeichnung/Link eines Objekts (Kurs, Standort, Service) |

### 1. Experience Source

- **Beispiele:** abgeschlossener Kurs, wahrgenommener Termin, erfüllte Buchung, abgeschlossener Auftrag/Ticket im Helpdesk.
- Liefert Ereignisse `experience_completed`, `experience_cancelled`, `experience_refunded` mit stabiler `experience_id`, `event_id`, Objekt- und Empfängerreferenz sowie dem Versandberechtigungsstatus.
- Es zählt nur ein **ausdrücklich freigegebenes Abschlussereignis**. Beliebige Formular- oder Datenbankänderungen („Datensatz aktualisiert“) lösen keine Einladung aus.
- Review Captain wendet danach an: „höchstens eine Einladung pro tatsächlichem Erlebnis und Empfänger“, Kampagnenziel, Wartezeit, Suppression/Opt-out, Consent, Ausschlüsse. Der Connector kann diese Regeln nicht umgehen.
- `experience_cancelled`/`experience_refunded` heben geplante, noch nicht versendete Anfragen auf.

### 2. Recipient Resolver

- Event-Payloads enthalten **keine E-Mail-Adresse**, nur `recipient_ref` (z. B. `crm-contact:4711`).
- Kontaktdaten werden erst unmittelbar vor einer erlaubten Aktion abgefragt (Zweck `review_request` oder `review_request_reminder`) und nicht dauerhaft gespeichert.
- Unbekannte Referenz (`null`), `permission` ≠ `allowed` oder ein Fehler → **kein Versand**.

### 3. Review Source (Review Import Source)

- Liefert lokale bzw. kundeneigene Reviews (z. B. Kursbewertungen aus einer LMS-Tabelle, Feedback aus einem CRM) mit stabiler Quell-ID (`external_id`), Herkunft und Moderationsvorschlag.
- Reviews landen mit `origin=connector` und durchlaufen die normale Review-Captain-Moderation; importierte Reviews werden **nie** automatisch veröffentlicht.
- Plattform-Reviews (Google, Trustpilot …) werden **nicht** über Custom Connectoren importiert, sondern nur über offizielle Provider-Adapter (Anbieterbedingungen).

### 4. Request Transport

- Übergibt eine von Review Captain bereits geprüfte, gerenderte Anfrage an einen Versandweg (kundeneigenes CRM, Marketing-Automation, SMS-Gateway).
- Der Connector **kann nicht** Empfänger, Einladungsberechtigung, Opt-out oder Deduplizierung ändern; er meldet nur `accepted`/`rejected` bzw. wirft eine `ConnectorException`.

### 5. Context Provider

- Liefert für `(context_type, context_id)` nur eine freigegebene öffentliche Bezeichnung und optional eine öffentliche URL (z. B. „Yoga-Grundkurs Mittwoch“, Standortseite).
- Wird für Vorlagen (Platzhalter `{context_label}`), Widget-Auswahl und Admin-Anzeige genutzt. Keine personenbezogenen Daten.

## Typische Kombinationen

| Projekt | Fähigkeiten |
|---|---|
| Kursbuchung (Plugin X) | `experience_source`, `recipient_resolver`, `context_provider` |
| CRM mit eigenem Mailversand | `experience_source`, `recipient_resolver`, `request_transport` |
| Altes Feedback-System migrieren | `review_source`, `context_provider` |
| Helpdesk „Ticket gelöst“ | `experience_source`, `recipient_resolver` |

## Nicht-Ziele (v1)

- Kein Push-Eingang per PHP-Aufruf durch den Connector (v1 ist Polling über `poll_experiences`; ein Push-Hook nach Konzept `review_captain_experience_event_v1` ist eine offene Entscheidung, siehe [contract-v1.md](contract-v1.md#offene-punkte)).
- Keine frei ausführbare Konfiguration (PHP, beliebige URLs aus Admin-Feldern).
- Keine Sandbox/Isolation gegen bösartige Plugins.
- Keine Cross-Site-Übertragung über PHP-Hooks.
- Kein Schreiben in Yoast/SEO-Daten, keine eigenen öffentlichen URLs.
