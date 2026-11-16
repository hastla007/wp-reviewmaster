# CON-002 / CON-003 – Connector-Vertrag v1

Status: **verbindlich** für Core-Implementierung und Referenz-Connector ab 2026-11-16. Änderungen am Vertrag nur per ADR (`docs/adr/`) und gemäß [Versionierung](#versionierung-und-kompatibilität).
Gilt nur für **lokale** Connectoren derselben WordPress-Installation. Fähigkeiten und Abgrenzung: [use-cases.md](use-cases.md).

## Überblick

| Element | Wert |
|---|---|
| Registry-Hook | `review_captain_connectors_v1` (Action) |
| Registry-Klasse | `ReviewCaptain\Connectors\Registry` |
| Registrierung | `$registry->register( ConnectorInterface $connector )` |
| Haupt-Interface | `ReviewCaptain\Connectors\Contract\ConnectorInterface` |
| Capability-Interfaces | `ReviewCaptain\Connectors\Contract\{ExperienceSource,RecipientResolver,ReviewSource,RequestTransport,ContextProvider}Interface` |
| Exception | `ReviewCaptain\Connectors\ConnectorException( string $error_code, string $message )` |
| Job-Hook | `review_captain_process_job_v1` (Filter) |
| Contract-Version | Major `1`, Connector meldet `1.<minor>` bzw. `1.x` |

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

1. Review Captain löst `review_captain_connectors_v1` **einmal pro Request** aus, nachdem `review_captain_loaded` gefeuert wurde. Connector-Plugins hängen ihren Callback spätestens in `plugins_loaded` ein. Ist Review Captain nicht aktiv, wird der Hook nie ausgelöst – der Connector muss dann ohne Fehler untätig bleiben (keine harte Abhängigkeit auf Klassen von Review Captain außerhalb dieses Callbacks bzw. `class_exists`-Guard vor `implements`, siehe [developer-guide.md](developer-guide.md)).
2. `register()` wirft **nie** und bricht den Request nie ab. Ungültige Connectoren werden verworfen bzw. als nicht ausführbar markiert und in der Diagnose angezeigt. Der Rückgabewert von `register()` ist **nicht** Teil des Vertrags.
3. Registrierungen außerhalb des Hooks (z. B. direkter Zugriff auf eine Registry-Instanz) sind nicht unterstützt.
4. Die Registrierung führt **keine** Connector-Aktion aus. Ein neu erkannter Connector ist standardmäßig **inaktiv**, bis ein Administrator ihn aktiviert (siehe [licensing.md](licensing.md)).
5. Der Core ruft Capability-Methoden nur im Jobrunner bzw. in Admin-Aktionen mit Capability- und Nonce-Prüfung auf – nie während eines Frontend-Requests. Auch `describe_context` wird nur im Hintergrund aufgerufen; das Frontend liest ausschließlich den Cache.

### Validierung bei `register()`

| Prüfung | Regel | Ergebnis bei Verstoß |
|---|---|---|
| Typ | Objekt implementiert `ConnectorInterface` | verworfen (Diagnose: `rc.contract.incompatible`) |
| `key()` | Regex `^[a-z0-9][a-z0-9_-]{2,63}$` (3–64 Zeichen) | verworfen, `rc.data.invalid_payload` |
| Eindeutigkeit | Schlüssel noch nicht registriert | zweite Registrierung verworfen, erste bleibt; Diagnose „doppelter Schlüssel“ |
| `contract_version()` | Format `^1\.(\d+|x)$`; Major muss `1` sein | Status `incompatible_contract`, nicht ausführbar, kein Fatal Error |
| `capabilities()` | nicht leer, nur bekannte Werte, keine Duplikate | unbekannte Werte → `incompatible_contract`; leer → verworfen |
| Capability ↔ Interface | jede deklarierte Capability hat das passende Interface | `incompatible_contract` |
| `label()`, `developer()` | nicht leer, max. 191 Zeichen, werden escaped ausgegeben | verworfen |
| `version()` | nicht leer, max. 64 Zeichen (SemVer empfohlen) | verworfen |
| Exceptions | Jede Exception/`Throwable` aus den Metadaten-Methoden wird gefangen | verworfen, Diagnose |

Ein implementiertes Interface **ohne** deklarierte Capability wird ignoriert (keine Aktion). Reservierte Schlüsselpräfixe: `rc-`, `review-captain` und Schlüssel offizieller Suite-Adapter (`woocommerce`, `voucher-captain`, `pass-captain`, `affiliate-captain`, `form-captain`).

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

Der Core ergänzt `source = key()` und lehnt Ereignisse ab, deren E-Mail-ähnliche Werte (`@` in `recipient_ref`) personenbezogene Daten vermuten lassen (`rc.data.invalid_payload`).

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
- Bleibt das Ergebnis `null`, markiert der Core den Job als `failed` mit `rc.contract.incompatible` („kein Handler“) und pausiert nach `max_attempts`.
- Payload max. 64 KB JSON, enthält ein Feld `v` (Payload-Version) und **keine** Secrets, E-Mail-Adressen oder Reviewtexte.
- Handler müssen idempotent sein: derselbe Job kann nach Lease-Ablauf erneut ausgeführt werden.
- Jobs werden nur ausgeführt, wenn der Connector aktiv und vertragskompatibel ist und (Pro) das Entitlement besteht.
- Das Anlegen eigener Jobs erfolgt über die Core-Job-API (Signatur in der Core-Implementierung, `ReviewCaptain\Jobs\…`); Connectoren schreiben nicht direkt in die Tabelle `jobs`.

## 5. Fehlerbehandlung

```php
throw new \ReviewCaptain\Connectors\ConnectorException( 'rc.auth.invalid', 'CRM-API-Schlüssel abgelehnt' );
```

- `$error_code` folgt `rc.<bereich>.<code>` und muss in `ReviewCaptain\Support\ErrorCode` bekannt sein (Liste: `docs/architecture/error-codes.md`); unbekannte Codes werden als `rc.temp.unavailable` behandelt.
- `$message` ist für Administratoren bestimmt, wird gekürzt (500 Zeichen) und sanitisiert gespeichert. **Keine** Secrets, E-Mail-Adressen, Tokens oder Reviewtexte in der Nachricht.

| Code | Bedeutung | Core-Reaktion |
|---|---|---|
| `rc.net.timeout` | Zeitüberschreitung | Retry mit exponentiellem Backoff |
| `rc.temp.unavailable` | temporärer Fehler | Retry mit Backoff |
| `rc.quota.exceeded` | Rate Limit | Retry nach Backoff (bzw. gemeldetem Wartezeitraum) |
| `rc.auth.invalid` | Anmeldung/Schlüssel ungültig | kein Retry, Connector pausiert, Admin-Hinweis „Zugang prüfen“ |
| `rc.perm.failed` | fehlende Berechtigung im Zielsystem | kein Retry, Job `dead` |
| `rc.data.invalid_payload` | ungültige Daten | kein Retry, Eintrag verworfen bzw. Job `dead` |
| `rc.remote.removed` | Objekt in Quelle gelöscht | kein Retry, ggf. Tombstone |
| `rc.contract.incompatible` | Vertrag passt nicht | Connector pausiert (`incompatible_contract`) |
| `rc.source.unavailable` | Quellsystem/-plugin nicht verfügbar | Jobs pausiert (`source_unavailable`), Resume nach erneuter Validierung |

Die maßgebliche Retry-Klasse definiert `ErrorCode`; diese Tabelle beschreibt die erwartete Wirkung.

## Versionierung und Kompatibilität

- **Semantische Versionierung** des Vertrags. Der Hook-Name enthält die Major-Version (`_v1`). Eine Major-Version 2 erhält einen neuen Hook (`review_captain_connectors_v2`); v1 bleibt mindestens für die Deprecation-Frist parallel bestehen.
- **Major-Kompatibilität:** Ein Connector mit `contract_version()` Major `1` ist mit jedem Core kompatibel, der Contract 1.x unterstützt. Anderer Major → `incompatible_contract`, nicht ausgeführt, kein Fatal Error.
- **Minor-Releases** (1.1, 1.2 …) sind rein additiv: neue optionale Interfaces, neue optionale Payload-Felder, neue Capability-Werte. Bestehende Methoden, Signaturen, Pflichtfelder und Bedeutungen ändern sich nicht. Meldet ein Connector eine höhere Minor-Version als der Core kennt, wird er akzeptiert; unbekannte zusätzliche Fähigkeiten werden ignoriert und in der Diagnose als Hinweis angezeigt.
- **Deprecation-Frist:** Ein als veraltet markiertes Vertragselement bleibt mindestens **2 Minor-Releases und mindestens 6 Monate** (es gilt das spätere Datum) funktionsfähig. Deprecations werden im Changelog, in dieser Datei und per `_doing_it_wrong()`/Diagnosehinweis (nur Admin) angekündigt. Entfernt wird nur in einer neuen Major-Version.
- Die vom Core unterstützte Contract-Version wird in der Diagnose angezeigt.

## Offene Punkte

| ID | Frage | Arbeitsannahme |
|---|---|---|
| CON-OPEN-1 | Push-Eingang `review_captain_experience_event_v1` (Konzept §7.4) zusätzlich zum Polling? | nicht in 1.0; ggf. additiv in 1.1 |
| CON-OPEN-2 | Hook `review_captain_connector_error_v1` (Konzept §7.10) | nicht in 1.0; Fehler über Diagnose/Audit-Log |
| CON-OPEN-3 | Deklaratives Konfigurationsschema (CON-011: Text, URL, Auswahl, Zahl, Secret) | optionales Interface in 1.1 (Pro-Manager) |
| CON-OPEN-4 | Öffentliche Job-API zum Einreihen eigener Connector-Jobs | Signatur wird mit CON-009 festgelegt und hier nachgetragen |
| CON-OPEN-5 | Sollen `origin=connector`-Reviews mit `platform=local` für Schema in Frage kommen? | ja, nur bei nachweislicher Abgabe auf dieser Website; siehe [../seo/schema-eligibility.md](../seo/schema-eligibility.md) |
