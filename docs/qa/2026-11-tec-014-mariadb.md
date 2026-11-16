# TEC-014 / RC-136: Skalierung auf MariaDB mit 100.000 Reviews

Datum: 16.11.2026 · Prüfer: Claude · Skript: [`tests/e2e/perf.cjs`](../../tests/e2e/perf.cjs) (unverändert, 3 Wiederholungen, Median)

Ergänzt den SQLite-Lauf aus [2026-11-e2e.md](2026-11-e2e.md) §5 (20.000 Reviews) um die in TEC-014 verlangte Größenordnung auf einer echten MySQL-kompatiblen Datenbank.

## Umgebung

| Bestandteil | Stand |
| --- | --- |
| Review Captain Core | Branch `claude/plugin-todo-abarbeiten-xzv9c8` (= `claude/sleepy-gates-6mm217` + Schema v3) |
| Review Captain Pro | captain-pro `claude/review-captain-pro` 53428c1, `build.php --in-place`, aktiv (ohne Lizenz) |
| WordPress | 6.9 (johnpbloch/wordpress-core), Twenty Twenty-Five, `WP_DEBUG` an |
| Datenbank | **MariaDB 10.11** (Ubuntu-Paket, Standardkonfiguration, kein Tuning) |
| PHP / Server | PHP 8.3.6, `php -S` (ein Prozess, kein Objekt-Cache) |
| Daten | `DevSeeder`: 100.000 Reviews (6,5 s), 10.000 Jobs (0,3 s) |

## Ergebnisse

| Messung | Schema v2 (vorher) | Schema v3 (nachher) |
| --- | --- | --- |
| Frontend Startseite (Referenz ohne Widget) | 59 ms | 48 ms |
| Shortcode-Seite (12 Reviews, Grid+CTA+Formular) | 189 ms | **108 ms** |
| Shortcode-Seite paginiert (24/Seite) | 191 ms | **121 ms** |
| Produktseite (Kontextfilter) | 188 ms | **99 ms** |
| Admin-Liste Seite 1 | 129 ms | 117 ms |
| Admin-Liste Seite 500 | 188 ms | 187 ms |
| Admin-Suche | 589 ms | 527 ms |
| Admin-Liste Status „ausstehend“ | 119 ms | 99 ms |
| Dashboard | 193 ms | 160 ms |
| Diagnose | 68 ms | 66 ms |
| CSV-Export 100.000 Zeilen (22,8 MB) | 18.665 ms | **1.421 ms** |
| Migration v2 → v3 (Nachbefüllung `sorted_at`) | – | 3,3 s |

Installation, Aktivierung von Core + Pro und Migration auf MariaDB liefen ohne PHP-Warnungen, Notices oder Deprecations von Review Captain (`debug.log`).

## Ursachen und Änderungen

Langsame Abfragen über das MariaDB-Slow-Query-Log (Schwelle 30 ms):

1. `ORDER BY COALESCE(published_at, created_at)` im Widget: kein nutzbarer Index, ~50.000 gelesene Zeilen je Widget (~100 ms). → Schema v3 mit `sorted_at` + `KEY status_sorted` ([ADR-0010](../adr/0010-sortierspalte-sorted-at.md)).
2. CSV-Export mit `LIMIT/OFFSET`: quadratischer Aufwand. → Keyset-Paginierung `after_id`.

## Offen

- `summary_by_platform()` liest bei jedem Widget-Aufruf alle freigegebenen Reviews (~60 ms bei 100.000). Empfehlung: Ergebnis mit Versionsschlüssel cachen (Objekt-Cache/Transient, invalidiert bei Review-Änderung; TEC-012).
- Admin-Suche ~0,5 s (`LIKE` über Text). Bei realen Daten über 1 s: FULLTEXT-Index (eigenes ADR).
- Messung mit PHP-FPM und Objekt-Cache (Redis) im Pilotbetrieb (OPS-006).
