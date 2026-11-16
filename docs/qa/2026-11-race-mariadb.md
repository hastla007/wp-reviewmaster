# TEC-005: Race-Tests mit parallelen Prozessen auf MariaDB

Datum: 16.11.2026 · Prüfer: Claude · Skripte: [`tests/e2e/race-mariadb.sh`](../../tests/e2e/race-mariadb.sh), [`tests/e2e/php/race-mariadb.php`](../../tests/e2e/php/race-mariadb.php)

Schließt die in TEC-005 offene Lücke „Zwei-Verbindungs-Integrationstests auf MySQL 8/MariaDB“ für den Core-Anteil. Statt zweier Verbindungen in einem Prozess laufen **echte, gleichzeitig gestartete PHP-Prozesse** mit je eigener DB-Verbindung gegen den unveränderten Core-Code (`JobQueue`, `ReviewRepository`). Umgebung wie im [Skalierungsbericht](2026-11-tec-014-mariadb.md) (MariaDB 10.11, InnoDB, REPEATABLE READ, 100.000 vorhandene Reviews).

## Szenarien und Ergebnis

| Szenario | Bezug | Ablauf | 8 Prozesse | 16 Prozesse |
| --- | --- | --- | --- | --- |
| Job-Claim unter Konkurrenz | R-06 (TEC-003) | 2.000 Jobs, alle Prozesse holen gleichzeitig in Blöcken zu 5 ab und schließen ab | jeder Job genau 1× beansprucht, alle `succeeded` mit 1 Versuch | ✓ |
| Idempotentes Enqueue | TEC-004 | alle Prozesse reihen dieselben 200 Schlüssel gleichzeitig ein | 200 Zeilen; jeder Prozess erhält die ID des Gewinners | ✓ |
| Dedupe externer Reviews | R-01 / R-07 (Core-Teil) | alle Prozesse `upsert_external()` dieselben 200 externen IDs | 200 Reviews, genau ein `created` je ID, keine Exception | ✓ |
| Revision-CAS | R-12 | alle Prozesse laden dieselbe Revision und schreiben einmal mit `check_revision` | genau 1 Gewinner | ✓ |

## Befund und Korrektur

- **Scheinfehler im PHP-Log:** Verlorene Insert-Rennen (Duplicate Key) wurden korrekt behandelt. `$wpdb` schrieb aber jedes Mal „WordPress database error … Duplicate entry“ ins Fehlerlog, auch ohne `WP_DEBUG`, und mit `WP_DEBUG_DISPLAY` sogar in die Ausgabe. Unter Last überdeckt das echte Fehler. **Korrigiert:** `JobQueue::enqueue()` und `ReviewRepository::insert()` unterdrücken den erwarteten Duplicate-Key-Fehler. Andere DB-Fehler werden weiter mit Code und ohne Datenwerte geloggt (`JobQueue::log_db_error()`). Nachlauf: 0 DB-Fehler im `debug.log`.
- **Testartefakt (kein Produktfehler):** Im ersten Lauf startete WP-Cron parallel. Der echte Runner pausierte 50 Jobs des Testtyps `race.noop` korrekt, weil es keinen Handler gab. Das Skript setzt jetzt `DISABLE_WP_CRON`.

## Grenzen

- Pro-Anteil R-02–R-04 und R-07–R-09 (Versand vs. Refund/Opt-out, Token-Refresh, Sync-Läufe) liegt im Pro-Repository und ist hier nicht geprüft.
- Nur MariaDB 10.11; MySQL 8.x sollte in CI zusätzlich laufen (gleiches Skript).
