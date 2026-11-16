# Race-Conditions – Szenarien, erwartetes Verhalten, Testfälle (TEC-005)

| | |
|---|---|
| Status | Spezifikation, Entwurf zur Abnahme |
| Bezug | TODO TEC-003, TEC-004, TEC-005, TEC-015; konzept.md 7.5, 7.9 |
| Mechanismen | `jobs`-Lease (conventions.md), UNIQUE-Constraints (`dedupe_key`, `invitation_key`, `suite_lock_key`, `send_key`, `event_id`, `idempotency_hash`, `sync_runs.active_profile_id`), CAS über `revision`/`generation` |

## Grundsätze

1. **Constraints statt Vorabprüfung.** Jede Eindeutigkeit wird über einen UNIQUE-Index erzwungen; Code reagiert auf Duplicate-Key, nie auf ein vorheriges `SELECT`.
2. **CAS für Zustandswechsel.** `UPDATE … WHERE id=? AND status=? AND revision=?` + `rows_affected === 1`; bei 0 neu laden und neu entscheiden.
3. **Widerruf gewinnt vor Versand.** Bis zur Transportübergabe muss jeder Widerruf wirksam werden; danach wird nichts „zurückgeholt“, sondern protokolliert.
4. **Lieber höchstens einmal als doppelt.** Unklare Ausgänge führen nie zu automatischem Zweitversand.
5. **Leases laufen ab, Arbeit ist idempotent.** Ein Worker kann nach Lease-Ablauf erneut starten; jeder Schritt muss Wiederholung vertragen.

## Testtechnik

- **Unit (ohne WordPress):** Services erhalten `Clock` und eine `Interleaver`-Abhängigkeit (Test-Double), die an definierten Punkten (`after_claim`, `before_transport`, `after_transport`, `before_cursor_commit`) Callbacks ausführt, um konkurrierende Aktionen einzuschieben.
- **Integration (MySQL/MariaDB):** zwei separate DB-Verbindungen (`mysqli`) simulieren zwei PHP-Worker; Prüfung der Constraints und CAS gegen echte Engine (InnoDB, READ COMMITTED und REPEATABLE READ).
- Jeder Testfall prüft zusätzlich: keine PII in Logs/Jobs-Payloads, korrekter `status_code`.

## Szenarien

### R-01 – Gleichzeitige Einlösung/Check-in bzw. Check-in + Eventende

| | |
|---|---|
| Situation | Zwei Worker verarbeiten parallel `experience_completed` (Check-in) und `experience_updated` (Eventende) – oder dasselbe Check-in-Ereignis wird doppelt zugestellt (zwei `event_id`, gleicher `idempotency_key`). Analog: Voucher-Einlösung doppelt gemeldet. |
| Erwartet | Genau eine `inbound_events`-Zeile je `idempotency_hash`; genau eine `experiences`-Zeile; höchstens eine `requests`-Zeile je `invitation_key`; zweiter Versuch → `duplicate` bzw. `rc.request.duplicate`. |
| Mechanismus | UNIQUE `event_id`, UNIQUE `idempotency_hash`, UNIQUE `experiences.experience_id` (Upsert), UNIQUE `invitation_key` + `suite_lock_key`. |

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R01-a | Zwei Verbindungen fügen gleichzeitig dasselbe Ereignis (gleicher `idempotency_key`, verschiedene `event_id`) ein | 1 Zeile, zweites Ergebnis `duplicate` |
| TC-R01-b | `completed` und `updated` desselben Erlebnisses parallel verarbeiten | 1 Anfrage `planned`, `updated` ändert keine zweite Anfrage |
| TC-R01-c | `updated` trifft **vor** `completed` ein | `updated` registriert/aktualisiert nur; `completed` plant genau einmal |
| TC-R01-d | 10 parallele Zustellungen desselben Ereignisses | 1 Anfrage, 9× `duplicate` |

### R-02 – Refund/Storno vs. Einladungsversand

| | |
|---|---|
| Situation | Versandjob ist bei `eligible` und hat den Claim; gleichzeitig trifft `experience_refunded` ein. |
| Erwartet | Trifft der Widerruf **vor** Schritt 6 der Versandreihenfolge (invitation-rule.md) ein → kein Versand, Status `cancelled` (`rc.experience.revoked`), S3/S4 zurückgesetzt. Trifft er **nach** Transportübergabe ein → Status bleibt `sent`, `request_log` vermerkt `cancel_after_send`, keine Erinnerung mehr. |
| Mechanismus | Widerrufs-Handler: CAS `planned/eligible → cancelled`; schlägt CAS fehl, weil Versandjob den Claim hält, setzt er `cancel_requested = 1` (eigener CAS). Versandjob prüft `cancel_requested` nach Resolver und unmittelbar vor Transport. |

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R02-a | Refund vor `due_at` | `cancelled`, Resolver nie aufgerufen |
| TC-R02-b | Refund nach Claim, vor Resolver (`after_claim`) | `cancelled`, kein Transport |
| TC-R02-c | Refund nach Resolver, vor Transport (`before_transport`) | `cancelled`, kein Transport, `request_sends`-Zeile gelöscht, Guard zurückgesetzt |
| TC-R02-d | Refund nach Transport (`after_transport`) | `sent`, Log `cancel_after_send`, Erinnerung unterdrückt |
| TC-R02-e | Teil-Erstattung einer Position bei Mehrprodukt-Anfrage | gemäß Kampagnenregel: Position entfernt oder Anfrage `cancelled` (RC-097) |

### R-03 – Opt-out / Berechtigungswiderruf vs. Versand

| | |
|---|---|
| Situation | Empfänger meldet sich (andere Anfrage) ab oder Quelle sendet `permission_changed` = `revoked`, während ein Versandjob läuft. |
| Erwartet | Opt-out setzt `recipient_guards.suppressed_at` (CAS). Versandjob scheitert an S4-CAS (`suppressed_at IS NULL` verletzt) → `skipped` / `rc.recipient.suppressed`. `permission_changed` storniert offene Anfragen; Resolver liefert zusätzlich `revoked`. |

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R03-a | Opt-out zwischen Resolver und S4 | `skipped`, kein Transport |
| TC-R03-b | `permission_changed` revoked nach Claim | `cancelled` oder `skipped`, kein Transport |
| TC-R03-c | Opt-out nach Transport | Status der versendeten Anfrage bleibt, alle anderen offenen Anfragen dieses Kontakts werden beim nächsten Versandversuch `skipped` |

### R-04 – Token-Refresh parallel

| | |
|---|---|
| Situation | Zwei Jobs desselben Profils (z. B. Sync und Test) erhalten `rc.auth.expired` und wollen gleichzeitig refreshen. Anbieter mit rotierendem Refresh-Token würden den zweiten Refresh ablehnen. |
| Erwartet | Genau ein Refresh. Der zweite Job wartet bzw. lädt das neue Token. Kein Profil fällt fälschlich auf `auth_required`. |
| Mechanismus | Refresh-Lock je Verbindung: Option/Zeile `token_refresh_lock` mit CAS (`lock_owner`, `lock_expires_at` 30 s). Nach Lock-Erhalt Token neu laden: ist es neuer als beim Fehler, kein Refresh. Credentials werden mit `credentials_revision` per CAS geschrieben. Zweiter Job ohne Lock: `rc.auth.refresh_conflict` (transient), Retry in 5 s. |

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R04-a | Zwei Refreshes parallel | Provider-Fake zählt genau 1 Refresh-Aufruf |
| TC-R04-b | Lock-Halter stirbt (Lease abgelaufen) | nächster Job übernimmt nach 30 s |
| TC-R04-c | Refresh liefert `invalid_grant`-äquivalenten Fehler | `rc.auth.invalid`, Profil `auth_required`, Jobs `paused` |
| TC-R04-d | Sync-Seite liefert `credentials_update` während anderer Job refresht | CAS-Konflikt → neu laden, kein Überschreiben mit älterem Token |

### R-05 – Zwei Sync-Läufe derselben Quelle

| | |
|---|---|
| Situation | Cron-Sync und manueller „Jetzt synchronisieren“-Klick oder zwei Cron-Runner starten gleichzeitig für dasselbe Profil. |
| Erwartet | Höchstens ein aktiver Lauf je Profil. Zweiter Start → `rc.lock.busy`, Admin-Hinweis „läuft bereits“. Cursor wird nie von einem veralteten Worker überschrieben. |
| Mechanismus | `sync_runs.active_profile_id` UNIQUE; Seitenjobs mit `idempotency_key sync:{profile_id}:{run_uuid}:{page_no}`; Cursor-Commit per CAS auf `generation`. Lease-Recovery: Lauf ohne Heartbeat > 15 min wird `failed` gesetzt, `active_profile_id = NULL`. |

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R05-a | Zwei Starts parallel | 1 Lauf, 1× `rc.lock.busy` |
| TC-R05-b | Seitenjob doppelt ausgeführt (Lease-Ablauf) | identische Reviews, Cursor-CAS des zweiten scheitert, keine Doppelzeilen |
| TC-R05-c | Full-Lauf bricht ab, Delta-Lauf startet | kein Tombstone durch Abwesenheit |
| TC-R05-d | Gleiche Review auf Seite 1 und 2 (verschobene Pagination) | 1 Zeile (`dedupe_key`) |

### R-06 – Job-Lease läuft während Ausführung ab

| | |
|---|---|
| Situation | Langsamer Worker überschreitet `lease_expires_at`; zweiter Worker claimt denselben Job. |
| Erwartet | Arbeit ist idempotent; Seiteneffekte mit externem Ausgang (Versand) sind zusätzlich durch S3 (`send_key`) und `send_claim` geschützt; der erste Worker darf nach Ablauf keinen Status mehr schreiben (CAS auf `lease_owner`). |

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R06-a | Versandjob von zwei Workern (Lease-Ablauf vor Transport) | 1 Transport-Aufruf |
| TC-R06-b | Erster Worker versucht nach Ablauf `succeeded` zu setzen | `rows_affected = 0`, kein Überschreiben |

### R-07 – Konkurrierende Kampagnen / Workflow und native Queue

| | |
|---|---|
| Situation | Native Kampagne und Workflow-`plan_request` (oder zwei aktive Kampagnen mit demselben Auslöser) planen parallel für dasselbe Erlebnis und dieselbe Empfängerreferenz. |
| Erwartet | Genau eine aktive Anfrage (S2 `suite_lock_key`). Die andere erhält `rc.request.duplicate`. Kampagnen mit `trigger_owner = workflow` werden von nativen Auslösern nicht bedient. |

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R07-a | Native + Workflow parallel | 1 Zeile `planned` |
| TC-R07-b | Erste Anfrage `cancelled` durch Admin, danach neues Ereignis | Sperre freigegeben; neue Planung zulässig nur durch neues `completed`-Ereignis derselben Quelle |
| TC-R07-c | Käufer- und Teilnehmerreferenz, gleiche E-Mail | 1 Versand (S3) |

### R-08 – Mehrere Versandjobs an denselben Kontakt

| | |
|---|---|
| Situation | Käufer mit drei Tickets (drei Erlebnisse) oder Woo-Bestellung + Gutscheineinlösung derselben Person; Versandjobs laufen gleichzeitig. |
| Erwartet | Bei aktivem Cooldown sendet genau einer; die anderen `skipped` / `rc.request.frequency_limited`. |
| Mechanismus | S4-CAS auf `recipient_guards.last_sent_at`. |

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R08-a | 3 parallele Versandjobs, Cooldown 30 Tage | 1× `sent`, 2× `skipped` |
| TC-R08-b | Transport des Gewinners scheitert eindeutig | Guard zurückgesetzt; ein anderer Job darf beim Retry senden |

### R-09 – Out-of-Order-Ereignisse

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R09-a | `experience_refunded` vor `experience_completed` | Erlebnis `revoked`, keine Anfrage |
| TC-R09-b | `permission_changed` granted nach `skipped` (`rc.consent.missing`) | `skipped → planned` (T12), wenn nicht abgelaufen |
| TC-R09-c | `experience_completed` nach `expires_at`-Äquivalent (zu alt, Kampagnen-Maximalalter) | `skipped` / `rc.request.expired` |

### R-10 – Backup-Restore / Upgrade (TEC-015)

| | |
|---|---|
| Situation | Datenbank wird auf einen Stand vor kürzlich versendeten Einladungen zurückgesetzt; Anfragen stehen wieder auf `eligible`. |
| Erwartet | Nach Restore läuft zuerst ein Reconciliation-Job (Versand pausiert, `hold_code = rc.temp.unavailable`, bis Abschluss): Anfragen mit `eligible`, deren `due_at` älter als Restore-Erkennung ist, werden geprüft; ohne Nachweis gegen den Transport-Log werden sie auf `hold` gelassen und dem Admin zur Freigabe angezeigt statt automatisch versendet. OAuth-Verbindungen werden getestet; abgelaufene Snapshot-Caches werden ausgeblendet. |
| Erkennung | Option `review_captain_instance_marker` (zufälliger Wert + Zeit) wird zusätzlich außerhalb der DB (Datei im Uploads-Verzeichnis, nicht öffentlich lesbar) abgelegt; Abweichung ⇒ Restore vermutet. **Produktentscheidung offen:** automatische Freigabe nach Zeitgrenze vs. immer manuell. |

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R10-a | Restore-Marker weicht ab | Versand-Jobs pausiert, Reconciliation läuft zuerst |
| TC-R10-b | Gesendete Anfrage durch Restore wieder `eligible` | kein automatischer Versand |

### R-11 – Quell-Plugin wird während eines Jobs deaktiviert

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R11-a | Resolver-Plugin deaktiviert zwischen Claim und Resolve | Resolver `unavailable` → `hold_code = rc.source.unavailable`, Status bleibt `eligible`, kein Versand |
| TC-R11-b | Reaktivierung | Vertrag wird validiert; Anfragen jenseits `expires_at` → `expired`, übrige laufen weiter |

### R-12 – Parallele Moderation

| Test | Ablauf | Erwartung |
|---|---|---|
| TC-R12-a | Zwei Moderatoren ändern denselben Review gleichzeitig | zweiter Speichervorgang erkennt geänderten `updated_at` (Optimistic Lock) und zeigt Konfliktmeldung |
| TC-R12-b | Sync aktualisiert Inhalt während Moderator freigibt | Status-CAS und Inhalts-Upsert unabhängig; beide Änderungen bleiben erhalten, `review_events` enthält beide |

## Umsetzung und Testzuordnung (Stand 2026-11-16)

Core-Tests laufen mit dem SQLite-Mini-WP (`vendor/bin/phpunit`). Statt zweier DB-Verbindungen schiebt `tests/Support/InterleavingWpdb.php` (der „Interleaver“ der Testtechnik) den konkurrierenden Vorgang **unmittelbar vor ein bestimmtes SQL-Statement** des geprüften Codes – genau die Stelle zwischen Lesen und Schreiben, an der ein zweiter Worker dazwischenkommen kann. Die Integrationsvariante mit zwei echten `mysqli`-Verbindungen auf MySQL 8/MariaDB bleibt offen (siehe Abnahme).

| Szenario | Zuständig | Tests |
|---|---|---|
| R-01 Doppelzustellung/paralleler Eingang | Core: UNIQUE `dedupe_key`, `jobs.idempotency_key`, Workflow-Entwurf; Pro: `inbound_events`, `experiences`, `invitation_key` | Core `tests/Unit/Races/R01ConcurrentIntakeTest.php` (zwei gleichzeitige `upsert_external`, paralleles `enqueue`, 10× derselbe Workflow-Entwurf); Pro `captain-pro tests/Addons/ReviewSuiteIntegrationsTest.php`, `ReviewRequestsTest.php` |
| R-02 Refund vs. Versand | Pro | `captain-pro tests/Addons/ReviewRequestSendTest.php`, `ReviewRequestsTest.php`, `ReviewWooAdapterTest.php` |
| R-03 Opt-out/Widerruf vs. Versand | Pro | `captain-pro tests/Addons/ReviewRequestSendTest.php`, `ReviewRequestsTest.php` |
| R-04 Token-Refresh parallel | Pro (Core stellt nur `profiles.credentials_rev`) | `captain-pro tests/Addons/ReviewPlatformAuthTest.php`, `ReviewPlatformConnectionsTest.php` |
| R-05 zwei Sync-Läufe | Core: idempotentes Anwenden von Seiten (`SyncService`); Pro: Laufsperre, Cursor-CAS | Core `tests/Unit/Races/R05SyncRunsTest.php` (TC-R05-b/-d, gleichzeitiges Anwenden derselben Seite); Pro `captain-pro tests/Addons/ReviewPlatformConnectionsTest.php`, `ReviewPlatformProviderTest.php` |
| R-06 Lease-Ablauf | Core: `JobQueue` (Claim-CAS, `lease_owner`-CAS); Pro: `send_key` | Core `tests/Unit/Races/R06JobLeaseTest.php` (doppelter Claim, TC-R06-b, Handler über Lease-Ende); Pro TC-R06-a `captain-pro tests/Addons/ReviewRequestSendTest.php` |
| R-07 Kampagnen/Workflow + native Queue | Pro (`suite_lock_key`); Core: Workflow-Entwürfe | Pro `captain-pro tests/Addons/ReviewSuiteIntegrationsTest.php`, `ReviewRequestsTest.php`; Core `tests/Unit/Races/WorkflowDraftRaceTest.php` (Entwurf abbrechen vs. Freigabe) |
| R-08 mehrere Versandjobs/Cooldown | Pro | `captain-pro tests/Addons/ReviewRequestSendTest.php`, `ReviewRequestsTest.php` |
| R-09 Out-of-Order | Pro | `captain-pro tests/Addons/ReviewRequestsTest.php`, `ReviewSuiteIntegrationsTest.php` |
| R-10 Backup-Restore | Core: Erkennung, Sperre, Reconciliation; Pro: Anfragen auf `hold` | Core `tests/Unit/Races/R10RestoreReconcileTest.php` (TC-R10-a/-b Core-Teil, Klon-Sperre); Pro `captain-pro tests/Addons/ReviewRequestSendTest.php` |
| R-11 Quell-Plugin deaktiviert | Core: Connector-Gate im Runner; Pro: `expires_at` | Core `tests/Unit/Races/R11SourceDeactivatedTest.php`; Pro `captain-pro tests/Addons/ReviewConnectorManagerTest.php` |
| R-12 parallele Moderation | Core | `tests/Unit/Races/R12ParallelModerationTest.php` (Status-CAS, TC-R12-a Optimistic Lock über `reviews.revision`, TC-R12-b Sync-Inhalt vs. Freigabe, Restore per Status-CAS) |
| Zusatz: Import-Rollback vs. Moderation | Core | `tests/Unit/Races/ImportRollbackVsModerationTest.php` (Rollback gewinnt, keine Waisen; nachlaufender Verlaufseintrag wird von der Reconciliation entfernt) |
| Zusatz: Eraser vs. Moderation | Core | `tests/Unit/Races/EraserVsModerationTest.php` (veralteter Editor kann anonymisierte Daten nicht zurückschreiben, Löschmodus, Foto) |

Bei der Umsetzung gefundene und behobene Fehler (Core):

1. **R-12b:** `ReviewRepository::upsert_external()` schrieb den zuvor gelesenen Status zurück und konnte eine gleichzeitige Moderationsentscheidung überschreiben. Jetzt schreibt das Upsert nur Inhalt; ein Restore (`removed` → Anfangsstatus) läuft als Status-CAS über `change_status()` mit Verlaufseintrag `remote_restored`.
2. **R-12a:** Der Review-Editor hatte keinen Optimistic Lock; zwei Moderierende überschrieben sich still. Neu: `reviews.revision` (Schema v2), CAS beim Speichern, Hinweis `review_conflict`. Schützt zugleich davor, dass ein veralteter Editor-Stand anonymisierte Daten zurückschreibt.
3. **R-10:** Der Runner-Heartbeat aktualisierte `last_seen_at` beim ersten Tick nach einem Restore und löschte damit den Restore-Nachweis, bevor die tägliche Reconciliation ihn sehen konnte; Jobs mit Außenwirkung liefen weiter. Neu: `Runner::heartbeat()` setzt bei Restore-/Klon-Verdacht sofort `review_captain_reconcile_required` und reiht genau eine Reconciliation ein; ein durch die Sperre verschobener Job behält seine ursprüngliche Fälligkeit (`payload._rc_due_at`), damit er als überfällig pausiert wird; die Reconciliation setzt ihren eigenen laufenden Job nicht mehr zurück (sonst Endlosschleife).
4. **Waisen:** Ein Moderations-Verlaufseintrag, der nach dem Löschen eines Reviews geschrieben wird, blieb dauerhaft liegen. Die Reconciliation meldet jetzt `orphan_rows` und entfernt sie mit `--fix`.

## Abnahme

- [ ] Gleichzeitig eintreffende Einlösung/Check-in (R-01), Refund vs. Einladungsversand (R-02), Tokenrefresh (R-04) und zwei Sync-Läufe derselben Quelle (R-05) sind als automatisierte Integrationstests mit zwei DB-Verbindungen umgesetzt und grün.
- [ ] Alle übrigen Szenarien (R-03, R-06–R-12) haben mindestens die aufgeführten Testfälle. (Core-Anteil R-06, R-10, R-11, R-12 und Core-Teil R-01/R-05: erledigt, siehe Tabelle; Pro-Anteil: Pro-Repository.)
- [ ] Testlauf auf MySQL 8 und MariaDB (unterstützte Mindestversionen gemäß RC-006).
- [ ] Kein Testfall erzeugt einen doppelten Transport-Aufruf (Transport-Fake zählt).
