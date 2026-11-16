# Technische Datenschutz-Checkliste (zu RC-135)

Status: Entwurf, Stand 2026-11-16. Technische Prüfung, **keine Rechtsberatung**. Die rechtliche Bewertung (RC-014) ist getrennt und offen.

## 1. Datenbestand (Core)

| Daten | Ort | Personenbezug | Frist / Löschung |
|---|---|---|---|
| Lokale Reviews: Name, E-Mail, User-ID, Text | `reviews` | ja | Eraser (`erase_mode`), abgelehnt nach `retention_rejected_days` |
| Einwilligung (`consent_publish`, `consent_at`) | `reviews` | ja | mit Review |
| Externe Reviews: Anzeigename, Text | `reviews` (`origin` = `provider_sync` oder `connector`) | ja | Plattformregel, `retention_external_days`, Tombstone |
| Moderationsverlauf (Nutzer-ID der Moderierenden) | `review_events` | ja (intern) | mit Review |
| Audit | `audit_log` | ja (intern) | `retention_logs_days` |
| Jobs (Payload ohne PII) | `jobs` | nein (zu prüfen) | `retention_jobs_days` |
| Rate-Limit-Zähler | Transient, gehashter/gekürzter Absenderwert | pseudonym | ≤ 1 h |
| Import-Batches | `import_batches` | gering (Nutzer-ID, Dateiname) | Vorschlag: mit Batch-Rollback/90 Tage |
| Einstellungen | `review_captain_settings` | P1-Felder | Reset/Uninstall |

## 2. Prüfpunkte

### Export/Eraser
- [ ] `wp_privacy_personal_data_exporters`: Reviews (alle Felder außer internen IDs), Einwilligungsstatus, Audit-Aktionen des Nutzers.
- [ ] `wp_privacy_personal_data_erasers`: `anonymize` (Name → „Anonym“, E-Mail/User-ID NULL) bzw. `delete`; Rückmeldung `items_retained` mit Begründung bei Anonymisierung.
- [ ] Eraser paginiert (große Bestände), idempotent.
- [ ] Externe Plattform-Reviews: Exporter listet sie nur, wenn E-Mail-Zuordnung existiert (normalerweise nicht) – dokumentiert.

### Einwilligung
- [ ] Formular: Einwilligungs-Checkbox nicht vorausgewählt; Text konfigurierbar; `consent_at` in UTC.
- [ ] Ohne Einwilligung (bei `require_consent=true`) keine Speicherung.
- [ ] Schema/Ausgabe nutzt nur `approved`-Reviews.

### Opt-out (Pro)
- [ ] Abmeldelink in jeder Einladung; Abmeldung wirkt siteweit sofort; offene Einladungen werden storniert.
- [ ] Sperrliste speichert nur minimalen Hash (Vorschlag), nicht die Adresse im Klartext.

### Retention
- [ ] Aufbewahrungsjob läuft täglich, zeitbudgetiert, protokolliert Anzahl (ohne Inhalte).
- [ ] Tombstone entfernt Text, Titel, Autor, Avatar-URL; behält `dedupe_key`, `removed_at`.
- [ ] Fristen greifen auch, wenn Pro deaktiviert ist (Core-Job).

### Löschereignis
- [ ] `review_captain_review_removed_v1` ohne Text/E-Mail; Empfänger (Pro/Connectoren) löschen eigene Kopien.
- [ ] Deinstallation mit `delete_data_on_uninstall=true` entfernt Tabellen, Optionen, Transients, Cron-Events, Capabilities (je Site).

### Externe Dienste
- [ ] Core-Frontend lädt keine externen Ressourcen (Fonts, Skripte, Bilder) ohne Aktivierung.
- [ ] Avatare: Hinweis auf Gravatar über `get_avatar()`.
- [ ] Embeds (TrustBox/Trustbadge) nur nach Consent ([../providers/widget-fallbacks.md](../providers/widget-fallbacks.md)).
- [ ] Datenschutzleitfaden-Text (`wp_add_privacy_policy_content`) beschreibt Formular, Speicherfristen, aktivierte Embeds, Pro-Anbieter.
- [ ] Pro-Lizenzserver erhält keine Review-, Kunden- oder Plattformdaten.
- [ ] readme.txt-Abschnitt „External services“ (WordPress.org-Pflicht) vollständig.

### Logs und Secrets
- [ ] Keine Secrets, Tokens, E-Mails, Reviewtexte in `last_error_message`, Debug-Log, Audit, Diagnosebericht.
- [ ] Debug-Log schaltet automatisch ab.
- [ ] `admin_email`-Benachrichtigung ohne Reviewtext.

### Multisite
- [ ] Daten jeder Site in eigenen Tabellen; Export/Eraser arbeiten site-lokal.

## Abnahme

Diese Checkliste ist für RC-135 erfüllt, wenn alle Punkte geprüft (Test oder dokumentierte manuelle Prüfung) sind und Export/Eraser, Einwilligungsstatus, Opt-out, Retention, Löschereignis und externe Services dokumentiert sind. Die rechtliche Freigabe (RC-014) bleibt eine getrennte Voraussetzung.
