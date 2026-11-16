# Connector-Vertrag v1 – CON-001 bis CON-007

**Status:** Entwurf – Basis für CON-008 (Core-Registry)  
**Stand:** 2026-11-16  
**Grundlage:** [Konzept §7.10](../../konzept.md), [Verträge](../m1/vertraege.md), [Fehlercodes](../m1/rc-029-030-rechte-fehlercodes.md)

## CON-001 – Use-Cases und Grenzen

| Fähigkeit | Zweck | Beispiel | Datenrichtung |
|---|---|---|---|
| `experience_source` | meldet abgeschlossene/widerrufene Erlebnisse | Buchungssystem meldet „Kurs besucht“ | Connector → RC |
| `recipient_resolver` | liefert Kontakt zur Versandzeit | CRM löst Teilnehmerreferenz auf | RC → Connector → RC |
| `review_source` | liefert Reviews aus einem eigenen System (nicht Plattform-Scraping) | Branchenportal mit offizieller Export-API des Kunden | Connector → RC |
| `request_transport` | versendet Einladungen über einen kundeneigenen Kanal | CRM-Mailing-System | RC → Connector |
| `context_provider` | liefert Widget-Kontext (Objekt, Label) | Kursseite des Buchungsplugins | Connector → RC |

Abgrenzung: **Suite-Adapter** (offizielle Captain-Plugins, gepflegt im Pro-Add-on, Recht `review.suite_integrations`) · **lokale Connectoren** (Drittanbieter-Plugins in derselben WP-Installation, dieser Vertrag) · **externe Systeme** (andere Server) nur über signierte Webhooks nach TEC-011 – nicht Teil von v1.

## CON-002 – Hauptvertrag

Registrierung über Aktion `wp_review_captain_register_connectors_v1( Registry $registry )`:

```php
add_action( 'wp_review_captain_register_connectors_v1', function ( $registry ) {
    $registry->register( new My_Booking_Connector() );
} );

interface ConnectorInterface {
    public function metadata(): array;
}
// metadata():
[
  'key'              => 'acme-booking',          // ^[a-z0-9-]{3,40}$, global eindeutig
  'label'            => 'ACME Booking',
  'version'          => '1.2.0',                 // Version des Connector-Plugins
  'contract'         => '1.0',                   // Connector-Vertragsversion
  'developer'        => 'ACME GmbH',
  'capabilities'     => [ 'experience_source', 'recipient_resolver' ],
  'config_schema'    => [ /* CON-011, optional */ ],
  'requires_core'    => '1.0.0',
]
```

- Eindeutige Connector-ID im System: `connector:{key}`; doppelte Registrierung desselben Keys → zweite wird abgelehnt (`duplicate`), Admin sieht Hinweis.
- Kompatibilität: gleiche Major-Version des Vertrags = kompatibel; höhere Minor des Connectors als Core → kompatibel, unbekannte optionale Felder werden ignoriert; andere Major → `incompatible_contract`, Connector pausiert, kein Fatal Error.
- Deprecation: mindestens zwei Minor-Releases und 6 Monate mit Admin-Hinweis.

## CON-003 – Capability-Verträge

```php
interface ExperienceSourceInterface {        // capability: experience_source
    /** Optional: Liste der Objekttypen, die der Connector meldet. */
    public function object_types(): array;
}
// Meldung erfolgt aktiv über: wp_review_captain_submit_experience_event_v1( $event ) mit source = 'connector:{key}'.

interface RecipientResolverInterface {       // capability: recipient_resolver – siehe RC-023
    public function resolve( string $recipient_ref, string $experience_id ): ResolvedRecipient;
}

interface ReviewSourceInterface {            // capability: review_source
    public function list_profiles(): array;                            // ProfileRef[]
    public function fetch_page( string $profile_ref, ?string $cursor ): SyncPage;  // wie Provider (RC-020)
}

interface RequestTransportInterface {        // capability: request_transport
    public function send( TransportMessage $m ): TransportResult;     // status: sent|failed_temporary|failed_permanent, message_id
}

interface ContextProviderInterface {         // capability: context_provider
    public function current_context(): ?array;                          // ['type'=>'course','id'=>'42','label'=>'…']
}
```

Fixtures für jede Fähigkeit liegen in `tests/fixtures/connector/` (CON-007).

## CON-004 – Event-Payloads

Connectoren nutzen exakt den Erlebnisvertrag [`experience-event-v1`](../contracts/experience-event-v1.schema.json). Pflicht: `experience_id`, `event_id`, `source` (`connector:{key}`), `object`, `recipient_ref` (bei Abschluss), `locale` (empfohlen), `occurred_at`, `communication`. Idempotency-Key = `sha256(source|event_id)`. Kontakt- und Reviewtexte werden **nie** im Event übertragen, sondern nur über `recipient_resolver` bzw. `review_source`.

## CON-005 – Sicherheits- und Datenmatrix

| Fähigkeit | Minimale Daten | Capability für Konfiguration | Consent-Prüfung | Retention/Erase | Logging-Allowlist | Retry-Klasse | Disconnect |
|---|---|---|---|---|---|---|---|
| experience_source | Event-Felder laut Schema | `rc_manage_settings` | `communication.basis` | Events 90 Tage | `event_type`, `source`, `code` | data (invalid) | neue Events abgelehnt; Einladungen `paused` |
| recipient_resolver | E-Mail, Vorname, Locale, Basis | – | bei jedem Aufruf | nicht gespeichert | `status`, `code` | temporary/permanent | Einladungen `paused` (`source_unavailable`) |
| review_source | Review-Felder RC-018 | `rc_connect_sources` | – | wie Sync-Snapshots | `profile_ref`, Zähler | wie Provider | Snapshots nach Frist ausblenden |
| request_transport | Empfänger, Betreff, Text, Abmeldelink | `rc_send_requests` | vor Übergabe | Message-ID im Versandprotokoll | `message_id`, `status` | temporary/permanent | Kampagnen mit diesem Transport pausiert |
| context_provider | Typ, ID, Label | – | – | – | – | – | Widgets ohne Kontext |

**Wichtig:** Connectoren sind PHP-Code im selben Prozess – das Framework ist **keine Sandbox**. Betreiber installieren nur vertrauenswürdige Connectoren; das Admin zeigt Entwickler und Version deutlich.

## CON-006 – Lizenzgrenze

| Teil | Ort | Recht |
|---|---|---|
| Öffentliche Interfaces, Registry, Validator, Statusanzeige (read-only) | Core | keins |
| Annahme und Validierung von Events (ohne Speicherung personenbezogener Referenzen) | Core | keins |
| Speicherung/Verarbeitung von Events, Einladungen, Connector-Jobs, Pro-Manager-Aktionen (Test, Pause, Retry), Konfiguration mit Secrets | Pro | `review.custom_connectors` |
| Offizielle Captain-Suite-Adapter | Pro | `review.suite_integrations` |
| Lokale Review-Erstellung über `wp_review_captain_create_local_review_v1` (immer `pending`, `unverified`) | Core | keins |

## CON-007 – Vertrags- und Kompatibilitätstests

| Test | Eingabe | Erwartung |
|---|---|---|
| T1 Pflichtmetadaten fehlen | `key` fehlt | Registrierung abgelehnt, Code `invalid_payload` |
| T2 ungültiger Key | `ACME Booking!` | abgelehnt |
| T3 unbekannte Major | `contract: 2.0` | registriert, Status `incompatible_contract`, keine Verarbeitung |
| T4 höhere Minor | `contract: 1.5` | kompatibel |
| T5 Fähigkeit deklariert, Interface fehlt | `recipient_resolver` ohne Implementierung | Status `invalid_payload`, Fähigkeit deaktiviert |
| T6 unbekannte Fähigkeit | `teleport` | ignoriert + Hinweis |
| T7 doppelter Key | zwei Connectoren `acme-booking` | zweiter abgelehnt (`duplicate`) |
| T8 ungültiges Event | E-Mail in `recipient_ref` | `invalid_payload` |
| T9 wiederholtes Event | gleiche `event_id` zweimal | zweites `duplicate` |
| T10 Fehlercodes | Connector wirft unbekannten Code | auf `provider_unavailable` (temporary) gemappt |
| T11 requires_core zu hoch | `requires_core: 9.0.0` | `incompatible_contract` |

T1–T7 und T11 werden mit CON-008 als PHPUnit-Tests umgesetzt; T8–T10 mit der Pro-Runtime.
