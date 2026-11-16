# Verträge v1 – RC-020, RC-021, RC-023

**Status:** Entwurf – wird mit RC-032 ff. als PHP-Interfaces im Core veröffentlicht  
**Stand:** 2026-11-16  
**Versionierung:** SemVer je Vertrag; jede Schnittstelle trägt eine eigene Konstante (`PROVIDER_CONTRACT`, `CONNECTOR_CONTRACT`, `RESOLVER_CONTRACT`), damit eine Klasse mehrere Verträge gleichzeitig implementieren kann (`provider/1.0`, `experience-event/1.0`, `recipient-resolver/1.0`). Minor = abwärtskompatible Erweiterung (neue optionale Felder/Methoden mit Default); Major = Bruch → neuer Hook-/Interface-Name mit Suffix `_v2`. Deprecation-Frist: mindestens zwei Minor-Releases und 6 Monate.

## RC-020 – Provider-Vertrag v1

Namespace `ReviewCaptain\Contracts\Provider`. Registrierung über Filter `wp_review_captain_providers_v1` (Array `key => ProviderInterface`). Core bringt nur den Provider `site` (eigene Reviews) und `manual` mit; API-Provider liefert Pro.

```php
interface ProviderInterface {
    const PROVIDER_CONTRACT = '1.0';
    public function key(): string;                 // 'google', 'apple_app_store', …
    public function label(): string;
    public function capabilities(): array;         // siehe unten
    public function connect( array $input ): ConnectResult;          // OAuth-Start-URL oder Credential-Validierung
    public function test( Connection $c ): StatusResult;              // leichter API-Aufruf, keine Daten speichern
    public function list_profiles( Connection $c ): array;            // ProfileRef[] – nur berechtigte
    public function sync_page( Connection $c, ProfileRef $p, ?string $cursor, array $opts ): SyncPage;
    public function normalize( array $raw, ProfileRef $p ): NormalizedReview;
    public function quota_status( Connection $c ): QuotaStatus;
    public function disconnect( Connection $c ): StatusResult;        // Token widerrufen soweit unterstützt
}
```

**capabilities()** – Discovery statt Annahmen: `read_reviews`, `read_summary`, `delta_by_updated_at`, `full_listing` (alle historischen Reviews abrufbar), `reports_deletions` (liefert Löschungen) vs. `deletion_by_absence` (Löschung = fehlt im Volllauf), `reply`, `invite`, `max_cache_hours` (int), `requires_attribution` (string), `rating_scale` ([min,max]).

**SyncPage:** `items` (raw[]), `next_cursor` (?string), `complete` (bool), `deleted_external_ids` (string[]), `rate_limit` (`remaining`, `reset_at`, `retry_after`).

**NormalizedReview** entspricht `rc_reviews`-Feldern (RC-018) ohne interne IDs: `external_id` (Pflicht), `type`, `rating_value`, `rating_min/max`, `title`, `content`, `language`, `author_name`, `author_avatar_url`, `published_at`, `source_updated_at`, `source_url`, `reply_text`, `reply_at`, `context_type/id`, `meta`.

**Pflichtverhalten:** keine Netzwerkaufrufe außer in `connect/test/list_profiles/sync_page/quota_status/disconnect`; Exceptions nur vom Typ `ProviderException` mit Code aus RC-030; keine Secrets in Exception-Messages; Timeout ≤ 15 s pro Request.

**Fixture-Beispiel** (`tests/fixtures/provider/google-page-1.json`, wird mit RC-083 im Pro-Repo befüllt):

```json
{ "items": [ { "reviewId": "abc123", "starRating": "FIVE", "comment": "Sehr gut", "createTime": "2026-11-01T10:00:00Z", "updateTime": "2026-11-01T10:00:00Z", "reviewer": { "displayName": "Anna M." } } ],
  "next_cursor": "token-2", "complete": false, "deleted_external_ids": [] }
```

## RC-021 – Ereignisvertrag Erlebnis v1

Aktion `wp_review_captain_experience_event_v1( array $event )`. Formales Schema: [`docs/contracts/experience-event-v1.schema.json`](../contracts/experience-event-v1.schema.json).

| Feld | Typ | Pflicht | Regel |
|---|---|---|---|
| `contract` | string | ✓ | `"1.0"`; unbekannte Major → abgelehnt mit `incompatible_contract` |
| `source` | string | ✓ | Schlüssel `^[a-z0-9-]{2,40}$` (`woocommerce`, `voucher-captain`, `pass-captain`, `form-captain`, `connector:{key}`) |
| `event_id` | string | ✓ | global eindeutig je Quelle; Idempotenz `sha256(source|event_id)` |
| `experience_id` | string | ✓ | vom besitzenden Plugin vergeben (RC-022), max. 191 Zeichen |
| `event_type` | enum | ✓ | `experience_completed`, `experience_cancelled`, `experience_refunded`, `experience_no_show`, `recipient_opted_out` |
| `occurred_at` | string (RFC 3339 UTC) | ✓ | |
| `object` | object | ✓ | `{ "type": "product|voucher_product|event|appointment|course|booking", "id": "…", "label": "…"? }` |
| `recipient_ref` | string | bei `experience_completed` ✓ | site-lokal, opak, **keine E-Mail**; max. 191 |
| `locale` | string | | BCP-47 |
| `communication` | object | bei `experience_completed` ✓ | `{ "basis": "consent|existing_customer|unknown|denied", "recorded_at": "…", "source": "checkout|form|account|…", "text_version": "…"? }` |
| `verification` | enum | | `purchase` (nur WooCommerce/Voucher nach Zahlung), `attendance` (nur Pass nach Check-in), sonst weglassen |
| `context` | object | | Allowlist: `campaign_ref`, `order_item_ids` (int[]), `channel`; max. 2 KB |

Verarbeitung: Event wird validiert, in `rc_experiences` (Pro) idempotent abgelegt, dann asynchron per Job verarbeitet. Ohne Pro: Core validiert und verwirft mit Diagnosezähler (kein Speichern personenbezogener Referenzen).

Rückgabe für synchrone Aufrufer über Funktion `wp_review_captain_submit_experience_event_v1( array $event ): array` → `['accepted'=>bool,'code'=>string,'event_key'=>string]`.

## RC-023 – Kontaktresolver-Vertrag v1

Filter `wp_review_captain_recipient_resolvers_v1` (`source => RecipientResolverInterface`).

```php
interface RecipientResolverInterface {
    const RESOLVER_CONTRACT = '1.0';
    /** Wird ausschließlich unmittelbar vor dem Versand aufgerufen. */
    public function resolve( string $recipient_ref, string $experience_id ): ResolvedRecipient;
}
final class ResolvedRecipient {
    public string $status;      // 'ok' | 'unknown' | 'revoked' | 'erased'
    public ?string $email;      // nur bei 'ok'
    public ?string $first_name; // optional
    public ?string $locale;
    public string $basis;       // aktueller Stand: consent|existing_customer|unknown|denied
}
```

Regeln: Ergebnis wird **nicht** gespeichert (nur für den Versandvorgang im Speicher); `status != ok` oder `basis ∈ {unknown, denied}` → Einladung `skipped` mit Grund `recipient_unresolved` bzw. `consent_missing`; fehlt der Resolver (Plugin inaktiv) → `paused` mit `source_unavailable`. Der Resolver muss Opt-outs der Quelle berücksichtigen; zusätzlich prüft Review Captain die eigene Suppression-Liste.
