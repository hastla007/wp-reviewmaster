# Datenbank- und Migrationsplan (RC-028)

Status: Spezifikation für Core v1, Stand 2026-11-16. Grundlage und verbindlich: [`conventions.md`](conventions.md) Abschnitt Datenbank, [ADR-0001](../adr/0001-custom-tables-statt-cpt.md).
Spalten/Indizes für `reviews` und `jobs` sind 1:1 aus `conventions.md` übernommen. Für `review_events`, `profiles`, `import_batches`, `audit_log` legt `conventions.md` nur den Zweck fest; die Spalten unten sind die **Spezifikation dieses Dokuments** (Änderung danach nur per ADR).

## 1. Grundregeln

- Präfix: `{$wpdb->prefix}review_captain_` (z. B. `wp_review_captain_reviews`, Multisite `wp_3_review_captain_reviews`).
- Engine/Charset: InnoDB, `$wpdb->get_charset_collate()` (in der Regel `utf8mb4_unicode_520_ci`). Indizierte VARCHAR-Spalten max. 191 Zeichen.
- Zeiten: `DATETIME` in UTC, Format `Y-m-d H:i:s`, gesetzt aus PHP (`gmdate()`), **nicht** über MySQL `NOW()`/`CURRENT_TIMESTAMP` (Session-Zeitzone ist auf vielen Hosts nicht UTC). Wo `conventions.md` „NOW“ schreibt, ist ein per `$wpdb->prepare` übergebener UTC-Zeitstempel gemeint.
- Enums als `VARCHAR(20…40)` statt MySQL-`ENUM` (dbDelta-freundlich, erweiterbar ohne ALTER). Zulässige Werte erzwingt PHP (Value Objects).
- JSON-Spalten (`meta`, `payload`, `settings`, `state`, `context`) als `LONGTEXT`, geschrieben mit `wp_json_encode()`; **niemals Secrets** außer in `profiles.credentials_enc` (verschlüsselt, siehe [secrets.md](secrets.md)).
- Alle SQL über `$wpdb->prepare()`. Keine Fremdschlüssel-Constraints (dbDelta/Hosting-Kompatibilität); referentielle Integrität per Code und [Reconciliation](reconciliation.md).

## 2. Tabellen

### 2.1 `reviews`

Spalten exakt nach `conventions.md`:

| Spalte | Typ | Anmerkung |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `uuid` | CHAR(36) NOT NULL UNIQUE | öffentliche Referenz in Events/Exporten |
| `kind` | VARCHAR(20) NOT NULL | `review` \| `testimonial` |
| `origin` | VARCHAR(20) NOT NULL | `local_submission` \| `manual` \| `csv_import` \| `provider_sync` \| `connector` |
| `platform` | VARCHAR(20) NOT NULL | `local` \| `google` \| `trustpilot` \| `trusted_shops` \| `app_store` \| `google_play` \| `other` |
| `profile_id` | BIGINT UNSIGNED NULL | → `profiles.id` |
| `external_id` | VARCHAR(191) NULL | stabile Plattform-ID |
| `rating_value` | DECIMAL(6,2) NULL | |
| `rating_scale_min` | DECIMAL(6,2) NOT NULL DEFAULT 1 | |
| `rating_scale_max` | DECIMAL(6,2) NOT NULL DEFAULT 5 | |
| `title` | VARCHAR(255) NOT NULL DEFAULT '' | |
| `body` | LONGTEXT NOT NULL | sanitisiertes Plain-Text/eingeschränktes HTML |
| `language` | VARCHAR(20) NOT NULL DEFAULT '' | BCP 47 |
| `author_name` | VARCHAR(191) NOT NULL DEFAULT '' | |
| `author_email` | VARCHAR(191) NULL | **nur** `origin=local_submission` |
| `author_user_id` | BIGINT UNSIGNED NULL | |
| `verification` | VARCHAR(30) NOT NULL DEFAULT 'unverified' | `unverified` \| `purchase_verified` \| `attendance_confirmed` \| `external_platform` |
| `status` | VARCHAR(20) NOT NULL DEFAULT 'pending' | `pending` \| `approved` \| `rejected` \| `archived` \| `flagged` \| `removed` |
| `context_type` | VARCHAR(40) NULL | `post` \| `product` \| `event` \| `voucher` \| `app` \| `form` |
| `context_id` | VARCHAR(64) NULL | |
| `source_url` | TEXT NULL | nur `https`, validiert (siehe [embed-security.md](embed-security.md)) |
| `published_at` | DATETIME NULL | Originaldatum |
| `fetched_at` | DATETIME NULL | letzter erfolgreicher Abruf |
| `refresh_due_at` | DATETIME NULL | Pflicht-Refresh laut [retention-matrix.md](retention-matrix.md) |
| `consent_publish` | TINYINT(1) NOT NULL DEFAULT 0 | |
| `consent_at` | DATETIME NULL | |
| `import_batch_id` | BIGINT UNSIGNED NULL | → `import_batches.id` |
| `experience_id` | VARCHAR(191) NULL | Suite-Erlebnis (RC-022) |
| `meta` | LONGTEXT NULL | JSON, keine Secrets |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |
| `removed_at` | DATETIME NULL | Tombstone |
| `dedupe_key` | VARCHAR(191) NULL UNIQUE | siehe 2.1.1 |

Indizes (exakt `conventions.md`):

```
PRIMARY KEY  (id)
UNIQUE KEY uuid (uuid)
UNIQUE KEY dedupe_key (dedupe_key)
KEY status_kind_platform (status, kind, platform)
KEY rating_value (rating_value)
KEY created_at (created_at)
KEY context (context_type, context_id)
KEY author_email (author_email)
KEY import_batch_id (import_batch_id)
```

#### 2.1.1 `dedupe_key` (TEC-004)

Formel: `sha1( platform . '|' . (profile_id ?? 0) . '|' . external_id )` (40 Hex-Zeichen, kleingeschrieben), nur wenn `external_id` gesetzt ist, sonst `NULL` (mehrere `NULL` sind in UNIQUE zulässig).

- **Bevorzugt** als generierte Spalte (MySQL ≥ 5.7, MariaDB ≥ 10.2):
  `dedupe_key VARCHAR(191) GENERATED ALWAYS AS (IF(external_id IS NULL, NULL, SHA1(CONCAT(platform, '|', IFNULL(profile_id, 0), '|', external_id)))) STORED`
- dbDelta kann generierte Spalten nicht zuverlässig vergleichen. Deshalb legt `Schema` die Spalte **nicht** im dbDelta-SQL an, sondern `Migrator` per separatem, idempotentem `ALTER TABLE … ADD COLUMN` + `ADD UNIQUE KEY` nach Prüfung über `SHOW COLUMNS`.
- **Fallback** (DB ohne generierte Spalten oder fehlgeschlagenes ALTER): gewöhnliche Spalte `VARCHAR(191) NULL`, die das Repository bei jedem Insert/Update mit derselben Formel in PHP befüllt. Der UNIQUE-Key bleibt identisch. Welcher Modus aktiv ist, steht in der Diagnose. PHP-Formel und SQL-Formel werden per Unit-Test gegeneinander geprüft.
- Inserts aus Sync nutzen `INSERT … ON DUPLICATE KEY UPDATE` (bzw. Insert → bei Duplicate-Fehler Update), damit parallele Läufe keine Dubletten erzeugen.

### 2.2 `review_events`

| Spalte | Typ | Anmerkung |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `event_id` | CHAR(36) NOT NULL UNIQUE | = `event_id` im Hook-Payload |
| `review_id` | BIGINT UNSIGNED NOT NULL | |
| `event_type` | VARCHAR(40) NOT NULL | `created` \| `status_changed` \| `edited` \| `refreshed` \| `removed` \| `restored` \| `consent_changed` \| `anonymized` |
| `previous_status` | VARCHAR(20) NULL | |
| `new_status` | VARCHAR(20) NULL | |
| `actor_type` | VARCHAR(20) NOT NULL | `user` \| `system` \| `job` \| `cli` \| `connector` |
| `actor_user_id` | BIGINT UNSIGNED NULL | |
| `reason_code` | VARCHAR(64) NULL | z. B. `rc.remote.removed` |
| `note` | VARCHAR(500) NULL | Moderationsnotiz, kein Reviewtext, keine E-Mail |
| `created_at` | DATETIME NOT NULL | |

Indizes: `PRIMARY (id)`, `UNIQUE event_id`, `KEY review_created (review_id, created_at)`, `KEY created_at (created_at)`.

### 2.3 `profiles`

| Spalte | Typ | Anmerkung |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `uuid` | CHAR(36) NOT NULL UNIQUE | |
| `platform` | VARCHAR(20) NOT NULL | Werte wie `reviews.platform` |
| `connection_type` | VARCHAR(20) NOT NULL DEFAULT 'link' | `link` (nur Profil-URL/CTA, Core) \| `manual` \| `api` (Pro) \| `connector` |
| `owner` | VARCHAR(64) NOT NULL DEFAULT 'core' | `core` \| `pro` \| `connector:<key>` |
| `label` | VARCHAR(191) NOT NULL | |
| `profile_url` | TEXT NULL | öffentlicher Link, nur https |
| `external_ref` | VARCHAR(191) NULL | Location-Name, Business-Unit-ID, eTrusted-Channel, Apple-App-ID, Paketname |
| `status` | VARCHAR(20) NOT NULL DEFAULT 'active' | `active` \| `paused` \| `auth_required` \| `disconnected` \| `archived` |
| `credentials_enc` | LONGTEXT NULL | verschlüsselter Envelope ([secrets.md](secrets.md)); nie exportiert |
| `credentials_rev` | INT UNSIGNED NOT NULL DEFAULT 0 | Compare-and-swap bei Tokenrefresh |
| `key_id` | VARCHAR(16) NULL | Fingerprint des verwendeten Schlüssels |
| `sync_cursor` | LONGTEXT NULL | JSON, providerdefiniert |
| `settings` | LONGTEXT NULL | JSON: Budgets, Refresh-Overrides ([jobs.md](jobs.md) §8) |
| `state` | LONGTEXT NULL | JSON: Budgetverbrauch, Backoff-bis, Zähler |
| `last_sync_at` | DATETIME NULL | |
| `last_success_at` | DATETIME NULL | |
| `next_sync_at` | DATETIME NULL | Anzeige „frühestens“ |
| `last_error_code` | VARCHAR(64) NULL | `rc.*` |
| `disconnected_at` | DATETIME NULL | |
| `created_at` / `updated_at` | DATETIME NOT NULL | |

Indizes: `PRIMARY (id)`, `UNIQUE uuid`, `UNIQUE platform_ref (platform, external_ref)` (mehrere `NULL` erlaubt), `KEY status (status)`.

### 2.4 `jobs`

Exakt `conventions.md`:

| Spalte | Typ |
|---|---|
| `id` | BIGINT UNSIGNED PK |
| `type` | VARCHAR(100) NOT NULL |
| `idempotency_key` | VARCHAR(191) NOT NULL UNIQUE |
| `payload` | LONGTEXT NULL (JSON, keine Secrets, keine E-Mails) |
| `status` | VARCHAR(20) NOT NULL DEFAULT 'queued' – `queued` \| `running` \| `succeeded` \| `failed` \| `dead` \| `paused` |
| `attempts` | INT UNSIGNED NOT NULL DEFAULT 0 |
| `max_attempts` | INT UNSIGNED NOT NULL DEFAULT 8 |
| `available_at` | DATETIME NOT NULL |
| `lease_owner` | VARCHAR(64) NULL |
| `lease_expires_at` | DATETIME NULL |
| `last_error_code` | VARCHAR(64) NULL |
| `last_error_message` | VARCHAR(500) NULL (sanitisiert) |
| `created_at` / `updated_at` | DATETIME NOT NULL |

Indizes: `PRIMARY (id)`, `UNIQUE idempotency_key`, `KEY status_available (status, available_at)`, `KEY type_status (type, status)`. Semantik: [jobs.md](jobs.md).

### 2.5 `import_batches`

| Spalte | Typ | Anmerkung |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `uuid` | CHAR(36) NOT NULL UNIQUE | |
| `source_format` | VARCHAR(40) NOT NULL | z. B. `csv_v1`, `play_console_csv` |
| `file_sha256` | CHAR(64) NOT NULL | Wiederholungserkennung; Datei selbst wird nach Verarbeitung gelöscht |
| `status` | VARCHAR(20) NOT NULL | `uploaded` \| `validating` \| `importing` \| `completed` \| `failed` \| `rolled_back` |
| `rows_total` / `rows_imported` / `rows_skipped` / `rows_failed` | INT UNSIGNED NOT NULL DEFAULT 0 | |
| `error_summary` | LONGTEXT NULL | JSON: Zeilennummer + Fehlercode, **keine** Zeileninhalte |
| `created_by` | BIGINT UNSIGNED NULL | |
| `created_at` / `completed_at` / `rolled_back_at` | DATETIME | |

Indizes: `PRIMARY`, `UNIQUE uuid`, `KEY status_created (status, created_at)`. Rollback = Löschen aller `reviews` mit `import_batch_id` in Batches à 500 per Job, sofern sie seither nicht moderiert/geändert wurden (sonst Rückfrage).

### 2.6 `audit_log`

| Spalte | Typ | Anmerkung |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `occurred_at` | DATETIME NOT NULL | |
| `actor_type` | VARCHAR(20) NOT NULL | wie `review_events.actor_type` |
| `actor_user_id` | BIGINT UNSIGNED NULL | |
| `action` | VARCHAR(64) NOT NULL | z. B. `profile.connected`, `profile.disconnected`, `settings.updated`, `privacy.erased`, `secrets.rotated`, `job.requeued` |
| `object_type` | VARCHAR(40) NULL | |
| `object_ref` | VARCHAR(64) NULL | UUID/ID, keine PII |
| `result` | VARCHAR(20) NOT NULL | `success` \| `failure` |
| `error_code` | VARCHAR(64) NULL | |
| `context` | LONGTEXT NULL | JSON, whitelist-gefiltert: keine Secrets, kein Reviewtext, keine E-Mail, **keine IP** |

Indizes: `PRIMARY`, `KEY occurred_at`, `KEY action_occurred (action, occurred_at)`, `KEY object (object_type, object_ref)`.

### 2.7 Kandidaten für weitere Indizes (nicht Teil von v1)

Nur nach Messung ([scaling.md](scaling.md)) und per ADR: `reviews (refresh_due_at)` für Refresh-/Purge-Jobs, `reviews (profile_id, status)`, `reviews (status, created_at)` für sortierte Adminlisten, FULLTEXT `(title, body)` für Suche.

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
