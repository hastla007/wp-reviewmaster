# Review-Provider-Vertrag v1 (RC-020)

| | |
|---|---|
| Status | an den implementierten Stand angeglichen (Core v1), Stand 2026-11-16 |
| Vertragsversion | `1.0` (`ProviderRegistry::CORE_CONTRACT`; Provider melden `1.<minor>` oder `1.x`) |
| Registry-Hook | `review_captain_providers_v1` (action, `ProviderRegistry::HOOK`, [conventions.md](../architecture/conventions.md)) |
| Namespace | Interface: `ReviewCaptain\Providers\Contract\ProviderInterface`; Werteobjekte/Dienste: `ReviewCaptain\Providers\` (`SyncPage`, `ProviderResult`, `ProviderException`, `ProviderRegistry`, `SyncService`) |
| Maßgeblicher Code | `src/Providers/Contract/ProviderInterface.php`, `src/Providers/*.php`, Tests `tests/Unit/Providers/` |
| Bezug | TODO RC-020, RC-082, RC-083, TEC-003, TEC-006, TEC-007; konzept.md 4, 7.9, 10 |

> **Keine Anbieterfakten in diesem Dokument.** Alle Fixtures verwenden den fiktiven Provider `demo` (`platform = other`, Host `reviews.example.test`). Werte für Google, Trustpilot, Trusted Shops, App Store und Google Play (Auth-Art, Scopes, Seitengröße, Cache-TTL, Rate Limits, Lösch-Feeds) werden erst im jeweiligen Auth-/Provider-Record (RC-010–RC-013, RC-026) bestätigt.

> **Wo dieses Dokument und der Code abweichen, gilt der Code.** Teile früherer Entwürfe, die in Vertrag 1.0 nicht umgesetzt sind (OAuth-`connect`, `disconnect`, `normalize` als Vertragsmethode, `Connection`-Objekt, Capabilities-JSON, `sync_runs`-Tabelle), stehen im Abschnitt [Ausblick](#ausblick-nicht-in-vertrag-10-umgesetzt).

## Grundsätze

1. Ein Provider ist ein **Adapter**: Er spricht mit genau einer Plattform-API und liefert normalisierte `Review`-Objekte. Persistenz, Dedupe, Tombstones, Jobs, Locks, Budgets, Credentials-Speicherung und Sichtbarkeit sind Aufgabe des Core (`SyncService`) bzw. der Pro-Sync-Runtime.
2. Provider sind **read-only** in v1. Die Fähigkeiten `invite` und `reply` (`ProviderRegistry::WRITE_CAPABILITIES`) sind bekannt, werden vom Core 1.0 aber nie ausgeführt (die Registry vermerkt einen Hinweis).
3. Kein Provider-Aufruf im Frontend-Request. Alle Methoden außer den Metadaten werden nur aus Jobs, WP-CLI oder Admin-Aktionen aufgerufen.
4. Provider persistieren keine Credentials. Sie erhalten entschlüsselte Credentials nur im Speicher als Parameter (`test_connection( $credentials )`, `list_profiles( $credentials )`, `$profile['credentials']`).
5. Ein Ausfall eines Providers blockiert keine anderen Quellen. `register()` wirft nie; inkompatible Provider bleiben registriert, aber pausiert (`rc.contract.incompatible`), nie fatal.

## Registrierung

```php
add_action( 'review_captain_providers_v1', static function ( \ReviewCaptain\Providers\ProviderRegistry $registry ): void {
    $registry->register( new \Acme\ReviewDemo\DemoProvider() );
} );
```

- `ProvidersModule` ruft `ProviderRegistry::fire()` auf `init` (Priorität 20) auf; `fire()` feuert den Hook höchstens einmal pro Request. Wirft ein Registrierungs-Callback, wird das als Ablehnung mit `rc.internal.error` vermerkt, nie als Fatal Error.
- Der Fixture-Provider `demo` (`FixtureProvider`, Fixtures unter `tests/fixtures/providers/demo/`) wird nur registriert, wenn `REVIEW_CAPTAIN_DEV_FIXTURES === true` **und** `wp_get_environment_type()` `local` oder `development` ist.

### `ProviderRegistry` (öffentliche API)

| Methode | Rückgabe | Bedeutung |
|---|---|---|
| `fire(): void` | – | feuert `review_captain_providers_v1` einmal pro Request |
| `register( mixed $provider ): bool` | `true` = registriert (ggf. inkompatibel), `false` = abgewiesen | wirft nie |
| `get( string $key ): ?ProviderInterface` | nur **kompatible** Provider | |
| `keys(): list<string>` | alle registrierten Schlüssel (auch inkompatible) | |
| `details( string $key ): ?array` | `{compatible, error_code, messages, meta: {key, label, version, platform, contract_version, capabilities, class}}` | Diagnose, ohne Providerobjekt |
| `rejected(): list<array{key: ?string, class: string, error_code: string, messages: list<string>}>` | abgewiesene Registrierungen | |
| `status( string $key ): array{state, error_code, next_action}` | `state` = `active` \| `paused` \| `source_unavailable` | siehe unten |

Konstanten: `HOOK = 'review_captain_providers_v1'`, `KEY_PATTERN = '/^[a-z0-9][a-z0-9_.-]{1,63}$/'`, `CONTRACT_PATTERN = '/^1\.(\d+|x)$/'`, `CORE_CONTRACT = '1.0'`, `WRITE_CAPABILITIES = ['invite', 'reply']`.

### Prüfungen bei `register()`

| Prüfung (Reihenfolge wie im Code) | Fehlercode | Folge |
|---|---|---|
| Objekt implementiert `ProviderInterface` | `rc.contract.incompatible` | abgewiesen (`rejected()`) |
| Metadaten-Methoden (`key`, `label`, `version`, `platform`, `contract_version`, `capabilities`) werfen nicht | `rc.contract.invalid` | abgewiesen |
| `key()` passt auf `KEY_PATTERN` | `rc.contract.invalid` | abgewiesen |
| `key()` noch nicht registriert | `rc.data.duplicate` | abgewiesen, die erste Registrierung bleibt |
| `label()` nicht leer, ≤ 191 Zeichen; `version()` nicht leer, ≤ 64 Zeichen | `rc.contract.invalid` | abgewiesen |
| `platform()` ≠ `Platform::LOCAL` | `rc.contract.invalid` | abgewiesen |
| `contract_version()` passt auf `CONTRACT_PATTERN` (`1.<minor>` oder `1.x`) | `rc.contract.incompatible` | registriert, `compatible = false` → Status `paused` |
| jede Fähigkeit ∈ `ProviderInterface::CAPABILITIES` | `rc.contract.incompatible` | unbekannte Fähigkeit wird verworfen und gemeldet; Provider `paused` |
| `read_reviews` ist enthalten | `rc.contract.incompatible` | Provider `paused` |
| `invite`/`reply` enthalten | – | Hinweis „werden in Vertrag 1.0 nicht ausgeführt“, Provider bleibt kompatibel |

`status( $key )`:

| `state` | `error_code` | Wann |
|---|---|---|
| `active` | `null` | registriert und kompatibel |
| `paused` | `rc.contract.incompatible` | registriert, aber inkompatibel |
| `source_unavailable` | `rc.source.unavailable` | nicht registriert (z. B. Add-on inaktiv) |

Offizielle Provider tragen `key() === platform()->value` (z. B. `google`). Drittanbieter-Provider verwenden `Platform::OTHER` und einen eigenen Key mit Präfix (`acme.bookings`).

## Interface

```php
namespace ReviewCaptain\Providers\Contract;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Providers\ProviderResult;
use ReviewCaptain\Providers\SyncPage;

interface ProviderInterface {
    public const CAPABILITIES = array( 'read_reviews', 'list_profiles', 'summary', 'invite', 'reply', 'delete_sync' );

    public function key(): string;              // ^[a-z0-9][a-z0-9_.-]{1,63}$
    public function label(): string;            // übersetzbarer Anzeigename
    public function version(): string;          // SemVer der Implementierung
    public function platform(): Platform;       // Enum, nicht Platform::LOCAL
    public function contract_version(): string; // "1.<minor>" oder "1.x"
    /** @return list<string> Teilmenge von self::CAPABILITIES */
    public function capabilities(): array;

    /** Credentials/Berechtigung prüfen, ohne Daten zu importieren. Wirft bei ungültigen Credentials NICHT. */
    public function test_connection( array $credentials ): ProviderResult;

    /**
     * Zugängliche Profile/Standorte/Apps.
     * @return array{profiles: list<array{external_ref: string, label: string, public_url: ?string, selectable: bool, reason: ?string}>, next_cursor: ?string}
     */
    public function list_profiles( array $credentials, ?string $cursor = null ): array;

    /**
     * Eine Seite normalisierter Reviews.
     * @param array $profile id (int), external_ref (string), credentials (array, nur im Speicher), settings (array)
     * @param int   $limit   1..100
     * @throws \ReviewCaptain\Providers\ProviderException
     */
    public function sync_page( array $profile, ?string $cursor, int $limit ): SyncPage;

    /** @return array{state: string, remaining: ?int, reset_at: ?string, retry_after_seconds: ?int} */
    public function quota_status(): array;
}
```

### Fähigkeiten (`capabilities()`)

| Fähigkeit | Bedeutung in Core 1.0 |
|---|---|
| `read_reviews` | **Pflicht**; `sync_page()` liefert Reviews |
| `list_profiles` | `list_profiles()` liefert auswählbare Profile |
| `summary` | Plattform-Score/Anzahl verfügbar (Anzeige RC-112, Pro) |
| `delete_sync` | `SyncPage::removed_external_ids` wird gefüllt (Lösch-Feed) |
| `invite`, `reply` | bekannt, werden nie ausgeführt |

### `test_connection( array $credentials ): ProviderResult`

Darf bei ungültigen Credentials **nicht** werfen, sondern liefert `ProviderResult::error( 'rc.auth.invalid', … )`. Importiert nichts.

### `list_profiles( array $credentials, ?string $cursor = null ): array`

```json
{
  "profiles": [
    { "external_ref": "loc-100", "label": "Filiale Wien", "public_url": "https://reviews.example.test/p/loc-100", "selectable": true, "reason": null },
    { "external_ref": "loc-200", "label": "Filiale Graz (nicht verifiziert)", "public_url": null, "selectable": false, "reason": "rc.auth.permission_denied" }
  ],
  "next_cursor": null
}
```

Nur `selectable = true` darf der Admin auswählen (RC-066). `reason` ist ein `rc.*`-Code aus [error-codes.md](../architecture/error-codes.md) oder `null`.

### `sync_page( array $profile, ?string $cursor, int $limit ): SyncPage`

- `$profile`: `id` (int, `profiles.id`), `external_ref` (string), `credentials` (array, nur im Speicher), `settings` (array).
- `$cursor`: opak, `null` = erste Seite. `$limit`: 1..100 (Provider kappt selbst).
- Rückgabe: `SyncPage` (unten). Fehler: `ProviderException`.

### `quota_status(): array`

```json
{ "state": "warning", "remaining": 4, "reset_at": "2026-11-16T07:00:00Z", "retry_after_seconds": null }
```

`state`: `ok` \| `warning` \| `exhausted` \| `unknown`. Darf gecachte Werte der letzten Antwort liefern. Unabhängig davon erzwingt der Core lokale Budgets je Profil (`Jobs\Budget`, TEC-007).

## Werteobjekte

### `SyncPage` (`ReviewCaptain\Providers\SyncPage`, final, immutable)

```php
new SyncPage( array $reviews, ?string $next_cursor = null, array $removed_external_ids = array() );
```

| Eigenschaft | Typ | Regel (Konstruktor wirft `\InvalidArgumentException`) |
|---|---|---|
| `reviews` | `list<Review>` (readonly) | nur `ReviewCaptain\Domain\Review`-Objekte |
| `next_cursor` | `?string` (readonly) | `null` = fertig, sonst 1..1024 Byte |
| `removed_external_ids` | `list<string>` (readonly) | nicht leer, ≤ 191 Zeichen; Duplikate werden entfernt |

`has_more(): bool` = `next_cursor !== null`.

### `ProviderResult` (`ReviewCaptain\Providers\ProviderResult`, final, immutable)

| Element | Bedeutung |
|---|---|
| `ProviderResult::ok( array $data = array(), string $message = '' ): self` | Erfolg |
| `ProviderResult::error( string $error_code, string $message = '', array $data = array() ): self` | Fehlschlag; unbekannte Codes werden zu `rc.perm.failed` |
| `$ok` (bool), `$error_code` (?string), `$message` (string), `$data` (array) | readonly; `message` wird mit `ConnectorException::sanitize_message()` bereinigt |
| `next_action(): string` | nächste Admin-Aktion laut `ErrorCode::admin_action()` |

### `ProviderException` (`ReviewCaptain\Providers\ProviderException extends ReviewCaptain\Connectors\ConnectorException`)

```php
new ProviderException( string $error_code, string $message = '', ?int $retry_after_seconds = null );
```

- `error_code()`: bekannter Code laut `ErrorCode::is_known()`, sonst **`rc.perm.failed`** (bei `ConnectorException` wäre der Fallback `rc.temp.unavailable`). `raw_error_code()` liefert den übergebenen Code für die Diagnose.
- `retry_after(): ?int` – `Retry-After`-Hinweis in Sekunden.
- Die Nachricht wird sanitisiert (`ConnectorException::sanitize_message()`: Markup entfernt, E-Mail-Adressen → `[email]`, tokenartige Zeichenketten ≥ 32 Zeichen → `[redacted]`, max. 500 Zeichen).
- `ProviderException::from_http( int $status, ?int $retry_after = null, bool $token_expired = false ): self` bildet HTTP-Status auf Standardcodes ab:

| HTTP | Code |
|---|---|
| `0` (Timeout/Verbindungsfehler) | `rc.net.timeout` |
| 401 mit `$token_expired = true` | `rc.auth.expired` |
| 401 sonst | `rc.auth.invalid` |
| 403 | `rc.auth.permission_denied` |
| 404, 410 | `rc.remote.removed` |
| 429 | `rc.quota.rate_limited` (+ `retry_after`) |
| ≥ 500 | `rc.temp.unavailable` |
| alles andere | `rc.data.invalid_payload` |

Ein Tages-/Monatskontingent signalisiert der Provider selbst mit `rc.quota.exceeded`, eine nicht erreichbare Gegenstelle mit `rc.net.unreachable`.

## Übernahme in die Datenbank: `SyncService`

`ReviewCaptain\Providers\SyncService` (Dienst `provider_sync` im Plugin-Container):

```php
public function __construct( ReviewRepository $reviews, int $ttl_seconds = DAY_IN_SECONDS );
/** @return array{created: int, updated: int, unchanged: int, removed: int, invalid: int} */
public function apply( SyncPage $page, int $profile_id ): array;
```

Regeln:

- Je Review: der Core überschreibt Herkunftsfelder, die der Provider nicht kontrolliert – `id`/`uuid` leer, `kind = review`, `origin = provider_sync`, `profile_id = $profile_id`, `verification = external_platform`, `author_email`/`author_user_id` = `null`, `consent_publish = false`, `consent_at`/`import_batch_id`/`removed_at` = `null`, `fetched_at` = jetzt, `refresh_due_at` = jetzt + `max(60, ttl_seconds)`, `meta.last_verified_at` = jetzt (ISO 8601).
- Anfangsstatus neuer Datensätze über den Filter `review_captain_sync_initial_status` (Default `ReviewStatus::PENDING`; nur `PENDING` oder `APPROVED` zulässig).
- Reviews ohne externe Plattform (`Platform::is_external()`), ohne `external_id` oder mit Validierungsfehlern (`Review::validate()`) werden einzeln als `invalid` gezählt; die Seite scheitert nicht als Ganzes.
- Upsert über `ReviewRepository::upsert_external()` mit `dedupe_key` ([database.md](../architecture/database.md) §2.1.1). Der Moderationsstatus vorhandener Datensätze bleibt erhalten; zuvor als `removed` markierte Datensätze werden wiederhergestellt. `updated` vs. `unchanged` entscheidet ein Inhalts-Fingerprint (Bewertung, Skala, Titel, Text, Sprache, Autor, Quell-URL, Datum, Kontext).
- `removed_external_ids` → Status `removed` (Grund `remote_removed`) für den Datensatz mit passendem `dedupe_key`; die Plattform wird aus `profiles.platform` ermittelt.
- Verarbeitung ist **at-least-once**; Idempotenz durch den `dedupe_key`-Upsert. Cursorverwaltung, Folgejobs und Locks je Profil sind Aufgabe der Pro-Sync-Runtime (in Core 1.0 gibt es keinen Sync-Jobtyp).

## Fixtures

Ablage: `tests/fixtures/providers/<key>/`. Vorhanden für `demo` (RC-083):

| Datei | Inhalt |
|---|---|
| `capabilities.json` | Metadaten inkl. `capabilities` (`read_reviews`, `list_profiles`, `summary`, `delete_sync`) |
| `list_profiles.json` | inkl. eines nicht auswählbaren Profils |
| `sync_page_1.json`, `sync_page_2.json` | Pagination mit Cursor, ein Duplikat über Seitengrenze |
| `sync_page_changed.json` | geänderte Review (gleiche `external_id`) |
| `sync_page_removed.json` | Löschung über `removed` |
| `error_401.json`, `error_401_expired.json`, `error_403.json`, `error_429.json`, `error_5xx.json`, `invalid_payload.json` | Fehlerabbildung über `ProviderException::from_http()` |

Seitenfixtures haben die Form `{ "request": { "profile_external_ref", "cursor", "options" }, "response": { "items", "removed", "next_cursor", "has_more", "summary", "quota", "credentials_update" } }` (Rohformat des fiktiven Anbieters); `FixtureProvider` wählt die Seite über `request.cursor`, normalisiert `items` zu `Review`-Objekten und übernimmt `removed[].external_id` in `SyncPage::removed_external_ids`.

### Beispiel `demo/error_429.json`

```json
{ "http_status": 429, "headers": { "Retry-After": "120" }, "expected_exception": { "code": "rc.quota.rate_limited", "retry_after_seconds": 120 } }
```

## Versionierung und Deprecation

- Minor-Erweiterungen (neue optionale Fähigkeiten, neue Felder in Rückgaben) sind rückwärtskompatibel; der Core ignoriert unbekannte Felder. Eine unbekannte Fähigkeit macht einen Provider in Core 1.0 allerdings inkompatibel (`paused`), daher werden neue Fähigkeiten nur mit Minor-Erhöhung von `CORE_CONTRACT` eingeführt.
- Breaking Changes nur mit neuem Hook `review_captain_providers_v2` und neuem Interface; v1 bleibt mindestens für die Deprecation-Frist parallel registrierbar (Vorschlag: 12 Monate **und** 2 Minor-Releases des Core; Produktentscheidung offen, siehe [README](README.md)).

## Ausblick (nicht in Vertrag 1.0 umgesetzt)

Die folgenden Teile früherer Entwürfe sind **nicht** Bestandteil von `ProviderInterface` 1.0. Sie dürfen nur per Minor-/Major-Erhöhung und ADR eingeführt werden:

- **`connect( array $request ): array`** mit Modi `oauth_start` / `oauth_callback` / `credentials` (State/Nonce/Redirect-URI-Prüfung durch den Core, TEC-010) und **`disconnect()`** (Token-Widerruf, `api_revoke` \| `not_supported`). In 1.0 verwaltet die Pro-Runtime Verbindungen; siehe [rest-oauth.md](../architecture/rest-oauth.md), [provider-auth.md](../architecture/provider-auth.md).
- **`Connection`-Value-Object** (`credential()`, `account_ref()`, `http()` mit Host-Allowlist). In 1.0 erhalten Provider Credentials als Array.
- **`normalize( array $raw, array $profile )`** als Vertragsmethode. In 1.0 normalisiert der Provider intern und liefert `Review`-Objekte in `SyncPage` (`FixtureProvider::normalize()` ist eine öffentliche Hilfsmethode, nicht Teil des Interfaces).
- **Capabilities-Objekt** (`facts_status`, `auth`, `hosts`, `features` wie `sync_full`/`sync_delta`/`deletion_feed`/`full_listing`, `write_features`, `rating_scale`, `max_page_size`, `max_cache_ttl_seconds`, `rate_limit`, `config_fields`). In 1.0 ist `capabilities()` eine Liste aus `ProviderInterface::CAPABILITIES`; `capabilities.json` der Fixtures enthält die übrigen Felder nur als Dokumentation.
- **Sync-Rückgabefelder** `summary`, `quota` und `credentials_update` in der Seitenantwort sowie `options` (`mode`, `since`, `run_uuid`).
- **Tombstoning durch Abwesenheit** bei vollständigen Full-Läufen (`full_listing`, Grund `remote_not_found`).
- **Pro-Tabelle `{prefix}review_captain_sync_runs`** mit `active_profile_id` UNIQUE (höchstens ein aktiver Lauf je Profil), Cursor-CAS über `generation`, Folgejob `sync.page` mit Idempotenzschlüssel `sync:{profile_id}:{run_uuid}:{page_no}`.
- Zusätzliche Fehlercodes `rc.auth.insufficient_scope`, `rc.remote.not_found`, `rc.remote.server_error`, `rc.net.connection`, `rc.lock.busy` – in 1.0 durch `rc.auth.permission_denied`, `rc.remote.removed`, `rc.temp.unavailable`, `rc.net.timeout`/`rc.net.unreachable` abgedeckt (siehe [error-codes.md](../architecture/error-codes.md), „geplant“).

## Abnahme

- [ ] `ProviderInterface` (Namespace `ReviewCaptain\Providers\Contract`) mit `key`, `label`, `version`, `platform`, `contract_version`, `capabilities`, `test_connection`, `list_profiles`, `sync_page`, `quota_status` ist mit Signatur und Rückgabeform beschrieben.
- [ ] Registry `review_captain_providers_v1`: Contract-Tests für fehlendes Interface, werfende Metadaten, ungültigen Key, doppelten Key (`rc.data.duplicate`), Plattform `local`, unbekannte Major-Version und fehlendes `read_reviews` (→ `paused`, `rc.contract.incompatible`, kein Fatal).
- [ ] Fixture-Suite für `demo` deckt Pagination, Duplikat über Seitengrenze, Änderung, Löschung (Feed), 401/401-abgelaufen/403/429/5xx und ungültiges Payload ab (RC-083).
- [ ] `SyncService::apply()` ist idempotent (zweimal dieselbe Seite → `created` = 0 beim zweiten Lauf) und erhält den Moderationsstatus.
- [ ] Kein Log, Fehlertext oder Export enthält Credentials, Header oder Reviewtexte.
