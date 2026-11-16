# Upgrade-/Restore-Reconciliation (TEC-015)

Status: Spezifikation, Stand 2026-11-16. Grundlage: `konzept.md` 7.9 („Backup/Restore: … Nach Restore läuft zuerst ein Reconciliation-Job, der doppelte Einladungen verhindert, OAuth-Verbindungen validiert und abgelaufene Plattform-Caches löscht“), [jobs.md](jobs.md), [secrets.md](secrets.md), [retention-matrix.md](retention-matrix.md).

## 1. Auslöser

| Anlass | Erkennung | Modus |
|---|---|---|
| Schema-Upgrade | Migrator hat `review_captain_db_version` erhöht | `upgrade` |
| Plugin-Reaktivierung | Aktivierungs-Hook, Multisite je Site | `reactivate` |
| Backup-Restore (vermutet) | (a) `review_captain_instance.last_seen_at` liegt > 24 h zurück, obwohl der Runner laut Konfiguration hätte laufen müssen; (b) jüngstes `jobs.updated_at` / `review_captain_runner_heartbeat` deutlich älter als die Wanduhr bei gleichzeitig aktivem Plugin; (c) Schlüssel-Check (`review_captain_key_check`) passt nicht | `restore` |
| Klon/Umzug (Staging, neue Domain) | `sha256( home_url() )` ≠ `review_captain_instance.home_url_hash` | `clone` – **mit Sperre** |
| Manuell | `wp review-captain reconcile` oder Diagnose-Button (`review_captain_manage_settings`) | wählbar |

`review_captain_instance.last_seen_at` wird vom Runner höchstens alle 5 min aktualisiert.

## 2. Sperre für Außenwirkung

Bei `restore` und `clone` wird sofort `review_captain_reconcile_required` gesetzt. Solange die Sperre aktiv ist:

- Der Runner claimt **nur** Jobtypen ohne Außenwirkung (`retention.*`, `maintenance.*`, `reconcile`).
- Keine API-Aufrufe an Provider, keine E-Mails, keine Pro-Einladungen, keine Connector-Aktionen mit Außenwirkung (Handler deklarieren `has_side_effects`; unbekannte Typen gelten als „mit Außenwirkung“).
- Admin-Hinweis für `review_captain_manage_settings`.

Bei `restore` hebt ein erfolgreicher Reconciliation-Lauf die Sperre auf. Bei `clone` ist zusätzlich eine **ausdrückliche Bestätigung** nötig („Dies ist die Produktivumgebung / eine Kopie – Verbindungen hier deaktivieren“, Nonce `review_captain_confirm_instance`). Wählt der Betreiber „Kopie“, werden alle API-Profile `paused` und Pro-Versand deaktiviert; danach wird `home_url_hash` aktualisiert.

## 3. Prüfschritte

Der Job `reconcile` (bzw. CLI) führt die Schritte in dieser Reihenfolge aus, jeder Schritt idempotent, resumable über `payload.step`/`payload.cursor`, Zeitbudget wie alle Jobs:

| # | Schritt | Aktion |
|---|---|---|
| 1 | Schema | `db_version` = Codeversion? Sonst abbrechen und Migration verlangen. `dedupe_key`-Modus prüfen. |
| 2 | Leases | Alle `running`-Jobs: Lease stammt aus der Zeit vor dem Anlass → `queued` (`available_at` = jetzt), `lease_owner`/`lease_expires_at` = NULL, `attempts` unverändert. |
| 3 | Überfällige Jobs mit Außenwirkung | `queued`-Jobs mit Außenwirkung und `available_at` älter als 24 h → `paused` mit Meldung „Nach Wiederherstellung erneut prüfen“; Pro-Handler validiert vor Freigabe Eligibility (Erlebnis noch gültig, kein Refund/Opt-out, keine bereits versendete Einladung laut Pro-Unique-Key und Versandnachweis). Einmal-Jobs, die laut Versandnachweis bereits ausgeführt wurden → `failed`. |
| 4 | Credentials | Jedes `api`-Profil: entschlüsselbar? (sonst `auth_required`). Danach (erst nach Aufhebung der Sperre bzw. im Modus `upgrade`/`reactivate`) Testaufruf/Token-Refresh; widerrufen/abgelaufen → `auth_required`, nie als gültig anzeigen. |
| 5 | Sync-Cursor | Cursor älter als der Anlass gilt als unsicher: auf „Delta ab `last_success_at − 7 Tage`“ zurücksetzen. Duplikate sind durch `dedupe_key` ausgeschlossen; an der Quelle gelöschte Reviews werden beim Delta-Lauf getombstoned. |
| 6 | Snapshots | Anzeigegrenzen sofort anwenden ([retention-matrix.md](retention-matrix.md)); z. B. alle Trustpilot-Snapshots mit `fetched_at` > 24 h ausblenden. Danach regulärer Purge. Cache-Generation erhöhen ([caching.md](caching.md)). |
| 7 | Privacy-Nachzug | Im `audit_log` vermerkte Löschungen/Anonymisierungen (`privacy.erased`, Review-UUID) erneut anwenden, falls die Daten durch Restore zurückgekehrt sind. |
| 8 | Externe IDs / Integrität | Zeilen mit `external_id`, aber `dedupe_key` NULL (Fallback-Modus) → neu berechnen; Kollisionen → ältere Zeile behalten, neuere als Tombstone markieren, Bericht. Reviews mit `profile_id` auf nicht existierendes Profil → `profile_id` behalten, Diagnosewarnung. `import_batches` im Status `importing` ohne aktiven Job → `failed`. |
| 9 | Abschluss | `review_captain_instance` aktualisieren, Sperre lösen (außer `clone` ohne Bestätigung), Audit `reconcile.completed` mit Zählern je Schritt. |

## 4. CLI

```
wp review-captain reconcile [--mode=upgrade|reactivate|restore|clone|manual] [--dry-run] [--step=<n>] [--network] [--format=table|json]
```

- `--dry-run` meldet nur, was geändert würde (Zähler je Schritt), ohne Schreibzugriffe.
- `--network` iteriert Sites (je Site eigener Lauf und eigene Sperre).
- Exit-Code ≠ 0, wenn ein Schritt fehlschlägt; Fehler mit `rc.*`-Code.

## 5. Garantien

- Eine Wiederherstellung sendet keine alten Einladungen doppelt (Sperre + Schritt 3 + Pro-Unique-Keys) und führt keine alten API-Schreibaktionen aus.
- Abgelaufene oder widerrufene Tokens werden nie als gültig dargestellt (Schritt 4).
- Reconciliation löscht keine Reviews außerhalb der definierten Fristen/Tombstone-Regeln.

## Abnahme

- [ ] Restore-Szenario (DB-Dump von vor 3 Tagen einspielen): Sperre aktiv, keine Provider-Requests/E-Mails bis Reconciliation fertig; Trustpilot-Snapshots ausgeblendet; Leases zurückgesetzt.
- [ ] Klon auf andere Domain: keine Außenwirkung, bis der Betreiber bestätigt; „Kopie“ pausiert alle API-Profile.
- [ ] Ein vor dem Backup bereits versendeter Einladungsjob wird nach Restore nicht erneut versendet (Pro-Integrationstest).
- [ ] Ein nach dem Backup widerrufener Token wird nach Restore als `auth_required` angezeigt.
- [ ] `wp review-captain reconcile --dry-run` ändert nichts und liefert Zähler; Lauf ist abbrech- und fortsetzbar.
- [ ] Nach Upgrade läuft Reconciliation automatisch einmal (Modus `upgrade`) ohne Sperre.
- [ ] Privacy-Löschung vor dem Restore ist nach Reconciliation wieder wirksam.
