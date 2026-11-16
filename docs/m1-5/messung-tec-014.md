# TEC-014 – Skalierungsmessung

**Stand:** 2026-11-16 · Review Captain Core 0.1.0  
**Umgebung:** PHP 8.3, MariaDB 10.11 (lokal, Standardkonfiguration), WordPress-Testumgebung (wp-phpunit), Einzelseite  
**Reproduzieren:** `vendor/bin/phpunit --testsuite perf` (legt 100.000 Reviews und 10.000 Jobs an und räumt danach auf)

## Ergebnis

| Messung | vor Optimierung | nach Optimierung | Ziel |
|---|---|---|---|
| Adminliste Seite 1 (Status-Filter) | 168 ms | **0,9 ms** | < 300 ms |
| Adminliste Seite 500 | 196 ms | **38 ms** | < 300 ms |
| Filter Quelle + Mindestbewertung | 43 ms | 81 ms | < 300 ms |
| Zählung gefiltert | 31 ms | 49 ms | < 300 ms |
| Volltextsuche (LIKE) | 101 ms | 151 ms | < 1 s |
| Zusammenfassung je Quelle | 88 ms | 85 ms | < 300 ms |
| Öffentliche Widget-Abfrage | 189 ms | **0,9 ms** | < 50 ms (zusätzlich 15 min Objektcache) |
| CSV-Export 100.000 Zeilen | 11,8 s | **1,2 s** | linear, ohne Timeout |
| Speicher-Peak CSV-Export | 6 MB | 6 MB | < 64 MB |
| Job-Claim 10 aus 10.000 | 16 ms | 16 ms | < 100 ms |
| Queue-Statistik | 6 ms | 6 ms | < 100 ms |

Die Einzelwerte schwanken zwischen Läufen um ±50 %. Entscheidend sind die Größenordnungen.

## Optimierungen

1. `published_at` ist jetzt immer gesetzt (Fallback: Erstellzeit). Sortierung und Datumsfilter laufen direkt auf `published_at` statt `COALESCE(...)` und nutzen den Index `(status, published_at)`.
2. Der CSV-Export nutzt Keyset-Paginierung (`id > letzte ID`) statt OFFSET. Jeder Batch ist dadurch gleich schnell, der Gesamtaufwand wächst linear.

## Offen

- Messung auf typischem Shared Hosting und mit aktivem Objektcache (Redis) im Pilotbetrieb (OPS-006).
- Wenn die Suche bei realen Daten über 1 s liegt, einen FULLTEXT-Index ergänzen (Schemaänderung per ADR).
