# Datenbank- und Migrationsplan (RC-028)

Status: an den implementierten Stand angeglichen (Schema v1, `src/Install/Schema.php`), Stand 2026-11-16. Grundlage: [`conventions.md`](conventions.md) Abschnitt Datenbank, [ADR-0001](../adr/0001-custom-tables-statt-cpt.md).
Wo dieses Dokument und der Code abweichen, gilt `src/Install/Schema.php`. Nicht umgesetzte Teile früherer Fassungen sind als „Ausblick (nicht in Schema v1)“ markiert; Änderungen am Schema nur per neuem Migrationsschritt und ADR.

## 1. Grundregeln

- Präfix: `{$wpdb->prefix}review_captain_` (z. B. `wp_review_captain_reviews`, Multisite `wp_3_review_captain_reviews`).
- Engine/Charset: InnoDB, `$wpdb->get_charset_collate()` (in der Regel `utf8mb4_unicode_520_ci`). Indizierte VARCHAR-Spalten max. 191 Zeichen.
- Zeiten: `DATETIME` in UTC, Format `Y-m-d H:i:s`, gesetzt aus PHP (`gmdate()`), **nicht** über MySQL `NOW()`/`CURRENT_TIMESTAMP` (Session-Zeitzone ist auf vielen Hosts nicht UTC). Wo `conventions.md` „NOW“ schreibt, ist ein per `$wpdb->prepare` übergebener UTC-Zeitstempel gemeint.
- Enums als `VARCHAR(20…40)` statt MySQL-`ENUM` (dbDelta-freundlich, erweiterbar ohne ALTER). Zulässige Werte erzwingt PHP (Value Objects).
- JSON-Spalten (`reviews.meta`, `jobs.payload`, `profiles.settings`, `profiles.sync_cursor`, `audit_log.context`) als `LONGTEXT`, geschrieben mit `wp_json_encode()`; **niemals Secrets** außer in `profiles.credentials_enc` (verschlüsselt, siehe [secrets.md](secrets.md)).
- Alle SQL über `$wpdb->prepare()`. Keine Fremdschlüssel-Constraints (dbDelta/Hosting-Kompatibilität); referentielle Integrität per Code und [Reconciliation](reconciliation.md).

## 2. Tabellen

Maßgeblich ist `src/Install/Schema.php` (`Schema::VERSION = 1`, Option `review_captain_db_version`). Die Tabellen unten geben das dbDelta-SQL von Schema v1 **exakt** wieder (Typen in der Schreibweise des Codes). Spalten, die frühere Fassungen dieses Dokuments vorgesehen hatten, die aber nicht in Schema v1 enthalten sind, stehen jeweils unter „Ausblick (nicht in Schema v1)“; sie dürfen nur über einen neuen Migrationsschritt (`Schema::VERSION` erhöhen, Schritt in `Migrator::steps()`) eingeführt werden.

Logische Tabellenschlüssel (`Schema::TABLES`): `reviews`, `review_events`, `profiles`, `jobs`, `import_batches`, `audit_log`. Der volle Name ergibt sich aus `Schema::table( $key )` = `$wpdb->prefix . 'review_captain_' . $key`.

### 2.1 `reviews`

| Spalte | Typ (Schema v1) | Anmerkung |
|---|---|---|
| `id` | `bigint(20) unsigned NOT NULL AUTO_INCREMENT` | PK |
| `uuid` | `char(36) NOT NULL` | öffentliche Referenz in Events/Exporten |
| `kind` | `varchar(20) NOT NULL DEFAULT 'review'` | `review` \| `testimonial` (`Domain\Kind`) |
| `origin` | `varchar(30) NOT NULL DEFAULT 'manual'` | `local_submission` \| `manual` \| `csv_import` \| `provider_sync` \| `connector` (`Domain\Origin`) |
| `platform` | `varchar(30) NOT NULL DEFAULT 'local'` | `local` \| `google` \| `trustpilot` \| `trusted_shops` \| `app_store` \| `google_play` \| `other` (`Domain\Platform`) |
| `profile_id` | `bigint(20) unsigned NULL` | → `profiles.id` |
| `external_id` | `varchar(191) NULL` | stabile Plattform-ID |
| `dedupe_key` | `char(40) NULL` | siehe 2.1.1 |
| `rating_value` | `decimal(6,2) NULL` | |
| `rating_scale_min` | `decimal(6,2) NOT NULL DEFAULT '1.00'` | |
| `rating_scale_max` | `decimal(6,2) NOT NULL DEFAULT '5.00'` | |
| `title` | `varchar(255) NOT NULL DEFAULT ''` | |
| `body` | `longtext NOT NULL` | sanitisiertes Plain-Text/eingeschränktes HTML |
| `language` | `varchar(20) NOT NULL DEFAULT ''` | BCP 47 |
| `author_name` | `varchar(191) NOT NULL DEFAULT ''` | |
| `author_email` | `varchar(191) NULL` | **nur** `origin=local_submission` |
| `author_user_id` | `bigint(20) unsigned NULL` | |
| `verification` | `varchar(30) NOT NULL DEFAULT 'unverified'` | `unverified` \| `purchase_verified` \| `attendance_confirmed` \| `external_platform` (`Domain\Verification`) |
| `status` | `varchar(20) NOT NULL DEFAULT 'pending'` | `pending` \| `approved` \| `rejected` \| `archived` \| `flagged` \| `removed` (`Domain\ReviewStatus`) |
| `context_type` | `varchar(40) NULL` | `post` \| `product` \| `event` \| `voucher` \| `app` \| `form` |
| `context_id` | `varchar(64) NULL` | |
| `source_url` | `text NULL` | nur `https`, validiert (siehe [embed-security.md](embed-security.md)) |
| `published_at` | `datetime NULL` | Originaldatum |
| `fetched_at` | `datetime NULL` | letzter erfolgreicher Abruf |
| `refresh_due_at` | `datetime NULL` | Pflicht-Refresh laut [retention-matrix.md](retention-matrix.md) |
| `consent_publish` | `tinyint(1) NOT NULL DEFAULT 0` | |
| `consent_at` | `datetime NULL` | |
| `import_batch_id` | `bigint(20) unsigned NULL` | → `import_batches.id` |
| `experience_id` | `varchar(191) NULL` | Suite-Erlebnis (RC-022) |
| `meta` | `longtext NULL` | JSON, keine Secrets |
| `created_at` | `datetime NOT NULL` | |
| `updated_at` | `datetime NOT NULL` | |
| `removed_at` | `datetime NULL` | Tombstone |

Indizes (exakt Schema v1):

```
PRIMARY KEY  (id)
UNIQUE KEY uuid (uuid)
UNIQUE KEY dedupe_key (dedupe_key)
KEY status_kind_platform (status,kind,platform)
KEY rating_value (rating_value)
KEY created_at (created_at)
KEY context (context_type,context_id)
KEY author_email (author_email)
KEY import_batch_id (import_batch_id)
KEY profile_id (profile_id)
KEY refresh_due_at (refresh_due_at)
```

#### 2.1.1 `dedupe_key` (TEC-004)

`dedupe_key` ist eine **gewöhnliche Spalte** `char(40) NULL` mit `UNIQUE KEY dedupe_key`. Sie wird ausschließlich in PHP befüllt: `Review::dedupe_key()` (`src/Domain/Review.php`) liefert

```
sha1( platform . '|' . (string) ( profile_id ?? 0 ) . '|' . external_id )
```

(40 Hex-Zeichen, kleingeschrieben), wenn `external_id` gesetzt und nicht leer ist, sonst `NULL` (mehrere `NULL` sind in UNIQUE zulässig). `Review::to_row()` schreibt den Wert bei jedem Insert/Update mit; es gibt **keine** generierte Spalte, keinen separaten `ALTER TABLE`-Schritt und damit auch keinen „generierten Modus“ bzw. „Fallback-Modus“.

- Upserts aus Sync und CSV-Import laufen über `ReviewRepository::upsert_external()`: Suche per `find_by_dedupe_key()`, sonst Insert; verliert der Insert ein Rennen gegen einen parallelen Lauf (Duplicate-Key-Fehler → `RuntimeException`), wird der vorhandene Datensatz erneut gelesen und aktualisiert. Der Moderationsstatus vorhandener Datensätze bleibt erhalten (außer bei `removed`).
- Direkte SQL-Schreibzugriffe außerhalb des Repositorys (z. B. `DevSeeder`) müssen `dedupe_key` selbst mit derselben Formel setzen.
- Die Reconciliation zählt Zeilen mit `external_id`, aber `dedupe_key IS NULL` (`missing_dedupe_keys`, [reconciliation.md](reconciliation.md) Schritt 8) – solche Zeilen können nur durch Schreibzugriffe am Repository vorbei entstehen.

### 2.2 `review_events`

Moderations-/Änderungshistorie je Review, geschrieben von `ReviewRepository::log_event()` (und beim CSV-Rollback gelesen/gelöscht).

| Spalte | Typ (Schema v1) | Anmerkung |
|---|---|---|
| `id` | `bigint(20) unsigned NOT NULL AUTO_INCREMENT` | PK |
| `review_id` | `bigint(20) unsigned NOT NULL` | → `reviews.id` |
| `action` | `varchar(40) NOT NULL` | z. B. `created`, `status_changed`, `edited` |
| `from_status` | `varchar(20) NULL` | |
| `to_status` | `varchar(20) NULL` | |
| `reason` | `varchar(500) NOT NULL DEFAULT ''` | Moderationsnotiz (sanitisiert, max. 500 Zeichen), kein Reviewtext, keine E-Mail |
| `user_id` | `bigint(20) unsigned NULL` | handelnder Benutzer, `NULL` bei System/Job/CLI ohne Benutzer |
| `created_at` | `datetime NOT NULL` | |

Indizes: `PRIMARY KEY (id)`, `KEY review_id (review_id)`, `KEY created_at (created_at)`.

**Ausblick (nicht in Schema v1):** `event_id` CHAR(36) UNIQUE (= `event_id` im Hook-Payload), `actor_type` (`user` \| `system` \| `job` \| `cli` \| `connector`), `reason_code` VARCHAR(64) (z. B. `rc.remote.removed`) und ein zusammengesetzter Index `review_created (review_id, created_at)`. Die früher vorgesehenen Namen `event_type`/`previous_status`/`new_status`/`actor_user_id`/`note` heißen in v1 `action`/`from_status`/`to_status`/`user_id`/`reason`.

### 2.3 `profiles`

| Spalte | Typ (Schema v1) | Anmerkung |
|---|---|---|
| `id` | `bigint(20) unsigned NOT NULL AUTO_INCREMENT` | PK |
| `platform` | `varchar(30) NOT NULL` | Werte wie `reviews.platform` |
| `label` | `varchar(191) NOT NULL DEFAULT ''` | |
| `profile_url` | `text NULL` | öffentlicher Link, nur https |
| `review_url` | `text NULL` | Link „Bewertung schreiben“ (CTA) |
| `external_ref` | `varchar(191) NULL` | Location-Name, Business-Unit-ID, eTrusted-Channel, Apple-App-ID, Paketname |
| `status` | `varchar(20) NOT NULL DEFAULT 'manual'` | Core setzt nur `manual` (`Profiles::STATUS_MANUAL`); weitere Werte setzt Pro |
| `settings` | `longtext NULL` | JSON: Budgets, Refresh-Overrides ([jobs.md](jobs.md) §8) |
| `credentials_enc` | `longtext NULL` | verschlüsselter Envelope ([secrets.md](secrets.md)); nie exportiert |
| `credentials_rev` | `int(10) unsigned NOT NULL DEFAULT 0` | Compare-and-swap bei Tokenrefresh |
| `key_id` | `varchar(16) NULL` | Fingerprint des verwendeten Schlüssels |
| `sync_cursor` | `longtext NULL` | JSON, providerdefiniert |
| `last_sync_at` | `datetime NULL` | |
| `last_error_code` | `varchar(64) NULL` | `rc.*` |
| `created_at` | `datetime NOT NULL` | |
| `updated_at` | `datetime NOT NULL` | |

Indizes: `PRIMARY KEY (id)`, `UNIQUE KEY platform_ref (platform,external_ref)` (mehrere `NULL` erlaubt), `KEY status (status)`.

**Ausblick (nicht in Schema v1):** `uuid` CHAR(36) UNIQUE, `connection_type` (`link` \| `manual` \| `api` \| `connector`), `owner` (`core` \| `pro` \| `connector:<key>`), `state` LONGTEXT (Budgetverbrauch, Backoff-bis, Zähler – in v1 liegen Budgetzähler in Optionen `review_captain_budget_*`, siehe `Jobs\Budget`), `last_success_at`, `next_sync_at`, `disconnected_at` sowie die Statuswerte `active` \| `paused` \| `auth_required` \| `disconnected` \| `archived`.

### 2.4 `jobs`

| Spalte | Typ (Schema v1) |
|---|---|
| `id` | `bigint(20) unsigned NOT NULL AUTO_INCREMENT` (PK) |
| `type` | `varchar(100) NOT NULL` |
| `idempotency_key` | `varchar(191) NOT NULL` |
| `payload` | `longtext NULL` (JSON, keine Secrets, keine E-Mails) |
| `status` | `varchar(20) NOT NULL DEFAULT 'queued'` – `queued` \| `running` \| `succeeded` \| `failed` \| `dead` \| `paused` (`JobQueue::STATUSES`) |
| `attempts` | `int(10) unsigned NOT NULL DEFAULT 0` |
| `max_attempts` | `int(10) unsigned NOT NULL DEFAULT 8` |
| `available_at` | `datetime NOT NULL` |
| `lease_owner` | `varchar(64) NULL` |
| `lease_expires_at` | `datetime NULL` |
| `last_error_code` | `varchar(64) NULL` |
| `last_error_message` | `varchar(500) NULL` (sanitisiert) |
| `created_at` | `datetime NOT NULL` |
| `updated_at` | `datetime NOT NULL` |

Indizes: `PRIMARY KEY (id)`, `UNIQUE KEY idempotency_key (idempotency_key)`, `KEY status_available (status,available_at)`, `KEY type_status (type,status)`, `KEY lease_expires_at (lease_expires_at)`. Semantik: [jobs.md](jobs.md).

### 2.5 `import_batches`

| Spalte | Typ (Schema v1) | Anmerkung |
|---|---|---|
| `id` | `bigint(20) unsigned NOT NULL AUTO_INCREMENT` | PK |
| `filename` | `varchar(191) NOT NULL DEFAULT ''` | ursprünglicher Dateiname (nur Anzeige); die Datei selbst wird nach Verarbeitung gelöscht |
| `status` | `varchar(20) NOT NULL DEFAULT 'completed'` | vom Importer gesetzt: `running` → `completed`, nach Rollback `rolled_back`; die Reconciliation setzt hängende `importing`-Batches auf `failed` |
| `rows_total` | `int(10) unsigned NOT NULL DEFAULT 0` | |
| `rows_imported` | `int(10) unsigned NOT NULL DEFAULT 0` | |
| `rows_skipped` | `int(10) unsigned NOT NULL DEFAULT 0` | |
| `created_by` | `bigint(20) unsigned NULL` | |
| `created_at` | `datetime NOT NULL` | |
| `rolled_back_at` | `datetime NULL` | |

Indizes: `PRIMARY KEY (id)`, `KEY status (status)`. Rollback = Löschen der `reviews` mit `import_batch_id` (samt ihrer `review_events`), sofern sie seither nicht moderiert/geändert wurden.

**Ausblick (nicht in Schema v1):** `uuid` CHAR(36) UNIQUE, `source_format` (z. B. `csv_v1`, `play_console_csv`), `file_sha256` CHAR(64) (Wiederholungserkennung), `rows_failed`, `error_summary` LONGTEXT (JSON: Zeilennummer + Fehlercode, keine Zeileninhalte), `completed_at`, Index `status_created (status, created_at)` und der vollständige Statussatz `uploaded` \| `validating` \| `importing` \| `completed` \| `failed` \| `rolled_back`.

### 2.6 `audit_log`

Geschrieben von `Audit\AuditLog::record()`; `context` wird gegen eine Allowlist gefiltert (Filter `review_captain_audit_allowed_keys`), Schlüssel, die nach Secret/Token/E-Mail/Text/IP aussehen, werden nie gespeichert.

| Spalte | Typ (Schema v1) | Anmerkung |
|---|---|---|
| `id` | `bigint(20) unsigned NOT NULL AUTO_INCREMENT` | PK |
| `event` | `varchar(80) NOT NULL` | z. B. `review.status_changed`, `settings.updated`, `reconcile.completed` |
| `object_type` | `varchar(40) NOT NULL DEFAULT ''` | |
| `object_id` | `varchar(64) NOT NULL DEFAULT ''` | UUID/ID, keine PII |
| `user_id` | `bigint(20) unsigned NULL` | |
| `context` | `longtext NULL` | JSON, allowlist-gefiltert: keine Secrets, kein Reviewtext, keine E-Mail, **keine IP** |
| `created_at` | `datetime NOT NULL` | |

Indizes: `PRIMARY KEY (id)`, `KEY event (event)`, `KEY created_at (created_at)`, `KEY object (object_type,object_id)`.

**Ausblick (nicht in Schema v1):** `actor_type` (wie geplant für `review_events`), `result` (`success` \| `failure`), `error_code` VARCHAR(64) und ein Index `action_occurred`. Die früher vorgesehenen Namen `occurred_at`/`actor_user_id`/`action`/`object_ref` heißen in v1 `created_at`/`user_id`/`event`/`object_id`.

### 2.7 Kandidaten für weitere Indizes (nicht Teil von v1)

Nur nach Messung ([scaling.md](scaling.md)) und per ADR: `reviews (profile_id, status)`, `reviews (status, created_at)` für sortierte Adminlisten, FULLTEXT `(title, body)` für Suche. (`reviews (refresh_due_at)` und `reviews (profile_id)` sind bereits in Schema v1 enthalten.)

## 3. Optionen (Präfix `review_captain_`)

| Option | Autoload | Inhalt |
|---|---|---|
| `review_captain_db_version` | ja | Integer-Schemaversion (beginnt bei `1`), unabhängig von der Plugin-Version |
| `review_captain_settings` | ja | globale Einstellungen (Schema über `review_captain_settings_schema`), keine Secrets |
| `review_captain_caps_installed` | nein | Liste der vom Plugin bereits vergebenen Capabilities je Rolle (siehe [capabilities.md](capabilities.md)) |
| `review_captain_migration_lock` | nein | `{owner, expires_at}` – Migrationssperre |
| `review_captain_migration_error` | nein | letzter Migrationsfehler (Schritt, Code, Zeit) |
| `review_captain_runner_heartbeat` | nein | letzte Runner-Läufe je Kanal (`wp_cron`, `cli`, `action_scheduler`) |
| `review_captain_instance` | nein | `{instance_uuid, home_url_hash, last_seen_at}` für Restore-/Klon-Erkennung ([reconciliation.md](reconciliation.md)) |
| `review_captain_reconcile_required` | nein | Sperre für Jobs mit Außenwirkung bis Reconciliation abgeschlossen |
| `review_captain_key_check` | nein | Schlüssel-Fingerprint + Testchiffrat ([secrets.md](secrets.md)) |
| `review_captain_oauth_state_{sha256}` | nein | kurzlebige OAuth-States ([rest-oauth.md](rest-oauth.md)) |

Die Datenlösch-Option bei Deinstallation ist Teil von `review_captain_settings` (`uninstall.delete_all_data`, Default `false`).

## 4. Installation, Upgrade, Rollback

### 4.1 Wann migriert wird

Nur bei (a) Aktivierung, (b) `admin_init`, wenn `review_captain_db_version` < Codeversion, (c) `wp review-captain db migrate`. **Nie** bei Frontend-, REST- oder Cron-Requests; der Job-Runner prüft die Version und pausiert sich bei Abweichung (kein Claim), bis migriert ist.

### 4.2 Ablauf

1. Sperre setzen: `add_option( 'review_captain_migration_lock', … )` (atomar, schlägt fehl, wenn vorhanden). Abgelaufene Sperre (> 10 min) wird per `UPDATE … WHERE option_value = <alter Wert>` übernommen.
2. `Schema::install()` – dbDelta mit allen Tabellen (strenges dbDelta-Format: zwei Leerzeichen nach `PRIMARY KEY`, jede Spalte eigene Zeile, Schlüssel benannt).
3. `Migrator` führt geordnete, **idempotente** Schritte `N → N+1` aus (z. B. `Migration0002AddDedupeKey`). Nach jedem Schritt wird `review_captain_db_version` erhöht – ein Abbruch setzt beim nächsten Lauf am fehlenden Schritt fort.
4. Datenmigrationen über ~5.000 Zeilen laufen nicht synchron, sondern als Job (`maintenance.migrate_backfill`, Batches à 500, Zeitbudget wie alle Jobs).
5. Capabilities nachziehen ([capabilities.md](capabilities.md) §4).
6. Reconciliation-Job einplanen ([reconciliation.md](reconciliation.md)), Sperre lösen.

Fehler: Schritt-ID + Fehlercode in `review_captain_migration_error`, Admin-Hinweis für `review_captain_manage_settings`, Wiederholung frühestens nach 15 min. Das Frontend arbeitet mit dem letzten konsistenten Schema weiter (Schritte sind additiv).

### 4.3 Kompatibilität und Rollback

- **Expand/Contract:** Ein Release fügt nur hinzu (Spalten nullable oder mit Default, neue Indizes, neue Tabellen). Entfernen/Umbenennen erst, wenn mindestens ein Minor-Release lang kein Code die alte Struktur nutzt.
- **Keine automatischen Down-Migrationen.** Downgrade auf eine ältere Plugin-Version innerhalb derselben Major-Version funktioniert, weil das Schema additiv ist; die ältere Version erkennt `db_version` > bekannte Version, zeigt einen Hinweis und führt keine Migration aus (kein dbDelta, das Spalten „zurückbaut“ – dbDelta löscht ohnehin nie Spalten).
- Major-Upgrades mit Contract-Schritten: vorher Backup-Hinweis in der Oberfläche; `wp review-captain db migrate --dry-run` listet SQL.
- Restore eines alten Backups: Versionsvergleich + Reconciliation.

### 4.4 Deinstallation (`uninstall.php`)

Nur wenn `uninstall.delete_all_data = true` **pro Site**: Tabellen droppen, Optionen `review_captain_*` löschen, Capabilities entfernen, Cron-Events entfernen. Sonst bleibt alles erhalten. Daten in Quellsystemen (Plattformen) werden nie gelöscht.

## 5. Multisite-Scope

- Jede Site hat eigene Tabellen und Optionen über `$wpdb->prefix`; es gibt **keine** netzwerkweiten Tabellen (`base_prefix`) in v1.
- Netzwerkaktivierung installiert nicht synchron alle Sites, sondern jede Site beim ersten `admin_init`/CLI-Aufruf (Versionsvergleich). `wp review-captain db migrate --network` iteriert Sites in Batches.
- Neue Site (`wp_initialize_site`): bei netzwerkweit aktivem Plugin sofort installieren.
- Site-Löschung: Tabellen über den Filter `wpmu_drop_tables` ergänzen, damit WordPress sie mitlöscht.
- Details: [multisite.md](multisite.md).

## 6. Ausblick: Pro-Tabellen (nicht verbindlich)

Pro besitzt eigene Tabellen und eine eigene Schemaversion (`review_captain_pro_db_version`); Core liest sie nie. Vorschlag (Präfix und Spalten entscheidet das Pro-Repository per ADR):

| Tabelle (Vorschlag) | Zweck | Wichtigster Unique-Key |
|---|---|---|
| `{prefix}review_captain_pro_requests` | Einladungs-Lifecycle (RC-024) | `sha1(experience_id|recipient_ref|campaign_id)` (RC-025) |
| `{prefix}review_captain_pro_campaigns` | Kampagnen | `uuid` |
| `{prefix}review_captain_pro_suppressions` | Opt-out/Sperren je Empfängerreferenz | `recipient_ref_hash` |
| `{prefix}review_captain_pro_provider_events` | eingegangene Provider-/Suite-Ereignisse | `(source, event_id)` |
| `{prefix}review_captain_pro_media_testimonials` | Bild/Video/Audio-Testimonials inkl. Rechtebeleg | `uuid` |
| `{prefix}review_captain_pro_trust_assets` | Trustmarks/Auszeichnungen | `uuid` |

Pro-Jobs nutzen die Core-Tabelle `jobs` (eigene `type`-Werte mit Präfix `pro.`).

## Abnahme

- [ ] Frische Installation (MySQL 8.0, MariaDB 10.6) erzeugt alle sechs Tabellen mit exakt den Spalten/Indizes aus `conventions.md` bzw. diesem Dokument (`SHOW CREATE TABLE` gegen Snapshot-Test).
- [ ] Zweiter Aktivierungslauf und `wp review-captain db migrate` ändern nichts (dbDelta-Diff leer).
- [ ] `dedupe_key` funktioniert im generierten und im Fallback-Modus; PHP- und SQL-Formel liefern identische Werte (Test).
- [ ] Abgebrochene Migration (Fehler in Schritt N simuliert) setzt beim nächsten `admin_init` bei Schritt N fort; Frontend bleibt funktionsfähig.
- [ ] Frontend-Request mit veralteter `db_version` löst keine Schemaänderung aus (Test mit Query-Logging).
- [ ] Downgrade um eine Minor-Version erzeugt keinen Fatal Error und keinen Datenverlust.
- [ ] Multisite: Site 2 und Site 3 haben getrennte Tabellen; Löschen von Site 3 entfernt ihre Tabellen.
- [ ] Deinstallation ohne Löschoption hinterlässt alle Daten; mit Löschoption sind Tabellen, Optionen, Caps und Cron-Events entfernt.
