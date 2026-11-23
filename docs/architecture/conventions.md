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
| `review_tags`, `review_reports`, `review_media`, `questions` | ab Schema v2 (M7, standardmäßig ungenutzt): interne Tags, öffentliche Meldungen ohne Meldenden-Daten, Foto-Reviews, Produkt-Q&A ([database.md](database.md) §2.8) |

### `reviews` – Spalten

`id` BIGINT PK, `uuid` CHAR(36) UNIQUE, `kind` (`review`|`testimonial`), `origin` VARCHAR(30) (`local_submission`|`manual`|`csv_import`|`provider_sync`|`connector`), `platform` VARCHAR(30) (`local`|`google`|`trustpilot`|`trusted_shops`|`app_store`|`google_play`|`other`), `profile_id` BIGINT NULL, `external_id` VARCHAR(191) NULL, `rating_value` DECIMAL(6,2) NULL, `rating_scale_min` DECIMAL(6,2) DEFAULT 1, `rating_scale_max` DECIMAL(6,2) DEFAULT 5, `title` VARCHAR(255), `body` LONGTEXT, `language` VARCHAR(20), `author_name` VARCHAR(191), `author_email` VARCHAR(191) NULL (nur lokale Einreichungen), `author_user_id` BIGINT NULL, `verification` (`unverified`|`purchase_verified`|`attendance_confirmed`|`external_platform`), `status` (`pending`|`approved`|`rejected`|`archived`|`flagged`|`removed`), `context_type` VARCHAR(40) NULL (`post`|`product`|`event`|`voucher`|`app`|`form`), `context_id` VARCHAR(64) NULL, `source_url` TEXT NULL, `published_at` DATETIME NULL (Originaldatum), `fetched_at` DATETIME NULL, `refresh_due_at` DATETIME NULL, `consent_publish` TINYINT, `consent_at` DATETIME NULL, `import_batch_id` BIGINT NULL, `experience_id` VARCHAR(191) NULL, `submission_key` CHAR(40) NULL UNIQUE (v2, RC-123), `revision` INT UNSIGNED DEFAULT 0 (v2, Optimistic Lock), `meta` LONGTEXT (JSON, keine Secrets), `created_at`, `updated_at` DATETIME (UTC), `removed_at` DATETIME NULL (Tombstone).

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

Öffentliche Hooks und Stabilitätsstufen: [ADR-0007](../adr/0007-oeffentliche-hooks-v1.md) (V = versionierter Vertrag, S = stabiler Erweiterungspunkt, B = Betriebs-Stellschraube, Default änderbar). Generierte Referenz mit Parametern und Fundstellen: [`docs/hooks.md`](../hooks.md) (`php bin/list-hooks.php --write`).

| Hook | Art | Stufe | Zweck |
|---|---|---|---|
| `review_captain_loaded` | action | S | Core geladen (`plugins_loaded`), Pro/Connectoren registrieren sich hier; Parameter `Plugin` |
| `review_captain_installed` | action | S | nach Installation/Upgrade des Schemas je Site (`$from`, `$to`) |
| `review_captain_connectors_v1` | action(Registry) | V | Connector-Registrierung (CON-002) |
| `review_captain_providers_v1` | action(Registry) | V | Review-Provider-Registrierung (RC-020) |
| `review_captain_widgets_v1` | action(Registry) | V | Widget-Typen registrieren (RC-118) |
| `review_captain_process_job_v1` | filter | V | Jobhandler für fremde Jobtypen (CON-009) |
| `review_captain_review_created_v1` | action | V | nach Commit, Payload ohne Text/E-Mail |
| `review_captain_review_status_changed_v1` | action | V | nach Commit |
| `review_captain_review_removed_v1` | action | V | nach Commit |
| `review_captain_request_planner_v1` | filter | V | Pro registriert den Anfrage-Planer für `WorkflowService` (`RequestPlannerInterface`, Vertrag 1.x); ohne → `rc.contract.unavailable` (RC-WF-03) |
| `review_captain_spam_checks_v1` | filter | V | zusätzliche Spam-Prüfungen/externe Captcha-Anbieter für Bewertungs-, Melde- und Frageformular (SET-RC-11); Rückgabe Liste von Gründen, jeder Grund lehnt neutral ab. **Nur nach Rechtsprüfung.** Angelehnt an Form Captains `form_captain_spam_checks_v1` |
| `review_captain_pow_max_number_v1` | filter | B | Schwierigkeit der eingebauten Rechenprüfung (Standard 50 000, 1 000–1 000 000, wie `form_captain_pow_max_number_v1`) |
| `review_captain_settings_schema` | filter | S | Pro ergänzt Einstellungsfelder (Core-Felder nicht entfern-/änderbar) |
| `review_captain_pro_settings_writable` | filter | S | Pro erlaubt das Schreiben von Pro-Feldern (Default `false`) |
| `review_captain_show_pro_info` | filter | S | Pro-Hinweis im Dashboard ausblenden (Default `true`) |
| `review_captain_setup_checklist_detected` | filter | S | erkannte Integrationen der Einrichtungs-Checkliste |
| `review_captain_diagnostics_sections` | filter | S | Abschnitte der Diagnoseseite (`title`, `render`) |
| `review_captain_schema_eligible` | filter | S | nur verschärfen (true→false), nie lockern |
| `review_captain_schema_post_item_type` | filter | S | schema.org-Typ des bewerteten Objekts je Beitrag |
| `review_captain_template` | filter | S | Pfad eines Frontend-Templates (Theme-Override) |
| `review_captain_moderation_url` | filter | S | Link in der Admin-Benachrichtigung |
| `review_captain_embed_hosts` | filter | S | erlaubte Hosts für Anbieter-Embeds |
| `review_captain_audit_allowed_keys` | filter | S | Allowlist für Audit-Kontext (verbotene Muster bleiben verboten) |
| `review_captain_privacy_personal_meta_keys` | filter | S | `meta`-Schlüssel mit personenbezogenen Daten (Privacy-Export/-Löschung) |
| `review_captain_sync_initial_status` | filter | S | Anfangsstatus neu synchronisierter Reviews (`pending`\|`approved`) |
| `review_captain_reconcile_report` | filter | S | Reconciliation erweitern (Pro: Credentials, Cursor) |
| `review_captain_instance_confirmed` | action | S | Betreiber hat Klon/Umzug bestätigt (`$is_copy`) |
| `review_captain_budget_warning` | action | S | API-Budget eines Scopes erreicht Warnschwelle |
| `review_captain_runner_max_jobs` | filter | B | max. Jobs je Runner-Lauf (Default 50) |
| `review_captain_runner_concurrency` | filter | B | max. gleichzeitig laufende Jobs (Default 2) |
| `review_captain_side_effect_free_job_prefixes` | filter | B | Jobtyp-Präfixe ohne Außenwirkung (laufen trotz Reconcile-Sperre) |
| `review_captain_connector_runtime_v1` | filter | V | Pro meldet die Laufzeit für eigene Connectoren (CON-006, QA F-03); Default `false` = aktivierte Connectoren laufen nicht |
| `review_captain_settings_hints` | filter | S | graue Hinweiszeilen der Einstellungen `{id, tab, title, text, link}`; Pro entfernt Zeilen aktiver Funktionen ([pro-matrix.md](../settings/pro-matrix.md)) |
| `review_captain_settings_hint_link` | filter | S | Link einer Core-Hinweiszeile (Default leer) |
| `review_captain_settings_cross_check` | filter | S | Feldübergreifende Prüfungen eines Tabs (z. B. für Add-on-Felder) |
| `review_captain_public_text` | filter | S | öffentlicher Text (Einstellung oder übersetzter Standard), Kennung `tab.key` |
| `review_captain_verification_notice` | filter | S | Text „So prüfen wir Bewertungen“; `''` blendet aus |
| `review_captain_privacy_policy_url` | filter | S | Datenschutz-Link für `{privacy_link}` im Einwilligungstext |
| `review_captain_notification` | filter | S | Benachrichtigungs-Mail (`to`, `subject`, `body`) je Ereignis, ohne personenbezogenen Kontext |
| `review_captain_notify_recipients` | filter | S | Empfänger der Benachrichtigungen je Ereignis |
| `review_captain_report_reasons` | filter | S | angebotene Meldegründe (nur Werte aus `ReportStore::REASONS`) |
| `review_captain_submission_hold_reasons` | filter | S | Gründe, eine Einreichung zur Moderation zurückzuhalten (Codes, kein Inhalt) |
| `review_captain_blocked_email_domains` | filter | S | gesperrte E-Mail-Domains des Formulars |
| `review_captain_widget_defaults` | filter | S | globale Widget-Vorgaben (`limit`, `layout`, `columns`, `heading_level`, `new_tab`) |
| `review_captain_removed_review_behavior` | filter | S | Umgang mit an der Quelle entfernten Reviews; nur `tombstone` erzwingbar |
| `review_captain_debug_log_line` | filter | S | Diagnose-Logzeile ohne personenbezogene Daten; `''` unterdrückt |
| `review_captain_form_max_items` | filter | B | max. Produkte je Mehrprodukt-Formular (1–50, Einstellung) |
| `review_captain_form_min_seconds` | filter | B | Mindestzeit bis zum Absenden (Spam-Schutz, Einstellung) |
| `review_captain_max_feedback_links` | filter | B | max. Feedback-Links je Kampagne |
| `review_captain_max_tags` | filter | B | max. Schlagwörter je Review |
| `review_captain_photo_max_edge` | filter | B | längste Bildkante beim Speichern (400–4000, Einstellung) |
| `review_captain_retention_audit_days` | filter | B | Aufbewahrung des Änderungsprotokolls in Tagen (Einstellung) |
| `review_captain_schema_min_reviews` | filter | B | Mindestzahl Reviews für strukturierte Daten (Einstellung) |
| `review_captain_trusted_proxy_header` | filter | B | vertrauenswürdiger Proxy-Header für die Client-IP (Einstellung) |

Interne Cron-Hooks ohne Stabilitätszusage: `review_captain_run_jobs` (5-Minuten-Tick), `review_captain_daily_maintenance`.

Event-Payloads (`ReviewCaptain\Events\Events::payload()`): `['schema' => 'review_captain.review.v1', 'event_id' => uuid, 'review_uuid', 'kind', 'platform', 'origin', 'status', 'previous_status', 'verification', 'rating_value' (float\|null, seit Vertrag 1.1.0), 'rating_scale_min', 'rating_scale_max', 'context_type', 'context_id', 'experience_id', 'removal' (`tombstoned`\|`deleted` bei `review_removed`, sonst `null`; seit 1.1.0), 'occurred_at' (ISO 8601 UTC)]` – niemals Reviewtext, E-Mail, Tokens.

### Reservierte Namen (spezifiziert, in Core v1 nicht implementiert)

Aus [`docs/contracts/README.md`](../contracts/README.md) („Ergänzungen gegenüber conventions.md“) übernommen, per [ADR-0007](../adr/0007-oeffentliche-hooks-v1.md) reserviert. Sie sind **kein** Vertrag, solange sie nicht implementiert sind; andere Plugins dürfen diese Namen nicht belegen.

| Art | Name | Spezifiziert in |
|---|---|---|
| action | `review_captain_experience_event_v1` | [experience-event-v1.md](../contracts/experience-event-v1.md) |
| action | `review_captain_request_status_changed_v1`, `review_captain_request_reminder_sent_v1`, `review_captain_request_responded_v1`, `review_captain_recipient_opted_out_v1` | [request-lifecycle.md](../contracts/request-lifecycle.md), [workflow-provider-v1.md](../contracts/workflow-provider-v1.md) |
| Funktion | `review_captain_submit_experience_event_v1()` | experience-event-v1.md |
| Funktion | `review_captain_plan_request_v1()`, `review_captain_cancel_request_v1()`, `review_captain_create_local_review_v1()`, `review_captain_delete_local_review_draft_v1()`, `review_captain_get_review_summary_v1()` (die Logik ist als Container-Service `workflow` = `ReviewCaptain\Workflow\WorkflowService` implementiert; die Funktions-Wrapper bleiben reserviert) | workflow-provider-v1.md |
| Klasse/API | `ReviewCaptain\Contracts\Connector\RecipientResolverInterface` (Connector-Fassung implementiert als `ReviewCaptain\Connectors\Contract\RecipientResolverInterface`), `ReviewCaptain\Api::register_request_service()`, `::workflow_descriptor_v1()` | [recipient-resolver-v1.md](../contracts/recipient-resolver-v1.md), workflow-provider-v1.md |
| Payload-Schema | `review_captain.request.v1`, `review_captain.recipient.v1`, `review_captain.workflow_provider.v1`, `review_captain.review_record.v1` | jeweilige Dokumente |
| Option | `review_captain_requests_cooldown_days`, `review_captain_data_version`, `review_captain_instance_marker` | [invitation-rule.md](../contracts/invitation-rule.md), widget-v1.md, [race-conditions.md](../contracts/race-conditions.md) |
| Pro-Tabellen (Vorschlag) | `review_captain_requests`, `review_captain_request_log`, `review_captain_request_sends`, `review_captain_recipient_guards`, `review_captain_inbound_events`, `review_captain_experiences`, `review_captain_sync_runs`, `review_captain_testimonial_media`, `review_captain_testimonial_rights`, `review_captain_inbound_nonces` (nur TEC-011); Versionsoption `review_captain_pro_db_version` | jeweilige Dokumente |

Bereits implementiert und damit verbindlich (aus derselben Liste): Payload-Schemata `review_captain.review.v1` (`Events`), `review_captain.experience_event.v1` (`Connectors\ExperienceEvent`), `review_captain.settings.v1` (`Admin\ConfigTransfer`); Provider-Vertrag `ReviewCaptain\Providers\Contract\ProviderInterface` (statt `ReviewCaptain\Contracts\Provider\*`); admin-post-Action und Nonce-Aktion des Formulars `review_captain_submit` (statt `review_captain_submit_review`); `meta`-Allowlist laut [review-schema-v1.md](../contracts/review-schema-v1.md).

## Öffentliche Ausgabe

- Shortcode: `[review_captain v="1" ...]` (Attribute siehe [widget-v1.md](../contracts/widget-v1.md)), Formular: `[review_captain_form v="1"]`, Bewertungs-CTA: `[review_captain_cta v="1" profile="…"]`, ab v2 (nur mit `submissions.qa_enabled`) Produkt-Q&A: `[review_captain_qa v="1" context_type="product" context_id="…|current"]` (ohne Schema-Markup).
- Öffentliche admin-post-Aktionen ab v2 (jeweils nur mit eigenem Schalter): `review_captain_report` (Nonce `review_captain_report`), `review_captain_question` (Nonce `review_captain_question`), `review_captain_photo` (GET, nur lesend, Zugriffsprüfung).
- Block: `review-captain/reviews` (dynamisch, serverseitig gerendert), `review-captain/form`.
- REST-Namespace: `review-captain/v1` (Admin-Zwecke, Editor-Vorschau). Keine öffentlichen Schreib-Endpunkte; Einreichung per `admin-post.php` (Action `review_captain_submit`) mit Nonce + Honeypot + Mindestzeit + Rate-Limit.
- Kein Frontend-JavaScript nötig; CSS-Klassenpräfix `rc-`.
- Schema-Markup nur für `platform=local`, `kind=review`, `status=approved`, sichtbar gerendert, nicht selbstbezogen (Organization/LocalBusiness); siehe `docs/seo/`.

## Fehlercodes

Format `rc.<bereich>.<code>`, z. B. `rc.auth.invalid`, `rc.quota.exceeded`, `rc.net.timeout`, `rc.contract.incompatible`, `rc.data.invalid_payload`, `rc.remote.removed`, `rc.temp.unavailable`, `rc.perm.failed`, `rc.source.unavailable`. Liste und Retry-Klasse (`transient`, `rate_limit`, `auth`, `config`, `permanent`, `data`): `ReviewCaptain\Support\ErrorCode`, Doku [error-codes.md](error-codes.md) (nicht implementierte Codes dort als „geplant“).

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
