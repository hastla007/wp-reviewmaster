# Job-Runner, Cron, Lease, Idempotenz, Retry und API-Budgets

Status: Spezifikation für Core v1, Stand 2026-11-16. Deckt **TEC-001, TEC-002, TEC-003, TEC-004, TEC-006, TEC-007** ab (TEC-005 Race-Tests verweisen hierauf). Entscheidung: [ADR-0003](../adr/0003-eigener-job-runner-mit-lease.md). Tabelle `jobs`: [database.md](database.md) §2.4 (exakt nach [`conventions.md`](conventions.md)).

## 1. Komponenten

| Komponente | Klasse (Vorschlag) | Aufgabe |
|---|---|---|
| Queue | `ReviewCaptain\Jobs\JobQueue` | `enqueue()`, `claim()`, `complete()`, `reschedule()`, `pause()`, `bury()` (→ `dead`) |
| Runner | `ReviewCaptain\Jobs\Runner` | Tick: Zeitbudget, Claim-Schleife, Heartbeat |
| Handler-Registry | `ReviewCaptain\Jobs\HandlerRegistry` | Core-Jobtypen; fremde Typen über Filter `review_captain_process_job_v1` |
| Retry-Politik | `ReviewCaptain\Jobs\RetryPolicy` | Fehlerklasse → nächster Zustand ([§6](#6-retry--und-dead-letter-politik-tec-006)) |
| Budget | `ReviewCaptain\Jobs\Budget` | API-Budgets je Profil ([§8](#8-api-budgets-und-parallelität-tec-007)) |
| CLI | `ReviewCaptain\Cli\JobsCommand` | `wp review-captain jobs …` |

## 2. Auslöser (TEC-001)

Alle Auslöser rufen **denselben** `Runner::tick( $channel, $time_budget )` auf. Die `jobs`-Tabelle ist die einzige Wahrheit.

| Kanal | Mechanismus | Default |
|---|---|---|
| WP-Cron | wiederkehrendes Event, Hook `review_captain_run_jobs`, eigenes Intervall `review_captain_5min` (300 s) über Filter `cron_schedules` | aktiv, Zeitbudget 20 s |
| System-Cron | `wp review-captain jobs run` | **empfohlen für Produktion**, Zeitbudget 20 s (per `--time-limit` änderbar) |
| Action Scheduler | Nur wenn `function_exists( 'as_schedule_recurring_action' )` (z. B. durch WooCommerce oder Standalone-AS): wiederkehrende Aktion `review_captain_run_jobs`, Gruppe `review-captain`, alle 300 s, `unique = true`. AS dient nur als Taktgeber; es werden keine Review-Captain-Jobs in AS gespeichert. | automatisch, abschaltbar in „Erweitert“ |
| Admin-Aktion | „Jetzt ausführen“ (Sync/Job) setzt `available_at = jetzt` und stößt `spawn_cron()` an – **kein** synchrones Ausführen im Admin-Request | – |

`review_captain_run_jobs` ist ein interner Hook ohne Stabilitätszusage (kein öffentlicher Vertrag, siehe [ADR-0002](../adr/0002-hook-praefix-review-captain.md)).

Registrierung: Bei Aktivierung und bei `init` (falls fehlend) `wp_schedule_event( time(), 'review_captain_5min', 'review_captain_run_jobs' )`. Deaktivierung entfernt das Event (`wp_clear_scheduled_hook`) und die AS-Aktion (`as_unschedule_all_actions( 'review_captain_run_jobs', [], 'review-captain' )`).

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

Multisite: `wp review-captain jobs run --network` iteriert alle Sites mit aktivem Plugin (Zeitbudget **je Site**, Gesamtlimit `--network-time-limit`, Default 240 s).

Ohne System-Cron läuft WP-Cron nur bei Seitenaufrufen; Ausführungszeiten sind dann **nicht** zugesichert.

### 2.2 CLI-Befehle (Vorschlag)

| Befehl | Zweck |
|---|---|
| `wp review-captain jobs run [--time-limit=20] [--max-jobs=50] [--type=<type>] [--network]` | Runner-Tick |
| `wp review-captain jobs list [--status=<s>] [--type=<t>] [--overdue] [--format=table\|json]` | Übersicht |
| `wp review-captain jobs show <id>` | Details (Payload ohne Secrets) |
| `wp review-captain jobs retry <id>` | `dead`/`failed`/`paused` → `queued`, `attempts = 0` |
| `wp review-captain jobs cleanup` | Aufräumen gemäß [retention-matrix.md](retention-matrix.md) |

## 3. Runner-Ablauf und Zeitbudget

```
tick(channel, budget = 20 s):
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

- Neue Jobs werden nur geclaimt, solange Budget übrig ist. Ein laufender Handler wird nicht hart abgebrochen; Handler **müssen** `remaining` respektieren und in kleinen Schritten arbeiten (z. B. eine API-Seite) und dann mit `JobResult::continue()` einen Checkpoint zurückgeben.
- Der Runner selbst setzt `ignore_user_abort( true )` nur im WP-Cron-Kanal; `set_time_limit()` wird nicht erhöht.
- Frontend-Requests führen nie Jobs synchron aus.

### 3.1 Handler-Vertrag

```php
interface JobHandler {
    public function handle( JobContext $job ): JobResult; // $job->payload(), $job->remaining_seconds(), $job->extend_lease()
}
// JobResult::success()                         → succeeded (oder Re-Arm bei periodischen Jobs, §5.2)
// JobResult::continue( array $payload_patch )  → queued, available_at = jetzt, attempts unverändert (Checkpoint)
// JobResult::retry( ErrorCode $code, ?int $retry_after_s ) → RetryPolicy
// JobResult::fail( ErrorCode $code, string $message )      → RetryPolicy (permanent/data → dead)
```

Unbekannte Jobtypen: `apply_filters( 'review_captain_process_job_v1', null, $job_context )`. Liefert kein Handler ein `JobResult`, wird der Job `paused` mit `rc.source.unavailable` (Handler-Plugin vermutlich deaktiviert) und automatisch wieder `queued`, sobald ein Handler registriert ist (Prüfung im Tick, max. einmal pro Stunde).

Uncaught `Throwable` im Handler → behandelt wie `retry( rc.temp.unavailable )`; Nachricht wird sanitisiert (§7).

### 3.2 Core-Jobtypen (Vorschlag)

| `type` | Zweck | `idempotency_key` |
|---|---|---|
| `retention.purge` | Fristablauf, Tombstone-Bereinigung | `retention.purge` (periodisch) |
| `retention.refresh_gate` | Snapshots ohne fristgerechten Refresh verbergen | `retention.refresh_gate` (periodisch) |
| `import.csv` | CSV-Import in Batches | `import.csv:{batch_uuid}` |
| `import.rollback` | Rollback eines Imports | `import.rollback:{batch_uuid}` |
| `privacy.erase` | Löschanfrage an Jobs/Logs weiterreichen | `privacy.erase:{request_id}` |
| `reconcile` | [reconciliation.md](reconciliation.md) | `reconcile:{anlass}:{yyyymmddhh}` |
| `maintenance.jobs_cleanup` | alte Jobs löschen | `maintenance.jobs_cleanup` (periodisch) |
| `maintenance.migrate_backfill` | Datenmigration | `migrate:{db_version}:{step}` |
| Pro (Beispiele) | `pro.provider.sync`, `pro.provider.token_check`, `pro.request.send` | `pro.provider.sync:{profile_uuid}`, `pro.request.send:{request_uuid}` |

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

- **Lease-Default 300 s** (Einstellung „Erweitert“, Bereich 60–3600 s). `lease_owner` = `{8 Hex Host-Hash}-{pid}-{16 Hex random}` (≤ 64 Zeichen), je Tick neu.
- **Verlängern:** `extend_lease()` → `UPDATE … SET lease_expires_at = :now + lease WHERE id = %d AND lease_owner = %s AND status = 'running'`; bei 0 Zeilen hat der Worker die Lease verloren und muss sofort ohne weitere Schreibvorgänge abbrechen.
- **Abschluss** (Statuswechsel aus `running`) immer mit `WHERE id = %d AND lease_owner = %s`. Ein Worker mit verlorener Lease kann so kein Ergebnis überschreiben.
- **Recovery nach Prozessabbruch:** Jobs mit abgelaufener Lease werden vom nächsten Tick normal geclaimt (zweite Bedingung im Claim); der Versuch zählt (`attempts + 1`). Ist danach `attempts > max_attempts`, geht der Job sofort auf `dead` mit `last_error_message = "Lease abgelaufen, Prozessabbruch vermutet"` und `last_error_code = rc.temp.unavailable`.
- Externe Seiteneffekte (E-Mail-Versand, API-Schreibaktionen) müssen selbst idempotent sein (Provider-Idempotency-Key bzw. Statusvermerk **vor** dem Versand in einer eigenen Tabelle), weil eine abgelaufene Lease einen zweiten Versuch erlaubt.

## 5. Idempotenz (TEC-004)

### 5.1 Unique-Keys in der Datenbank

| Schutz gegen | Mechanismus |
|---|---|
| doppelte externe Reviews | `reviews.dedupe_key` UNIQUE = `sha1(platform|profile_id|external_id)` ([database.md](database.md) §2.1.1) |
| doppelte Jobs | `jobs.idempotency_key` UNIQUE |
| doppelte Hook-Events | `review_events.event_id` UNIQUE |
| doppelte Profile | `profiles (platform, external_ref)` UNIQUE |
| doppelter CSV-Import | `import_batches.file_sha256` + Rückfrage |
| doppelte Einladungen (Pro) | Unique `experience_id + recipient_ref + campaign_id` in Pro-Tabelle (RC-025) |
| doppelte Provider-/Suite-Events (Pro) | Unique `(source, event_id)` in Pro-Tabelle |

### 5.2 Einreihen

`enqueue( type, key, payload, available_at )` = `INSERT IGNORE`-Semantik (Insert; bei Duplicate-Key-Fehler bestehende Zeile lesen):

- bestehender Job `queued`/`running`/`paused` → nichts tun (optional `available_at` vorziehen: `UPDATE … SET available_at = LEAST(available_at, :neu) WHERE status = 'queued'`).
- bestehender Job `succeeded`/`failed`/`dead` → nur re-armen, wenn der Typ als **periodisch** markiert ist oder der Aufrufer explizit `requeue: true` übergibt (z. B. Admin „Wiederholen“). Einmal-Jobs (z. B. `pro.request.send:{uuid}`) werden nie automatisch erneut ausgeführt.
- Periodische Jobs (Sync je Profil, Retention, Cleanup) sind **eine langlebige Zeile**: nach Erfolg `status = 'queued'`, `attempts = 0`, `available_at = nächstes Intervall`.

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

> Offene Entscheidung: Ob ein eigener Status `cancelled` nötig ist, wird per ADR entschieden; bis dahin `failed` mit `last_error_code = rc.source.unavailable` und Meldung „durch Trennen abgebrochen“.

### 6.2 Fehlerklassen

Die Zuordnung Fehlercode → Klasse liegt verbindlich in `ReviewCaptain\Support\ErrorCode` / [error-codes.md](error-codes.md). Arbeitsstand:

| Klasse | typische Codes / HTTP | Verhalten Job | Verhalten Profil / Admin |
|---|---|---|---|
| **transient** | `rc.net.timeout`, `rc.temp.unavailable`; HTTP 408, 500, 502, 503, 504, Verbindungsfehler | `queued`, `available_at = jetzt + backoff(attempts)`; nach **8 Versuchen** → `dead` | nach `dead`: Warnung auf Quellen-/Diagnoseseite |
| **rate_limit** | `rc.quota.exceeded`; HTTP 429, Provider-Quota-Header | `queued`, `available_at = jetzt + Retry-After`; zählt **nicht** als Versuch (`attempts − 1`); Schutzgrenze 48 Rate-Limit-Verschiebungen (Payload-Zähler `_rl_deferrals`) → `dead` | Profil-`state.backoff_until` gesetzt, alle Jobs des Profils respektieren es; Admin-Warnung bei Wiederholung |
| **auth** | `rc.auth.invalid`; HTTP 401, 403 wegen fehlendem Scope/Token widerrufen, Entschlüsselung fehlgeschlagen | `paused` (alle offenen Jobs des Profils) | Profil `status = auth_required`; Admin-Hinweis (`admin_notices`) für Nutzer mit `review_captain_manage_sources`: „Verbindung erneuern“; nach Reconnect `paused → queued` |
| **permanent** | `rc.perm.failed`; HTTP 400, 404 (Profil/Location), 405, 410, 422 bei Requestfehlern | `dead` | Fehler mit Code in Diagnose |
| **data** | `rc.data.invalid_payload` | Ganze Antwort unbrauchbar → `dead`. **Einzelnes** ungültiges Item → Item überspringen, Zähler `items_invalid` im Job-/Profil-State, Job läuft weiter | Diagnose zeigt Anzahl übersprungener Items |
| (Konfiguration) | `rc.contract.incompatible`, `rc.source.unavailable` | `paused` | Integrationsstatus `incompatible_contract` bzw. Quelle nicht verfügbar |
| (kein Fehler) | `rc.remote.removed` (HTTP 404 auf einzelne Review) | – | Review wird getombstoned ([retention-matrix.md](retention-matrix.md)) |

### 6.3 Backoff (transient)

```
delay(n) = min( 6 h, 60 s × 3^(n−1) ) × Zufall(0,8 … 1,2)     // n = bisherige attempts
n:      1     2     3      4      5      6       7
delay:  1 min 3 min 9 min  27 min 81 min 243 min 360 min (Deckel)
```

Nach dem 8. fehlgeschlagenen Versuch (`attempts = max_attempts = 8`) → `dead`. Gesamtspanne ≈ 12 h.

### 6.4 Retry-After

- Header als Sekunden oder HTTP-Datum akzeptieren; Wert auf 60 s … 24 h begrenzen. Fehlt der Header bei 429: 15 min.
- Provider-spezifische Quota-Header (z. B. verbleibendes Stundenkontingent) werden vom Adapter in `retry_after_s` übersetzt.

### 6.5 Admin-Aktionen

Dead-Letter-Liste in Diagnose (Typ, Profil, Code, sanitisierte Meldung, Versuche, Zeitpunkt) mit „Erneut einreihen“ (Capability je Bereich, siehe [capabilities.md](capabilities.md)) und „Verwerfen“ (→ `failed`). Manuelle Wiederholungen respektieren Rate-Limit-Backoff des Profils.

## 7. Fehlermeldungen und Logs

`last_error_message` ≤ 500 Zeichen, sanitisiert: Entfernen von `Authorization`/`apikey`-Headern, Query-Parametern (`access_token`, `code`, `key`, `client_secret`), JWTs (`eyJ…`), E-Mail-Adressen und Reviewtext. Rohantworten werden nicht gespeichert.

## 8. API-Budgets und Parallelität (TEC-007)

Je Profil in `profiles.settings` (Defaults je Provider vom Adapter, Werte **Arbeitsannahme**, gegen Anbieterdoku zu verifizieren):

| Einstellung | Bedeutung | Default |
|---|---|---|
| `page_size` | Einträge je API-Aufruf (≤ Provider-Maximum) | Provider-Maximum, max. 100 |
| `max_pages_per_run` | Seiten je Joblauf, danach `continue()` | 5 |
| `max_requests_per_day` | Tagesbudget (UTC-Tag) | 500 |
| `min_interval_minutes` | Mindestabstand zwischen Sync-Läufen | 60 (Trustpilot: so, dass Refresh ≤ 24 h eingehalten wird) |
| `lookback_days` | Initialimport-Rückblick | 365 (Google Play: API liefert nur ca. 7 Tage, siehe [provider-auth.md](provider-auth.md)) |
| `warn_threshold_pct` | Admin-Warnung ab Budgetverbrauch | 80 |

- Verbrauch in `profiles.state` (`day`, `requests_used`, `backoff_until`), Inkrement per atomarem `UPDATE` vor jedem API-Aufruf; ist das Budget erschöpft → Job `queued` bis zum nächsten UTC-Tag (zählt nicht als Versuch), Profilwarnung.
- **Parallelität:** global max. 2 gleichzeitig laufende Jobs je Site (Einstellung, 1–5). Je Profil max. **1** API-Job gleichzeitig: Profil-Sperre über `GET_LOCK( 'rc:{blog_id}:profile:{profile_id}', 0 )` (sessiongebunden, wird bei Prozessabbruch automatisch frei). Nicht erhaltene Sperre → `queued`, +60 s, kein Versuch. Hosts ohne `GET_LOCK` (selten, z. B. bestimmte Proxys): Fallback auf Lease-Prüfung „kein anderer `running`-Job mit gleichem Profil im Payload“ (nicht atomar, in Diagnose markiert).
- Ein manueller Sync kann Budgets nicht überschreiten; er zieht nur `available_at` vor.

## 9. Anzeige fälliger/überfälliger Jobs (TEC-002)

Diagnose und Quellen-Seite zeigen:

- **Runner-Gesundheit:** letzter Lauf je Kanal aus `review_captain_runner_heartbeat`; Status `ok` (< 10 min), `verzögert` (10–30 min), `gestört` (> 30 min oder `DISABLE_WP_CRON` ohne CLI-/AS-Heartbeat in 30 min). Bei `verzögert`/`gestört` Hinweis auf System-Cron (§2.1).
- **Fällig:** `queued` mit `available_at <= jetzt`. **Überfällig:** `available_at < jetzt − 10 min` (zwei verpasste Ticks). Anzeige als Anzahl je Typ, ältester Fälligkeitszeitpunkt relativ („seit 42 Minuten fällig“).
- **Keine Scheinpräzision:** Nächste Ausführungen werden als „frühestens ab 14:05 Uhr“ formuliert; ohne gesunden System-Cron/AS zusätzlich „abhängig von Seitenaufrufen“.
- Zähler: `running`, `paused` (mit Grund), `dead` (letzte 7 Tage), Budgetverbrauch je Profil.

## Abnahme

- [ ] WP-Cron-Event `review_captain_run_jobs` existiert nach Aktivierung mit Intervall 300 s und ist nach Deaktivierung entfernt.
- [ ] `wp review-captain jobs run` arbeitet fällige Jobs ab und endet spätestens nach `--time-limit` + Dauer des letzten Handler-Schritts (Default-Test: ≤ 25 s bei 10.000 wartenden Jobs).
- [ ] Mit Action Scheduler existiert genau eine wiederkehrende AS-Aktion `review_captain_run_jobs`; ohne AS keine Fehler.
- [ ] Parallelitätstest: 4 gleichzeitige Runner auf 1.000 Jobs → jeder Job genau einmal `succeeded`, kein Job doppelt im Handler (TEC-003/TEC-005).
- [ ] Prozessabbruch während `running` → nach Lease-Ablauf erneuter Claim; Ergebnis des alten Workers wird verworfen (`lease_owner`-Bedingung).
- [ ] Doppeltes `enqueue` mit gleichem Key erzeugt eine Zeile; paralleler Sync derselben Quelle erzeugt keine doppelten Reviews.
- [ ] Je Fehlerklasse ein Test: transient (Backoff-Folge, `dead` nach 8), rate_limit (Retry-After eingehalten, kein Versuch verbraucht), auth (`paused` + Profil `auth_required` + Admin-Hinweis), permanent/data (`dead`; einzelnes Item übersprungen).
- [ ] Budget erschöpft → keine weiteren API-Aufrufe am selben UTC-Tag; Warnung ab 80 %.
- [ ] Diagnose zeigt Runner-Status, fällige/überfällige Jobs und formuliert Zeiten als „frühestens“.
- [ ] `last_error_message` enthält in Tests mit präparierten Fehlermeldungen keine Tokens, Secrets, E-Mails.
