# ADR-0002: Hook-Präfix `review_captain_` statt `wp_review_captain_`

- **Status:** Angenommen
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Core-Maintainer Review Captain
- **Betrifft:** RC-020, RC-021, CON-002, CON-009; `conventions.md` (Hooks), `konzept.md` 7.4
- **Ersetzt:** –

## Kontext

`konzept.md` 7.4 schlägt Ereignisnamen wie `wp_review_captain_experience_event_v1` und `wp_review_captain_create_local_review_v1` vor. Das Präfix `wp_` ist in WordPress faktisch für Core-Funktionen, -Hooks und -Tabellen reserviert (`wp_insert_post`, `wp_loaded` …). Plugin-Prüfungen (Plugin Check, WordPress.org-Review) und WPCS (`WordPress.NamingConventions.PrefixAllGlobals`) bemängeln `wp_`-Präfixe; zudem droht Kollision mit künftigen Core-Namen. Die übrigen Konventionen (Optionen, Konstanten, Capabilities) verwenden bereits `review_captain_` bzw. `REVIEW_CAPTAIN_`.

## Betrachtete Optionen

1. `wp_review_captain_*` wie im Konzept – Nachteile siehe oben.
2. `rc_*` – zu kurz, hohes Kollisionsrisiko mit anderen Plugins.
3. `review_captain_*` – eindeutig, konsistent mit Slug/Optionen/Capabilities.

## Entscheidung

Alle Actions/Filter, Cron-Hooks und PHP-API-Funktionen verwenden das Präfix `review_captain_`. Öffentliche Verträge tragen eine Versionsendung `_v1` (Ausnahmen gemäß Hook-Tabelle in `conventions.md`, z. B. `review_captain_loaded`). Die Konzeptnamen werden wie folgt abgebildet:

| Konzept (7.4) | Verbindlicher Name |
|---|---|
| `wp_review_captain_experience_event_v1` | `review_captain_experience_event_v1` |
| `wp_review_captain_request_v1` | `review_captain_request_v1` |
| `wp_review_captain_widget_context_v1` | `review_captain_widget_context_v1` |
| `wp_review_captain_review_summary_v1` | `review_captain_review_summary_v1` |
| `wp_review_captain_create_local_review_v1()` | `review_captain_create_local_review_v1()` |

Die Hooks der rechten Spalte sind erst Vertrag, wenn sie in die Hook-Tabelle von `conventions.md` aufgenommen sind (eigener PR, ggf. eigenes ADR für den Payload). Interne, nicht öffentliche Hooks (z. B. Cron-Hook `review_captain_run_jobs`) tragen dasselbe Präfix, aber keine Versionsendung und keine Stabilitätszusage.

## Konsequenzen

- Positiv: WPCS-/Plugin-Check-konform, keine Core-Kollision, einheitliche Suche (`grep review_captain_`).
- Negativ: Suite-Plugins, die bereits gegen Konzeptnamen entwickelt haben, müssen umbenennen. Es werden **keine** `wp_`-Aliasse angeboten.
- Folgearbeiten: `konzept.md` 7.4 bei nächster Überarbeitung anpassen; Suite-Adapter (Voucher/Pass/Form Captain) informieren.

## Abnahme

- [ ] `grep -rn "wp_review_captain" src/ templates/ blocks/` liefert keine Treffer.
- [ ] WPCS `PrefixAllGlobals` mit Präfixen `review_captain`, `ReviewCaptain`, `REVIEW_CAPTAIN` läuft ohne Fehler.
- [ ] Hook-Tabelle in `conventions.md` und Implementierung stimmen überein (Test oder Review-Checkliste).

## Referenzen

- WordPress Plugin Handbook „Best Practices – Prefix everything“
- `docs/architecture/conventions.md` Abschnitt Hooks
