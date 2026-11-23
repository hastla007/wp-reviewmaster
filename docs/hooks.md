# Hook-Referenz (generiert)

> Diese Datei wird von `bin/list-hooks.php` erzeugt – nicht von Hand bearbeiten. Aktualisieren: `php bin/list-hooks.php --write`; prüfen (CI): `php bin/list-hooks.php --check`.

Erfasst sind alle Aufrufe von `do_action()`/`apply_filters()` (inkl. `_ref_array`/`_deprecated`) mit Hook-Namen, die mit `review_captain_` beginnen, in `src/`, `templates/`, `blocks/`, `review-captain.php` und `uninstall.php`. Als Klassenkonstante angegebene Namen sind aufgelöst. Parameter stammen aus dem Docblock direkt über dem Aufruf, sonst aus den Argumenten. Welche Hooks öffentliche, versionierte Verträge sind, regelt [ADR-0007](adr/0007-oeffentliche-hooks-v1.md) bzw. die Hook-Tabelle in [conventions.md](architecture/conventions.md); alle übrigen sind Erweiterungspunkte ohne Stabilitätszusage ([ADR-0002](adr/0002-hook-praefix-review-captain.md)). Interne Cron-Hooks (`review_captain_run_jobs`, `review_captain_daily_maintenance`) werden von WP-Cron/Action Scheduler ausgelöst und erscheinen hier daher nicht.

Anzahl: 54 Aufrufstellen, 52 Hooks.

| Name | Typ | Parameter | Datei:Zeile |
|---|---|---|---|
| `review_captain_audit_allowed_keys` | filter | `self::ALLOWED_KEYS` | `src/Audit/AuditLog.php:87` |
| `review_captain_blocked_email_domains` | filter | `list<string> $domains` – Domains | `src/Frontend/FormHandler.php:431` |
| `review_captain_budget_warning` | action | `string $scope` – Scope<br>`int $used` – Used units<br>`int $limit` – Limit | `src/Jobs/Budget.php:89` |
| `review_captain_connector_runtime_v1` | filter | `bool $enabled` – Default false<br>`string $key` – Connector key | `src/Connectors/Registry.php:223` |
| `review_captain_connectors_v1` | action | `Registry $registry` – Registry | `src/Connectors/Registry.php:82` |
| `review_captain_debug_log_line` | filter | `string $line` – Line without personal data | `src/Jobs/Runner.php:424` |
| `review_captain_diagnostics_sections` | filter | `array()` | `src/Admin/DiagnosticsPage.php:239` |
| `review_captain_embed_hosts` | filter | `list<string> $hosts` – Hosts | `src/Security/EmbedPolicy.php:46` |
| `review_captain_form_max_items` | filter | `int $max` – Maximum | `src/Frontend/FormHandler.php:468` |
| `review_captain_form_min_seconds` | filter | `int $value` – Seconds from the setting | `src/Frontend/FormHandler.php:481` |
| `review_captain_installed` | action | `int $from` – Previous schema version<br>`int $to` – New schema version | `src/Install/Installer.php:115` |
| `review_captain_instance_confirmed` | action | `bool $is_copy` – Copy | `src/Maintenance/Reconciler.php:230` |
| `review_captain_loaded` | action | `Plugin $plugin` – Plugin instance | `src/Plugin.php:132` |
| `review_captain_max_feedback_links` | filter | `int $max` – Maximum | `src/Feedback/CampaignStore.php:53` |
| `review_captain_max_tags` | filter | `int $max` – Maximum | `src/Moderation/TagStore.php:39` |
| `review_captain_moderation_url` | filter | `string $url` – URL<br>`Review $review` – Review | `src/Frontend/FormHandler.php:762` |
| `review_captain_moderation_url` | filter | `string $url` – URL (review list filtered to open reports)<br>`\ReviewCaptain\Domain\Review $review` – Reported review | `src/Moderation/ReportHandler.php:114` |
| `review_captain_notification` | filter | `array{to: list<string>, subject: string, body: string} $mail` – Mail<br>`string $event` – review\|report\|question<br>`array<string, mixed> $context` – Context (no personal data) | `src/Moderation/Notifier.php:101` |
| `review_captain_notify_recipients` | filter | `list<string> $list` – Addresses<br>`string $event` – review\|report\|question | `src/Moderation/Notifier.php:68` |
| `review_captain_photo_max_edge` | filter | `int $edge` – Edge from the setting | `src/Media/PhotoStore.php:63` |
| `review_captain_privacy_personal_meta_keys` | filter | `list<string> $keys` – Keys | `src/Privacy/PersonalData.php:244` |
| `review_captain_privacy_policy_url` | filter | `string $url` – URL ('' = no link) | `src/Frontend/PublicTexts.php:124` |
| `review_captain_pro_settings_writable` | filter | `bool $writable` – Default false | `src/Admin/SettingsPage.php:91` |
| `review_captain_process_job_v1` | filter | `array\|null $result` – null = not handled<br>`string $type` – Job type<br>`array $payload` – Payload | `src/Jobs/Runner.php:326` |
| `review_captain_providers_v1` | action | `ProviderRegistry $registry` – Registry | `src/Providers/ProviderRegistry.php:65` |
| `review_captain_public_text` | filter | `string $text` – Text (setting or translated default)<br>`string $id` – "tab.key", e.g. "submissions.consent_text" or "display.label_unverified" | `src/Frontend/PublicTexts.php:68` |
| `review_captain_reconcile_report` | filter | `array $report` – Report<br>`bool $fix` – Whether fixes may be applied<br>`string $mode` – Mode | `src/Maintenance/Reconciler.php:172` |
| `review_captain_removed_review_behavior` | filter | `string $value` – 'hide'\|'tombstone' | `src/Providers/SyncService.php:55` |
| `review_captain_report_reasons` | filter | `list<string> $reasons` – Reason codes | `src/Moderation/ReportStore.php:52` |
| `review_captain_request_planner_v1` | filter | `RequestPlannerInterface\|null $planner` – Planner (null = none) | `src/Workflow/WorkflowService.php:379` |
| `review_captain_request_planner_v1` | filter | `null` | `src/Workflow/WorkflowService.php:392` |
| `review_captain_retention_audit_days` | filter | `int $days` – Days (setting privacy.retention_audit_days, default 365) | `src/Maintenance/Retention.php:63` |
| `review_captain_review_created_v1` | action | `self::payload( $review )` | `src/Events/Events.php:61` |
| `review_captain_review_removed_v1` | action | `self::payload( $review, null, 'tombstoned' === $removal ? 'tombstoned' : 'deleted' )` | `src/Events/Events.php:81` |
| `review_captain_review_status_changed_v1` | action | `self::payload( $review, $previous )` | `src/Events/Events.php:71` |
| `review_captain_runner_concurrency` | filter | `self::CONCURRENCY` | `src/Jobs/Runner.php:194` |
| `review_captain_runner_max_jobs` | filter | `self::MAX_JOBS` | `src/Jobs/Runner.php:193` |
| `review_captain_schema_eligible` | filter | `bool $eligible` – Always true here<br>`Review $r` – Review<br>`array<string, mixed> $ctx` – Context | `src/Seo/SchemaEligibility.php:134` |
| `review_captain_schema_min_reviews` | filter | `int $min` – Minimum from the setting | `src/Seo/SeoModule.php:143` |
| `review_captain_schema_post_item_type` | filter | `$type`<br>`$r` | `src/Seo/SchemaBuilder.php:126` |
| `review_captain_settings_cross_check` | filter | `list<array{fields: list<string>, message: string}> $out` – Failed checks<br>`string $tab` – Tab<br>`array<string, mixed> $values` – Tab values | `src/Settings/SettingsSchema.php:609` |
| `review_captain_settings_hint_link` | filter | `string $link` – Default ''<br>`string $id` – Hint id | `src/Admin/SettingsHints.php:70` |
| `review_captain_settings_hints` | filter | `list<array{id: string, tab: string, title: string, text: string, link: string}> $hints` – Hint lines | `src/Admin/SettingsHints.php:94` |
| `review_captain_settings_schema` | filter | `array $core` – Schema | `src/Settings/SettingsSchema.php:336` |
| `review_captain_setup_checklist_detected` | filter | `array<string, bool> $detected` – Detected steps | `src/Admin/SetupChecklist.php:112` |
| `review_captain_show_pro_info` | filter | `bool $show` – Default: setting general.show_pro_hints (on); the Pro add-on returns false | `src/Admin/DashboardPage.php:173` |
| `review_captain_side_effect_free_job_prefixes` | filter | `self::SIDE_EFFECT_FREE_PREFIXES` | `src/Jobs/Runner.php:235` |
| `review_captain_submission_hold_reasons` | filter | `list<string> $reasons` – Reasons found by the settings<br>`Review $review` – Review (not yet stored) | `src/Frontend/FormHandler.php:410` |
| `review_captain_sync_initial_status` | filter | `ReviewStatus $status` – Status (pending by default) | `src/Providers/SyncService.php:151` |
| `review_captain_template` | filter | `string $file` – Absolute path<br>`string $name` – Template name | `src/Frontend/TemplateLoader.php:45` |
| `review_captain_trusted_proxy_header` | filter | `string $header` – Header from the setting | `src/Security/RateLimiter.php:45` |
| `review_captain_verification_notice` | filter | `string $text` – Plain text (paragraphs separated by blank lines)<br>`string $context` – widget\|form<br>`list<string> $levels` – Levels occurring on this site (Verification values, "testimonial") | `src/Frontend/Renderer.php:225` |
| `review_captain_widget_defaults` | filter | `array $values` – limit, layout, columns, heading_level, new_tab | `src/Widgets/WidgetDefinition.php:271` |
| `review_captain_widgets_v1` | action | `WidgetRegistry $registry` – Registry | `src/Widgets/WidgetRegistry.php:92` |
