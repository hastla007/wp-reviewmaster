# Workflow-Captain-Providervertrag v1 (RC-WF-01, RC-WF-02, RC-WF-03, RC-WF-06)

| | |
|---|---|
| Status | Spezifikation, Entwurf zur Abnahme. **Registrierungsmechanismus auf Workflow-Captain-Seite ist nicht bestätigt** (siehe „Anbindung“). |
| Schema-ID | `review_captain.workflow_provider.v1` |
| Vertragsversion | `1.0` |
| Provider-Key | `review-captain` |
| Bezug | TODO RC-WF-01–06, RC-085–RC-093, RC-103; konzept.md 7.3 E, 7.4, 7.9 |
| Verwandt | [`experience-event-v1.md`](experience-event-v1.md), [`request-lifecycle.md`](request-lifecycle.md), [`invitation-rule.md`](invitation-rule.md), [`experience-ownership.md`](experience-ownership.md) |

## Rolle

Review Captain stellt Workflow Captain **Trigger** (Post-Commit-Ereignisse) und **Actions** (Services) bereit. Workflow Captain ist ein zusätzlicher Auslöser, **kein** alternativer Regelpfad: Jede Action läuft durch dieselben Services wie die native Queue (`RequestPlanner`, `ReviewModeration`). Fehlt Workflow Captain oder ist sein Vertrag inkompatibel, läuft Review Captain unverändert weiter.

## RC-WF-01 – Vertragsmetadaten

### Descriptor

Review Captain Pro liefert den Descriptor über `\ReviewCaptain\Api::workflow_descriptor_v1(): array`:

```json
{
  "schema": "review_captain.workflow_provider.v1",
  "contract": "1.0",
  "provider_key": "review-captain",
  "provider_version": "1.0.0",
  "deprecation": { "policy": "major_parallel", "min_months": 12, "min_minor_releases": 2 },
  "triggers": [
    { "key": "request.planned", "entitlements": ["review.suite_integrations", "review.review_requests"], "payload_schema": "review_captain.request.v1" },
    { "key": "review.published", "entitlements": ["review.suite_integrations"], "payload_schema": "review_captain.review.v1" }
  ],
  "actions": [
    { "key": "plan_request", "entitlements": ["review.suite_integrations", "review.review_requests"], "input_schema": "review_captain.workflow.plan_request.v1" },
    { "key": "create_draft_review", "entitlements": ["review.suite_integrations"], "input_schema": "review_captain.workflow.create_draft_review.v1" }
  ]
}
```

(Auszug; vollständige Liste in den Tabellen unten.)

| Element | Regel |
|---|---|
| Schema-Version | `schema` je Payload (`review_captain.request.v1`, `review_captain.review.v1`, `review_captain.recipient.v1`); `contract` = `1.<minor>` |
| Stabile Event-ID | `event_id` = UUID v5 (Namespace `review-captain`, Name = `idempotency_key`). Wiederholtes Feuern desselben Übergangs ergibt dieselbe ID. |
| Idempotency-Key | Trigger: `review-captain:{site_id}:{object}:{object_uuid}:{transition}:{revision}`; Actions: vom Aufrufer, Pflicht, ≤ 191 Zeichen, ohne `|`, `@`, Leerzeichen |
| Capability | Konfiguration der Workflow-Verbindung: `review_captain_manage_requests` + Nonce. Ausführung im Hintergrund prüft keine User-Capability, sondern dass die Verbindung von einem berechtigten Nutzer aktiviert wurde und aktiv ist. |
| Entitlement | Trigger und Actions nur mit aktivem `review.suite_integrations`; Anfrage-bezogene zusätzlich `review.review_requests`. Fehlend → Trigger werden nicht ausgeliefert, Actions liefern `rc.entitlement.missing`. |
| Payload-Allowlist | je Trigger unten; alles andere wird nicht übergeben. **Nie**: Reviewtext, Titel, Autorname, E-Mail, `recipient_ref`, `contact_hash`, Tokens, Vorlagentext. |
| Deprecation | Breaking Change nur als neuer Major (`…v2`) parallel zu v1; v1 bleibt mindestens 12 Monate **und** 2 Minor-Releases nach Erscheinen von v2 (Vorschlag, Produktentscheidung offen). Unbekannte Major → `rc.contract.incompatible`, Verbindung `incompatible_contract`. |

### Anbindung

Workflow Captains Registrierungs-Hook ist **nicht** Teil dieses Repositorys und nicht bestätigt. Vorgabe für Review Captain Pro: Der Adapter registriert den Descriptor ausschließlich über den öffentlichen, versionierten Registry-Vertrag von Workflow Captain, sobald dieser dokumentiert ist; bis dahin bleibt der Adapter deaktiviert und die Integrationsseite zeigt `source_unavailable`.

## RC-WF-02 – Trigger (Post-Commit)

Alle Trigger werden **nach Commit** aus den Review-Captain-Hooks abgeleitet. Ohne Workflow Captain feuern nur die Review-Captain-Hooks.

| Trigger-Key | Quelle (Hook) | Bedingung |
|---|---|---|
| `request.planned` | `review_captain_request_status_changed_v1` | `status = planned` |
| `request.skipped` | dto. | `status = skipped` |
| `request.sent` | dto. | `status = sent` |
| `request.failed` | dto. | `status = failed` |
| `request.cancelled` | dto. | `status = cancelled` |
| `request.expired` | dto. | `status = expired` |
| `request.reminder_sent` | `review_captain_request_reminder_sent_v1` | – |
| `request.responded` | `review_captain_request_responded_v1` | lokale Review über Anfragelink eingegangen |
| `review.created` | `review_captain_review_created_v1` | – |
| `review.moderated` | `review_captain_review_status_changed_v1` | neuer Status ∈ {`rejected`, `flagged`, `archived`} |
| `review.published` | dto. | neuer Status = `approved` |
| `review.withdrawn` | `review_captain_review_removed_v1` oder `status_changed` `approved → archived` | – |
| `recipient.opted_out` | `review_captain_recipient_opted_out_v1` | Abmeldung über Link oder Quelle |

### Payload-Allowlists

`review_captain.request.v1` (für `request.*`):

```json
{
  "schema": "review_captain.request.v1",
  "contract": "1.0",
  "event_id": "0e7c3b1a-5d2f-5a8e-9c4b-6f1d2e3a4b5c",
  "idempotency_key": "review-captain:1:request:7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d:skipped:4",
  "request_uuid": "7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d",
  "campaign_id": 12,
  "experience_id": "pass-captain:1:attendance:T-7781:OCC-55",
  "source": "pass-captain",
  "status": "skipped",
  "previous_status": "eligible",
  "status_code": "rc.consent.revoked",
  "target_platform": "google",
  "reminder_count": 0,
  "occurred_at": "2026-11-17T09:00:04Z"
}
```

`review_captain.review.v1` (für `review.*`, identisch zu conventions.md + `contract`/`idempotency_key`):

```json
{
  "schema": "review_captain.review.v1",
  "contract": "1.0",
  "event_id": "3a9f1c2e-7b4d-5e6f-8a1b-2c3d4e5f6a7b",
  "idempotency_key": "review-captain:1:review:0b7e9a3c-51a2-4c1e-8f3e-2a9d7c6b5e44:approved:2",
  "review_uuid": "0b7e9a3c-51a2-4c1e-8f3e-2a9d7c6b5e44",
  "kind": "review",
  "platform": "local",
  "status": "approved",
  "previous_status": "pending",
  "origin": "local_submission",
  "occurred_at": "2026-11-16T12:00:00Z"
}
```

`review_captain.recipient.v1` (für `recipient.opted_out`) – ohne jede Empfängeridentität:

```json
{
  "schema": "review_captain.recipient.v1",
  "contract": "1.0",
  "event_id": "9b8a7c6d-5e4f-5a3b-8c2d-1e0f9a8b7c6d",
  "idempotency_key": "review-captain:1:optout:7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d:1",
  "request_uuid": "7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d",
  "scope": "site",
  "occurred_at": "2026-11-18T07:30:00Z"
}
```

Logging der Trigger-Auslieferung: Trigger-Key, `event_id`, Ergebnis, Fehlercode – keine Payload-Kopie mit Texten/E-Mail/Tokens (es gibt keine).

## RC-WF-03 – Actions/Services

Alle Services sind PHP-Funktionen des Core (öffentlicher, lizenzfreier Vertrag). Anfrage-Services delegieren an die von Pro registrierte Implementierung (`\ReviewCaptain\Api::register_request_service( RequestServiceInterface $service )` während `review_captain_loaded`); ohne Pro liefern sie `rc.contract.unavailable`.

Rückgabe aller Services:

```json
{ "success": false, "status": "rejected", "code": "rc.consent.missing", "data": {} }
```

### `review_captain_plan_request_v1( array $input ): array`

Eingabe:

```json
{
  "contract": "1.0",
  "experience_id": "pass-captain:1:attendance:T-7781:OCC-55",
  "recipient_ref": "pass-captain:attendee:9f8e7d6c",
  "campaign_id": 12,
  "idempotency_key": "workflow-captain:1:run:88123:plan",
  "requested_by": "workflow:417"
}
```

Erzwungene Prüfungen (identisch zu T1, nicht konfigurierbar durch Workflow):

| Prüfung | Fehlercode |
|---|---|
| Entitlements `review.suite_integrations` + `review.review_requests` | `rc.entitlement.missing` |
| Kampagne existiert, aktiv, `trigger_owner = workflow` (Ownership-Übergabe; native Auslöser ignorieren diese Kampagne) | `rc.request.not_eligible` |
| Erlebnis existiert in `experiences` mit `state = completed` (aus einem gültigen `experience_completed` einer Quelle) | `rc.experience.not_completed` |
| Erlebnis nicht `revoked` (Refund, Absage, No-show, Void) | `rc.experience.revoked` |
| `send_permission` des letzten Ereignisses für diesen Empfänger = `granted` | `rc.consent.missing` |
| Empfänger nicht supprimiert / Opt-out | `rc.recipient.suppressed` |
| Einladungsschlüssel + Suite-Sperre frei | `rc.request.duplicate` |
| Frequenzgrenze (Vorprüfung; endgültig beim Versand) | `rc.request.frequency_limited` |

Workflow kann **nicht** setzen: Zielplattform, Vorlage, Verzögerung außerhalb der Kampagne, Consent-Status, Empfängerkontakt, Moderationsstatus. Ergebnis `data`: `{ "request_uuid": "…", "status": "planned", "due_at": "…" }`.

### `review_captain_cancel_request_v1( array $input ): array`

```json
{ "contract": "1.0", "request_uuid": "7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d", "reason": "workflow_rule", "idempotency_key": "workflow-captain:1:run:88124:cancel", "requested_by": "workflow:417" }
```

Nur `planned`/`eligible` (T5, `rc.request.cancelled_by_workflow`). Bereits gesendete Anfragen: `rc.request.invalid_transition`. Abbrechen ist immer erlaubt, auch für native Kampagnen (Abbruch kann Regeln nicht umgehen, nur verschärfen).

### `review_captain_create_local_review_v1( array $payload ): array`

(Name aus konzept.md 7.4 ohne `wp_`-Präfix.) Erstellt genau einen **lokalen, wartenden, nicht verifizierten** Review-Entwurf.

```json
{
  "contract": "1.0",
  "rating": 4,
  "review_text": "Kurs war gut strukturiert.",
  "title": "Guter Einstieg",
  "display_name": "Lena",
  "review_type": "service",
  "context_type": "form",
  "context_id": "contact-form-3",
  "source_plugin": "form-captain",
  "source_entry_id": "5531",
  "idempotency_key": "form-captain:1:5531",
  "verified": false
}
```

| Regel | Umsetzung |
|---|---|
| `rating` | Ganzzahl 1–5, Pflicht |
| `review_text` | Pflicht, Klartext, Längenlimits wie lokales Formular |
| `verified` | muss `false` sein, sonst `rc.data.invalid_payload` |
| Ergebnisfelder | `kind=review`, `origin=connector`, `platform=local`, `status=pending` (erzwungen), `verification=unverified` (erzwungen), `body=review_text`, `author_name=display_name`, `external_id=idempotency_key`, `meta.source_plugin`, `meta.source_entry_id`, `meta.review_type`, `meta.idempotency_key` |
| Idempotenz | zweiter Aufruf mit gleichem `idempotency_key` → `success=true`, `status=duplicate`, gleiche `review_id` |
| keine Einladung | erzeugt nie eine Review-Anfrage, nie Plattformimport, nie Veröffentlichung |

Rückgabe `data`: `{ "review_id": 981, "review_uuid": "…" }` (konzept: `success/code/review_id`).

### `review_captain_delete_local_review_draft_v1( array $input ): array`

`{ "contract": "1.0", "source_plugin": "form-captain", "source_entry_id": "5531" }` → löscht den verknüpften Review **nur** wenn `status = pending`; sonst `success=false`, `code=rc.review.not_pending` und ein Admin-Hinweis in beiden Plugins (freigegebene Reviews folgen den Privacy-Regeln, konzept 7.3 E7).

### `review_captain_get_review_summary_v1( array $input ): array`

```json
{ "contract": "1.0", "context_type": "event", "context_id": "OCC-55", "kind": "review", "period_from": "2026-01-01T00:00:00Z", "period_to": null, "min_sample": 5 }
```

```json
{
  "success": true,
  "status": "ok",
  "code": null,
  "data": {
    "as_of": "2026-11-18T08:00:00Z",
    "by_platform": [
      { "platform": "local", "count": 14, "rating_value": 4.43, "rating_scale_min": 1, "rating_scale_max": 5, "sample_ok": true },
      { "platform": "google", "count": 3, "rating_value": null, "rating_scale_min": 1, "rating_scale_max": 5, "sample_ok": false }
    ]
  }
}
```

Nur sichtbare Datensätze (Sichtbarkeitsregel); je Plattform getrennt, Originalskala; kein plattformübergreifender Score; unter `min_sample` (Default 5, mindestens 3 erzwungen – Produktentscheidung offen) kein Durchschnitt. Keine Texte, Namen, IDs einzelner Reviews.

## RC-WF-06 – Zulässige Auslöser je Quelle

| Quelle / Ereignis | darf Anfrage planen | blockiert |
|---|---|---|
| Pass Captain: gültiger Check-in bzw. „Teilnahme bestätigt“ (`experience_completed`) | ja | Refund (`experience_refunded`), Absage (`experience_cancelled`), No-show (`experience_no_show`), Check-in aufgehoben (`experience_voided`), Consent fehlt/unbekannt, Opt-out/Suppression, Duplikat (S1–S3) |
| Voucher Captain: Einlösung einer als Leistung klassifizierten Gutscheinart (`experience_completed`) | ja | Ausgabe/Kauf/Zustellung/Guthaben (kein `completed`), Refund, Void, Consent fehlt, Opt-out, Duplikat |
| WooCommerce: konfigurierter Bestellstatus | ja (native oder Workflow) | Refund/Storno/Chargeback, Positionen mit Ownership-Übergabe, Consent fehlt, Opt-out, Duplikat |
| Form Captain: Einsendung | **nein** – nur `review_captain_create_local_review_v1` für explizit gemappte Felder; Ergebnis immer `pending`/`unverified` | jeder Versuch, aus einer Einsendung `plan_request` aufzurufen → `rc.experience.not_completed` (Form erzeugt kein `experiences`-Completion) |
| Workflow-eigene Ereignisse ohne Quell-Experience | nein | `rc.experience.not_completed` |

## Abnahme

- [ ] Providervertrag v1 dokumentiert Schema-Version, stabile Event-ID (UUID v5 aus Idempotency-Key), Capability, Entitlement, Payload-Allowlist, Idempotency-Key und Deprecation-Regel.
- [ ] Alle RC-WF-02-Trigger feuern ausschließlich nach Commit; Payload-Scan bestätigt: keine Reviewtexte, E-Mail, Tokens, `recipient_ref`.
- [ ] `plan_request` durchläuft denselben Planner wie die native Queue; Contract-Tests für jede Zeile der Prüftabelle.
- [ ] Workflow kann Einladungsberechtigung, Consent, Opt-out, Suppression, Frequenzlimits, Plattformziel und Moderation nicht umgehen (negativer Test je Punkt).
- [ ] Höchstens eine Einladung je Experience/Empfänger/Kampagne – auch bei paralleler nativer und Workflow-Planung (race-conditions.md R-07).
- [ ] Form-Entwurf ist immer `pending`/`unverified`, idempotent, ohne Einladung.
- [ ] Ohne Workflow Captain oder mit inkompatiblem Vertrag läuft Review Captain normal; Status `incompatible_contract`/`source_unavailable` sichtbar.
