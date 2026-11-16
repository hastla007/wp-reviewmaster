# RC-WF-01 – Workflow-Captain-Providervertrag v1

**Status:** Entwurf – Abgleich mit dem Workflow-Captain-Integrationsvertrag offen  
**Stand:** 2026-11-16

## Grundsätze

- Review Captain ist **Provider** für Workflow Captain: stellt Trigger (Ereignisse) und Actions (Services) bereit.
- Workflow kann keine Review-Regel umgehen: Einladungsberechtigung, Consent, Opt-out, Suppression, Cooldown, Plattformziel und Moderation werden in Review Captain geprüft, nicht im Workflow.
- Registrierung nur, wenn Review Captain Pro mit `review.suite_integrations` aktiv ist; ohne Recht werden Trigger/Actions als „nicht verfügbar“ gemeldet, Review Core läuft normal.

## Provider-Deskriptor

```php
[
  'provider'       => 'review-captain',
  'schema_version' => '1.0',
  'entitlement'    => 'review.suite_integrations',
  'capability'     => 'rc_manage_requests',       // für das Konfigurieren im Workflow-Editor
  'triggers'       => [ /* siehe unten */ ],
  'actions'        => [ /* siehe unten */ ],
  'deprecation'    => 'Felder werden frühestens zwei Minor-Releases und 6 Monate nach Ankündigung entfernt; Major = neuer Provider-Schlüssel review-captain.v2',
]
```

## Trigger (Post-Commit, RC-WF-02)

Alle Trigger feuern erst nach erfolgreichem DB-Commit, tragen `event_id` (UUID v4, stabil bei Wiederholung) und `idempotency_key = sha256(trigger|object_uuid|state_version)`.

| Trigger | Payload-Allowlist |
|---|---|
| `review.request.planned` / `skipped` / `sent` / `failed` | `request_uuid`, `campaign_id`, `experience_id`, `status`, `reason_code`, `occurred_at` |
| `review.request.reminder_sent` | wie oben |
| `review.created` / `review.moderated` / `review.published` / `review.withdrawn` | `review_uuid`, `provider`, `type`, `rating_value`, `status`, `context_type`, `context_id`, `occurred_at` |
| `review.recipient.opted_out` | `opt_out_uuid`, `occurred_at` (keine E-Mail) |

Nie enthalten: Reviewtext, Namen, E-Mail, Tokens.

## Actions / Services (RC-WF-03)

| Action | Eingabe | Verhalten |
|---|---|---|
| `review.request.plan` | Erlebnis-Event (Schema experience-event-v1) | identisch zum Suite-Weg; Dedupe über `invite_key` |
| `review.request.cancel` | `experience_id` | storniert nicht gesendete Einladungen |
| `review.draft.create` | Felder wie `wp_review_captain_create_local_review_v1` | immer `pending`, `unverified` |
| `review.aggregate.read` | `context_type`, `context_id`, Zeitraum | Anzahl, Durchschnitt je Quelle; nur ab Mindeststichprobe 5 |

## Ownership-Übergabe (RC-WF-04)

Standard: native Queue von Review Captain plant Einladungen. Ein Betreiber kann pro Kampagne „Auslösung durch Workflow Captain“ wählen; dann ignoriert die native Kampagne Suite-Ereignisse für diese Quelle. Parallele Wege werden durch `invite_key` dedupliziert.
