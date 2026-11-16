# ADR-0003: Eigener Job-Runner mit Lease, Action Scheduler optional

- **Status:** Angenommen
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Core-Maintainer Review Captain
- **Betrifft:** TEC-001 bis TEC-007, TEC-015; `docs/architecture/jobs.md`
- **Ersetzt:** Konzeptaussage 7.5 „Action Scheduler, wenn WooCommerce installiert ist, sonst eigener Vertrag“

## Kontext

Review Captain braucht Hintergrundjobs (Sync, Retention, CSV-Import, Reconciliation, Pro: Einladungen) mit atomarem Claim, Lease/Recovery, DB-Idempotenz, fehlerklassenabhängigem Retry und Dead-Letter. Core darf WooCommerce nicht voraussetzen. Konzept 7.5 sah zwei Ausführungspfade vor (Action Scheduler mit WooCommerce, sonst eigene Queue). Zwei Datenquellen für denselben Job hätten doppelte Semantik bei Status, Idempotenz, Retry-After, Diagnose und Backup/Restore bedeutet.

## Betrachtete Optionen

1. **Action Scheduler als Pflicht-Abhängigkeit** (gebündelt) – zusätzliche ~30 Tabellen-/Codeabhängigkeit, Versionskonflikte mit gebündelten Kopien anderer Plugins, keine Lease-Semantik pro Job im gewünschten Sinn, Unique-Keys nur eingeschränkt.
2. **Zwei Backends** (AS oder eigene Queue) – doppelte Implementierung und Tests, uneinheitliche Diagnose.
3. **Eine eigene Queue (`jobs`-Tabelle) als einzige Wahrheit**; Ausführung angestoßen durch WP-Cron, System-Cron/WP-CLI oder – falls vorhanden – Action Scheduler als reiner Taktgeber.

## Entscheidung

Option 3:

- Die Tabelle `{$wpdb->prefix}review_captain_jobs` ist die einzige Quelle für Jobzustand, Idempotenz, Retry und Lease.
- Ausführung über den internen Cron-Hook `review_captain_run_jobs` (WP-Cron alle 5 min), `wp review-captain jobs run` (empfohlen per System-Cron) und optional Action Scheduler: Ist `as_schedule_recurring_action()` verfügbar, wird zusätzlich eine wiederkehrende AS-Aktion `review_captain_run_jobs` (Gruppe `review-captain`) registriert, die denselben Runner aufruft. AS speichert **keine** Review-Captain-Jobs.
- Claim per atomarem `UPDATE … WHERE id=%d AND (status='queued' … OR (status='running' AND lease_expires_at < now))` mit `rows_affected === 1`, Lease-Default 300 s, Zeitbudget 20 s pro Lauf.
- Details: `docs/architecture/jobs.md`.

## Konsequenzen

- Positiv: ein Verhalten auf allen Hosts, ohne WooCommerce; Backup/Restore und Reconciliation betreffen nur eine Tabelle; Diagnose einheitlich.
- Negativ: eigener Code für Runner, Locking, Cleanup; Tests für Parallelität (TEC-005) liegen bei uns.
- Pro und Connectoren nutzen dieselbe Queue (`review_captain_process_job_v1` für fremde Jobtypen).

## Abnahme

- [ ] Zwei parallel gestartete Runner (`wp review-captain jobs run` ×2) führen keinen Job doppelt aus (Integrationstest mit 1.000 Jobs, Zähler je Job = 1).
- [ ] Ein per `kill -9` abgebrochener Job wird nach Ablauf der Lease erneut geclaimt und als neuer Versuch gezählt.
- [ ] Mit aktivem WooCommerce/Action Scheduler entstehen keine AS-Einträge außer der Taktgeber-Aktion.
- [ ] Ohne WooCommerce laufen alle Core-Jobs über WP-Cron und CLI.

## Referenzen

- `konzept.md` 7.5, 10; `TODO.md` M1.5
- `docs/architecture/jobs.md`
