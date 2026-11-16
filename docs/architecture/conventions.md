# Review Captain – verbindliche technische Konventionen (Core)

Status: verbindlich für die Core-Implementierung ab 2026-11-16. Änderungen nur per ADR (`docs/adr/`).
Offene Produktentscheidungen (Mindestversionen, Pläne, Preise) sind als **Arbeitsannahme** markiert und blockieren den Core nicht.

## Identität

| Element | Wert |
|---|---|
| Plugin-Slug / Ordner | `review-captain` (Repository-Wurzel = Plugin-Wurzel) |
| Hauptdatei | `review-captain.php` |
| PHP-Namespace | `ReviewCaptain\` → `src/` (PSR-4, eigener Autoloader, Composer optional) |
| Textdomain | `review-captain`, Domain Path `/languages` |
| Konstanten-Präfix | `REVIEW_CAPTAIN_` (`REVIEW_CAPTAIN_VERSION`, `REVIEW_CAPTAIN_FILE`, `REVIEW_CAPTAIN_DIR`, `REVIEW_CAPTAIN_URL`) |
| Options-Präfix | `review_captain_` |
| Hook-Präfix | `review_captain_` (kein `wp_`-Präfix: in WordPress für Core reserviert, siehe ADR-0002) |
| Mindestversionen | **Arbeitsannahme** PHP 8.1, WordPress 6.5 (RC-006 offen) |
| Pro-Add-on | `review-captain-pro` (separates Repo `captain-pro/addons/review-captain-pro`); Core enthält **keinen** Lizenzcode |

## Datenbank (Präfix `{$wpdb->prefix}review_captain_`)

Multisite: jede Site hat eigene Tabellen über `$wpdb->prefix`. Schema-Version in Option `review_captain_db_version`. Schemaänderungen nur über `ReviewCaptain\Install\Schema` (dbDelta) + `Migrator`, nie bei Frontend-Requests (nur Aktivierung, `admin_init` mit Versionsvergleich, WP-CLI).

| Tabelle | Zweck |
|---|---|
| `reviews` | normalisierte Reviews und Core-Text-Testimonials (`kind`) |
| `review_events` | Moderations-/Änderungshistorie je Review |
| `profiles` | Quellprofile (Plattform, Label, Profil-URL, externe Referenz, Status) – im Core für CTA/manuelle Quellen, Pro nutzt sie für Sync |
| `jobs` | Hintergrundjobs mit Lease/Idempotenz |
| `import_batches` | CSV-Importläufe inkl. Rollback |
| `audit_log` | datensparsames Audit |

### `reviews` – Spalten

`id` BIGINT PK, `uuid` CHAR(36) UNIQUE, `kind` (`review`|`testimonial`), `origin` VARCHAR(30) (`local_submission`|`manual`|`csv_import`|`provider_sync`|`connector`), `platform` VARCHAR(30) (`local`|`google`|`trustpilot`|`trusted_shops`|`app_store`|`google_play`|`other`), `profile_id` BIGINT NULL, `external_id` VARCHAR(191) NULL, `rating_value` DECIMAL(6,2) NULL, `rating_scale_min` DECIMAL(6,2) DEFAULT 1, `rating_scale_max` DECIMAL(6,2) DEFAULT 5, `title` VARCHAR(255), `body` LONGTEXT, `language` VARCHAR(20), `author_name` VARCHAR(191), `author_email` VARCHAR(191) NULL (nur lokale Einreichungen), `author_user_id` BIGINT NULL, `verification` (`unverified`|`purchase_verified`|`attendance_confirmed`|`external_platform`), `status` (`pending`|`approved`|`rejected`|`archived`|`flagged`|`removed`), `context_type` VARCHAR(40) NULL (`post`|`product`|`event`|`voucher`|`app`|`form`), `context_id` VARCHAR(64) NULL, `source_url` TEXT NULL, `published_at` DATETIME NULL (Originaldatum), `fetched_at` DATETIME NULL, `refresh_due_at` DATETIME NULL, `consent_publish` TINYINT, `consent_at` DATETIME NULL, `import_batch_id` BIGINT NULL, `experience_id` VARCHAR(191) NULL, `meta` LONGTEXT (JSON, keine Secrets), `created_at`, `updated_at` DATETIME (UTC), `removed_at` DATETIME NULL (Tombstone).

Unique: `uuid`; `(platform, profile_id, external_id)` über die gewöhnliche Spalte `dedupe_key` CHAR(40) UNIQUE NULL = `sha1(platform|profile_id|external_id)` wenn `external_id` gesetzt, befüllt in PHP durch `Review::dedupe_key()` (TEC-004; keine generierte Spalte). Indizes: `(status, kind, platform)`, `(rating_value)`, `(created_at)`, `(context_type, context_id)`, `(author_email)`, `(import_batch_id)`, `(profile_id)`, `(refresh_due_at)`. Exakte Typen und Defaults: [database.md](database.md) (maßgeblich `src/Install/Schema.php`).

### `jobs` – Spalten

`id`, `type` VARCHAR(100), `idempotency_key` VARCHAR(191) UNIQUE, `payload` LONGTEXT JSON, `status` (`queued`|`running`|`succeeded`|`failed`|`dead`|`paused`), `attempts` INT, `max_attempts` INT, `available_at` DATETIME, `lease_owner` VARCHAR(64) NULL, `lease_expires_at` DATETIME NULL, `last_error_code` VARCHAR(64) NULL, `last_error_message` VARCHAR(500) NULL (sanitisiert), `created_at`, `updated_at`. Index `(status, available_at)`, `(type, status)`, `(lease_expires_at)`.

Claim ist ein atomarer `UPDATE … SET lease_owner=%s, lease_expires_at=… WHERE id=%d AND (status='queued' OR (status='running' AND lease_expires_at < NOW))` mit Prüfung `rows_affected === 1`.

## Rechte (Capabilities)

| Capability | Zweck | Standard |
|---|---|---|
| `review_captain_moderate` | Reviews/Testimonials ansehen, anlegen, moderieren | administrator, editor |
| `review_captain_manage_sources` | Quellen/Profile verbinden/trennen | administrator |
| `review_captain_manage_requests` | Kampagnen/Anfragen (Pro) | administrator |
| `review_captain_view_reports` | Auswertungen/Diagnose lesen | administrator |
| `review_captain_manage_trust` | Trustmarks/Badges (Pro) | administrator |
| `review_captain_manage_privacy` | Export/Import/Löschung, Aufbewahrung | administrator |
| `review_captain_manage_settings` | globale Einstellungen | administrator |

Jede schreibende Aktion: Capability-Prüfung **und** Nonce (`review_captain_{action}`). REST: jede Route mit expliziter `permission_callback`.

## Hooks (öffentliche Verträge, versioniert)

| Hook | Art | Zweck |
|---|---|---|
| `review_captain_loaded` | action | Core geladen, Pro/Connectoren registrieren sich hier |
| `review_captain_connectors_v1` | action(Registry) | Connector-Registrierung (CON-002) |
| `review_captain_providers_v1` | action(Registry) | Review-Provider-Registrierung (RC-020) |
| `review_captain_widgets_v1` | action(Registry) | Widget-Typen registrieren (RC-118) |
| `review_captain_process_job_v1` | filter | Jobhandler für fremde Jobtypen (CON-009) |
| `review_captain_review_created_v1` | action | nach Commit, Payload ohne Text/E-Mail |
| `review_captain_review_status_changed_v1` | action | nach Commit |
| `review_captain_review_removed_v1` | action | nach Commit |
| `review_captain_settings_schema` | filter | Pro ergänzt Einstellungsfelder |
| `review_captain_schema_eligible` | filter | nur verschärfen (true→false), nie lockern |

Event-Payloads: `['schema' => 'review_captain.review.v1', 'event_id' => uuid, 'review_uuid', 'kind', 'platform', 'status', 'previous_status', 'origin', 'occurred_at' (ISO 8601 UTC)]` – niemals Reviewtext, E-Mail, Tokens.

## Öffentliche Ausgabe

- Shortcode: `[review_captain v="1" ...]` (Attribute siehe `docs/contracts/widget-v1.md`), Formular: `[review_captain_form v="1"]`.
- Block: `review-captain/reviews` (dynamisch, serverseitig gerendert), `review-captain/form`.
- REST-Namespace: `review-captain/v1` (Admin-Zwecke, Editor-Vorschau). Keine öffentlichen Schreib-Endpunkte; Einreichung per `admin-post.php` mit Nonce + Honeypot + Rate-Limit.
- Kein Frontend-JavaScript nötig; CSS-Klassenpräfix `rc-`.
- Schema-Markup nur für `platform=local`, `kind=review`, `status=approved`, sichtbar gerendert, nicht selbstbezogen (Organization/LocalBusiness); siehe `docs/seo/`.

## Fehlercodes

Format `rc.<bereich>.<code>`, z. B. `rc.auth.invalid`, `rc.quota.exceeded`, `rc.net.timeout`, `rc.contract.incompatible`, `rc.data.invalid_payload`, `rc.remote.removed`, `rc.temp.unavailable`, `rc.perm.failed`, `rc.source.unavailable`. Liste und Retry-Klasse: `ReviewCaptain\Support\ErrorCode`, Doku `docs/architecture/error-codes.md`.

## Code-Stil

WordPress Coding Standards (WPCS 3) mit PSR-4-Dateinamen-Ausnahme, `declare(strict_types=1);`, alle SQL über `$wpdb->prepare`, alle Ausgaben escaped, alle Strings übersetzbar (`__()`, `esc_html__()` mit Textdomain `review-captain`). Zeiten in DB als UTC `Y-m-d H:i:s`.

## Repository-Layout

```
review-captain.php   uninstall.php   readme.txt
src/                 PHP-Klassen (Namespace ReviewCaptain)
templates/           überschreibbare Ausgabetemplates (Theme: review-captain/…)
assets/css, assets/js
blocks/              block.json + Editor-Skripte (ohne Build-Pflicht, wp.element/wp.blocks)
languages/
tests/unit           PHPUnit (ohne WordPress, Brain Monkey / Stubs)
examples/            Demo-Connector, Connector-SDK
bin/                 Build-/Hilfsskripte
docs/                Spezifikationen (nicht im Release-ZIP)
```
