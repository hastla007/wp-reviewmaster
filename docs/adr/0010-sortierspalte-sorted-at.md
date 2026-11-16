# ADR-0010: Indizierte Sortierspalte `sorted_at` und Keyset-Paginierung im Export

- **Status:** Vorgeschlagen
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Claude (Vorschlag) / Core-Maintainer
- **Betrifft:** TEC-014, RC-136, RC-044; [database.md](../architecture/database.md), [scaling.md](../architecture/scaling.md)
- **Ersetzt:** –

## Kontext

Messung auf MariaDB 10.11 mit 100.000 Reviews ([Messbericht](../qa/2026-11-tec-014-mariadb.md)): Jedes Frontend-Widget sortierte mit `ORDER BY COALESCE(published_at, created_at)`. Dafür gibt es keinen Index, also las MariaDB ~50.000 Zeilen für 12 Treffer (~100 ms je Widget). Der CSV-Export paginierte mit `LIMIT/OFFSET`; der Aufwand wuchs quadratisch (18,7 s für 100.000 Zeilen).

## Betrachtete Optionen

1. **Generated Column** `COALESCE(published_at, created_at)` mit Index. Elegant, aber `dbDelta` und die SQLite-Integration (Tests, SQLite-Hosting) unterstützen Generated Columns nicht verlässlich.
2. **`published_at` NOT NULL machen.** Ändert die Bedeutung („noch nicht veröffentlicht“) und betrifft Pro und Importe.
3. **Eigene Spalte `sorted_at`**, die das Repository bei jedem Insert/Update pflegt, mit Index `(status, sorted_at)` und Nachbefüllung per Migration.

## Entscheidung (Vorschlag)

Option 3. Schema v3: `sorted_at datetime NULL`, `KEY status_sorted (status,sorted_at)`. `ReviewRepository::insert()`/`update()` setzen `sorted_at = published_at ?: created_at` (`ReviewRepository::sorted_at()`). `orderby=published_at` sortiert nach `sorted_at`. Migrationsschritt 3 füllt alte Zeilen in Blöcken von 5.000 nach (portabel ohne `UPDATE … LIMIT`). Der CSV-Export paginiert über `after_id` (`id > letzte ID`).

## Konsequenzen

- Positiv: Widget-Seiten 189 → 108 ms, CSV-Export 18,7 → 1,4 s bei 100.000 Reviews; Migration 3,3 s.
- Negativ: Eine zusätzliche Spalte, die konsistent bleiben muss. Direkte Schreibzugriffe an der Repository vorbei (heute nur `DevSeeder`, angepasst) müssen sie mitsetzen. Pro schreibt nicht direkt in die Tabelle (geprüft: nur Lesezugriffe in `CoreBridge`/`Analytics`).
- Migration: dbDelta legt Spalte/Index additiv an, Rollback auf v2 bleibt lesbar (Spalte wird ignoriert).
- Folgearbeiten: Zusammenfassung je Plattform (`summary_by_platform`, ~60 ms Vollscan je Seitenaufruf) cachen (TEC-012); Suche (~0,5 s) bei Bedarf mit FULLTEXT.

## Abnahme

- [x] Tests: `tests/Unit/M7/SchemaV3Test.php`, `tests/Unit/M7/SortedAtTest.php` (Pflege bei Insert/Update, Reihenfolge, Keyset-Export ohne Duplikate).
- [x] Migration auf 100.000 Zeilen MariaDB ohne verbleibende `NULL`-Werte.
- [ ] Core-Maintainer stimmt zu (Status → Angenommen); `database.md` um Spalte/Index ergänzt.

## Referenzen

- [Messbericht TEC-014 auf MariaDB](../qa/2026-11-tec-014-mariadb.md)
