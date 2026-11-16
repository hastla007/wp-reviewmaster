# CON-002 / CON-003 – Connector-Vertrag v1

Status: **verbindlich**, an den implementierten Stand angeglichen (Core v1, `src/Connectors/**`), Stand 2026-11-16. Wo dieses Dokument und der Code abweichen, gilt der Code. Änderungen am Vertrag nur per ADR (`docs/adr/`) und gemäß [Versionierung](#versionierung-und-kompatibilität).
Gilt nur für **lokale** Connectoren derselben WordPress-Installation. Fähigkeiten und Abgrenzung: [use-cases.md](use-cases.md).

## Überblick

| Element | Wert |
|---|---|
| Registry-Hook | `review_captain_connectors_v1` (Action) |
| Registry-Klasse | `ReviewCaptain\Connectors\Registry` |
| Registrierung | `$registry->register( mixed $connector ): bool` (wirft nie; Rückgabewert nicht Vertragsbestandteil) |
| Validator | `ReviewCaptain\Connectors\ContractValidator` (`validate( mixed $candidate, bool $allow_reserved = false ): array`) |
| Payload-Validator | `ReviewCaptain\Connectors\ExperienceEvent::validate( array $payload ): list<string>` |
| Haupt-Interface | `ReviewCaptain\Connectors\Contract\ConnectorInterface` |
| Capability-Interfaces | `ReviewCaptain\Connectors\Contract\{ExperienceSource,RecipientResolver,ReviewSource,RequestTransport,ContextProvider}Interface` |
| Exception | `ReviewCaptain\Connectors\ConnectorException( string $error_code, string $message = '', ?\Throwable $previous = null )` |
| Job-Hook | `review_captain_process_job_v1` (Filter) |
| Contract-Version | Major `1`, Connector meldet `1.<minor>` bzw. `1.x`; Core unterstützt `1.0` (`ContractValidator::CORE_CONTRACT`) |
| Statuswerte | `active` \| `inactive` \| `paused` \| `source_unavailable` (`Registry::STATE_*`) |

## 1. Registrierung

```php
add_action(
    'review_captain_connectors_v1',
    static function ( \ReviewCaptain\Connectors\Registry $registry ): void {
        $registry->register( new \Acme\BookingConnector\Connector() );
    }
);
```

Regeln:

1. Review Captain löst `review_captain_connectors_v1` **einmal pro Request** aus (`Registry::fire()`, aufgerufen von `ConnectorsModule` auf `init` mit Priorität 20; `review_captain_loaded` ist dann bereits auf `plugins_loaded` gelaufen). Connector-Plugins hängen ihren Callback spätestens in `plugins_loaded` ein. Wirft ein Callback, wird das als Ablehnung mit `rc.internal.error` in der Diagnose vermerkt. Ist Review Captain nicht aktiv, wird der Hook nie ausgelöst – der Connector muss dann ohne Fehler untätig bleiben (keine harte Abhängigkeit auf Klassen von Review Captain außerhalb dieses Callbacks bzw. `class_exists`-Guard vor `implements`, siehe [developer-guide.md](developer-guide.md)).
2. `register()` wirft **nie** und bricht den Request nie ab. Ungültige Connectoren werden verworfen bzw. als nicht ausführbar markiert und in der Diagnose angezeigt. Der Rückgabewert von `register()` ist **nicht** Teil des Vertrags.
3. Registrierungen außerhalb des Hooks (z. B. direkter Zugriff auf eine Registry-Instanz) sind nicht unterstützt.
4. Die Registrierung führt **keine** Connector-Aktion aus. Ein neu erkannter Connector ist standardmäßig **inaktiv** (`inactive`), bis ein Administrator ihn aktiviert (siehe [licensing.md](licensing.md)). Der Core aktiviert nie selbst; die Aktivierung schreibt der Connector-Manager von Review Captain Pro über `Registry::set_state()`.
5. Der Core ruft Capability-Methoden nur im Jobrunner bzw. in Admin-Aktionen mit Capability- und Nonce-Prüfung auf – nie während eines Frontend-Requests. Auch `describe_context` wird nur im Hintergrund aufgerufen; das Frontend liest ausschließlich den Cache.

### Validierung bei `register()`

`ContractValidator::validate()` liefert eines von drei Ergebnissen (`outcome`): `rejected` (nicht registriert, nur in `Registry::rejected()` sichtbar), `incompatible` (registriert, aber pausiert mit `rc.contract.incompatible`, nie ausgeführt) oder `ok` (kompatibel, ggf. mit Hinweisen). Prüfreihenfolge und Fehlercodes exakt nach Code:

| # | Prüfung | Regel | Ergebnis bei Verstoß | Fehlercode |
|---|---|---|---|---|
| 1 | Typ | Objekt implementiert `ConnectorInterface` | `rejected` | `rc.contract.incompatible` |
| 2 | Metadaten | `key()`, `label()`, `version()`, `contract_version()`, `developer()`, `capabilities()` werfen keine `Throwable` | `rejected` | `rc.contract.invalid` |
| 3 | `key()` | Regex `^[a-z0-9][a-z0-9_-]{2,63}$` (3–64 Zeichen, `ContractValidator::KEY_PATTERN`) | `rejected` | `rc.data.invalid_payload` |
| 4 | reservierter Schlüssel | nicht in `RESERVED_KEYS` und ohne `RESERVED_PREFIXES` (außer Klassen im Namespace `ReviewCaptain*`, z. B. Pro) | `rejected` | `rc.contract.invalid` |
| 5 | `label()`, `developer()` | nicht leer, max. 191 Zeichen, werden escaped ausgegeben | `rejected` | `rc.contract.invalid` |
| 6 | `version()` | nicht leer, max. 64 Zeichen (SemVer empfohlen) | `rejected` | `rc.contract.invalid` |
| 7 | `capabilities()` | nicht leer | `rejected` | `rc.contract.invalid` |
| 8 | Eindeutigkeit (in `Registry::register()`) | Schlüssel noch nicht registriert | zweite Registrierung `rejected`, erste bleibt | `rc.data.duplicate` |
| 9 | Fähigkeit kein String | – | `incompatible` | `rc.contract.incompatible` |
| 10 | doppelte Fähigkeit | – | zusammengeführt, Hinweis (bleibt `ok`) | – |
| 11 | `contract_version()` | Format `^1\.(\d+\|x)$` (`CONTRACT_PATTERN`) | `incompatible` | `rc.contract.incompatible` |
| 12 | Minor > `CORE_CONTRACT_MINOR` (0) | – | akzeptiert, Hinweis „zusätzliche Fähigkeiten werden ignoriert“ | – |
| 13 | bekannte Fähigkeit | ∈ `ContractValidator::CAPABILITIES` | `incompatible` | `rc.contract.incompatible` |
| 14 | Capability ↔ Interface | jede deklarierte Fähigkeit hat das passende Interface | `incompatible` | `rc.contract.incompatible` |
| – | Validator selbst wirft | – | `rejected` | `rc.internal.error` |

Ein implementiertes Interface **ohne** deklarierte Capability wird ignoriert (keine Aktion). Reservierte Schlüssel (`ContractValidator::RESERVED_KEYS`): `woocommerce`, `voucher-captain`, `pass-captain`, `affiliate-captain`, `form-captain`; reservierte Präfixe (`RESERVED_PREFIXES`): `rc-`, `review-captain`.

### Registry-API (`ReviewCaptain\Connectors\Registry`)

| Methode | Rückgabe / Wirkung |
|---|---|
| `fire(): void` | feuert `review_captain_connectors_v1` einmal pro Request und merkt sich registrierte Schlüssel in `review_captain_connectors_known` |
| `fired(): bool` | ob der Hook bereits lief |
| `register( mixed $connector ): bool` | siehe oben; wirft nie |
| `get( string $key ): ?ConnectorInterface` | auch inkompatible Connectoren – vor Methodenaufrufen `is_executable()` prüfen |
| `has( string $key ): bool` | im aktuellen Request registriert |
| `keys(): list<string>` | registrierte Schlüssel |
| `details( string $key ): ?array` | `{compatible, error_code, messages, notices, meta: {key, label, version, contract_version, developer, capabilities, class}}` |
| `rejected(): list<array{key: ?string, class: string, error_code: ?string, messages: list<string>}>` | verworfene Registrierungen |
| `supports( string $key, string $capability ): bool` | kompatibel **und** Fähigkeit deklariert |
| `is_executable( string $key ): bool` | `status()['state'] === 'active'` |
| `status( string $key ): array{state, error_code, next_action}` | siehe Tabelle unten |
| `set_state( string $key, string $state, ?string $error_code = null ): bool` | nur für den Pro-Connector-Manager: `active` \| `paused` \| `inactive` (`active` nur für registrierte, kompatible Connectoren); gespeichert in `review_captain_connectors_state` |
| `forget( string $key ): void` | Schlüssel aus `review_captain_connectors_known` entfernen und Zustand auf `inactive` |
| `unavailable(): array<string, array{label, version}>` | bekannte, aber aktuell nicht registrierte Connectoren |
| `owner_of_job_type( string $type ): ?string` | Connector-Schlüssel zu einem Jobtyp `{connector_key}.{name}`, wenn registriert und kompatibel |

### Status (`Registry::status()`)

| `state` | `error_code` | Bedingung |
|---|---|---|
| `inactive` | `null` | nicht registriert und nie gesehen **oder** registriert, kompatibel, aber nicht aktiviert (Standard für neue Connectoren) |
| `source_unavailable` | `rc.source.unavailable` | früher registriert (`review_captain_connectors_known`), im aktuellen Request aber nicht (Plugin deaktiviert/Quellsystem fehlt) |
| `paused` | `rc.contract.incompatible` | registriert, aber inkompatibel |
| `paused` | gespeicherter Code oder `null` | vom Pro-Manager pausiert (`set_state( …, 'paused', $code )`) |
| `active` | `null` | registriert, kompatibel und aktiviert |

Einen eigenen Status `incompatible_contract` gibt es nicht; inkompatible Connectoren sind `paused` mit `rc.contract.incompatible`.

## 2. `ConnectorInterface`

```php
namespace ReviewCaptain\Connectors\Contract;

interface ConnectorInterface {
    /** Stabiler, eindeutiger Schlüssel. Regex ^[a-z0-9][a-z0-9_-]{2,63}$. Darf sich nie ändern. */
    public function key(): string;

    /** Übersetzbarer Anzeigename. */
    public function label(): string;

    /** Version des Connector-Plugins (SemVer empfohlen, z. B. "1.2.0"). */
    public function version(): string;

    /** Implementierte Contract-Version, Major 1: "1.0", "1.1" … oder "1.x". */
    public function contract_version(): string;

    /**
     * Deklarierte Fähigkeiten.
     * @return list<'experience_source'|'recipient_resolver'|'review_source'|'request_transport'|'context_provider'>
     */
    public function capabilities(): array;

    /** Entwickler/Anbieter (Firma oder Person), wird im Admin angezeigt. */
    public function developer(): string;
}
```

Metadaten-Methoden müssen **schnell und seiteneffektfrei** sein (keine DB-Schreibzugriffe, keine HTTP-Aufrufe), da sie bei jeder Registrierung aufgerufen werden.

## 3. Capability-Interfaces

Alle Methoden laufen im Hintergrundjob (Lease, Timeout). Allgemeine Regeln:

- Rückgaben sind assoziative Arrays mit genau den dokumentierten Schlüsseln; unbekannte Schlüssel werden ignoriert, fehlende Pflichtschlüssel oder falsche Typen machen den Eintrag ungültig (`rc.data.invalid_payload`).
- Fehler werden als `ConnectorException` geworfen (siehe [§5](#5-fehlerbehandlung)). Andere `Throwable` werden gefangen und als `rc.temp.unavailable` behandelt.
- Strings sind UTF-8; Zeitangaben ISO 8601 mit Zeitzone (z. B. `2026-11-16T09:30:00Z`); der Core speichert UTC.
- Cursor sind opake Strings des Connectors, max. 191 Zeichen. Erster Aufruf: `''`. `next_cursor = null` bedeutet „vorerst keine weiteren Daten“; der Core speichert dann den letzten nicht-leeren Cursor und fragt beim nächsten Intervall erneut mit diesem an.
- `$limit` liegt zwischen 1 und 100. Der Connector darf weniger, nie mehr Einträge liefern (Überschuss wird verworfen und als Fehler protokolliert).

### 3.1 `ExperienceSourceInterface` (`experience_source`)

```php
interface ExperienceSourceInterface {
    /**
     * @return array{events: list<array<string,mixed>>, next_cursor: ?string}
     */
    public function poll_experiences( string $cursor, int $limit ): array;
}
```

Ereignis-Payload (CON-004), je Eintrag in `events`:

| Feld | Typ | Pflicht | Regel |
|---|---|---|---|
| `contract` | string | ja | `"1.0"` (Payload-Version) |
| `event_id` | string | ja | global eindeutig und stabil je Nachricht, max. 191; Wiederholung = Duplikat |
| `event_type` | string | ja | `experience_completed` \| `experience_cancelled` \| `experience_refunded` |
| `experience_id` | string | ja | stabil je tatsächlichem Erlebnis, max. 191; Basis für „höchstens eine Einladung“ |
| `occurred_at` | string | ja | ISO 8601 mit Zeitzone |
| `object_type` | string | ja | öffentlicher Objekttyp, z. B. `course`, `appointment`, `product`, `event`; `^[a-z0-9_]{2,40}$` |
| `object_id` | string | ja | stabile öffentliche Objekt-ID, max. 64 |
| `recipient_ref` | string | ja bei `experience_completed` | site-lokale Referenz, **keine E-Mail**, max. 191 |
| `locale` | ?string | nein | z. B. `de_DE`; `null` → Site-Sprache |
| `communication_allowed` | bool | ja | `true` nur bei nachweisbarer Versandberechtigung; fehlend/unbekannt = `false` |
| `consent` | array | ja | `{status: 'granted'\|'denied'\|'unknown', source: string(≤64), at: ?string}` |
| `idempotency_key` | string | nein | Standard: `sha1(connector_key|event_id)`; max. 191 |
| `context` | array | nein | max. 10 skalare Werte, Schlüssel `^[a-z0-9_]{1,40}$`, Werte ≤ 191 Zeichen; z. B. `campaign_hint`. Keine personenbezogenen Daten |

Geprüft wird jedes Ereignis mit `ExperienceEvent::validate()` (Fehlerliste `feld:grund`, Gründe `missing`, `invalid`, `too_long`, `forbidden`, `pii`, `incompatible`); `ExperienceEvent::error_code()` bildet sie auf `rc.contract.incompatible` (Payload-Major ≠ 1) bzw. `rc.data.invalid_payload` ab. Felder aus `ExperienceEvent::FORBIDDEN_FIELDS` (z. B. `email`, `name`, `phone`, `body`, `token`) sind verboten, E-Mail-ähnliche Werte in `recipient_ref` ebenso. `ExperienceEvent::with_defaults()` ergänzt `source = key()`, `idempotency_key = sha1(connector_key|event_id)` und `locale = null`.

Verarbeitungsregeln (Core):

- Dedupe 1: `event_id` bereits gesehen → ignoriert (kein Fehler).
- Dedupe 2: pro `(source, experience_id, Empfänger)` höchstens eine Einladung, unabhängig von der Zahl der `experience_completed`-Ereignisse.
- `experience_cancelled`/`experience_refunded` storniert geplante Anfragen derselben `experience_id`; bereits versendete werden nicht zurückgeholt, Erinnerungen entfallen.
- Verspätete Ereignisse (`occurred_at` älter als das Kampagnen-Höchstalter) werden nicht eingeplant.
- `communication_allowed=false` oder `consent.status≠granted` (sofern Kampagne Consent verlangt) → keine Einladung, Status „blockiert: keine Berechtigung“.

### 3.2 `RecipientResolverInterface` (`recipient_resolver`)

```php
interface RecipientResolverInterface {
    /**
     * @param string $purpose 'review_request' | 'review_request_reminder'
     * @return array{email: string, locale: ?string, permission: string}|null
     */
    public function resolve( string $recipient_ref, string $purpose ): ?array;
}
```

| Feld | Regel |
|---|---|
| `email` | gültige Adresse (`is_email`) |
| `locale` | `?string`, z. B. `de_DE` |
| `permission` | `allowed` \| `denied` \| `unknown` – nur `allowed` führt zum Versand |

- `null` = Referenz unbekannt → Abbruch, Status `recipient_unknown`, kein Retry.
- Aufruf unmittelbar vor dem Versand; das Ergebnis wird nur für die Dauer des Jobs gehalten und nicht in `jobs.payload`, Logs oder Diagnoseexporten gespeichert.
- Unbekannte `purpose`-Werte muss der Connector mit `permission='denied'` oder `null` beantworten.

### 3.3 `ReviewSourceInterface` (`review_source`)

```php
interface ReviewSourceInterface {
    /**
     * @return array{reviews: list<array<string,mixed>>, next_cursor: ?string}
     */
    public function fetch_reviews( string $cursor, int $limit ): array;
}
```

Review-Payload je Eintrag (Abbildung auf `reviews`-Spalten, siehe Konventionen):

| Feld | Typ | Pflicht | Regel |
|---|---|---|---|
| `external_id` | string | ja | stabile Quell-ID, max. 191; Dedupe über `dedupe_key` |
| `kind` | string | nein | `review` (Standard) \| `testimonial` |
| `platform` | string | nein | `local` nur, wenn die Review auf **dieser Website** abgegeben wurde; sonst `other` (Standard) |
| `rating_value` | ?float | nein | innerhalb `rating_scale_min..max` |
| `rating_scale_min` / `rating_scale_max` | float | nein | Standard 1 / 5 |
| `title` | string | nein | max. 255, Plaintext |
| `body` | string | ja | Plaintext oder einfaches HTML (wird mit `wp_kses` auf erlaubte Tags reduziert) |
| `language` | ?string | nein | max. 20 |
| `author_name` | string | ja | Anzeigename, max. 191 |
| `published_at` | string | ja | ISO 8601 – Originaldatum |
| `context_type` / `context_id` | ?string | nein | `post` \| `product` \| `event` \| `voucher` \| `app` \| `form`; ID max. 64 |
| `source_url` | ?string | nein | `http(s)`-URL |
| `verification` | string | nein | `unverified` (Standard) \| `purchase_verified` \| `attendance_confirmed` |
| `consent_publish` | bool | ja | nur `true`, wenn der Verfasser der Veröffentlichung zugestimmt hat |
| `removed` | bool | nein | `true` = Tombstone: in der Quelle gelöscht/widerrufen → Core setzt `status=removed` |

Nicht erlaubt: `author_email`, Telefonnummern, interne Kunden-IDs im Text. Der Core setzt immer `origin=connector`, `status=pending` (Moderation) und ignoriert vom Connector gelieferte Status. Updates derselben `external_id` aktualisieren Inhalt, setzen aber eine bereits freigegebene Review bei inhaltlicher Änderung zurück auf `pending`.

### 3.4 `RequestTransportInterface` (`request_transport`)

```php
interface RequestTransportInterface {
    /**
     * @return array{status: string, provider_message_id: ?string}
     */
    public function send( array $message ): array;
}
```

`$message` (vom Core erzeugt, Version `1.0`):

| Feld | Inhalt |
|---|---|
| `contract` | `"1.0"` |
| `request_uuid` | UUID der Review-Anfrage |
| `idempotency_key` | stabil je Anfrage und Versuch-Serie; der Connector **muss** damit Mehrfachversand verhindern |
| `kind` | `initial` \| `reminder` |
| `recipient` | `{email: string, locale: ?string}` (aus dem Resolver, nur für diesen Aufruf) |
| `subject`, `body_html`, `body_text` | fertig gerendert, nicht verändern |
| `review_url` | signierter Link zum Bewertungsformular/Ziel |
| `unsubscribe_url` | Abmeldelink (muss mitversendet werden) |
| `experience_id`, `object_type`, `object_id` | Referenzen |

Rückgabe `status`: `accepted` (vom Zielsystem angenommen) oder `rejected` (dauerhaft abgelehnt, kein Retry). Temporäre Fehler → `ConnectorException` mit wiederholbarem Code. `provider_message_id` max. 191 Zeichen oder `null`. Der Connector darf Empfänger, Text, Links und Zeitpunkt nicht ändern.

### 3.5 `ContextProviderInterface` (`context_provider`)

```php
interface ContextProviderInterface {
    /**
     * @return array{label: string, url: ?string}|null
     */
    public function describe_context( string $context_type, string $context_id ): ?array;
}
```

- `label`: öffentlicher Name, max. 191, Plaintext.
- `url`: öffentliche `http(s)`-URL derselben Site oder `null`.
- `null` = Kontext unbekannt/nicht freigegeben. Keine personenbezogenen Daten.
- Der Core cacht Ergebnisse (Transient, Standard 12 h) und ruft die Methode nie im Frontend-Request auf.

## 4. Eigene Jobs: `review_captain_process_job_v1`

Connectoren können eigene Hintergrundjob-Typen im Review-Captain-Jobrunner verarbeiten (atomarer Claim/Lease, Idempotenz, Retry/Backoff, Dead-Letter – siehe Tabelle `jobs` in den Konventionen).

```php
/**
 * @param array|null $result  null = noch nicht behandelt
 * @param string     $type    Jobtyp
 * @param array      $payload versionierte, JSON-serialisierbare Payload
 * @return array|null         ['status' => 'succeeded'] oder unverändert $result
 */
add_filter( 'review_captain_process_job_v1', function ( $result, string $type, array $payload ) {
    if ( null !== $result || 'acme-booking.reconcile' !== $type ) {
        return $result;
    }
    // … Arbeit erledigen oder ConnectorException werfen …
    return [ 'status' => 'succeeded' ];
}, 10, 3 );
```

Regeln:

- Jobtypen eines Connectors sind `{connector_key}.{name}` mit `name` `^[a-z0-9_]{1,30}$` (Spalte `type` max. 100 Zeichen). Fremde Präfixe dürfen nicht behandelt werden.
- Bleibt das Ergebnis `null`, **pausiert** der Core den Job mit `rc.contract.incompatible` („Kein Handler für Jobtyp … registriert“), ohne einen Versuch zu verbrauchen. Pausierte Jobs mit `rc.contract.incompatible` oder `rc.source.unavailable` werden höchstens einmal pro Stunde wieder eingereiht (Transient `review_captain_resume_unhandled`).
- Gehört der Jobtyp zu einem bekannten Connector, der nicht `active` ist, wird der Job vor dem Filter mit `rc.source.unavailable` pausiert (CON-014).
- Zulässige Handler-Ergebnisse (`Runner::apply_outcome()`): `true` oder `['status' => 'succeeded']` → erledigt; `['status' => 'continue', 'payload' => array, 'delay' => int]` → Checkpoint (Payload wird gemergt, kein Versuch verbraucht); `['status' => 'failed'|'retry', 'error_code' => 'rc.*', 'message' => string, 'retry_after' => int]` → Fehler nach Retry-Klasse; `WP_Error` mit `rc.*`-Code → Fehler; alles andere → Fehler `rc.contract.invalid`. Geworfene `ConnectorException` → Fehler mit deren Code; andere `Throwable` → `rc.temp.unavailable`.
- Payload max. 64 KB JSON, enthält ein Feld `v` (Payload-Version) und **keine** Secrets, E-Mail-Adressen oder Reviewtexte.
- Handler müssen idempotent sein: derselbe Job kann nach Lease-Ablauf erneut ausgeführt werden.
- Jobs werden nur ausgeführt, wenn der Connector aktiv und vertragskompatibel ist und (Pro) das Entitlement besteht.
- Das Anlegen eigener Jobs erfolgt über die Core-Job-API; Connectoren schreiben nicht direkt in die Tabelle `jobs`:

  ```php
  $jobs = \ReviewCaptain\Plugin::instance()->get( 'jobs' ); // ReviewCaptain\Jobs\JobQueue
  $id   = $jobs->enqueue( string $type, array $payload, string $idempotency_key, int $delay_seconds = 0, int $max_attempts = 8 ); // int|false
  ```

  `enqueue()` liefert bei bereits vorhandenem Idempotenzschlüssel die bestehende Job-ID (keine zweite Zeile) und `false` bei ungültigem Typ (`^[a-z0-9_.\-]+$`, max. 100 Zeichen) oder Payload > 64 KiB. Schlüssel > 191 Zeichen werden gekürzt und mit SHA-1 ergänzt (`JobQueue::normalize_key()`). Vorher mit `Plugin::instance()->has( 'jobs' )` prüfen.

## 5. Fehlerbehandlung

```php
throw new \ReviewCaptain\Connectors\ConnectorException( 'rc.auth.invalid', 'CRM-API-Schlüssel abgelehnt' );
```

- `$error_code` folgt `rc.<bereich>.<code>` und muss in `ReviewCaptain\Support\ErrorCode` bekannt sein (Liste: `docs/architecture/error-codes.md`); unbekannte Codes werden als `rc.temp.unavailable` behandelt.
- `$message` ist für Administratoren bestimmt, wird gekürzt (500 Zeichen) und sanitisiert gespeichert. **Keine** Secrets, E-Mail-Adressen, Tokens oder Reviewtexte in der Nachricht.

| Code | Bedeutung | Retry-Klasse | Core-Reaktion (Job) |
|---|---|---|---|
| `rc.net.timeout`, `rc.net.unreachable` | Zeitüberschreitung / nicht erreichbar | `transient` | Retry mit exponentiellem Backoff, nach `max_attempts` `dead` |
| `rc.temp.unavailable` | temporärer Fehler (auch Fallback für unbekannte Codes) | `transient` | Retry mit Backoff |
| `rc.quota.rate_limited`, `rc.quota.exceeded`, `rc.quota.budget_exhausted` | Rate Limit / Kontingent / lokales Budget | `rate_limit` | erneut nach `retry_after` (60 s … 24 h, Standard 15 min), verbraucht keinen Versuch |
| `rc.auth.invalid`, `rc.auth.expired`, `rc.auth.revoked`, `rc.auth.permission_denied` | Anmeldung/Schlüssel/Berechtigung im Zielsystem | `auth` | Job `paused`, Admin-Hinweis „Zugang prüfen“ |
| `rc.contract.incompatible` | Vertrag passt nicht / kein Handler | `config` | Job `paused`, stündliche Wiederaufnahme |
| `rc.source.unavailable` | Quellsystem/-plugin nicht verfügbar | `config` | Job `paused`, stündliche Wiederaufnahme nach erneuter Prüfung |
| `rc.data.invalid_payload`, `rc.data.duplicate`, `rc.consent.missing` | ungültige Daten | `data` | kein Retry, Eintrag verworfen bzw. Job `dead` |
| `rc.contract.invalid`, `rc.remote.removed`, `rc.perm.failed` | endgültiger Fehler / Objekt in Quelle gelöscht | `permanent` | kein Retry, Job `dead`, ggf. Tombstone |

Der Connector selbst wird durch Jobfehler nicht automatisch umgestellt; Pausieren/Fortsetzen eines Connectors erfolgt über `Registry::set_state()` (Pro).

Die maßgebliche Retry-Klasse definiert `ErrorCode`; diese Tabelle beschreibt die erwartete Wirkung.

## Versionierung und Kompatibilität

- **Semantische Versionierung** des Vertrags. Der Hook-Name enthält die Major-Version (`_v1`). Eine Major-Version 2 erhält einen neuen Hook (`review_captain_connectors_v2`); v1 bleibt mindestens für die Deprecation-Frist parallel bestehen.
- **Major-Kompatibilität:** Ein Connector mit `contract_version()` Major `1` ist mit jedem Core kompatibel, der Contract 1.x unterstützt. Anderer Major → Status `paused` mit `rc.contract.incompatible`, nicht ausgeführt, kein Fatal Error.
- **Minor-Releases** (1.1, 1.2 …) sind rein additiv: neue optionale Interfaces, neue optionale Payload-Felder, neue Capability-Werte. Bestehende Methoden, Signaturen, Pflichtfelder und Bedeutungen ändern sich nicht. Meldet ein Connector eine höhere Minor-Version als der Core kennt, wird er akzeptiert; unbekannte zusätzliche Fähigkeiten werden ignoriert und in der Diagnose als Hinweis angezeigt.
- **Deprecation-Frist:** Ein als veraltet markiertes Vertragselement bleibt mindestens **2 Minor-Releases und mindestens 6 Monate** (es gilt das spätere Datum) funktionsfähig. Deprecations werden im Changelog, in dieser Datei und per `_doing_it_wrong()`/Diagnosehinweis (nur Admin) angekündigt. Entfernt wird nur in einer neuen Major-Version.
- Die vom Core unterstützte Contract-Version wird in der Diagnose angezeigt.

## Offene Punkte

| ID | Frage | Arbeitsannahme |
|---|---|---|
| CON-OPEN-1 | Push-Eingang `review_captain_experience_event_v1` (Konzept §7.4) zusätzlich zum Polling? | nicht in 1.0; ggf. additiv in 1.1 |
| CON-OPEN-2 | Hook `review_captain_connector_error_v1` (Konzept §7.10) | nicht in 1.0; Fehler über Diagnose/Audit-Log |
| CON-OPEN-3 | Deklaratives Konfigurationsschema (CON-011: Text, URL, Auswahl, Zahl, Secret) | optionales Interface in 1.1 (Pro-Manager) |
| CON-OPEN-4 | Öffentliche Job-API zum Einreihen eigener Connector-Jobs | erledigt: `JobQueue::enqueue()` über `Plugin::instance()->get( 'jobs' )`, siehe §4 |
| CON-OPEN-5 | Sollen `origin=connector`-Reviews mit `platform=local` für Schema in Frage kommen? | ja, nur bei nachweislicher Abgabe auf dieser Website; siehe [../seo/schema-eligibility.md](../seo/schema-eligibility.md) |
