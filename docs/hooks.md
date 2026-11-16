# Hook-Referenz (generiert)

> Diese Datei wird von `bin/list-hooks.php` erzeugt – nicht von Hand bearbeiten. Aktualisieren: `php bin/list-hooks.php --write`; prüfen (CI): `php bin/list-hooks.php --check`.

Erfasst sind alle Aufrufe von `do_action()`/`apply_filters()` (inkl. `_ref_array`/`_deprecated`) mit Hook-Namen, die mit `review_captain_` beginnen, in `src/`, `templates/`, `blocks/`, `review-captain.php` und `uninstall.php`. Als Klassenkonstante angegebene Namen sind aufgelöst. Parameter stammen aus dem Docblock direkt über dem Aufruf, sonst aus den Argumenten. Welche Hooks öffentliche, versionierte Verträge sind, regelt [ADR-0007](adr/0007-oeffentliche-hooks-v1.md) bzw. die Hook-Tabelle in [conventions.md](architecture/conventions.md); alle übrigen sind Erweiterungspunkte ohne Stabilitätszusage ([ADR-0002](adr/0002-hook-praefix-review-captain.md)). Interne Cron-Hooks (`review_captain_run_jobs`, `review_captain_daily_maintenance`) werden von WP-Cron/Action Scheduler ausgelöst und erscheinen hier daher nicht.

Anzahl: 31 Aufrufstellen, 30 Hooks.

| Name | Typ | Parameter | Datei:Zeile |
|---|---|---|---|
| `review_captain_audit_allowed_keys` | filter | `self::ALLOWED_KEYS` | `src/Audit/AuditLog.php:81` |
| `review_captain_budget_warning` | action | `string $scope` – Scope<br>`int $used` – Used units<br>`int $limit` – Limit | `src/Jobs/Budget.php:89` |
| `review_captain_connector_runtime_v1` | filter | `bool $enabled` – Default false<br>`string $key` – Connector key | `src/Connectors/Registry.php:223` |
| `review_captain_connectors_v1` | action | `Registry $registry` – Registry | `src/Connectors/Registry.php:82` |
| `review_captain_diagnostics_sections` | filter | `array()` | `src/Admin/DiagnosticsPage.php:205` |
| `review_captain_embed_hosts` | filter | `list<string> $hosts` – Hosts | `src/Security/EmbedPolicy.php:46` |
| `review_captain_installed` | action | `int $from` – Previous schema version<br>`int $to` – New schema version | `src/Install/Installer.php:115` |
| `review_captain_instance_confirmed` | action | `bool $is_copy` – Copy | `src/Maintenance/Reconciler.php:230` |
| `review_captain_loaded` | action | `Plugin $plugin` – Plugin instance | `src/Plugin.php:132` |
| `review_captain_moderation_url` | filter | `string $url` – URL<br>`Review $review` – Review | `src/Frontend/FormHandler.php:616` |
| `review_captain_privacy_personal_meta_keys` | filter | `list<string> $keys` – Keys | `src/Privacy/PersonalData.php:244` |
| `review_captain_pro_settings_writable` | filter | `bool $writable` – Default false | `src/Admin/SettingsPage.php:78` |
| `review_captain_process_job_v1` | filter | `array\|null $result` – null = not handled<br>`string $type` – Job type<br>`array $payload` – Payload | `src/Jobs/Runner.php:326` |
| `review_captain_providers_v1` | action | `ProviderRegistry $registry` – Registry | `src/Providers/ProviderRegistry.php:65` |
| `review_captain_reconcile_report` | filter | `array $report` – Report<br>`bool $fix` – Whether fixes may be applied<br>`string $mode` – Mode | `src/Maintenance/Reconciler.php:172` |
| `review_captain_request_planner_v1` | filter | `RequestPlannerInterface\|null $planner` – Planner (null = none) | `src/Workflow/WorkflowService.php:379` |
| `review_captain_request_planner_v1` | filter | `null` | `src/Workflow/WorkflowService.php:392` |
| `review_captain_review_created_v1` | action | `self::payload( $review )` | `src/Events/Events.php:54` |
| `review_captain_review_removed_v1` | action | `self::payload( $review )` | `src/Events/Events.php:73` |
| `review_captain_review_status_changed_v1` | action | `self::payload( $review, $previous )` | `src/Events/Events.php:64` |
| `review_captain_runner_concurrency` | filter | `self::CONCURRENCY` | `src/Jobs/Runner.php:194` |
| `review_captain_runner_max_jobs` | filter | `self::MAX_JOBS` | `src/Jobs/Runner.php:193` |
| `review_captain_schema_eligible` | filter | `bool $eligible` – Always true here<br>`Review $r` – Review<br>`array<string, mixed> $ctx` – Context | `src/Seo/SchemaEligibility.php:117` |
| `review_captain_schema_post_item_type` | filter | `$type`<br>`$r` | `src/Seo/SchemaBuilder.php:126` |
| `review_captain_settings_schema` | filter | `array $core` – Schema | `src/Settings/SettingsSchema.php:168` |
| `review_captain_setup_checklist_detected` | filter | `array<string, bool> $detected` – Detected steps | `src/Admin/SetupChecklist.php:112` |
| `review_captain_show_pro_info` | filter | `bool $show` – Default true; the Pro add-on returns false | `src/Admin/DashboardPage.php:171` |
| `review_captain_side_effect_free_job_prefixes` | filter | `self::SIDE_EFFECT_FREE_PREFIXES` | `src/Jobs/Runner.php:235` |
| `review_captain_sync_initial_status` | filter | `ReviewStatus $status` – Status (pending by default) | `src/Providers/SyncService.php:124` |
| `review_captain_template` | filter | `string $file` – Absolute path<br>`string $name` – Template name | `src/Frontend/TemplateLoader.php:45` |
| `review_captain_widgets_v1` | action | `WidgetRegistry $registry` – Registry | `src/Widgets/WidgetRegistry.php:92` |
