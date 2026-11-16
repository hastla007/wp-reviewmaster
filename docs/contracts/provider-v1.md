# Review-Provider-Vertrag v1 (RC-020)

| | |
|---|---|
| Status | Spezifikation, Entwurf zur Abnahme |
| Vertragsversion | `1.0` (SemVer Major.Minor) |
| Registry-Hook | `review_captain_providers_v1` (action, conventions.md) |
| Namespace | `ReviewCaptain\Contracts\Provider\` (Core, lizenzfrei) |
| Bezug | TODO RC-020, RC-082, RC-083, TEC-003, TEC-006, TEC-007; konzept.md 4, 7.9, 10 |

> **Keine Anbieterfakten in diesem Dokument.** Alle Fixtures verwenden den fiktiven Provider `demo` (`platform = other`, Host `reviews.example.test`). Werte für Google, Trustpilot, Trusted Shops, App Store und Google Play (Auth-Art, Scopes, Seitengröße, Cache-TTL, Rate Limits, Lösch-Feeds) werden erst im jeweiligen Auth-/Provider-Record (RC-010–RC-013, RC-026) bestätigt und im Provider über `capabilities()` mit `facts_status` ausgewiesen.

## Grundsätze

1. Ein Provider ist ein **Adapter**: Er spricht mit genau einer Plattform-API, liefert Rohdaten und normalisiert sie. Persistenz, Dedupe, Tombstones, Jobs, Locks, Budgets, Credentials-Speicherung und Sichtbarkeit sind Aufgabe des Core bzw. der Pro-Sync-Runtime.
2. Provider sind **read-only** in v1. `write_features` muss leer sein; Antworten/Einladungen über Plattform-APIs sind späterer Vertragsumfang.
3. Kein Provider-Aufruf im Frontend-Request. Aufrufe nur aus Jobs, WP-CLI oder Admin-Aktionen mit Capability `review_captain_manage_sources` + Nonce.
4. Provider persistieren keine Credentials selbst. Sie bekommen sie über `Connection` und geben Änderungen (Token-Refresh) im Ergebnis zurück.
5. Ein Ausfall eines Providers blockiert keine anderen Quellen. Inkompatible Provider werden pausiert, nie fatal.

## Registrierung

```php
add_action( 'review_captain_providers_v1', static function ( \ReviewCaptain\Contracts\Provider\ProviderRegistry $registry ): void {
    $registry->register( new \Acme\ReviewDemo\DemoProvider() );
} );
```

- Der Core feuert `review_captain_providers_v1` einmal pro Request, nachdem `review_captain_loaded` gelaufen ist (Lazy: nur wenn Admin-Quellenseite, Job oder CLI Provider benötigt).
- `ProviderRegistry::register( ReviewProviderInterface $provider ): RegistrationResult` validiert:

| Prüfung | Fehler | Folge |
|---|---|---|
| `key()` passt auf `^[a-z0-9][a-z0-9_.-]{1,63}$` | `rc.contract.invalid_metadata` | nicht registriert, Admin-Hinweis |
| `key()` noch nicht registriert | `rc.contract.duplicate_key` | erster gewinnt, zweiter abgewiesen |
| `platform()` ∈ `reviews.platform`-Enum | `rc.contract.invalid_metadata` | nicht registriert |
| Major von `contract_version()` == `1` | `rc.contract.incompatible` | registriert mit Status `incompatible_contract`, keine Ausführung |
| Minor > vom Core unterstützter Minor | – | registriert, Felder unbekannter Minor werden ignoriert |
| `capabilities()` valide, `write_features` leer | `rc.contract.invalid_capabilities` | Status `incompatible_contract` |

Offizielle Provider tragen `key() === platform()` (z. B. `google`). Drittanbieter-Provider verwenden `platform() = 'other'` und einen eigenen Key mit Präfix (`acme.bookings`).

## Interface

```php
namespace ReviewCaptain\Contracts\Provider;

interface ReviewProviderInterface {
    public function key(): string;              // stabiler Schlüssel, z. B. 'demo'
    public function platform(): string;         // Wert aus reviews.platform
    public function label(): string;            // übersetzbarer Anzeigename
    public function version(): string;          // SemVer der Implementierung
    public function contract_version(): string; // '1.0'
    public function capabilities(): array;      // siehe „capabilities()“

    /** Verbindungsaufbau (OAuth-Start/-Callback oder Credential-Eingabe). */
    public function connect( array $request ): array;

    /** Verbindung + Berechtigung prüfen, ohne Daten zu importieren. */
    public function test( Connection $connection ): array;

    /** Zugängliche Profile/Standorte/Apps auflisten (paginierbar). */
    public function list_profiles( Connection $connection, ?string $cursor = null ): array;

    /** Eine Seite Reviews eines Profils abrufen. */
    public function sync_page( Connection $connection, array $profile, ?string $cursor, array $options ): array;

    /** Ein Roh-Item in einen Datensatz nach review.v1 (Provider-Felder) überführen. Rein, ohne I/O. */
    public function normalize( array $raw, array $profile ): array;

    /** Verbindung trennen: Token beim Anbieter widerrufen, soweit unterstützt. */
    public function disconnect( Connection $connection ): array;

    /** Aktueller Quota-/Rate-Limit-Zustand. Darf gecachte Werte aus letzter Antwort liefern. */
    public function quota_status( Connection $connection ): array;
}
```

`Connection` (Core-Value-Object, read-only): `profile_connection_id(): int`, `credential( string $name ): ?string` (entschlüsselt nur im Speicher), `account_ref(): ?string`, `http(): HttpClient` (Core-Client mit Host-Allowlist aus `capabilities().hosts`, Timeouts, SSRF-Schutz, Logging ohne Header/Body).

### Fehler

Methoden werfen bei Fehlern `ReviewCaptain\Contracts\Provider\ProviderException`:

```php
new ProviderException( string $code, string $safe_message = '', ?int $retry_after_seconds = null, array $context = [] );
```

- `$code` muss ein Code aus [`../architecture/error-codes.md`](../architecture/error-codes.md) sein; unbekannte Codes werden als `rc.perm.failed` behandelt.
- `$safe_message` enthält keine Tokens, Header, Reviewtexte oder E-Mails (max. 500 Zeichen, wird zusätzlich sanitisiert).
- `$context` nur Allowlist-Schlüssel: `http_status`, `endpoint_key` (symbolisch, keine URL mit Query), `profile_external_ref`.

Empfohlene Abbildung von HTTP-Antworten:

| Situation | Code |
|---|---|
| 401, Token abgelaufen und Refresh möglich | `rc.auth.expired` (Core triggert Refresh, dann Retry) |
| 401 sonst / Refresh fehlgeschlagen | `rc.auth.invalid` |
| 403 fehlender Scope/Tarif | `rc.auth.insufficient_scope` |
| 404 Profil | `rc.remote.not_found` |
| 429 | `rc.quota.rate_limited` (+ `Retry-After`) |
| Tages-/Monatskontingent erschöpft | `rc.quota.exceeded` |
| 5xx | `rc.remote.server_error` |
| Timeout / Verbindung | `rc.net.timeout` / `rc.net.connection` |
| Unerwartetes Antwortformat | `rc.data.invalid_payload` |

## Methoden im Detail

### `capabilities()`

```json
{
  "contract": "1.0",
  "facts_status": "fixture",
  "auth": ["api_key"],
  "hosts": ["reviews.example.test"],
  "features": ["list_profiles", "sync_full", "sync_delta", "deletion_feed", "full_listing", "summary"],
  "write_features": [],
  "rating_scale": { "min": 1, "max": 5, "step": 1 },
  "max_page_size": 50,
  "default_page_size": 20,
  "max_cache_ttl_seconds": 86400,
  "min_sync_interval_seconds": 3600,
  "rate_limit": { "requests": 60, "per_seconds": 60 },
  "config_fields": [
    { "key": "api_key", "type": "secret", "label": "API-Schlüssel", "required": true }
  ]
}
```

| Feld | Bedeutung |
|---|---|
| `facts_status` | `fixture` \| `documented` (aus Anbieterdoku übernommen, nicht getestet) \| `sandbox_verified` \| `live_verified`. Admin zeigt Werte < `sandbox_verified` als „unbestätigt“. Live-Release eines Adapters erst ab `live_verified` (TODO Arbeitsregeln). |
| `auth` | `oauth2` \| `api_key` \| `service_account` \| `signed_jwt` \| `none` |
| `hosts` | exakte Hosts für den Core-HTTP-Client (keine Wildcards) |
| `features` | `list_profiles`, `sync_full`, `sync_delta`, `deletion_feed` (API liefert Löschungen), `full_listing` (vollständige Auflistung ermöglicht Reconcile durch Abwesenheit), `summary` (Plattform-Score/Anzahl), `replies_read` |
| `write_features` | v1: muss `[]` sein (reserviert: `replies_write`, `invitations`) |
| `max_cache_ttl_seconds` | maximale Anzeigedauer ohne Refresh → `refresh_due_at` (review-schema-v1.md) |
| `config_fields` | typisiertes Konfigurationsschema (`text`, `url`, `select`, `number`, `secret`), CON-011 |

### `connect( array $request ): array`

Drei Modi; der Core prüft State/Nonce/Redirect-URI **vor** dem Aufruf (TEC-010) und übergibt nur geprüfte Werte.

| `mode` | Eingabe | Ausgabe |
|---|---|---|
| `oauth_start` | `redirect_uri`, `state` (vom Core erzeugt, gebunden an User + Nonce, 10 min gültig) | `{ "type": "redirect", "authorize_url": "https://…" }` |
| `oauth_callback` | `code`, `redirect_uri` (State bereits geprüft) | `{ "type": "connected", "credentials": {…}, "credentials_expires_at": "…", "scopes": […], "account": { "external_ref": "…", "label": "…" } }` |
| `credentials` | `fields` gemäß `config_fields` | wie `oauth_callback` |

Credentials werden vom Core verschlüsselt gespeichert (TEC-008) und nie geloggt.

Beispiel `credentials`-Ergebnis (Fixture):

```json
{
  "type": "connected",
  "credentials": { "api_key": "«wird verschlüsselt gespeichert»" },
  "credentials_expires_at": null,
  "scopes": ["reviews:read"],
  "account": { "external_ref": "acct-demo-1", "label": "Demo GmbH" }
}
```

### `test( Connection ): array`

```json
{ "ok": true, "account": { "external_ref": "acct-demo-1", "label": "Demo GmbH" }, "scopes": ["reviews:read"], "checks": [ { "key": "auth", "ok": true }, { "key": "read_reviews", "ok": true } ] }
```

Fehlschlag: `ProviderException` (z. B. `rc.auth.insufficient_scope`). `test()` importiert nichts und verbraucht höchstens 2 API-Requests.

### `list_profiles( Connection, ?cursor ): array`

```json
{
  "profiles": [
    { "external_ref": "loc-100", "label": "Filiale Wien", "public_url": "https://reviews.example.test/p/loc-100", "language": "de-AT", "kind": "location", "selectable": true, "reason": null },
    { "external_ref": "loc-200", "label": "Filiale Graz (nicht verifiziert)", "public_url": null, "language": "de-AT", "kind": "location", "selectable": false, "reason": "rc.auth.insufficient_scope" }
  ],
  "next_cursor": null
}
```

Nur `selectable = true` darf der Admin auswählen (RC-066). Die Auswahl wird serverseitig gegen eine frische `list_profiles`-Antwort geprüft (keine Profilwahl per manipuliertem Request, TEC-010).

### `sync_page( Connection, profile, ?cursor, options ): array`

`options`: `{ "mode": "full"|"delta", "page_size": int ≤ max_page_size, "since": ISO8601|null, "run_uuid": "…" }`.

```json
{
  "items": [ { "…": "Rohdaten des Anbieters, unverändert" } ],
  "removed": [ { "external_id": "demo-rev-0999", "reason": "remote_removed" } ],
  "next_cursor": "eyJwIjoyfQ",
  "has_more": true,
  "summary": { "rating_value": 4.6, "rating_scale_min": 1, "rating_scale_max": 5, "count": 1234, "as_of": "2026-11-16T06:00:00Z" },
  "quota": { "state": "ok", "remaining": 57, "reset_at": "2026-11-16T06:01:00Z", "retry_after_seconds": null, "source": "provider_header" },
  "credentials_update": null
}
```

Regeln:

- **Cursor** ist ein opaker String ≤ 1024 Byte (Provider darf Base64-JSON verwenden). Der Core speichert ihn erst **nach** Commit der Seite (siehe Ablauf). `null` + `has_more=false` = Lauf beendet.
- `items` ≤ `page_size`. Leere Seite mit `has_more=true` ist erlaubt (Lücken in der API).
- `removed` nur bei Feature `deletion_feed`. Bei `full_listing` ohne Lösch-Feed tombstont der Core am Ende eines **vollständigen** Full-Laufs alle Datensätze des Profils, deren `meta.last_verified_at` älter als der Laufbeginn ist (Reason `remote_not_found`). Abgebrochene Läufe tombstonen nie.
- `summary` nur bei Feature `summary`; wird in den Profildaten gespeichert (Plattform-Bewertungsleiste RC-112).
- `credentials_update` nicht leer → Core speichert die neuen Credentials atomar (siehe `race-conditions.md` R-04) bevor die Items verarbeitet werden.

### `normalize( array $raw, array $profile ): array`

Rein funktional, ohne I/O, deterministisch. Liefert nur die Provider-Felder aus review-schema-v1.md:

`kind`(=`review`), `platform`, `external_id`, `rating_value`, `rating_scale_min`, `rating_scale_max`, `title`, `body`, `language`, `author_name`, `context_type`, `context_id`, `source_url`, `published_at`, `meta` (nur Allowlist-Schlüssel).

Der Core ergänzt `uuid`, `origin=provider_sync`, `profile_id`, `verification=external_platform`, `status`, `fetched_at`, `refresh_due_at`, `consent_publish=0`, Zeitstempel und validiert gegen [`schema/review.v1.json`](schema/review.v1.json). Ungültige Items werden einzeln mit `rc.data.invalid_payload` verworfen und gezählt; die Seite scheitert nicht als Ganzes.

### `disconnect( Connection ): array`

```json
{ "revoked": true, "method": "api_revoke" }
```

`method`: `api_revoke` \| `not_supported` (Admin erhält Hinweis, Zugang beim Anbieter manuell zu entziehen). Unabhängig vom Ergebnis löscht der Core die Credentials, setzt offene Provider-Jobs auf `cancelled` (konzept 7.9) und wendet die Aufbewahrungsregel für Snapshots an (RC-027; Ablauf → Tombstone `disconnect_retention`).

### `quota_status( Connection ): array`

```json
{ "state": "warning", "remaining": 4, "reset_at": "2026-11-16T07:00:00Z", "retry_after_seconds": null, "source": "provider_header" }
```

`state`: `ok` \| `warning` \| `exhausted` \| `unknown`. Unabhängig davon erzwingt der Core lokale Budgets je Profil (TEC-007).

## Sync-Ablauf (Core/Pro-Runtime)

```mermaid
sequenceDiagram
    participant J as Job sync.page
    participant P as Provider
    participant DB as reviews / sync_runs
    J->>DB: Lauf-Lock prüfen (sync_runs.active_profile_id UNIQUE)
    J->>P: sync_page(connection, profile, cursor, options)
    P-->>J: items, removed, next_cursor, quota
    J->>J: normalize + validate je Item
    J->>DB: BEGIN; Upsert via dedupe_key; Tombstones; meta.last_verified_at
    J->>DB: UPDATE sync_runs SET cursor=next, page_no=page_no+1 WHERE id=? AND generation=?; COMMIT
    J->>J: has_more ? Folgejob sync.page (idempotency sync:{profile_id}:{run_uuid}:{page_no}) : Lauf abschließen
```

- Verarbeitung ist **at-least-once**; Idempotenz durch `dedupe_key`-Upsert und Cursor-CAS (`generation`).
- Pro-Tabelle `{prefix}review_captain_sync_runs` (Vorschlag): `id`, `uuid`, `profile_id`, `mode`, `status` (`running`\|`succeeded`\|`failed`\|`cancelled`), `active_profile_id` BIGINT NULL **UNIQUE** (= `profile_id` solange `running`, sonst NULL → höchstens ein aktiver Lauf je Profil), `cursor` TEXT, `page_no`, `generation` INT, `items_upserted`, `items_invalid`, `items_removed`, `last_error_code`, `started_at`, `finished_at`.
- Fehlerbehandlung je Retry-Klasse aus `error-codes.md`; `rc.auth.*` pausiert das Profil (Status `auth_required`), `rc.quota.*` verschiebt auf `retry_after`/`reset_at`.

## Fixtures

Ablage: `tests/fixtures/providers/<key>/` (für `demo` zusätzlich als Beispiel unter `examples/`). Pflicht-Fixtures je Provider (RC-083):

| Datei | Inhalt |
|---|---|
| `capabilities.json` | Ergebnis von `capabilities()` |
| `list_profiles.json` | inkl. eines nicht auswählbaren Profils |
| `sync_page_1.json`, `sync_page_2.json` | Pagination mit Cursor, ein Duplikat über Seitengrenze |
| `sync_page_changed.json` | geänderte Review (gleiche `external_id`) |
| `sync_page_removed.json` | Löschung (`removed` oder Abwesenheit bei Full-Lauf) |
| `raw_item.json` + `normalized_item.json` | Paar für `normalize()`-Test |
| `error_401.json`, `error_403.json`, `error_429.json`, `error_5xx.json`, `invalid_payload.json` | Fehlerabbildung |

### Beispiel `demo/sync_page_1.json`

```json
{
  "request": { "profile_external_ref": "loc-100", "cursor": null, "options": { "mode": "full", "page_size": 2, "since": null, "run_uuid": "5d0a7e3b-2c1f-4e8a-9b6d-7f0e1a2b3c4d" } },
  "response": {
    "items": [
      { "id": "demo-rev-1001", "stars": 4, "headline": "Schnelle Lieferung", "text": "Alles wie beschrieben, gerne wieder.", "lang": "de_DE", "reviewer": { "display": "M. K." }, "created": "2026-11-10T09:12:00Z", "updated": "2026-11-10T09:12:00Z", "link": "https://reviews.example.test/r/demo-rev-1001" },
      { "id": "demo-rev-1002", "stars": 2, "headline": "", "text": "Wartezeit am Telefon war lang.", "lang": "de", "reviewer": { "display": "" }, "created": "2026-11-11T17:40:00Z", "updated": "2026-11-12T08:00:00Z", "link": "https://reviews.example.test/r/demo-rev-1002", "reply": { "text": "Danke für den Hinweis.", "date": "2026-11-12T08:00:00Z" } }
    ],
    "removed": [],
    "next_cursor": "eyJwIjoyfQ",
    "has_more": true,
    "summary": null,
    "quota": { "state": "ok", "remaining": 59, "reset_at": "2026-11-16T06:01:00Z", "retry_after_seconds": null, "source": "provider_header" },
    "credentials_update": null
  }
}
```

### Beispiel `demo/normalized_item.json` (für `demo-rev-1002`)

```json
{
  "kind": "review",
  "platform": "other",
  "external_id": "demo-rev-1002",
  "rating_value": 2,
  "rating_scale_min": 1,
  "rating_scale_max": 5,
  "title": "",
  "body": "Wartezeit am Telefon war lang.",
  "language": "de",
  "author_name": "",
  "context_type": null,
  "context_id": null,
  "source_url": "https://reviews.example.test/r/demo-rev-1002",
  "published_at": "2026-11-11T17:40:00Z",
  "meta": {
    "provider_key": "demo",
    "review_type": "service",
    "remote_updated_at": "2026-11-12T08:00:00Z",
    "reply": { "body": "Danke für den Hinweis.", "published_at": "2026-11-12T08:00:00Z" },
    "attribution": { "label": "Demo Reviews", "url": "https://reviews.example.test/p/loc-100", "notice": null }
  }
}
```

### Beispiel `demo/sync_page_removed.json`

```json
{
  "request": { "profile_external_ref": "loc-100", "cursor": "eyJkIjoiMjAyNi0xMS0xOCJ9", "options": { "mode": "delta", "page_size": 20, "since": "2026-11-17T06:00:00Z", "run_uuid": "8e2b4c6d-1a3f-4b5c-9d7e-0f1a2b3c4d5e" } },
  "response": { "items": [], "removed": [ { "external_id": "demo-rev-1001", "reason": "remote_removed" } ], "next_cursor": null, "has_more": false, "summary": null, "quota": { "state": "ok", "remaining": 58, "reset_at": null, "retry_after_seconds": null, "source": "provider_header" }, "credentials_update": null }
}
```

### Beispiel `demo/error_429.json`

```json
{
  "http_status": 429,
  "headers": { "Retry-After": "120" },
  "expected_exception": { "code": "rc.quota.rate_limited", "retry_after_seconds": 120 }
}
```

## Versionierung und Deprecation

- Minor-Erweiterungen (neue optionale Felder, neue `features`) sind rückwärtskompatibel; Core ignoriert unbekannte Felder, Provider dürfen fehlende optionale Eingaben nicht voraussetzen.
- Breaking Changes nur mit neuem Hook `review_captain_providers_v2` und neuem Interface-Namespace; v1 bleibt mindestens für die Deprecation-Frist parallel registrierbar (Vorschlag: 12 Monate **und** 2 Minor-Releases des Core; Produktentscheidung offen, siehe README).

## Abnahme

- [ ] connect, test, list_profiles, sync_page(cursor), normalize, disconnect (delete) + Tombstone-Pfade, quota_status und capabilities() sind mit Signatur und Rückgabeform beschrieben und versioniert (`contract_version() = '1.0'`).
- [ ] Registry `review_captain_providers_v1`: Contract-Tests für ungültigen Key, doppelten Key, unbekannte Major-Version (→ `incompatible_contract`, kein Fatal), nicht leere `write_features`.
- [ ] Fixture-Suite für `demo` liegt vor und deckt Pagination, Duplikat über Seitengrenze, Änderung, Löschung (Feed und Abwesenheit), Quota/429, Tokenablauf und ungültiges Payload ab (RC-083).
- [ ] Zwei parallele Sync-Jobs desselben Profils: nur einer läuft (`active_profile_id` UNIQUE), der zweite endet mit `rc.lock.busy` ohne Datenänderung.
- [ ] Abgebrochener Full-Lauf tombstont nichts; vollständiger Full-Lauf tombstont nicht mehr gelieferte Datensätze.
- [ ] Kein Log, Fehlertext oder Export enthält Credentials, Header oder Reviewtexte.
- [ ] Admin zeigt `facts_status` < `sandbox_verified` als „unbestätigt“.
