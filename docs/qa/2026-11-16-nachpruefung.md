# Nachprüfung QA-Befunde und Release-Prüfungen (16.11.2026)

**Core:** 1e65a9f · WordPress 6.9.11 (SQLite) · PHP 8.3 (Testschalter MINVER-1) · Umgebung und Skripte wie [2026-11-e2e.md](2026-11-e2e.md).

## Befunde aus 2026-11-e2e.md

| Befund | Stand |
|---|---|
| F-01 Textdomain-Notice bei WP-CLI | behoben (Commit 3d68332); in den Läufen dieser Prüfung kein Review-Captain-Eintrag im debug.log |
| F-02 Job-Takt nach CLI-Aktivierung | behoben; `doctor` meldet direkt nach `wp plugin activate` „Job tick is scheduled“ |
| F-03 Connectoren ohne Pro | behoben (3d68332) |
| F-04 `poll_experiences()` ohne Aufrufer | **offen, Pro** (CON-016) |
| F-05 schwebendes Badge verdeckt Formular (mobil) | **offen, Pro** (RC-120) |
| F-06 Fokus nach Serverfehler | **behoben, im Browser bestätigt:** nach leerem Absenden Redirect auf `#rc-form-1-errors`, `document.activeElement` = Fehlerbox (`role="alert"`, `autofocus`); 5 Felder mit `aria-invalid` |
| F-07 Überschriftenebene fest h3 | **behoben:** `heading_level` (2–6) je Widget; axe meldet kein `heading-order` mehr |
| F-08 frischer Restore | dokumentiert (`--mode=restore`) |
| F-09 Product-Markup auf fremden Seiten | **behoben, bestätigt** in [2026-11-seo-016.md](2026-11-seo-016.md) |
| F-10 Pro-Analytics auf SQLite | nur SQLite, Pro |

## RC-137 Barrierefreiheit/Mobil (Core-Seiten, ohne Pro)

`tests/e2e/a11y-mobile.cjs` mit `E2E_PAGES=rc-shortcodes,rc-blocks,rc-produkt`, Desktop, iPhone 13, Pixel 7:

- axe: **keine Verstöße durch Review Captain.** Verbleibend nur Theme-Meldungen: `list` (serious, TT5-Navigation) und `landmark-unique` (moderate, zwei `nav` ohne Label).
- Kein horizontales Scrollen auf allen Viewports. Hinweis: Quelllinks `rc-source__link` sind 21 px hoch (Zielgröße unter 24 px, WCAG 2.2 2.5.8 erlaubt das bei ausreichendem Abstand im Fließtext; beobachten).
- Tastatur: Tab-Reihenfolge korrekt, sichtbarer Fokus überall, Honeypot nicht fokussierbar, Absenden per Enter, Fokus nach Fehler auf der Fehlerbox.
- Offen: Screenreader-Test mit NVDA/VoiceOver (manuell), Pro-Widgets (Karussell, Badge; F-05), reduced motion für Pro-Animationen.

## RC-136 Performance (Ausschnitt)

- **Keine externen HTTP-Requests im öffentlichen Request:** MU-Plugin protokollierte `pre_http_request` auf 6 Frontend-Seiten (inkl. `?rc_page=2`) → leer.
- Antwortzeiten (php -S, SQLite, wenige Reviews): 53–68 ms je Seite.
- Indexe und Skalierung: [TEC-014 auf MariaDB](2026-11-tec-014-mariadb.md) (Schema v3 mit `status_sorted`). Offen: `summary_by_platform` pro Widget ungecacht (~60 ms bei 20 000 Reviews, TEC-012), Admin-Suche ~0,5 s.
