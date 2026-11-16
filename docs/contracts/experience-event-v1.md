# Experience-Ereignisvertrag v1 (RC-021, CON-004)

| | |
|---|---|
| Status | Spezifikation, Entwurf zur Abnahme |
| Schema-ID | `review_captain.experience_event.v1` |
| Vertragsversion | `1.0` |
| JSON-Schema | [`schema/experience-event.v1.json`](schema/experience-event.v1.json) |
| Eingang | Action `review_captain_experience_event_v1`, Funktion `review_captain_submit_experience_event_v1()` |
| Bezug | TODO RC-021, CON-004, TEC-004; konzept.md 7.4, 7.9, 7.10 |

> Namensangleichung: Frühere Fassungen von konzept.md schlugen `wp_review_captain_experience_event_v1` vor. Nach conventions.md/ADR-0002 entfällt das `wp_`-Präfix; verbindlich ist `review_captain_experience_event_v1` (konzept.md ist inzwischen angeglichen).

## Zweck

Ein **besitzendes Plugin** (siehe [`experience-ownership.md`](experience-ownership.md)) meldet fachliche Ereignisse zu einem tatsächlichen Erlebnis. Review Captain entscheidet daraus – nie der Produzent – ob, wann und an wen eine Einladung geplant wird. Das Ereignis transportiert **nur Referenzen**: keine E-Mail, kein Name, keine Adresse, kein Ticket-/Gutscheincode, keine Zahlungsdaten, kein Reviewtext. Kontaktangaben holt ausschließlich der Resolver zur Versandzeit ([`recipient-resolver-v1.md`](recipient-resolver-v1.md)).

## Eingang

```php
// Produzent ohne harte Abhängigkeit (No-op, wenn Review Captain fehlt):
do_action( 'review_captain_experience_event_v1', $payload );

// Produzent, der das Ergebnis braucht (z. B. für eigene Diagnose):
if ( function_exists( 'review_captain_submit_experience_event_v1' ) ) {
    $result = review_captain_submit_experience_event_v1( $payload );
}
```

- Aufruf **nach Commit** der eigenen Daten (z. B. nach gespeichertem Check-in), nie innerhalb einer offenen Transaktion.
- Der Core validiert synchron (nur Schema + Allowlist + Dedupe-Insert, keine externen Aufrufe, Ziel < 20 ms) und legt bei Annahme einen Job `experience_event.process` an (`jobs.idempotency_key = exp_evt:{event_id}`). Keine Planung/kein Versand im Request.
- Ohne aktive Anfrage-Engine (Pro inaktiv oder ohne `review.review_requests`) wird validiert und mit `ignored` / `rc.entitlement.missing` beantwortet; nichts wird gespeichert.

Rückgabe von `review_captain_submit_experience_event_v1()`:

```json
{ "status": "accepted", "code": null, "event_id": "1b2c3d4e-5f60-4718-8a9b-0c1d2e3f4a5b", "experience_id": "pass-captain:1:attendance:T-7781:OCC-55" }
```

| `status` | Bedeutung |
|---|---|
| `accepted` | gespeichert, Verarbeitung eingeplant |
| `duplicate` | `event_id` oder `(source, idempotency_key)` bereits bekannt; keine Aktion (`rc.event.duplicate`) |
| `rejected` | Validierung fehlgeschlagen (`rc.data.invalid_payload`, `rc.data.pii_forbidden`, `rc.contract.incompatible`, `rc.source.not_owner` …) |
| `ignored` | gültig, aber keine Verarbeitung (`rc.entitlement.missing`, Quelle deaktiviert `rc.source.disabled`) |

## Felder

| Feld | Typ | Pflicht | Regel |
|---|---|---|---|
| `schema` | string | ja | `review_captain.experience_event.v1` |
| `contract` | string | ja | `1.<minor>`; unbekannte Major → `rejected` / `rc.contract.incompatible` |
| `source` | string | ja | stabiler Quellschlüssel: `woocommerce`, `voucher-captain`, `pass-captain`, `form-captain`, Custom-Connector-Key (`^[a-z0-9][a-z0-9_.-]{1,63}$`). Muss in der Connector-/Adapter-Registry aktiv sein. |
| `event_id` | UUID v4 | ja | globale Nachrichten-ID; bei erneutem Versand derselben Nachricht wiederverwenden |
| `experience_id` | string ≤ 191 | ja | vom Owner vergebene Erlebnis-ID (Format `experience-ownership.md`); muss mit `{source}:` beginnen (`rc.source.not_owner` sonst) |
| `event_type` | enum | ja | siehe unten |
| `occurred_at` | ISO 8601 UTC (`Z`) | ja | fachlicher Zeitpunkt in der Quelle, nicht Sendezeit; > 1 h in der Zukunft → `rejected` |
| `object_ref` | object | ja | `{ "type": string ≤ 40, "id": string ≤ 64, "context_type": reviews.context_type|null }` – öffentliche stabile Referenz (Produkt, Gutscheinart, Event/Termin …). `context_type` mappt auf `reviews.context_type` für spätere Review-Zuordnung. |
| `recipient_ref` | string ≤ 191 \| null | ja (Feld) | site-lokale, opake Empfängerreferenz `{source}:{art}:{id}`; **darf kein `@` enthalten** und nicht aus Kontaktangaben ableitbar sein (kein Hash der E-Mail). `null` nur bei widerrufenden Ereignissen = „alle Empfänger dieses Erlebnisses“. |
| `locale` | BCP-47 \| null | ja (Feld) | Sprache für Vorlage; `null` → Fallback Website-Sprache (RC-094) |
| `send_permission` | object | ja | siehe unten; fehlend/unklar = nicht erlaubt |
| `idempotency_key` | string ≤ 191 | ja | deterministischer fachlicher Schlüssel, z. B. `pass-captain:1:checkin:T-7781:OCC-55` |
| `context` | object | nein | Allowlist: `campaign_ref` (string ≤ 64), `affiliate_ref` (string ≤ 64, nicht personenbezogen), `items` (Array ≤ 50 von `object_ref`, z. B. Woo-Positionen), `trigger_detail` (string ≤ 40, z. B. `checkin`, `event_ended`, `redemption`) |
| `producer` | object | nein | `{ "plugin": string, "version": string }` für Diagnose |

Maximale Payload-Größe (JSON-kodiert): 16 KiB. Unbekannte Felder einer höheren Minor-Version werden vor der Validierung entfernt und nicht gespeichert.

### `event_type`

| Wert | Bedeutung | Wirkung in Review Captain |
|---|---|---|
| `experience_created` | Erlebnis angelegt (Kauf, Ticket ausgestellt, Gutschein ausgegeben) | nur Registrierung des Erlebnisses; **plant nie** eine Einladung |
| `experience_completed` | tatsächliche Nutzung/Teilnahme abgeschlossen (Einlösung/Leistung, Check-in, Termin wahrgenommen, Bestellung erfüllt) | Kampagnen mit passendem Auslöser **dürfen** planen (Einladungsregel) |
| `experience_updated` | ergänzende Information zum selben Erlebnis (z. B. Eventende nach Check-in) | aktualisiert bestehendes Erlebnis/geplante Anfrage; plant keine zweite |
| `experience_cancelled` | Absage/Storno | widerruft: offene Anfragen → `cancelled` |
| `experience_refunded` | Erstattung (voll; Teil-Erstattung mit `context.items`) | widerruft gemäß Kampagnenregel (RC-097) |
| `experience_no_show` | Nichtteilnahme festgestellt | widerruft |
| `experience_voided` | Einlösung/Check-in aufgehoben oder ungültig | widerruft |
| `permission_changed` | Versandberechtigung des `recipient_ref` geändert | neuer Status gilt sofort; `denied`/`revoked` blockiert alle offenen Anfragen dieses Empfängers |

Widerrufende Ereignisse sind **endgültig** für das Erlebnis (`experiences.state = revoked`); ein später eintreffendes `experience_completed` mit älterem oder neuerem `occurred_at` plant nichts mehr (`rc.experience.revoked`).

### `send_permission`

| Feld | Typ | Regel |
|---|---|---|
| `status` | `granted` \| `denied` \| `revoked` \| `unknown` | nur `granted` erlaubt Planung; alles andere → Anfrage `skipped` (`rc.consent.missing` / `rc.consent.revoked`) |
| `channel` | `email` | v1 nur E-Mail |
| `basis` | `consent` \| `legitimate_interest` \| `contract` \| `other` \| null | Kennzeichnung der Grundlage laut Quelle; **welche Grundlage je Markt zulässig ist, entscheidet RC-014**. Review Captain akzeptiert pro Kampagne nur konfigurierte Grundlagen. |
| `recorded_at` | ISO 8601 UTC \| null | Zeitpunkt der Erfassung in der Quelle |
| `origin` | string ≤ 64 \| null | Herkunft in der Quelle, z. B. `checkout_optin`, `account_preference` |
| `evidence_ref` | string ≤ 191 \| null | Referenz auf Nachweis in der Quelle (kein Inhalt) |

Die Berechtigung im Ereignis ist ein **Planungs**-Indiz. Maßgeblich ist die erneute Prüfung zur Versandzeit über den Resolver.

## Verarbeitung (Kurzfassung)

1. Validierung → `inbound_events` Insert (Unique `event_id`, Unique `sha1(source|idempotency_key)`); Duplikat → `duplicate`.
2. Job `experience_event.process`: `experiences`-Zeile per Upsert (Unique `experience_id`), Zustandsübergang `open → completed → revoked` (revoked ist absorbierend).
3. Bei `experience_completed`: je aktiver Kampagne mit passendem Auslöser Anfrage nach [`invitation-rule.md`](invitation-rule.md) anlegen (`planned`) oder als `skipped` protokollieren.
4. Bei widerrufenden Ereignissen/`permission_changed`: offene Anfragen per CAS auf `cancelled` bzw. `skipped` setzen ([`request-lifecycle.md`](request-lifecycle.md)).

Pro-Tabellen (Vorschlag):

- `{prefix}review_captain_inbound_events`: `id`, `event_id` CHAR(36) UNIQUE, `source` VARCHAR(64), `idempotency_hash` CHAR(40) UNIQUE (= `sha1(source|idempotency_key)`), `experience_id` VARCHAR(191), `event_type` VARCHAR(40), `occurred_at`, `received_at`, `result` VARCHAR(20), `result_code` VARCHAR(64) NULL, `payload` LONGTEXT (validierte, minimierte JSON-Kopie), `processed_at` NULL. Index `(experience_id)`, `(source, received_at)`.
- `{prefix}review_captain_experiences`: `id`, `experience_id` VARCHAR(191) UNIQUE, `source`, `object_type`, `object_id`, `state` (`open`\|`completed`\|`revoked`), `revoked_reason` VARCHAR(40) NULL, `first_event_at`, `completed_at` NULL, `revoked_at` NULL, `revision` INT, `created_at`, `updated_at`.

## Beispiele

Pass-Captain-Check-in:

```json
{
  "schema": "review_captain.experience_event.v1",
  "contract": "1.0",
  "source": "pass-captain",
  "event_id": "1b2c3d4e-5f60-4718-8a9b-0c1d2e3f4a5b",
  "experience_id": "pass-captain:1:attendance:T-7781:OCC-55",
  "event_type": "experience_completed",
  "occurred_at": "2026-11-14T18:05:00Z",
  "object_ref": { "type": "event_occurrence", "id": "OCC-55", "context_type": "event" },
  "recipient_ref": "pass-captain:attendee:9f8e7d6c",
  "locale": "de-AT",
  "send_permission": { "status": "granted", "channel": "email", "basis": "consent", "recorded_at": "2026-10-01T12:00:00Z", "origin": "checkout_optin", "evidence_ref": "consent-4471" },
  "idempotency_key": "pass-captain:1:checkin:T-7781:OCC-55",
  "context": { "trigger_detail": "checkin" },
  "producer": { "plugin": "pass-captain", "version": "2.4.0" }
}
```

Eventende zum selben Erlebnis (kein zweiter Versand):

```json
{
  "schema": "review_captain.experience_event.v1",
  "contract": "1.0",
  "source": "pass-captain",
  "event_id": "2c3d4e5f-6071-4829-9bac-1d2e3f4a5b6c",
  "experience_id": "pass-captain:1:attendance:T-7781:OCC-55",
  "event_type": "experience_updated",
  "occurred_at": "2026-11-14T22:00:00Z",
  "object_ref": { "type": "event_occurrence", "id": "OCC-55", "context_type": "event" },
  "recipient_ref": "pass-captain:attendee:9f8e7d6c",
  "locale": "de-AT",
  "send_permission": { "status": "granted", "channel": "email", "basis": "consent", "recorded_at": "2026-10-01T12:00:00Z", "origin": "checkout_optin", "evidence_ref": "consent-4471" },
  "idempotency_key": "pass-captain:1:event_ended:T-7781:OCC-55",
  "context": { "trigger_detail": "event_ended" }
}
```

WooCommerce-Erstattung (widerrufend, alle Empfänger):

```json
{
  "schema": "review_captain.experience_event.v1",
  "contract": "1.0",
  "source": "woocommerce",
  "event_id": "3d4e5f60-7182-4a3b-8cbd-2e3f4a5b6c7d",
  "experience_id": "woocommerce:1:order:10452",
  "event_type": "experience_refunded",
  "occurred_at": "2026-11-15T09:30:00Z",
  "object_ref": { "type": "order", "id": "10452", "context_type": null },
  "recipient_ref": null,
  "locale": null,
  "send_permission": { "status": "unknown", "channel": "email", "basis": null, "recorded_at": null, "origin": null, "evidence_ref": null },
  "idempotency_key": "woocommerce:1:refund:10452:full",
  "context": { "trigger_detail": "refund_full" }
}
```

Abgelehntes Ereignis (E-Mail im Payload):

```json
{ "status": "rejected", "code": "rc.data.pii_forbidden", "event_id": "4e5f6071-8293-4b4c-9dce-3f4a5b6c7d8e", "experience_id": null }
```

## Abnahme

- [ ] Pflichtfelder source, event_id, experience_id, event_type, occurred_at, object_ref, recipient_ref, locale, send_permission und idempotency_key sind formal im JSON-Schema festgelegt; Contract-Test validiert alle Beispiele.
- [ ] Payload mit E-Mail-artigem Wert in `recipient_ref` oder unbekannten Kontakt-/Textfeldern wird mit `rc.data.pii_forbidden` abgelehnt.
- [ ] `send_permission.status ≠ granted` plant nie eine Einladung; `unknown` gilt als nicht erlaubt.
- [ ] Gleiche `event_id` oder gleicher `(source, idempotency_key)` zweimal → genau eine Verarbeitung (`duplicate`).
- [ ] `experience_created` plant nie; `experience_updated` erzeugt keine zweite Anfrage.
- [ ] Widerrufendes Ereignis vor oder nach `experience_completed` (Out-of-Order) führt zu keiner Einladung bzw. storniert eine offene.
- [ ] Unbekannte Major-Version → `rc.contract.incompatible`, kein Fatal Error; Ereignis ohne aktive Anfrage-Engine → `ignored`.
- [ ] Verarbeitung findet nie im auslösenden Request statt (nur Job).
