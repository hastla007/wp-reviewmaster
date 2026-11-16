# Optionsschema der Einstellungen (SET-003)

Status: an den implementierten Stand angeglichen, Stand 2026-11-16. Maßgeblich sind `src/Settings/SettingsSchema.php` (Felder, Typen, Defaults, Grenzen) und `src/Settings/Settings.php` (Lesen/Schreiben); Typen, Defaults und Wertebereiche unten sind **exakt** aus dem Code übernommen. Wo dieses Dokument und der Code abweichen, gilt der Code; nicht umgesetzte Zusatzregeln früherer Fassungen sind als „Ausblick (nicht in v1)“ markiert.
Bezug: [konventionen](../architecture/conventions.md), [Inventar](inventory.md), [Vererbung](inheritance.md), [Defaults/Reset](defaults-reset.md), [Pro-Matrix](pro-matrix.md).

## 1. Speicherort

| Element | Wert |
|---|---|
| Option (Werte) | `review_captain_settings` – ein verschachteltes Array `[tab][key]` |
| Option (Schema-Version) | `review_captain_settings_version` – Ganzzahl, Start `1` |
| Autoload | `review_captain_settings`: ja (klein, wird im Frontend für `display` gebraucht); `…_version`: nein |
| Schreiben | eigenes Formular je Tab über `SettingsPage::save_tab()` (Nonce + Capability), Sanitisierung über `Settings::sanitize()` → `SettingsSchema::sanitize_value()` |
| Erweiterung durch Pro | Filter `review_captain_settings_schema` (Pro ergänzt Felder; Core-Felder werden nach dem Filter wiederhergestellt, können also weder entfernt noch umdefiniert werden). Pro-Felder (`owner = pro`) werden nur geschrieben, wenn der Filter `review_captain_pro_settings_writable` `true` liefert |
| Multisite | jede Site besitzt eine eigene Option (`get_option`, nicht `get_site_option`). In 1.0 gibt es **keine** Netzwerk-Optionen; Spalte „Scope“ ist daher überall `site`. `network` ist für spätere Versionen reserviert (siehe [inheritance.md](inheritance.md)). |
| Secrets | liegen **nie** in `review_captain_settings`. API-Schlüssel/Tokens gehören zu Quellprofilen (verschlüsselt, Quellen-Seite). |
| Objekt-Einstellungen | Profile (`profiles`-Tabelle), Widgets, Formulare (Block-/Shortcode-Attribute), Kampagnen (Pro) speichern ihre Overrides am Objekt, nicht in dieser Option. |

Tab-Schlüssel (= URL-Parameter `tab` der Einstellungsseite): `general`, `sources`, `requests`, `submissions`, `display`, `privacy`, `access`, `advanced`.

## 2. Lese- und Schreibsemantik

- **Lesen** (`Settings::all()`/`get()`): gespeicherte Werte werden über die Defaults gelegt (`SettingsSchema::defaults()`); fehlender Tab oder Schlüssel → **Default**. Gespeicherte Werte werden beim Lesen **nicht** erneut validiert (sie sind beim Schreiben bereits sanitisiert). Unbekannte Schlüssel bleiben erhalten (Vorwärtskompatibilität, z. B. Pro-Felder ohne aktives Pro).
- **Schreiben (Speichern eines Tabs):** nur die Felder des abgeschickten Tabs werden verarbeitet; andere Tabs bleiben unverändert. Sanitisierung je Typ (`SettingsSchema::sanitize_value()`):
  - `bool`: `true` für `true`, `1`, `'1'`, `'on'`, `'yes'`, `'true'`, sonst `false`;
  - `int`: nicht numerisch → **Default**; sonst auf `min`/`max` **begrenzt** (Clamp, kein Zurückweisen);
  - `enum`: nicht in `choices` → Default; `time`: Muster `^([01]\d|2[0-3]):[0-5]\d$`, sonst Default; `email`: leer → `''`, ungültig → Default;
  - `array`: Liste aus `sanitize_key`-Werten, leere entfernt, Duplikate entfernt; kein Array → Default;
  - `textarea`: `sanitize_textarea_field`; `string`: `sanitize_text_field` (keine Längengrenze im Schema).
  Weicht der gespeicherte Wert eines `int`/`enum`/`time`/`email`-Feldes vom eingegebenen ab, meldet die Seite das Feld als korrigiert (`invalid`). Geänderte Felder werden als Audit-Ereignis `settings.changed` protokolliert. Speichern verlangt Capability `review_captain_manage_settings` und Nonce; Tab `privacy` zusätzlich `review_captain_manage_privacy`, Tab `access` zusätzlich `promote_users` (`SettingsPage::can_write_tab()`).
- **Pro-Felder ohne aktives Pro/Entitlement:** werden angezeigt (gesperrt), beim Speichern **nicht** verändert, beim Lesen **nicht** entfernt.
- **Booleans** werden als `true`/`false` gespeichert (nicht `'1'`/`''`). Ein nicht angehakter Checkbox-Wert im abgeschickten Tab bedeutet `false`.
- **Felddefinition** (`SettingsSchema::fields()`): Schlüssel `type` (`string`\|`int`\|`bool`\|`enum`\|`array`\|`time`\|`email`\|`textarea`), `default`, `choices`, `min`/`max`, `owner` (`core`\|`pro`, Default `core`), `entitlement` (nur Pro), `privacy` (`none`\|`personal`\|`config`, Default `none`), `advanced` (progressive Offenlegung, Default `false`), `scope` (`site`\|`network`, Default `site`). Die Datenschutzklassen P0–P2 unten sind eine Dokumentationssicht; im Code ist nur `privacy = config` für `requests.sender_name`/`sender_email` gesetzt.
- **Strings** werden getrimmt; Mehrzeilentexte mit `sanitize_textarea_field`, außer wo anders angegeben.
- **Uhrzeiten** `HH:MM` 24h in **Website-Zeitzone** (`wp_timezone()`); Start > Ende bedeutet Fenster über Mitternacht. Ausblick (nicht in v1): Start = Ende als ungültig zurückweisen.

## 3. Datenschutzklassen

| Klasse | Bedeutung | Folge |
|---|---|---|
| `P0` | keine personenbezogenen Daten | normal exportierbar (Konfigurations-Export), Audit mit Alt-/Neuwert |
| `P1` | kann personenbezogene Daten des Betreibers enthalten (z. B. Absender-E-Mail, Markenname = Personenname) | Konfigurations-Export nur nach Bestätigung; Audit maskiert |
| `P2` | steuert Verarbeitung personenbezogener Daten Dritter (Einwilligung, Fristen, Löschung, Rollen) | Änderung immer auditpflichtig; Hilfetext mit Datenschutzbezug Pflicht |
| `S` | Secret | **nicht zulässig** in dieser Option |

## 4. Felder

Spalten: **Typ** · **Default** · **Validierung** · **Scope** · **Klasse** · **Entitlement** (`—` = Core, ohne Lizenz) · **Capability** (zum Ändern; immer zusätzlich Nonce).
Standard-Capability ist `review_captain_manage_settings`; Abweichungen sind angegeben.

### 4.1 `general` – Allgemein

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `brand_name` | string | `''` (= `get_bloginfo('name')`) | `sanitize_text_field` | site | P1 | — |
| `default_language` | string | `''` (= Site-Sprache `get_locale()`) | `sanitize_text_field` (Ausblick: nur installierte Locales) | site | P0 | — |
| `date_format` | string | `''` (= WP-Option `date_format`) | `sanitize_text_field` | site | P0 | — |
| `new_review_status` | enum `pending`\|`approved` | `pending` | Enum | site | P2 | — |

Hinweis: Zeitzone ist **keine** eigene Option; maßgeblich ist die WordPress-Zeitzone (Einstellungen → Allgemein), der Tab zeigt sie nur an.
`new_review_status = approved` gilt nur für **lokale Einreichungen** (Formular). Manuelle Einträge legt die moderierende Person ohnehin mit gewähltem Status an; CSV-Import nutzt die Spalte `status`.

### 4.2 `sources` – Quellen & Synchronisierung

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `sync_window_start` | time `HH:MM` | `'02:00'` | `^([01]\d\|2[0-3]):[0-5]\d$` | site | P0 | — |
| `sync_window_end` | time `HH:MM` | `'05:00'` | wie oben | site | P0 | — |
| `max_pages_per_run` | int (erweitert) | `5` | 1–50 | site | P0 | — |
| `pause_after_auth_errors` | int (erweitert) | `3` | 1–20 | site | P0 | — |
| `removed_review_behavior` | enum `hide`\|`tombstone` | `tombstone` | Enum | site | P2 | — |

Diese Felder gehören dem Core, weil Jobs, Provider-/Connector-Registry und Tombstone-Logik im Core liegen. Ohne registrierten Provider/Connector sind sie wirkungslos und werden mit Hinweis angezeigt („wirkt erst, wenn eine synchronisierende Quelle verbunden ist“). Credentials, Profile und Anbieter-Scopes liegen ausschließlich auf der Quellen-Seite.
Adapter dürfen strengere Werte erzwingen (z. B. Anbieter verlangt Löschung → `tombstone` unabhängig von `hide`).

### 4.3 `requests` – Anfragen & Zustellung (Pro)

Im Core nur **sichtbar gesperrt** (Beschreibung + Lizenzhinweis, keine Eingabe). Pro registriert Validierung und Wirkung über `review_captain_settings_schema`. Die Werte werden in derselben Option unter `[requests]` gespeichert; der Core verändert oder leert sie nie.

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `sender_name` | string | `''` (= `general.brand_name`) | `sanitize_text_field` | site | P1 (`privacy = config`) | `review.review_requests` |
| `sender_email` | email | `''` (kein Versand, solange leer) | `sanitize_email` + `is_email` | site | P1 (`privacy = config`) | `review.review_requests` |
| `send_window_start` | time `HH:MM` | `'09:00'` | wie Sync-Fenster | site | P0 | `review.review_requests` |
| `send_window_end` | time `HH:MM` | `'18:00'` | wie Sync-Fenster | site | P0 | `review.review_requests` |
| `daily_limit` | int | `200` | 1–100 000 | site | P0 | `review.review_requests` |
| `cooldown_days` | int | `90` | 0–3650 | site | P2 | `review.review_requests` |
| `reminders_enabled` | bool | `false` | bool | site | P2 | `review.review_requests` |

Capability zum Ändern: `review_captain_manage_settings` **und** `review_captain_manage_requests`.
Nicht als Einstellung vorhanden (feste Regeln): eine Einladung je Erlebnis + Empfänger + Kampagne; kein Versand bei fehlender/unbekannter Versandberechtigung; siteweite Opt-out-Sperre; keine Bewertungsauswahl als Routingkriterium.

### 4.4 `submissions` – Einreichungen & Moderation

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `enabled` | bool | `true` | bool | site | P2 | — |
| `require_consent` | bool | `true` | bool | site | P2 | — |
| `honeypot` | bool (erweitert) | `true` | bool | site | P0 | — |
| `rate_limit_per_hour` | int (erweitert) | `5` | 1–1000, je IP-Hash (IPv6: /64-Präfix) | site | P0 | — |
| `min_text_length` | int | `20` | 0–1000 (Formular verlangt mindestens 1 Zeichen) | site | P0 | — |
| `max_text_length` | int | `5000` | 50–20 000 | site | P0 | — |
| `allow_title` | bool | `true` | bool | site | P0 | — |
| `notify_admin` | bool | `true` | bool | site | P1 | — |

- `enabled = false`: Formular-Block/-Shortcode rendert einen neutralen Hinweis statt des Formulars; `admin-post.php`-Handler lehnt ab.
- `require_consent = true`: Pflicht-Checkbox „Veröffentlichung erlauben“, setzt `consent_publish`/`consent_at`. Bei `false` wird `consent_publish = 0` gespeichert und **das Review kann trotzdem nur nach Moderation veröffentlicht werden**; der Tab zeigt eine Warnung.
- `honeypot = false` zeigt eine Warnung; Nonce und Rate-Limit bleiben immer aktiv.
- `notify_admin`: E-Mail an `admin_email` ohne Reviewtext und ohne Autor-E-Mail, nur Link zur Moderation.
- Ausblick (nicht in v1): Inkonsistente Längen (min ≥ max) zurückweisen. In v1 werden beide Werte unabhängig begrenzt.

### 4.5 `display` – Darstellung (Voreinstellung neuer Widgets)

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `show_stars` | bool | `true` | bool | site | P0 | — |
| `show_date` | bool | `true` | bool | site | P0 | — |
| `show_author` | bool | `true` | bool | site | P2 | — |
| `show_avatar` | bool | `false` | bool | site | P2 | — |
| `date_style` | enum `relative`\|`absolute` | `absolute` | Enum | site | P0 | — |
| `empty_text` | string | `''` (= „Es sind noch keine Bewertungen vorhanden.“) | `sanitize_text_field` | site | P0 | — |
| `reduced_motion` | bool | `true` | bool | site | P0 | — |
| `schema_enabled` | bool | `true` | bool; `false` schaltet die gesamte Schema-Ausgabe des SEO-Moduls ab | site | P0 | — |
| `load_css` | bool (erweitert) | `true` | bool | site | P0 | — |

- Quellenlabel/-link externer Reviews ist **nicht abschaltbar** und hat daher keinen Schalter.
- `show_avatar = true` nutzt für lokale Reviews `get_avatar()`; ob dabei Gravatar kontaktiert wird, folgt der WP-Einstellung „Avatare anzeigen“. Der Tab weist darauf hin. Externe Avatar-URLs werden in 1.0 nicht geladen (OFFEN für Pro).
- `reduced_motion = true`: keine automatischen Animationen/Auto-Rotation; zusätzlich gilt immer `@media (prefers-reduced-motion: reduce)`.
- `load_css = false`: `assets/css/frontend.css` wird nicht eingereiht; Markup und Klassen bleiben gleich (Theme stylt selbst).

### 4.6 `privacy` – Datenschutz & Aufbewahrung

Capability zum Ändern: `review_captain_manage_settings` **und** `review_captain_manage_privacy`.

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `retention_external_days` | int | `30` | 1–3650 | site | P2 | — |
| `retention_rejected_days` | int | `90` | 1–3650 | site | P2 | — |
| `retention_logs_days` | int | `90` | 1–3650 | site | P2 | — |
| `retention_jobs_days` | int | `30` | 1–3650 | site | P0 | — |
| `erase_mode` | enum `anonymize`\|`delete` | `anonymize` | Enum | site | P2 | — |
| `delete_data_on_uninstall` | bool | `false` | bool | site | P2 | — |
| `privacy_notice` | textarea | `''` (= Standardtext mit Link zur WP-Datenschutzseite) | `sanitize_textarea_field` (Klartext; Ausgabe mit `wpautop`, nur `p`/`br`) | site | P0 | — |

- `retention_external_days`: Höchstdauer, die ein externer Review-Snapshot (`origin=provider_sync|connector`) **ohne erfolgreiche Bestätigung durch die Quelle** gespeichert bleibt; danach Tombstone. Anbieterregeln mit kürzerer Frist (z. B. Trustpilot-Refresh) haben Vorrang und werden vom Adapter erzwungen.
- `retention_rejected_days`: Frist ab Statuswechsel auf `rejected`, danach endgültiges Löschen (inkl. `review_events`).
- `retention_logs_days`: gilt für `audit_log` und sanitisierte Fehlerprotokolle.
- `retention_jobs_days`: abgeschlossene Jobs (`succeeded`/`dead`) werden danach gelöscht.
- `erase_mode`: Verhalten des WordPress-Privacy-Erasers für lokale Reviews einer E-Mail-Adresse (siehe [reference.md](reference.md)).
- `delete_data_on_uninstall`: in Multisite entscheidet jede Site für sich; Uninstall iteriert die Sites.

### 4.7 `access` – Zugriff & Rollen

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `moderate_roles` | array<string> | `['administrator','editor']` | `sanitize_key`, Duplikate entfernt; beim Speichern nur existierende Rollen (`wp_roles()`), `administrator` wird immer ergänzt | site | P2 | — |
| `settings_roles` | array<string> | `['administrator']` | wie oben, `administrator` wird immer ergänzt | site | P2 | — |

- Speichern synchronisiert die Capabilities `review_captain_moderate` bzw. `review_captain_manage_settings` an den Rollen (`WP_Role::add_cap`/`remove_cap`). Rollen sind in Multisite site-lokal.
- Nur Nutzer mit `review_captain_manage_settings` **und** `promote_users` dürfen diesen Tab ändern (`SettingsPage::can_write_tab()`, verhindert Rechteausweitung durch Redakteure).
- Die übrigen Capabilities (`manage_sources`, `manage_requests`, `view_reports`, `manage_trust`, `manage_privacy`) sind in 1.0 nicht über diesen Tab konfigurierbar (Default: administrator; Anpassung über Rollen-Plugins möglich). Erweiterung ist eine offene Entscheidung, siehe [structure.md](structure.md).

### 4.8 `advanced` – Erweitert

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `job_time_budget` | int (Sekunden, erweitert) | `20` | 5–120 | site | P0 | — |
| `lease_seconds` | int (Sekunden, erweitert) | `300` | 60–3600 (Ausblick: Prüfung `> job_time_budget`) | site | P0 | — |
| `debug_logging` | bool (erweitert) | `false` | bool | site | P0 | — |
| `use_action_scheduler` | bool (erweitert) | `true` | bool; wirkt nur, wenn Action Scheduler geladen ist, sonst WP-Cron | site | P0 | — |

- `debug_logging = true` protokolliert nur sanitisierte Kontexte (keine Secrets, kein Reviewtext, keine E-Mail). Ausblick (nicht in v1): automatische Abschaltung nach 7 Tagen über eine Option `review_captain_debug_until`.

## 5. Versionierung und Migration

- `review_captain_settings_version` = `1` (`SettingsSchema::VERSION`) für dieses Schema; wird bei jedem Speichern geschrieben.
- Migration nur über `ReviewCaptain\Install\Migrator` (Aktivierung, `admin_init` mit Versionsvergleich, WP-CLI), nie im Frontend.
- Regeln je Versionsschritt: (1) Umbenennung: Wert übernehmen, alten Schlüssel löschen; (2) neues Feld: nicht schreiben, Default greift beim Lesen; (3) entferntes Feld: löschen; (4) Typänderung: konvertieren, sonst Default; (5) Default-Änderung: bestehende gespeicherte Werte bleiben, nur fehlende erhalten den neuen Default.
- Erstinstallation schreibt **keine** Kopie der Defaults (die Option existiert erst nach dem ersten Speichern). Achtung: Speichern schreibt die vollständigen effektiven Werte (inkl. Defaults) aller Tabs; spätere Default-Änderungen greifen daher nur für Felder, die nie gespeichert wurden.
- Pro-Felder migriert ausschließlich Pro.
- Ausblick (nicht in v1, da es nur Version 1 gibt): Settings-Migrationsschritte und Audit-Eintrag `settings.migrated`.

## 6. Konfigurations-Export/-Import (SET-018, Grundsatz)

JSON `{ "schema": "review_captain.settings.v1", "version": 1, "settings": {…} }`. P1-Felder nur nach ausdrücklicher Bestätigung, `S` gibt es nicht. Import: Version prüfen → Vorschau der Änderungen je Feld → Validierung wie beim Speichern → Übernahme mit vorheriger Sicherung (Rollback-Kopie in `review_captain_settings_backup`) → Audit. Rollen aus `access` werden nur übernommen, wenn sie auf der Ziel-Site existieren.

## Abnahme

Entspricht SET-003, wenn:

- [ ] jeder Schlüssel aus §4 Typ, Validierung, Default, Scope, Datenschutzklasse, Entitlement und ändernde Capability hat;
- [x] die Schlüssel 1:1 mit der PHP-Schemadefinition übereinstimmen (Tab- und Feldnamen, Defaults, Grenzen; abgeglichen 2026-11-16);
- [ ] Lese-/Schreibsemantik (Fallback auf Default, Tab-weises Speichern, Pro-Felder unverändert) durch Unit-Tests abgedeckt ist;
- [ ] Versionsmigration und Konfigurations-Export beschrieben und keine Secrets in der Option zulässig sind;
- [ ] der UI-Geltungsbereich je Feld in [inventory.md](inventory.md) und die Override-Regeln in [inheritance.md](inheritance.md) referenziert sind.
