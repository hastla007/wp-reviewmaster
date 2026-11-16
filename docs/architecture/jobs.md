# Job-Runner, Cron, Lease, Idempotenz, Retry und API-Budgets

Status: Spezifikation für Core v1, an den implementierten Stand angeglichen (`src/Jobs/*`, `src/Support/ErrorCode.php`), Stand 2026-11-16. Wo dieses Dokument und der Code abweichen, gilt der Code; nicht umgesetzte Teile sind als „Ausblick (nicht in v1)“ markiert. Deckt **TEC-001, TEC-002, TEC-003, TEC-004, TEC-006, TEC-007** ab (TEC-005 Race-Tests verweisen hierauf). Entscheidung: [ADR-0003](../adr/0003-eigener-job-runner-mit-lease.md). Tabelle `jobs`: [database.md](database.md) §2.4 (exakt nach `src/Install/Schema.php`).

## 1. Komponenten

| Komponente | Klasse (implementiert) | Aufgabe |
|---|---|---|
| Queue | `ReviewCaptain\Jobs\JobQueue` (Dienst `jobs`) | `enqueue()`, `claim()`, `extend_lease()`, `complete()`, `continue_later()`, `fail()` (Retry-Politik je Klasse), `pause()`, `resume_paused_with_code()`, `resume_paused()`, `retry()`, `cancel()` (→ `failed`), `find()`, `stats()`, `recent()`, `purge_finished()`, statisch `backoff()`, `normalize_key()`, `sanitize_message()` |
| Runner | `ReviewCaptain\Jobs\Runner` (Dienst `jobs.runner`) | `run( $time_budget_seconds, $worker_id = null )`: Zeitbudget, Claim-Schleife; `register_handler()` für Core-Jobtypen; `process()` / Ergebnisauswertung; statisch `heartbeat()`, `is_side_effect_free()`, `allowed_context()`, `new_worker_id()` |
| Retry-Politik | `JobQueue::fail()` + `ErrorCode::retry_class()` | Fehlerklasse → nächster Zustand ([§6](#6-retry--und-dead-letter-politik-tec-006)) |
| Budget | `ReviewCaptain\Jobs\Budget` | Tagesbudgets je Scope, z. B. `profile:12` ([§8](#8-api-budgets-und-parallelität-tec-007)) |
| Diagnose | `ReviewCaptain\Jobs\Diagnostics` | Runner-Gesundheit, Zähler ([§9](#9-anzeige-fälligerüberfälliger-jobs-tec-002)) |
| Modul | `ReviewCaptain\Jobs\JobsModule` | Dienste, Cron-/AS-Planung, Diagnoseabschnitt |
| CLI | `ReviewCaptain\Cli\Commands` (registriert in `CliModule`) | `wp review-captain jobs …` |

Ausblick (nicht in v1): eigene Klassen `HandlerRegistry`, `RetryPolicy`, `JobsCommand` sowie das objektorientierte Handler-Interface aus §3.1.

## 2. Auslöser (TEC-001)

Alle Auslöser rufen **denselben** `Runner::run( $time_budget_seconds )` auf (Budget ≤ 0 = Einstellung `advanced.job_time_budget`, Default 20 s); der Kanal wird vorher per `Runner::heartbeat( $channel )` vermerkt. Die `jobs`-Tabelle ist die einzige Wahrheit.

| Kanal | Mechanismus | Default |
|---|---|---|
| WP-Cron | wiederkehrendes Event, Hook `review_captain_run_jobs`, eigenes Intervall `review_captain_5min` (300 s) über Filter `cron_schedules` | aktiv, Zeitbudget 20 s |
| System-Cron | `wp review-captain jobs run` | **empfohlen für Produktion**, Zeitbudget aus der Einstellung (per `--budget=<s>` änderbar) |
| Action Scheduler | Nur wenn `as_schedule_recurring_action` und `as_has_scheduled_action` existieren (z. B. durch WooCommerce oder Standalone-AS) **und** `advanced.use_action_scheduler = true`; dann wird das WP-Cron-Event entfernt: wiederkehrende Aktion `review_captain_run_jobs`, Gruppe `review-captain`, alle 300 s, `unique = true`. AS dient nur als Taktgeber; es werden keine Review-Captain-Jobs in AS gespeichert. | automatisch, abschaltbar in „Erweitert“ |
| Admin-Aktion | „Erneut einreihen“ (`JobQueue::retry()`) setzt `available_at = jetzt` – **kein** synchrones Ausführen im Admin-Request | – |
| Tägliche Wartung | WP-Cron-Event `review_captain_daily_maintenance` (Intervall `daily`, erster Lauf +1 h) ruft `Maintenance::run()` direkt auf; zusätzlich Jobtyp `maintenance.daily` | aktiv |

`review_captain_run_jobs` und `review_captain_daily_maintenance` sind interne Hooks ohne Stabilitätszusage (kein öffentlicher Vertrag, siehe [ADR-0002](../adr/0002-hook-praefix-review-captain.md)).

Registrierung (`JobsModule::ensure_scheduled()`): nach Installation (`review_captain_installed`) und auf `admin_init` (falls fehlend, nie im Frontend) `wp_schedule_event( time(), 'review_captain_5min', 'review_captain_run_jobs' )` bzw. die AS-Aktion. Deaktivierung entfernt das Event (`wp_clear_scheduled_hook`) und die AS-Aktion (`as_unschedule_all_actions( 'review_captain_run_jobs', [], 'review-captain' )`).

### 2.1 Empfohlene Produktionskonfiguration (TEC-002)

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```cron
*/5 * * * * cd /pfad/zu/wordpress && wp review-captain jobs run --quiet
# zusätzlich für den restlichen WordPress-Cron:
*/5 * * * * cd /pfad/zu/wordpress && wp cron event run --due-now --quiet
```

Multisite: in v1 je Site mit `wp review-captain jobs run --url=<site>` aufrufen. Ausblick (nicht in v1): `--network` mit Gesamtlimit `--network-time-limit`.

Ohne System-Cron läuft WP-Cron nur bei Seitenaufrufen; Ausführungszeiten sind dann **nicht** zugesichert.

### 2.2 CLI-Befehle (implementiert, `CliModule::definitions()`)

| Befehl | Zweck |
|---|---|
| `wp review-captain jobs run [--budget=<s>]` | Runner-Lauf |
| `wp review-captain jobs list [--status=<s>] [--limit=50] [--format=table\|json\|csv\|yaml]` | Übersicht |
| `wp review-captain jobs retry <id>` | `dead`/`failed`/`paused` → `queued`, `attempts = 0`; `queued` → sofort fällig |
| `wp review-captain jobs stats [--format=…]` | Zähler je Status, überfällige Jobs |
| `wp review-captain maintenance run [--budget=<s>]` | tägliche Wartung (Aufbewahrung, Aufräumen gemäß [retention-matrix.md](retention-matrix.md)) |
| `wp review-captain reconcile [--fix] [--mode=…] [--format=…]` | [reconciliation.md](reconciliation.md) |
| `wp review-captain doctor [--format=…]` | Schema, Cron und Schlüssel prüfen |
| `wp review-captain schema upgrade` | Schema installieren/aktualisieren |
| `wp review-captain dev seed` | Testdaten (nur Entwicklung) |

Ausblick (nicht in v1): `--max-jobs`, `--type`, `--network`, `jobs list --overdue`, `jobs show <id>`, `jobs cleanup`.

## 3. Runner-Ablauf und Zeitbudget

```
run(time_budget = advanced.job_time_budget):   // Pseudocode, Runner::run()
  if db_version != code_version            → return (Migration ausstehend)
  if Reconcile-Sperre aktiv                → nur Jobtypen ohne Außenwirkung (siehe reconciliation.md)
  write heartbeat(channel, now)
  deadline = start + min(budget, max_execution_time − 5 s)   // max_execution_time 0 = unbegrenzt → budget
  while now < deadline − 2 s and processed < max_jobs (50):
     if memory_get_usage() > 80 % memory_limit → break
     if running_jobs_with_valid_lease >= global_concurrency (2) → break
     candidates = SELECT id FROM jobs
                  WHERE (status='queued' AND available_at <= :now)
                     OR (status='running' AND lease_expires_at < :now)
                  ORDER BY available_at, id LIMIT 10
     claimed = first candidate where claim(id) == 1 row
     if none → break
     handle(job, remaining = deadline − now)
```

Implementierte Grenzen (`Runner::run()`): Abbruch, wenn Schema-Version veraltet; Budget = `min( budget, max_execution_time − 5 s )`; höchstens `review_captain_runner_max_jobs` (Default 50) Jobs je Lauf; Abbruch bei > 80 % `memory_limit`; globale Parallelität `review_captain_runner_concurrency` (Default 2) laufende Jobs mit gültiger Lease. Claim erfolgt einzeln (`claim( $worker_id, $lease, 1 )`). Bei aktiver Reconcile-Sperre (`review_captain_reconcile_required`) werden Jobs mit Außenwirkung ohne Versuchsverbrauch um 300 s verschoben (`continue_later`); ohne Außenwirkung gelten die Präfixe `retention.`, `maintenance.`, `reconcile`, `import.`, `dev.` (Filter `review_captain_side_effect_free_job_prefixes`).

- Neue Jobs werden nur geclaimt, solange Budget übrig ist. Ein laufender Handler wird nicht hart abgebrochen; Handler **müssen** in kleinen Schritten arbeiten (z. B. eine API-Seite) und dann mit `['status' => 'continue', …]` einen Checkpoint zurückgeben.
- `JobsModule::run_tick()` setzt `ignore_user_abort( true )` nur im WP-Cron-Kanal; `set_time_limit()` wird nicht erhöht. `Runner::allowed_context()` verhindert Läufe außerhalb von Cron/CLI.
- Frontend-Requests führen nie Jobs synchron aus.

### 3.1 Handler-Vertrag (implementiert)

Core-Handler werden mit `Runner::register_handler( string $type, callable $handler )` registriert und als `$handler( array $payload, array $job )` aufgerufen. Fremde Typen (Connectoren, Pro) laufen über den Filter:

```php
$outcome = apply_filters( 'review_captain_process_job_v1', null, string $type, array $payload );
```

Zulässige Ergebnisse (`Runner::apply_outcome()`):

| Ergebnis | Folge |
|---|---|
| `true` oder `['status' => 'succeeded']` | `complete()` → `succeeded` |
| `['status' => 'continue', 'payload' => array, 'delay' => int]` | `continue_later()`: Payload gemergt, `queued`, `available_at = jetzt + delay`, Versuch wird zurückgegeben (Checkpoint) |
| `['status' => 'failed'\|'retry', 'error_code' => 'rc.*', 'message' => string, 'retry_after' => int]` | `fail()` nach Retry-Klasse (§6) |
| `WP_Error` mit `rc.*`-Code | `fail()` |
| `null` (kein Handler) | `pause()` mit `rc.contract.incompatible` („Kein Handler für Jobtyp … registriert“), Versuch wird zurückgegeben |
| anderes | `fail()` mit `rc.contract.invalid` |
| geworfene `ConnectorException`/`ProviderException` bzw. Objekt mit `error_code()` | `fail()` mit deren Code (unbekannt → `rc.temp.unavailable`) und ggf. `retry_after()` |
| anderes `Throwable` | `fail()` mit `rc.temp.unavailable`; Nachricht wird sanitisiert (§7) |

Gehört der Typ (`{connector_key}.{name}`) einem bekannten, aber nicht aktiven Connector, wird der Job **vor** dem Filter mit `rc.source.unavailable` pausiert. Pausierte Jobs mit `rc.contract.incompatible` oder `rc.source.unavailable` werden höchstens einmal pro Stunde wieder eingereiht (`JobQueue::resume_paused_with_code()`, Transient `review_captain_resume_unhandled`).

Ausblick (nicht in v1): objektorientiertes Interface `JobHandler::handle( JobContext ): JobResult` mit `remaining_seconds()`/`extend_lease()` im Kontext.

### 3.2 Core-Jobtypen

Implementiert (Core v1):

| `type` | Zweck | `idempotency_key` |
|---|---|---|
| `reconcile` | [reconciliation.md](reconciliation.md); nach Schema-Upgrade automatisch mit `mode = upgrade` | `reconcile:upgrade:{db_version}` |
| `maintenance.daily` | tägliche Wartung (`Maintenance::run()`: Aufbewahrung, Jobs aufräumen) | vom Aufrufer gewählt |
| `dev.noop` | Test-/Entwicklungsjob | vom Aufrufer gewählt |

Ausblick (nicht in v1, Namen vorgemerkt): `retention.purge`, `retention.refresh_gate`, `import.csv`, `import.rollback` (CSV-Import läuft in v1 synchron im Admin, `CsvImporter`), `privacy.erase`, `maintenance.jobs_cleanup`, `maintenance.migrate_backfill`, `secrets.rotate`; Pro: `pro.provider.sync`, `pro.provider.token_check`, `pro.request.send`.

## 4. Claim und Lease (TEC-003)

Claim (atomar, exakt nach `conventions.md`, `:now` = `gmdate( 'Y-m-d H:i:s' )`):

```sql
UPDATE {prefix}review_captain_jobs
   SET status = 'running',
       lease_owner = %s,
       lease_expires_at = %s,           -- :now + lease_seconds
       attempts = attempts + 1,
       updated_at = %s
 WHERE id = %d
   AND (   (status = 'queued'  AND available_at <= %s)
        OR (status = 'running' AND lease_expires_at < %s) )
```

Erfolg nur bei `$wpdb->rows_affected === 1`; sonst hat ein anderer Worker gewonnen → nächster Kandidat.

- **Lease-Default 300 s** (Einstellung `advanced.lease_seconds`, Bereich 60–3600 s). `lease_owner` aus `Runner::new_worker_id()` (≤ 64 Zeichen), je Lauf neu.
- **Verlängern:** `extend_lease()` → `UPDATE … SET lease_expires_at = :now + lease WHERE id = %d AND lease_owner = %s AND status = 'running'`; bei 0 Zeilen hat der Worker die Lease verloren und muss sofort ohne weitere Schreibvorgänge abbrechen.
- **Abschluss** (Statuswechsel aus `running`) immer mit `WHERE id = %d AND lease_owner = %s`. Ein Worker mit verlorener Lease kann so kein Ergebnis überschreiben.
- **Recovery nach Prozessabbruch:** Jobs mit abgelaufener Lease werden vom nächsten Tick normal geclaimt (zweite Bedingung im Claim); der Versuch zählt (`attempts + 1`). Zusätzlich setzt die Reconciliation abgelaufene Leases zurück. Ausblick (nicht in v1): sofort `dead`, wenn nach dem Claim `attempts > max_attempts`.
- Externe Seiteneffekte (E-Mail-Versand, API-Schreibaktionen) müssen selbst idempotent sein (Provider-Idempotency-Key bzw. Statusvermerk **vor** dem Versand in einer eigenen Tabelle), weil eine abgelaufene Lease einen zweiten Versuch erlaubt.

## 5. Idempotenz (TEC-004)

### 5.1 Unique-Keys in der Datenbank

| Schutz gegen | Mechanismus |
|---|---|
| doppelte externe Reviews | `reviews.dedupe_key` UNIQUE = `sha1(platform|profile_id|external_id)` ([database.md](database.md) §2.1.1) |
| doppelte Jobs | `jobs.idempotency_key` UNIQUE |
| doppelte Hook-Events | Ausblick (nicht in Schema v1): `review_events.event_id` UNIQUE |
| doppelte Profile | `profiles (platform, external_ref)` UNIQUE |
| doppelter CSV-Import | `dedupe_key` bzw. Dubletten-Prüfung im `CsvImporter`; Ausblick: `import_batches.file_sha256` + Rückfrage |
| doppelte Einladungen (Pro) | Unique `experience_id + recipient_ref + campaign_id` in Pro-Tabelle (RC-025) |
| doppelte Provider-/Suite-Events (Pro) | Unique `(source, event_id)` in Pro-Tabelle |

### 5.2 Einreihen

`JobQueue::enqueue( string $type, array $payload, string $idempotency_key, int $delay_seconds = 0, int $max_attempts = 8 ): int|false`:

- `type` muss `^[a-z0-9_.\-]+$` erfüllen (≤ 100 Zeichen), Payload als JSON ≤ 64 KiB (`MAX_PAYLOAD_BYTES`), sonst `false`. `max_attempts` wird auf 1–100 begrenzt.
- Idempotenzschlüssel > 191 Zeichen werden zu `{erste 140 Zeichen}:sha1:{sha1}` gekürzt (`normalize_key()`).
- Existiert der Schlüssel bereits (gleich in welchem Status), liefert `enqueue()` die bestehende Job-ID und ändert **nichts**; ein verlorenes Insert-Rennen auf dem UNIQUE-Key wird ebenso aufgelöst.
- Wiederholen erledigter Jobs nur ausdrücklich über `retry( $id )` (Admin/CLI).

Ausblick (nicht in v1): `available_at` vorziehen, automatisches Re-Armen periodischer Jobs als langlebige Zeile, Option `requeue: true`.

## 6. Retry- und Dead-Letter-Politik (TEC-006)

### 6.1 Zustände

| Status | Bedeutung | wird geclaimt? |
|---|---|---|
| `queued` | wartet bis `available_at` (auch nach Retry) | ja |
| `running` | hat Lease | nur nach Lease-Ablauf |
| `succeeded` | erfolgreich abgeschlossen | nein |
| `failed` | beendet ohne Erfolg und **ohne** Wiederholungsbedarf, z. B. abgebrochen durch Disconnect, veraltet/überholt; entspricht dem „cancelled“ aus `konzept.md` 7.9 | nein |
| `dead` | Dead-Letter: Versuche erschöpft oder permanenter/Daten-Fehler; Admin-Aktion nötig | nein |
| `paused` | wartet auf Bedingung (Reconnect, Handler, Vertragskompatibilität) | nein |

> Offene Entscheidung: Ob ein eigener Status `cancelled` nötig ist, wird per ADR entschieden; bis dahin setzt `JobQueue::cancel()` den Job auf `failed` mit `last_error_code = rc.source.unavailable` und Meldung „Abgebrochen.“.

### 6.2 Fehlerklassen

Die Zuordnung Fehlercode → Klasse liegt verbindlich in `ReviewCaptain\Support\ErrorCode::retry_class()` ([error-codes.md](error-codes.md)). Codes, die nicht dem Muster `rc.<bereich>.<code>` entsprechen, behandelt `fail()` als `rc.temp.unavailable`; unbekannte, aber formal gültige Codes fallen in die Klasse `transient`.

| Klasse (`ErrorCode::CLASS_*`) | Codes (exakt nach Code) | Verhalten Job (`JobQueue::fail()`) | Verhalten Profil / Admin |
|---|---|---|---|
| **transient** | `rc.net.timeout`, `rc.net.unreachable`, `rc.temp.unavailable`, `rc.internal.error`, jeder unbekannte Code | `queued`, `available_at = jetzt + backoff(attempts)` (§6.3); ist `attempts >= max_attempts` (Default 8) → `dead` | nach `dead`: Warnung auf Diagnoseseite |
| **rate_limit** | `rc.quota.exceeded`, `rc.quota.rate_limited`, `rc.quota.budget_exhausted` | `queued`, `available_at = jetzt + retry_after` (§6.4); zählt **nicht** als Versuch (`attempts − 1`); Payload-Zähler `_rl_deferrals`; mehr als `max(48, 3 × max_attempts)` Verschiebungen → `dead` | Ausblick: Profil-Backoff (`backoff_until`) für alle Jobs des Profils |
| **auth** | `rc.auth.invalid`, `rc.auth.expired`, `rc.auth.revoked`, `rc.auth.permission_denied`, `rc.crypto.key_missing`, `rc.crypto.decrypt_failed` | `paused` | Hinweis „Verbindung neu autorisieren bzw. Schlüssel prüfen“; Fortsetzen über `resume_paused( $type_prefix )` bzw. `retry()`. Ausblick (Pro): Profil `status = auth_required` |
| **config** (neu) | `rc.contract.incompatible`, `rc.source.unavailable` | `paused`; automatische Wiederaufnahme höchstens stündlich | Hinweis „Quell-Plugin bzw. Connector prüfen (aktiv, kompatible Vertragsversion)“ |
| **data** | `rc.data.invalid_payload`, `rc.data.duplicate`, `rc.consent.missing` | `dead` (ganze Antwort unbrauchbar). **Einzelne** ungültige Items überspringt der Aufrufer (z. B. `SyncService` zählt `invalid`), der Job läuft weiter | Diagnose zeigt Anzahl übersprungener Items |
| **permanent** | `rc.contract.invalid`, `rc.remote.removed`, `rc.perm.failed` | `dead` | Fehler mit Code in Diagnose; `rc.remote.removed` an einzelnen Reviews führt stattdessen zum Tombstone ([retention-matrix.md](retention-matrix.md)) |

Typische HTTP-Abbildung (`ProviderException::from_http()`): 0/Timeout → `rc.net.timeout`; 401 → `rc.auth.invalid` bzw. `rc.auth.expired`; 403 → `rc.auth.permission_denied`; 404/410 → `rc.remote.removed`; 429 → `rc.quota.rate_limited`; ≥ 500 → `rc.temp.unavailable`; sonst `rc.data.invalid_payload`.

### 6.3 Backoff (transient)

`JobQueue::backoff( int $attempts, bool $jitter )` mit `BACKOFF_BASE = 60`, `BACKOFF_MAX = 21600`:

```
delay(n) = min( 6 h, 60 s × 3^(n−1) ) × U(0,8 … 1,2)     // n = attempts inkl. des fehlgeschlagenen Versuchs (1…20), Jitter ±20 %
n:      1     2     3      4      5      6       7
delay:  1 min 3 min 9 min  27 min 81 min 243 min 360 min (Deckel)
```

Nach dem 8. fehlgeschlagenen Versuch (`attempts = max_attempts = 8`) → `dead`. Gesamtspanne ≈ 12 h.

### 6.4 Retry-After

- `retry_after` (Sekunden, z. B. aus `ProviderException::retry_after()` oder dem Handler-Ergebnis) wird auf 60 s … 24 h begrenzt; fehlt er bei einer Rate-Limit-Klasse: 15 min (`RATE_LIMIT_DEFAULT = 900`).
- Die Umrechnung von HTTP-Datumswerten und providerspezifischen Quota-Headern in Sekunden ist Aufgabe des Adapters.

### 6.5 Admin-Aktionen

Dead-Letter-Liste in Diagnose (Typ, Code, sanitisierte Meldung, Versuche, Zeitpunkt) mit „Erneut einreihen“ (`JobQueue::retry()`, Capability je Bereich, siehe [capabilities.md](capabilities.md)) und „Verwerfen“ (`JobQueue::cancel()` → `failed`). Manuelle Wiederholungen respektieren Rate-Limit-Backoff des Profils.

## 7. Fehlermeldungen und Logs

`last_error_message` ≤ 500 Zeichen, sanitisiert: Entfernen von `Authorization`/`apikey`-Headern, Query-Parametern (`access_token`, `code`, `key`, `client_secret`), JWTs (`eyJ…`), E-Mail-Adressen und Reviewtext. Rohantworten werden nicht gespeichert.

## 8. API-Budgets und Parallelität (TEC-007)

Implementiert in v1 ist der Budgetzähler `ReviewCaptain\Jobs\Budget`: Zähler je Scope (z. B. `profile:12`) und UTC-Tag in der nicht automatisch geladenen Option `review_captain_budget_{scope}`; `consume( $scope, $units, $limit_per_day )` verweigert (und zählt nichts), wenn das Limit überschritten würde; `is_warning()` ab 80 % (`WARN_THRESHOLD`); `remaining()`, `used()`, `seconds_until_reset()`, `reset()`. Ein erschöpftes Budget meldet der Aufrufer mit `rc.quota.budget_exhausted` (Klasse `rate_limit`). Globale Vorgabe: `sources.max_pages_per_run` (Default 5, 1–50).

Ausblick (nicht in v1, Pro/Provider-Runtime): Je Profil in `profiles.settings` (Defaults je Provider vom Adapter, Werte **Arbeitsannahme**):

| Einstellung | Bedeutung | Default |
|---|---|---|
| `page_size` | Einträge je API-Aufruf (≤ Provider-Maximum) | Provider-Maximum, max. 100 |
| `max_pages_per_run` | Seiten je Joblauf, danach Checkpoint | 5 |
| `max_requests_per_day` | Tagesbudget (UTC-Tag) | 500 |
| `min_interval_minutes` | Mindestabstand zwischen Sync-Läufen | 60 (Trustpilot: so, dass Refresh ≤ 24 h eingehalten wird) |
| `lookback_days` | Initialimport-Rückblick | 365 (Google Play: API liefert nur ca. 7 Tage, siehe [provider-auth.md](provider-auth.md)) |
| `warn_threshold_pct` | Admin-Warnung ab Budgetverbrauch | 80 |

- **Parallelität:** global max. 2 gleichzeitig laufende Jobs je Site (Filter `review_captain_runner_concurrency`). Ausblick (nicht in v1): je Profil max. **1** API-Job gleichzeitig: Profil-Sperre über `GET_LOCK( 'rc:{blog_id}:profile:{profile_id}', 0 )` (sessiongebunden, wird bei Prozessabbruch automatisch frei). Nicht erhaltene Sperre → `queued`, +60 s, kein Versuch. Hosts ohne `GET_LOCK` (selten, z. B. bestimmte Proxys): Fallback auf Lease-Prüfung „kein anderer `running`-Job mit gleichem Profil im Payload“ (nicht atomar, in Diagnose markiert).
- Ein manueller Sync kann Budgets nicht überschreiten; er zieht nur `available_at` vor.

## 9. Anzeige fälliger/überfälliger Jobs (TEC-002)

Diagnose und Quellen-Seite zeigen:

- **Runner-Gesundheit:** letzter Lauf je Kanal aus `review_captain_runner_heartbeat`; Status `ok` (< 10 min), `verzögert` (10–30 min), `gestört` (> 30 min oder `DISABLE_WP_CRON` ohne CLI-/AS-Heartbeat in 30 min). Bei `verzögert`/`gestört` Hinweis auf System-Cron (§2.1).
- **Fällig:** `queued` mit `available_at <= jetzt`. **Überfällig:** `available_at < jetzt − 10 min` (zwei verpasste Ticks). Anzeige als Anzahl je Typ, ältester Fälligkeitszeitpunkt relativ („seit 42 Minuten fällig“).
- **Keine Scheinpräzision:** Nächste Ausführungen werden als „frühestens ab 14:05 Uhr“ formuliert; ohne gesunden System-Cron/AS zusätzlich „abhängig von Seitenaufrufen“.
- Zähler: `running`, `paused` (mit Grund), `dead` (letzte 7 Tage), Budgetverbrauch je Profil.

## Abnahme

- [ ] WP-Cron-Event `review_captain_run_jobs` existiert nach Aktivierung mit Intervall 300 s und ist nach Deaktivierung entfernt.
- [ ] `wp review-captain jobs run` arbeitet fällige Jobs ab und endet spätestens nach `--budget` + Dauer des letzten Handler-Schritts (Default-Test: ≤ 25 s bei 10.000 wartenden Jobs).
- [ ] Mit Action Scheduler existiert genau eine wiederkehrende AS-Aktion `review_captain_run_jobs`; ohne AS keine Fehler.
- [ ] Parallelitätstest: 4 gleichzeitige Runner auf 1.000 Jobs → jeder Job genau einmal `succeeded`, kein Job doppelt im Handler (TEC-003/TEC-005).
- [ ] Prozessabbruch während `running` → nach Lease-Ablauf erneuter Claim; Ergebnis des alten Workers wird verworfen (`lease_owner`-Bedingung).
- [ ] Doppeltes `enqueue` mit gleichem Key erzeugt eine Zeile; paralleler Sync derselben Quelle erzeugt keine doppelten Reviews.
- [ ] Je Fehlerklasse ein Test: transient (Backoff-Folge, `dead` nach 8), rate_limit (Retry-After eingehalten, kein Versuch verbraucht), auth (`paused`), config (`paused`, stündliche Wiederaufnahme), permanent/data (`dead`; einzelnes Item übersprungen).
- [ ] Budget erschöpft → keine weiteren API-Aufrufe am selben UTC-Tag; Warnung ab 80 %.
- [ ] Diagnose zeigt Runner-Status, fällige/überfällige Jobs und formuliert Zeiten als „frühestens“.
- [ ] `last_error_message` enthält in Tests mit präparierten Fehlermeldungen keine Tokens, Secrets, E-Mails.
