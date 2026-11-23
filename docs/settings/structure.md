# Einstellungsbaum, Informationsarchitektur und Einfach/Erweitert-Modus (SET-002, UX-010, UX-011)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Feldsemantik: [option-schema.md](option-schema.md). Seitenwireframes: [../ux/wireframes.md](../ux/wireframes.md).

## 1. Menü und Abgrenzung

Hauptmenü **Review Captain** (Dashicon `dashicons-star-filled`, Position nach „Kommentare“). Reihenfolge laut TODO-Empfehlung:

| # | Seite | Slug (`page=`) | Capability | Owner | Was dort gepflegt wird |
|---|---|---|---|---|---|
| 1 | Dashboard | `review-captain` | `review_captain_moderate` | Core | nur Überblick, keine Optionen |
| 2 | Reviews | `review-captain-reviews` | `review_captain_moderate` | Core | einzelne Reviews, Moderation, CSV-Import/-Export |
| 3 | Anfragen | `review-captain-requests` | `review_captain_manage_requests` | Pro | Kampagnen, Queue |
| 4 | Widgets | `review-captain-widgets` | `review_captain_moderate` | Core | einzelne Widget-Definitionen |
| 5 | Quellen | `review-captain-sources` | `review_captain_manage_sources` | Core (Pro: Verbindungen) | Profile, CTA, Credentials, Embeds |
| 6 | Integrationen | `review-captain-integrations` | `review_captain_manage_settings` | Pro | Suite-Adapter, Connectoren |
| 7 | Einstellungen | `review-captain-settings` | `review_captain_manage_settings` | Core | globale Standards und Richtlinien |
| 8 | Diagnose | `review-captain-diagnostics` | `review_captain_view_reports` | Core | read-only |
| 9 | Lizenz | `review-captain-license` | `manage_options` | Pro | Lizenz |

Ohne Pro erscheinen „Anfragen“ und „Integrationen“ **nicht** als eigene Menüpunkte; die Funktionen werden auf Dashboard und im gesperrten Tab „Anfragen & Zustellung“ erklärt (keine Upsell-Menüflut). Testimonials/Trustmarks (Pro) bekommen einen eigenen Menüpunkt nach „Widgets“, sobald Pro aktiv ist.

**Abweichung zu den Mockups:** `docs/mockups/*.svg` zeigen „Übersicht · Reviews · Quellen & Profile · Anfragen · Widgets · Testimonials · Einstellungen“. Diese Datei folgt der TODO-Empfehlung; die Benennung („Dashboard“ vs. „Übersicht“, „Quellen“ vs. „Quellen & Profile“) ist eine **offene Entscheidung** (UX-013-Begriffstest).

Grundregel: **Einstellungen = globale Standards und Richtlinien.** Objekte werden an ihrer Seite gepflegt. Jeder Tab verlinkt am Ende auf die zuständige Objektseite („Einzelne Quellen verwalten → Quellen“).

## 2. Tab-Baum

URL: `admin.php?page=review-captain-settings&tab=<tab>`; Standard-Tab `general`. Tab-Navigation mit `nav-tab-wrapper`; jeder Tab ist ein eigenes Formular (Speichern betrifft nur diesen Tab).

```
Einstellungen
├─ 1 Allgemein (general)
│   ├─ Identität ............ brand_name
│   ├─ Datum ................ date_format, [Anzeige WP-Zeitzone] (Sprache = WordPress-Sprache, keine eigene Einstellung)
│   ├─ Neue Reviews ......... new_review_status
│   ├─ Hinweise ............. show_pro_hints
│   └─ [Hinweiszeile Pro] Auswertungen
├─ 2 Quellen & Synchronisierung (sources)
│   ├─ Zeitfenster .......... sync_window_start, sync_window_end
│   ├─ Gelöschte Reviews .... removed_review_behavior
│   ├─ ▸ Erweitert .......... max_pages_per_run, pause_after_auth_errors
│   └─ [Hinweiszeile Pro] Automatischer Abgleich
├─ 3 Anfragen & Zustellung (requests)  [Felder nur vom Add-on; ohne Pro nur Hinweiszeilen, SET-RC-06]
│   ├─ Absender ............. sender_name, sender_email  (Pro)
│   ├─ Versandzeiten ........ send_window_start, send_window_end  (Pro)
│   ├─ Grenzen .............. daily_limit, cooldown_days  (Pro)
│   ├─ Erinnerungen ......... reminders_enabled  (Pro)
│   └─ [feste Regeln, nur Text, nur mit Feldern] eine Einladung je Erlebnis, kein Versand ohne Berechtigung, Opt-out-Sperre
├─ 4 Einreichungen & Moderation (submissions)
│   ├─ Formular ............. enabled, allow_title, min_text_length, max_text_length
│   ├─ Einwilligung ......... require_consent  (Datenschutztext → Tab Datenschutz)
│   ├─ Benachrichtigung ..... notify_admin
│   ├─ Moderationsregeln .... hold_words, hold_links, block_email_domains, ▸ min_seconds
│   ├─ Schutz vor Missbrauch  ▸ honeypot, captcha_provider, ▸ rate_limit_per_hour, ▸ trusted_proxy_header
│   ├─ Korrekturen .......... corrections_enabled, ▸ correction_link_hours
│   └─ Erweiterte Funktionen  tags/reports/photos (▸ photo_max_edge)/multi_product/qa/feedback
├─ 5 Darstellung (display)
│   ├─ Inhalte .............. show_stars, show_date, date_style, show_author, show_avatar
│   ├─ Bewertung und Farben . rating_display, decimal_places, star_color, accent_color
│   ├─ Widget-Vorgaben ...... widget_limit, widget_layout, widget_columns, widget_heading_level, widget_new_tab
│   ├─ Strukturierte Daten .. schema_enabled, schema_contexts, schema_min_reviews
│   ├─ Leerer Zustand ....... empty_text
│   ├─ Barrierefreiheit ..... reduced_motion
│   ├─ [Hinweis] Quellenangabe externer Reviews ist immer sichtbar
│   ├─ ▸ Erweitert .......... load_css
│   └─ [Hinweiszeile Pro] Weitere Widgets
├─ 6 Datenschutz & Aufbewahrung (privacy)  [+ manage_privacy]
│   ├─ Aufbewahrung ......... retention_external_days, retention_rejected_days, retention_contact_days,
│   │                         retention_approved_days, retention_corrections_days  [Vorschau „betrifft N Bewertungen“]
│   ├─ Datenschutzhinweis ... privacy_notice
│   ├─ Löschanfragen ........ erase_mode, [Link: Werkzeuge › Personenbezogene Daten]
│   ├─ ▸ Erweitert .......... retention_logs_days, retention_jobs_days
│   └─ Deinstallation ....... delete_data_on_uninstall  (+ Aktion „Alle Daten löschen…“)
├─ 7 Zugriff & Rollen (access)  [+ promote_users]
│   ├─ Moderation ........... moderate_roles
│   ├─ Einstellungen ........ settings_roles
│   └─ [read-only Tabelle] übrige Capabilities und welche Rollen sie haben
└─ 8 Erweitert (advanced)
    ├─ Hintergrundjobs ...... use_action_scheduler, job_time_budget, lease_seconds, [Cron-Hinweis]
    ├─ Fehlersuche .......... debug_logging
    └─ Wartung (Aktionen) ... Konfiguration exportieren/importieren, Einstellungen zurücksetzen
```

Diagnose ist **kein Tab**, sondern eine eigene read-only Seite.

## 3. Einfach-/Erweitert-Modus (UX-011)

Kein globaler Moduswechsel, sondern **pro Abschnitt** aufklappbare Bereiche (`<details class="rc-advanced">` mit `<summary>Erweiterte Optionen</summary>`), damit nichts „versteckt“ gespeichert wird, was Nutzer nie gesehen haben.

| Ebene | Inhalt | Darstellung |
|---|---|---|
| Einfach (sofort sichtbar) | häufige, verständliche Entscheidungen: Markenname, Status neuer Reviews, Sync-Zeitfenster, Formular an/aus, Einwilligung, Sterne/Datum/Autor, Aufbewahrungsfristen für Reviews, Rollen | offen |
| Erweitert | technische/seltene Werte: Seiten je Lauf, Pause nach Fehlern, Honeypot, Rate-Limit, CSS laden, Log-/Job-Fristen, gesamter Tab „Erweitert“ | eingeklappt; öffnet automatisch, wenn darin ein Wert vom Default abweicht oder ein Validierungsfehler vorliegt |

Zustand „aufgeklappt“ wird nur pro Nutzer gemerkt (User-Meta `review_captain_ui_advanced`, Vorschlag) – nie als Einstellung.

### Kontextabhängige Felder (Progressive Disclosure)

| Bedingung | Wirkung |
|---|---|
| keine synchronisierende Quelle/kein Connector registriert | Tab „Quellen & Synchronisierung“: Felder sichtbar, mit Info-Notice „wirkt erst, wenn …“, Link zu Quellen |
| Pro nicht aktiv oder Funktion „Bewertungsanfragen“ nicht lizenziert | Tab „Anfragen & Zustellung“: keine Felder, kein Formular, nur graue Hinweiszeilen am Ende (aus: Satz „Hier erscheinen Einstellungen von Erweiterungen.“); kein Schloss am Tab; keine Werte werden verändert (SET-RC-06) |
| Add-on-Feld angemeldet, aber nicht schreibbar (z. B. ohne `review_captain_manage_requests`) | Feld `disabled` mit neutralem Vermerk „Von einer Erweiterung verwaltet; derzeit nicht änderbar.“ |
| `general.show_pro_hints = false` | keine Hinweiszeilen, kein Kasten „In Review Captain Pro“, kein Vermerk auf der Quellen-Seite |
| `requests.sender_email` leer | Warnung „Ohne Absenderadresse wird nichts versendet“ |
| `submissions.enabled = false` | übrige Formularfelder bleiben sichtbar, aber grau beschrieben „gilt, sobald Einreichungen aktiv sind“ |
| `require_consent = false` | Warnhinweis neben dem Feld |
| `display.show_avatar = true` und WP „Avatare anzeigen“ aus | Hinweis „Avatare sind in WordPress ausgeschaltet“ |
| Action Scheduler nicht geladen | `use_action_scheduler` mit Hinweis „nicht verfügbar – WP-Cron wird verwendet“ |
| `DISABLE_WP_CRON` gesetzt | Cron-Hinweis mit Beispiel für Server-Cron/WP-CLI |
| Multisite | Hinweisbox in „Zugriff & Rollen“ und „Datenschutz“: „Gilt nur für diese Site“ |

## 4. Bedienregeln

- WordPress-native Formulare (`form-table`, `description`, `settings_errors()`); Speichern-Button je Tab unten, kein Auto-Save.
- Nach Speichern: Erfolgs-Notice „Einstellungen gespeichert.“ oder Fehler-Notice mit Feldliste; fehlerhafte Felder erhalten `aria-invalid="true"` und `aria-describedby` auf die Fehlermeldung.
- Ungespeicherte Änderungen beim Tabwechsel: `beforeunload`-Hinweis (progressive Verbesserung, kein Pflicht-JS).
- Mobile: Tabs umbrechen; unter 600 px wird `form-table` einspaltig (WP-Standard).
- Jede Option hat einen Hilfetext ohne Fachjargon (kein „TTL“, „Backoff“, „Entitlement“, „Lease“ im sichtbaren Text, ggf. in „Mehr erfahren“).

## Abnahme

Entspricht SET-002, UX-010 und UX-011, wenn:

- [ ] die acht Tabs in genau dieser Reihenfolge mit Abschnitten und Feldern wireframed sind und Diagnose eine separate read-only Seite bleibt;
- [ ] Menüreihenfolge, Seiten-Slugs, Capabilities und die Abgrenzung zu Quellen, Anfragen, Widgets, Integrationen und Diagnose freigegeben sind;
- [ ] für jeden Tab feststeht, was „einfach“ und was „erweitert“ ist, und kontextabhängige Felder (Quelle, Versandweg, Lizenzstatus) definiert sind;
- [ ] die Benennungsabweichung zu den Mockups entschieden ist.
