# Datenmodell – RC-018, RC-019, RC-028, TEC-004

**Status:** Entwurf – Basis für die Implementierung RC-035  
**Stand:** 2026-11-16

Tabellenpräfix `{$wpdb->prefix}rc_` (je Site, auch in Multisite). Zeichensatz `$wpdb->get_charset_collate()` (utf8mb4). Alle Zeiten UTC als `DATETIME`. Schema-Version in Option `review_captain_db_version`.

## RC-018 – Normalisiertes Review-Schema (`rc_reviews`, Core)

| Spalte | Typ | Pflicht | Bedeutung |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | ✓ | interne ID |
| `uuid` | CHAR(36) UNIQUE | ✓ | stabile öffentliche ID (Export/Import/Verträge) |
| `origin` | VARCHAR(20) | ✓ | `local` (Formular), `manual` (Admin erfasst extern), `import` (CSV), `sync` (Provider), `connector` (Suite/Connector, z. B. Form Captain) |
| `provider` | VARCHAR(32) | ✓ | `site` (eigene Website), `google`, `trustpilot`, `etrusted`, `apple_app_store`, `google_play`, `other` |
| `profile_id` | BIGINT UNSIGNED NULL | | FK `rc_profiles.id` |
| `external_id` | VARCHAR(191) NULL | | ID beim Anbieter |
| `external_key` | CHAR(64) NULL **UNIQUE** | | `sha256(provider|profile_ref|external_id)`; erzwingt Idempotenz bei Sync/Import (TEC-004) |
| `source_entry_key` | CHAR(64) NULL **UNIQUE** | | `sha256(idempotency_key)` für Connector-Einträge (z. B. `form-captain:{site}:{entry}`) |
| `type` | VARCHAR(20) | ✓ | `service`, `product`, `app`, `event`, `voucher`, `general` |
| `rating_value` | DECIMAL(5,2) NULL | | Originalwert (Text-only-Reviews erlaubt) |
| `rating_min` / `rating_max` | TINYINT UNSIGNED | ✓ | Originalskala, Default 1/5 |
| `title` | VARCHAR(255) NULL | | |
| `content` | LONGTEXT NULL | | Klartext (kein HTML; Zeilenumbrüche erlaubt) |
| `language` | VARCHAR(20) NULL | | BCP-47 |
| `author_name` | VARCHAR(191) NULL | | öffentlicher Anzeigename |
| `author_avatar_url` | VARCHAR(2048) NULL | | nur wenn Plattform erlaubt; Ausgabe nur bei aktivierter Avatar-Option |
| `author_user_id` | BIGINT UNSIGNED NULL | | WP-Nutzer bei lokaler Einreichung eingeloggt |
| `author_email_hash` | CHAR(64) NULL | | `hash_hmac('sha256', strtolower(email), site_secret)` für Privacy Export/Eraser; keine Klartext-E-Mail |
| `context_type` / `context_id` | VARCHAR(32) / VARCHAR(64) NULL | | z. B. `product`/`123`, `event`/`…`, `app`/`com.example` |
| `source_url` | VARCHAR(2048) NULL | | Link zum Original/Profil |
| `attribution` | VARCHAR(255) NULL | | Anbieter-Attributionshinweis |
| `reply_text` / `reply_at` | LONGTEXT / DATETIME NULL | | öffentliche Antwort (falls Plattform liefert) |
| `status` | VARCHAR(20) | ✓ | `pending`, `approved`, `rejected`, `archived`, `spam` |
| `verification` | VARCHAR(20) | ✓ | `unverified`, `purchase`, `attendance`, `external` (RC-041) |
| `verification_ref` | VARCHAR(191) NULL | | Quellvertrag, der verifiziert hat (z. B. `woocommerce:order`) |
| `reported_count` | INT UNSIGNED | ✓ | Default 0 |
| `published_at` | DATETIME NULL | | Originaldatum der Bewertung |
| `source_updated_at` | DATETIME NULL | | letzte Änderung beim Anbieter |
| `fetched_at` | DATETIME NULL | | letzter erfolgreicher Abruf (Aktualitätsprüfung) |
| `tombstoned_at` | DATETIME NULL | | beim Anbieter gelöscht/nicht mehr geliefert → nie mehr ausgeben |
| `import_batch_id` | BIGINT UNSIGNED NULL | | Rollback CSV-Import |
| `created_at` / `updated_at` | DATETIME | ✓ | |
| `meta` | LONGTEXT NULL | | JSON für providerspezifische Zusatzfelder (App-Version, Territory, Gerät) |

Indizes: `status_published (status, published_at)`, `provider_profile (provider, profile_id)`, `rating (rating_value)`, `context (context_type, context_id)`, `batch (import_batch_id)`, `email_hash (author_email_hash)`, `tombstone (tombstoned_at)`. Volltextsuche: `LIKE` auf `title/content/author_name` mit Pagination; FULLTEXT-Index optional ab Messung TEC-014.

**Ausgaberegel:** öffentlich nur `status='approved' AND tombstoned_at IS NULL` und für `origin='sync'` zusätzlich `fetched_at >= NOW() - Aktualitätsfrist`.

**Skalen:** Anzeige immer in Originalskala; Normalisierung (0–1) nur berechnet, nie gespeichert.

### Moderationsverlauf (`rc_review_log`, Core)

`id`, `review_id`, `action` (`created|approved|rejected|archived|spam|restored|reported|edited|tombstoned`), `reason` VARCHAR(255) NULL, `user_id` NULL, `created_at`. Index `(review_id, created_at)`.

## RC-019 – Testimonial-Schema (`rc_testimonials`, Core-Tabelle; Medienfelder Pro-Folge)

| Spalte | Typ | Bedeutung |
|---|---|---|
| `id`, `uuid` | | |
| `kind` | VARCHAR(10) | `text`, `image`, `video`, `audio` (Core: nur `text` bearbeitbar) |
| `quote` | LONGTEXT | freigegebene Textfassung |
| `display_name` / `display_name_mode` | VARCHAR(191) / VARCHAR(20) | `full`, `first_name`, `initials`, `anonymous` |
| `role_label` | VARCHAR(191) NULL | z. B. „Teamleitung“ |
| `attachment_id` | BIGINT NULL | WordPress-Medium (Bild/Video/Audio) |
| `poster_attachment_id` | BIGINT NULL | Vorschaubild |
| `external_embed_url` | VARCHAR(2048) NULL | nur Allowlist-Hosts (TEC-013) |
| `transcript` | LONGTEXT NULL | Pflicht für Audio/Video vor Freigabe |
| `captions_attachment_id` | BIGINT NULL | WebVTT |
| `rating_value` | TINYINT NULL | optional 1–5 |
| `context_type` / `context_id` | | wie Reviews |
| `language` | VARCHAR(20) | |
| `consent` | LONGTEXT (JSON) | Rechtebeleg je Element laut RC-015 (`name`, `text`, `image`, `voice`, `video`): Art, Datum, Textversion, Umfang, Ablage-Referenz |
| `valid_until` | DATETIME NULL | Ablauf der Rechte |
| `revoked_at` | DATETIME NULL | Widerruf → sofort ausgeblendet |
| `status` | VARCHAR(20) | `draft`, `approved`, `archived` |
| `created_at`, `updated_at` | | |

Freigabe (`approved`) nur, wenn alle für `kind` erforderlichen Consent-Elemente vorhanden und (`kind` ∈ video/audio) Transkript vorhanden. Ausgabe nur `approved AND revoked_at IS NULL AND (valid_until IS NULL OR valid_until > NOW())`.

## Weitere Core-Tabellen

| Tabelle | Zweck | Wichtige Spalten / Indizes |
|---|---|---|
| `rc_profiles` | Plattformprofile (Core: öffentlicher Link; Pro: verbunden) | `provider`, `label`, `public_url`, `external_ref`, `locale`, `connection_ref` NULL, `status`; UNIQUE `(provider, external_ref)` |
| `rc_widgets` | Widgetdefinitionen | `uuid`, `title`, `layout`, `config` JSON (Quellen, Filter, Anzeige), `status`, `schema_version` |
| `rc_jobs` | Job-Queue (TEC-001/003) | `type`, `unique_key` CHAR(64) UNIQUE NULL, `payload` JSON, `status`, `priority`, `attempts`, `max_attempts`, `available_at`, `claimed_by`, `lease_until`, `last_error_code`, `last_error_message`; Index `(status, available_at, priority)`, `(lease_until)` |
| `rc_import_batches` | CSV-Import | `uuid`, `filename`, `rows_total`, `rows_imported`, `rows_skipped`, `status` (`dry_run|imported|rolled_back`), `user_id`, `created_at` |
| `rc_audit_log` | Admin-/Sicherheitsereignisse (RC-037) | `event`, `object_type`, `object_id`, `user_id`, `context` JSON (Allowlist), `created_at`; Index `(created_at)`, `(object_type, object_id)` |

## Pro-Tabellen (Besitzer: Pro-Add-on, gleiches Präfix, eigene Versionsoption `review_captain_pro_db_version`)

| Tabelle | Zweck | Idempotenz (TEC-004) |
|---|---|---|
| `rc_connections` | verschlüsselte Zugangsdaten je Provider | – |
| `rc_sync_runs` | Abrufläufe, Cursor, Ergebnis | UNIQUE `(profile_id, run_key)` |
| `rc_campaigns` | Kampagnen (versioniert) | – |
| `rc_experiences` | empfangene Erlebnisereignisse | UNIQUE `event_key = sha256(source|event_id)` |
| `rc_requests` | Einladungen | UNIQUE `invite_key = sha256(experience_id|recipient_ref)` (RC-025) |
| `rc_suppressions` | Opt-out/Bounce | UNIQUE `email_hash` |
| `rc_trust_assets` | Trustmarks/Awards (Pro-Folge) | – |

## RC-028 – Migrations- und Upgradeplan

- **Installation/Upgrade:** `Schema::install()` nutzt `dbDelta()`; idempotent. Aufruf bei Aktivierung und, wenn `review_captain_db_version` < Code-Version, im Admin (`admin_init`, nur für `rc_manage_settings`/`activate_plugins`) bzw. per `wp review-captain db upgrade`. **Nie** im Frontend.
- **Datenmigrationen** (z. B. Feld umbenennen): nummerierte Schritte; große Datenmengen in Batches über `rc_jobs` (Job-Typ `migration.step`), Fortschritt in Diagnose.
- **Rollback:** Schemaänderungen nur additiv innerhalb einer Major-Version (neue Spalten nullable, keine Typverengung). Downgrade auf ältere Plugin-Version ist dadurch lesend möglich; destruktive Bereinigung alter Spalten erst eine Minor-Version später.
- **Multisite:** Tabellen je Site; Netzwerkaktivierung installiert lazy beim ersten Admin-Aufruf der Site bzw. per `wp site list | xargs wp review-captain db upgrade --url=`; neue Sites via `wp_initialize_site`, gelöschte Sites via `wp_uninitialize_site` (Tabellen entfernen).
- **Deinstallation:** `uninstall.php` löscht nur bei `delete_data_on_uninstall = true` (RC-048).
- **Versionen:** 1.0.0 → `db_version = 1`.
