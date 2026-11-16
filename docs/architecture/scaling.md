# Skalierungsgrenzen und Messplan (TEC-014)

Status: Messplan, Stand 2026-11-16. Ergebnisse werden in §6 nachgetragen. Grundlage: [database.md](database.md) (Indizes exakt nach [`conventions.md`](conventions.md)), [jobs.md](jobs.md).

## 1. Ziel

Nachweis, dass Core mit **≥ 100.000 Reviews** und **≥ 10.000 Jobs** (wartend + abgeschlossen) pro Site performant bleibt: indizierte Adminsuche/-filter, paginierte Exporte, begrenzte Joblaufzeit, schnelle öffentliche Widgets.

## 2. Testdaten: `wp review-captain dev seed`

```
wp review-captain dev seed --reviews=100000 --jobs=10000 [--profiles=20] [--seed=42] [--batch=1000] [--yes]
wp review-captain dev seed --purge          # entfernt nur Seed-Daten
```

- Läuft nur, wenn `wp_get_environment_type()` ∈ {`local`, `development`} ist **oder** `--yes` explizit gesetzt wird; auf `production` verlangt er zusätzlich eine interaktive Bestätigung. Ohne diese Bedingungen bricht er ohne Änderung ab.
- Deterministisch (`--seed`), Inserts in Batches (Multi-Row-INSERT, 1.000 Zeilen) per `$wpdb->prepare`.
- Seed-Daten sind markiert (`meta.seed = true`, `jobs.type` Präfix `dev.seed.`), damit `--purge` nur sie entfernt.
- Verteilung (realistisch, nicht gleichverteilt):

| Merkmal | Verteilung |
|---|---|
| `platform` | local 30 %, google 25 %, trustpilot 20 %, trusted_shops 10 %, app_store 8 %, google_play 7 % |
| `status` | approved 70 %, pending 15 %, rejected 5 %, archived 5 %, flagged 3 %, removed 2 % |
| `kind` | review 92 %, testimonial 8 % |
| `rating_value` | schief zu 4–5 Sternen |
| `body` | 50–2.000 Zeichen, mehrsprachig (de/en/fr), Lorem-Generator mit Umlauten/Emoji |
| `created_at` | über 5 Jahre, mit Häufung im letzten Jahr |
| `context_*` | 40 % mit Kontext auf 500 verschiedene Objekte |
| `author_email` | nur bei `local` |
| Jobs | 2.000 queued (davon 300 überfällig), 50 running (20 mit abgelaufener Lease), 7.000 succeeded, 500 dead, 450 paused |

## 3. Messszenarien

| # | Szenario | Abfrage / Aktion | Zielwert (p95, warmer DB-Cache) |
|---|---|---|---|
| S1 | Adminliste ungefiltert, Seite 1, 20/Seite, sortiert nach `created_at` DESC | `SELECT … ORDER BY created_at DESC LIMIT 20` + `COUNT(*)` | ≤ 150 ms gesamt |
| S2 | Filter Status + Plattform + Kind | Index `status_kind_platform` | ≤ 100 ms |
| S3 | Filter Rating ≥ 4 + Status | `rating_value` + Status | ≤ 150 ms |
| S4 | Filter Kontext (Produkt X) | `context` | ≤ 50 ms |
| S5 | Suche nach Autor-E-Mail (exakt) | `author_email` | ≤ 50 ms |
| S6 | Textsuche (`LIKE '%wort%'` auf `title`, `author_name`, `body`) | Full Scan erwartet | ≤ 800 ms; sonst ADR für FULLTEXT |
| S7 | Adminliste Seite 2.000 (OFFSET 40.000) | | ≤ 300 ms; sonst Keyset-Pagination auch im Admin |
| S8 | CSV-Export aller 100.000 Reviews | Keyset (`WHERE id > :last ORDER BY id LIMIT 500`), Streaming | Speicher < 64 MB, keine Abfrage > 100 ms, Gesamtdauer ≤ 60 s (CLI) bzw. als Job in Etappen |
| S9 | Öffentliches Widget (12 Reviews, approved, Plattform-Filter, ohne Fragment-Cache) | | ≤ 30 ms DB-Zeit |
| S10 | Claim-Kandidatenabfrage bei 10.000 Jobs | Index `status_available` | ≤ 20 ms |
| S11 | Runner-Tick mit 2.000 fälligen No-op-Jobs | | endet ≤ 22 s, verarbeitet > 200 Jobs |
| S12 | Retention-Purge über 100.000 Reviews | Batches à 500 | keine Abfrage > 500 ms, kein Lock > 1 s |
| S13 | Dedupe-Insert 10.000 externe Reviews, 2 parallele Prozesse | `INSERT … ON DUPLICATE KEY UPDATE` | 0 Dubletten, ≤ 30 s |
| S14 | Dashboard-KPIs (Anzahl, Durchschnitt je Quelle) | Aggregat | ≤ 200 ms ungecacht |

## 4. Durchführung

- Umgebungen: MySQL 8.0 und MariaDB 10.6; PHP 8.1 und 8.3; Referenz-VM 2 vCPU / 4 GB RAM; kein persistenter Objektcache (Worst Case) und mit Redis (Best Case).
- Messung: `SAVEQUERIES` + eigener Messbefehl `wp review-captain dev bench [--scenario=S1,…] [--runs=20] [--format=json]` (gibt p50/p95/max je Szenario aus); `EXPLAIN` je Abfrage wird gespeichert und darf keinen `ALL`-Scan auf `reviews` zeigen außer bei S6.
- CI: verkleinerter Lauf (10.000 Reviews / 1.000 Jobs) mit EXPLAIN-Assertions; voller Lauf vor jedem Minor-Release.

## 5. Bekannte Risiken und Kandidaten (nur per ADR)

- S1/S7: Index `(status, kind, platform)` deckt Sortierung nach `created_at` nicht ab → Kandidat `(status, created_at)`.
- Refresh/Retention: Kandidat `(refresh_due_at)`.
- Profil-Detail: Kandidat `(profile_id, status)`.
- S6: InnoDB-FULLTEXT `(title, body)` oder Suche auf `title`/`author_name` begrenzen.
- Jobs-Cleanup hält die Tabelle klein (Fristen in [retention-matrix.md](retention-matrix.md)). Periodische Jobs (Sync je Profil) sind je eine langlebige Zeile; Wachstum entsteht nur durch Einmal-Jobs (Importe, Pro-Einladungen).

## 6. Ergebnisse

| Datum | Umgebung | Szenarien | Ergebnis | Maßnahmen |
|---|---|---|---|---|
| – | – | – | ausstehend | – |

## Abnahme

- [ ] `wp review-captain dev seed` erzeugt reproduzierbar 100.000 Reviews und 10.000 Jobs in < 5 min und entfernt sie mit `--purge` vollständig.
- [ ] Alle Szenarien S1–S14 auf beiden DB-Systemen gemessen und in §6 dokumentiert; Zielwerte erreicht oder Abweichung mit ADR/Folgetask belegt.
- [ ] Kein EXPLAIN eines Admin-Filters (S2–S5) oder Widgets (S9) zeigt einen Full Scan.
- [ ] Export läuft keyset-paginiert mit begrenztem Speicher.
- [ ] Runner-Tick hält das Zeitbudget auch bei 10.000 Jobs ein.
