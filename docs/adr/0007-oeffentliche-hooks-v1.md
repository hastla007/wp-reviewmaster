# ADR-0007: Öffentliche Hooks von Core v1 und ihre Stabilitätsstufen

- **Status:** Angenommen
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Core-Maintainer Review Captain
- **Betrifft:** RC-020, RC-118, CON-002, CON-009, TEC-001, TEC-015, SET-003, SEO-015; `conventions.md` (Hooks), [`docs/hooks.md`](../hooks.md), [`docs/contracts/README.md`](../contracts/README.md)
- **Ersetzt:** – (ergänzt [ADR-0002](0002-hook-praefix-review-captain.md))

## Kontext

Die Hook-Tabelle in `conventions.md` nannte zehn Hooks. Die parallel entstandene Implementierung (`src/`) löst inzwischen 28 Hooks mit dem Präfix `review_captain_` aus (Stand 2026-11-16, ermittelt mit `bin/list-hooks.php`, Referenz [`docs/hooks.md`](../hooks.md)). Pro, Connectoren, Themes und Hosting-Integrationen nutzen bereits einen Teil davon. ADR-0002 legt fest, dass Hooks erst Vertrag sind, wenn sie in der Hook-Tabelle von `conventions.md` stehen; ohne Entscheidung bliebe offen, welche der zusätzlichen Hooks stabil sind.

Zusätzlich führt `docs/contracts/README.md` Namen (Hooks, Funktionen, Payload-Schemata, Optionen), die „per ADR nachzutragen“ sind, aber in Core v1 nicht implementiert wurden.

## Betrachtete Optionen

1. **Nur die zehn bisherigen Hooks sind öffentlich**, alle übrigen intern. – Widerspricht der tatsächlichen Nutzung (Pro nutzt z. B. `review_captain_pro_settings_writable`, `review_captain_reconcile_report`; Themes `review_captain_template`).
2. **Alle Hooks mit Präfix sind öffentlich mit derselben Zusage.** – Zu starr für Betriebs-Stellschrauben (Runner-Grenzen), deren Defaults sich ändern dürfen.
3. **Alle Hooks sind öffentlich, aber in drei Stabilitätsstufen.** – Klare Zusage je Hook, Defaults bleiben änderbar.

## Entscheidung

Option 3. Alle 28 von Core v1 ausgelösten Hooks mit Präfix `review_captain_` sind öffentliche API. Es gelten drei Stufen:

- **Stufe V (versionierter Vertrag, `_v1`):** Name, Parameter und Payload-Form ändern sich nur mit neuer Major-Version (`_v2`, parallel mindestens für die Deprecation-Frist). *Ergänzung 23.11.2026 (RC-4):* Zusätzliche, optionale Payload-Felder gelten nicht als Änderung der Form. Sie heben nur die MINOR-Version von `REVIEW_CAPTAIN_CONTRACT_VERSION`; Empfänger müssen unbekannte Schlüssel ignorieren. Entfernen, Umbenennen oder ein anderer Typ eines Felds bleiben Major-Änderungen.
- **Stufe S (stabiler Erweiterungspunkt):** Name und Parameter bleiben innerhalb von Core-Major 1 stabil; neue optionale Parameter werden nur angehängt. Entfernen/Umbenennen nur per ADR mit `do_action_deprecated()`/`apply_filters_deprecated()`-Übergang (mindestens 2 Minor-Releases).
- **Stufe B (Betriebs-Stellschraube):** Name und Parameter stabil wie Stufe S, aber **Default-Werte** und deren Wirkung dürfen sich in Minor-Releases ändern.

Interne Cron-Hooks (`review_captain_run_jobs`, `review_captain_daily_maintenance`) sind **keine** öffentliche API (ADR-0002) und werden von Dritten nicht ausgelöst.

### Liste der öffentlichen Hooks (Core v1)

| Hook | Art | Stufe | Datei |
|---|---|---|---|
| `review_captain_loaded` | action | S | `src/Plugin.php` |
| `review_captain_installed` | action | S | `src/Install/Installer.php` |
| `review_captain_connectors_v1` | action (Registry) | V | `src/Connectors/Registry.php` |
| `review_captain_providers_v1` | action (Registry) | V | `src/Providers/ProviderRegistry.php` |
| `review_captain_widgets_v1` | action (Registry) | V | `src/Widgets/WidgetRegistry.php` |
| `review_captain_process_job_v1` | filter | V | `src/Jobs/Runner.php` |
| `review_captain_review_created_v1` | action | V | `src/Events/Events.php` |
| `review_captain_review_status_changed_v1` | action | V | `src/Events/Events.php` |
| `review_captain_review_removed_v1` | action | V | `src/Events/Events.php` |
| `review_captain_settings_schema` | filter | S | `src/Settings/SettingsSchema.php` |
| `review_captain_pro_settings_writable` | filter | S | `src/Admin/SettingsPage.php` |
| `review_captain_pro_field_writable` | filter | S | `src/Admin/SettingsPage.php` |
| `review_captain_show_pro_info` | filter | S | `src/Admin/DashboardPage.php` |
| `review_captain_pro_info_items` | filter | S | `src/Admin/DashboardPage.php` (P6-7, 24.11.2026) |
| `review_captain_request_token_v1` | filter | V | `src/Frontend/RequestResponse.php` (P2-10, Vertrag 1.2.0) |
| `review_captain_request_responded_v1` | action | V | `src/Frontend/RequestResponse.php` (P2-10, Vertrag 1.2.0) |
| `review_captain_setup_checklist_detected` | filter | S | `src/Admin/SetupChecklist.php` |
| `review_captain_purchase_proof` | filter | S | `src/Frontend/PurchaseGate.php` |
| `review_captain_diagnostics_sections` | filter | S | `src/Admin/DiagnosticsPage.php` |
| `review_captain_schema_eligible` | filter (nur verschärfen) | S | `src/Seo/SchemaEligibility.php` |
| `review_captain_schema_post_item_type` | filter | S | `src/Seo/SchemaBuilder.php` |
| `review_captain_template` | filter | S | `src/Frontend/TemplateLoader.php` |
| `review_captain_moderation_url` | filter | S | `src/Frontend/FormHandler.php` |
| `review_captain_embed_hosts` | filter | S | `src/Security/EmbedPolicy.php` |
| `review_captain_audit_allowed_keys` | filter | S | `src/Audit/AuditLog.php` |
| `review_captain_privacy_personal_meta_keys` | filter | S | `src/Privacy/PersonalData.php` |
| `review_captain_sync_initial_status` | filter | S | `src/Providers/SyncService.php` |
| `review_captain_reconcile_report` | filter | S | `src/Maintenance/Reconciler.php` |
| `review_captain_instance_confirmed` | action | S | `src/Maintenance/Reconciler.php` |
| `review_captain_budget_warning` | action | S | `src/Jobs/Budget.php` |
| `review_captain_runner_max_jobs` | filter | B | `src/Jobs/Runner.php` |
| `review_captain_runner_concurrency` | filter | B | `src/Jobs/Runner.php` |
| `review_captain_side_effect_free_job_prefixes` | filter | B | `src/Jobs/Runner.php` |

Parameter und Zeilennummern: [`docs/hooks.md`](../hooks.md) (generiert). Die Hook-Tabelle in `conventions.md` übernimmt diese Liste.

### Spezifizierte, aber nicht implementierte Namen

Die in `docs/contracts/README.md` als „per ADR nachzutragen“ geführten Namen werden als **reserviert** in `conventions.md` aufgenommen, sind aber **kein** Vertrag von Core v1, solange sie nicht implementiert sind (u. a. `review_captain_experience_event_v1`, die `review_captain_request_*_v1`-Hooks, die Funktionen aus `workflow-provider-v1.md`, die Pro-Tabellen). Ihre Aufnahme als Vertrag erfolgt mit der Implementierung (eigener PR, ggf. eigenes ADR für Payload).

## Konsequenzen

- Positiv: Klarheit für Pro, Connector-Entwickler und Themes; Änderungen an Hooks sind reviewpflichtig.
- Negativ: 18 zusätzliche Hooks binden die Core-Entwicklung; Umbenennungen brauchen Deprecation-Übergänge.
- Migration/Kompatibilität: keine Codeänderung; bestehende Hooks werden nur klassifiziert.
- Folgearbeiten: `php bin/list-hooks.php --check` in CI aufnehmen; neue Hooks nur mit Eintrag in `conventions.md` und Aktualisierung von `docs/hooks.md`.
- *Ergänzung 23.11.2026 (SET-RC-P6):* 23 mit den Einstellungen (SET-RC-*) und CON-006 hinzugekommene Hooks waren ohne Stufe geblieben, darunter zwei, die Review Captain Pro nutzt (`review_captain_settings_hints`, `review_captain_connector_runtime_v1`). Sie sind jetzt in der Hook-Tabelle von `conventions.md` eingestuft (1 × V, 13 × S, 9 × B); maßgeblich für die Stufe ist ab jetzt allein diese Tabelle. `tests/Unit/Support/HookTiersTest.php` prüft, dass jeder Hook aus `docs/hooks.md` dort genau einmal mit Stufe V, S oder B steht.

## Abnahme

- [x] Jeder Hook aus `php bin/list-hooks.php` steht genau einmal in der Liste oben und in der Hook-Tabelle von `conventions.md` (Stand 2026-11-16: 28 Hooks).
- [x] `docs/hooks.md` ist mit `bin/list-hooks.php --write` erzeugt.
- [x] `php bin/list-hooks.php --check` läuft in CI (`.github/workflows/ci.yml`, Schritt „Hook reference up to date“).
- [x] Betroffene Dokumente (`conventions.md`, `docs/adr/README.md`, `docs/contracts/README.md`) im selben Änderungssatz aktualisiert.

## Referenzen

- [ADR-0002](0002-hook-praefix-review-captain.md) (Präfix, interne Hooks)
- [`docs/architecture/conventions.md`](../architecture/conventions.md) Abschnitt Hooks
- [`docs/hooks.md`](../hooks.md)
