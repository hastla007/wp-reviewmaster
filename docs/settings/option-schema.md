# Optionsschema der Einstellungen (SET-003)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Verbindlich für die Schlüssel (werden parallel in PHP umgesetzt); Wertebereiche/Grenzen sind als **Vorschlag** markiert, wo die PHP-Implementierung maßgeblich ist und abgeglichen werden muss.
Bezug: [konventionen](../architecture/conventions.md), [Inventar](inventory.md), [Vererbung](inheritance.md), [Defaults/Reset](defaults-reset.md), [Pro-Matrix](pro-matrix.md).

## 1. Speicherort

| Element | Wert |
|---|---|
| Option (Werte) | `review_captain_settings` – ein verschachteltes Array `[tab][key]` |
| Option (Schema-Version) | `review_captain_settings_version` – Ganzzahl, Start `1` |
| Autoload | `review_captain_settings`: ja (klein, wird im Frontend für `display` gebraucht); `…_version`: ja |
| Registrierung | `register_setting( 'review_captain_settings', 'review_captain_settings', [ 'sanitize_callback' => … ] )`, ein Settings-API-Formular je Tab |
| Erweiterung durch Pro | Filter `review_captain_settings_schema` (Pro ergänzt Felder, darf Core-Felder weder umbenennen noch Defaults lockern) |
| Multisite | jede Site besitzt eine eigene Option (`get_option`, nicht `get_site_option`). In 1.0 gibt es **keine** Netzwerk-Optionen; Spalte „Scope“ ist daher überall `site`. `network` ist für spätere Versionen reserviert (siehe [inheritance.md](inheritance.md)). |
| Secrets | liegen **nie** in `review_captain_settings`. API-Schlüssel/Tokens gehören zu Quellprofilen (verschlüsselt, Quellen-Seite). |
| Objekt-Einstellungen | Profile (`profiles`-Tabelle), Widgets, Formulare (Block-/Shortcode-Attribute), Kampagnen (Pro) speichern ihre Overrides am Objekt, nicht in dieser Option. |

Tab-Schlüssel (= URL-Parameter `tab` der Einstellungsseite): `general`, `sources`, `requests`, `submissions`, `display`, `privacy`, `access`, `advanced`.

## 2. Lese- und Schreibsemantik

- **Lesen:** gespeicherter Wert wird gegen das Schema normalisiert. Fehlender Tab, fehlender Schlüssel, falscher Typ oder Wert außerhalb der Validierung → **Default** des Schemas. Unbekannte Schlüssel werden ignoriert, aber nicht gelöscht (Vorwärtskompatibilität, z. B. Pro-Felder ohne aktives Pro).
- **Schreiben (Speichern eines Tabs):** nur die Felder des abgeschickten Tabs werden verarbeitet; andere Tabs bleiben unverändert. Ungültiger Wert → bisher gespeicherter (oder Default-)Wert bleibt, Fehlermeldung per `add_settings_error` am Feld. Speichern verlangt Capability `review_captain_manage_settings` und Nonce (Settings-API, `review_captain_settings-options`).
- **Pro-Felder ohne aktives Pro/Entitlement:** werden angezeigt (gesperrt), beim Speichern **nicht** verändert, beim Lesen **nicht** entfernt.
- **Booleans** werden als `true`/`false` gespeichert (nicht `'1'`/`''`). Ein nicht angehakter Checkbox-Wert im abgeschickten Tab bedeutet `false`.
- **Strings** werden getrimmt; Mehrzeilentexte mit `sanitize_textarea_field`, außer wo anders angegeben.
- **Uhrzeiten** `HH:MM` 24h in **Website-Zeitzone** (`wp_timezone()`); Start > Ende bedeutet Fenster über Mitternacht; Start = Ende ist ungültig.

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
| `brand_name` | string | `''` (= `get_bloginfo('name')`) | `sanitize_text_field`, max. 100 Zeichen | site | P1 | — |
| `default_language` | string | `''` (= Site-Sprache `get_locale()`) | `''` oder Locale aus `get_available_languages()` ∪ `en_US`, Muster `^[a-z]{2,3}(_[A-Z]{2})?(_[a-z]+)?$` | site | P0 | — |
| `date_format` | string | `''` (= WP-Option `date_format`) | `''` oder PHP-Datumsformat, max. 40 Zeichen, nur `[A-Za-z0-9 .,:/\\-]` | site | P0 | — |
| `new_review_status` | enum `pending`\|`approved` | `pending` | Enum | site | P2 | — |

Hinweis: Zeitzone ist **keine** eigene Option; maßgeblich ist die WordPress-Zeitzone (Einstellungen → Allgemein), der Tab zeigt sie nur an.
`new_review_status = approved` gilt nur für **lokale Einreichungen** (Formular). Manuelle Einträge legt die moderierende Person ohnehin mit gewähltem Status an; CSV-Import nutzt die Spalte `status`.

### 4.2 `sources` – Quellen & Synchronisierung

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `sync_window_start` | string `HH:MM` | `'02:00'` | `^([01]\d\|2[0-3]):[0-5]\d$`, ≠ `sync_window_end` | site | P0 | — |
| `sync_window_end` | string `HH:MM` | `'05:00'` | wie oben | site | P0 | — |
| `max_pages_per_run` | int | `5` | 1–50 (Vorschlag) | site | P0 | — |
| `pause_after_auth_errors` | int | `3` | 1–10 (Vorschlag) | site | P0 | — |
| `removed_review_behavior` | enum `hide`\|`tombstone` | `tombstone` | Enum | site | P2 | — |

Diese Felder gehören dem Core, weil Jobs, Provider-/Connector-Registry und Tombstone-Logik im Core liegen. Ohne registrierten Provider/Connector sind sie wirkungslos und werden mit Hinweis angezeigt („wirkt erst, wenn eine synchronisierende Quelle verbunden ist“). Credentials, Profile und Anbieter-Scopes liegen ausschließlich auf der Quellen-Seite.
Adapter dürfen strengere Werte erzwingen (z. B. Anbieter verlangt Löschung → `tombstone` unabhängig von `hide`).

### 4.3 `requests` – Anfragen & Zustellung (Pro)

Im Core nur **sichtbar gesperrt** (Beschreibung + Lizenzhinweis, keine Eingabe). Pro registriert Validierung und Wirkung über `review_captain_settings_schema`. Die Werte werden in derselben Option unter `[requests]` gespeichert; der Core verändert oder leert sie nie.

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `sender_name` | string | `''` (= `general.brand_name`) | `sanitize_text_field`, max. 100 | site | P1 | `review.review_requests` |
| `sender_email` | string | `''` (kein Versand, solange leer) | `is_email`, max. 191 | site | P1 | `review.review_requests` |
| `send_window_start` | string `HH:MM` | `'09:00'` (Vorschlag) | wie Sync-Fenster | site | P0 | `review.review_requests` |
| `send_window_end` | string `HH:MM` | `'18:00'` (Vorschlag) | wie Sync-Fenster | site | P0 | `review.review_requests` |
| `daily_limit` | int | `200` | 1–10 000 (Vorschlag) | site | P0 | `review.review_requests` |
| `cooldown_days` | int | `90` | 1–730 (Vorschlag) | site | P2 | `review.review_requests` |
| `reminders_enabled` | bool | `false` | bool | site | P2 | `review.review_requests` |

Capability zum Ändern: `review_captain_manage_settings` **und** `review_captain_manage_requests`.
Nicht als Einstellung vorhanden (feste Regeln): eine Einladung je Erlebnis + Empfänger + Kampagne; kein Versand bei fehlender/unbekannter Versandberechtigung; siteweite Opt-out-Sperre; keine Bewertungsauswahl als Routingkriterium.

### 4.4 `submissions` – Einreichungen & Moderation

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `enabled` | bool | `true` | bool | site | P2 | — |
| `require_consent` | bool | `true` | bool | site | P2 | — |
| `honeypot` | bool | `true` | bool | site | P0 | — |
| `rate_limit_per_hour` | int | `5` | 1–100 (Vorschlag), je IP-Hash und Formular | site | P0 | — |
| `min_text_length` | int | `20` | 0–1000, `< max_text_length` | site | P0 | — |
| `max_text_length` | int | `5000` | 100–20 000, `> min_text_length` | site | P0 | — |
| `allow_title` | bool | `true` | bool | site | P0 | — |
| `notify_admin` | bool | `true` | bool | site | P1 | — |

- `enabled = false`: Formular-Block/-Shortcode rendert einen neutralen Hinweis statt des Formulars; `admin-post.php`-Handler lehnt ab.
- `require_consent = true`: Pflicht-Checkbox „Veröffentlichung erlauben“, setzt `consent_publish`/`consent_at`. Bei `false` wird `consent_publish = 0` gespeichert und **das Review kann trotzdem nur nach Moderation veröffentlicht werden**; der Tab zeigt eine Warnung.
- `honeypot = false` zeigt eine Warnung; Nonce und Rate-Limit bleiben immer aktiv.
- `notify_admin`: E-Mail an `admin_email` ohne Reviewtext und ohne Autor-E-Mail, nur Link zur Moderation.
- Inkonsistente Längen (min ≥ max) → beide Felder behalten den alten Wert, Fehlermeldung.

### 4.5 `display` – Darstellung (Voreinstellung neuer Widgets)

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `show_stars` | bool | `true` | bool | site | P0 | — |
| `show_date` | bool | `true` | bool | site | P0 | — |
| `show_author` | bool | `true` | bool | site | P2 | — |
| `show_avatar` | bool | `false` | bool | site | P2 | — |
| `date_style` | enum `relative`\|`absolute` | `absolute` | Enum | site | P0 | — |
| `empty_text` | string | `''` (= „Noch keine Bewertungen vorhanden.“) | `sanitize_text_field`, max. 200 | site | P0 | — |
| `reduced_motion` | bool | `true` | bool | site | P0 | — |
| `schema_enabled` | bool | `true` | bool | site | P0 | — |
| `load_css` | bool | `true` | bool | site | P0 | — |

- Quellenlabel/-link externer Reviews ist **nicht abschaltbar** und hat daher keinen Schalter.
- `show_avatar = true` nutzt für lokale Reviews `get_avatar()`; ob dabei Gravatar kontaktiert wird, folgt der WP-Einstellung „Avatare anzeigen“. Der Tab weist darauf hin. Externe Avatar-URLs werden in 1.0 nicht geladen (OFFEN für Pro).
- `reduced_motion = true`: keine automatischen Animationen/Auto-Rotation; zusätzlich gilt immer `@media (prefers-reduced-motion: reduce)`.
- `load_css = false`: `assets/css/frontend.css` wird nicht eingereiht; Markup und Klassen bleiben gleich (Theme stylt selbst).

### 4.6 `privacy` – Datenschutz & Aufbewahrung

Capability zum Ändern: `review_captain_manage_settings` **und** `review_captain_manage_privacy`.

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `retention_external_days` | int | `30` | 1–365 (Vorschlag) | site | P2 | — |
| `retention_rejected_days` | int | `90` | 1–3650 (Vorschlag) | site | P2 | — |
| `retention_logs_days` | int | `90` | 7–730 (Vorschlag) | site | P2 | — |
| `retention_jobs_days` | int | `30` | 1–365 (Vorschlag) | site | P0 | — |
| `erase_mode` | enum `anonymize`\|`delete` | `anonymize` | Enum | site | P2 | — |
| `delete_data_on_uninstall` | bool | `false` | bool | site | P2 | — |
| `privacy_notice` | string | `''` (= Standardtext mit Link zur WP-Datenschutzseite) | `wp_kses` mit `a[href,target,rel]`, `strong`, `em`; max. 1000 | site | P0 | — |

- `retention_external_days`: Höchstdauer, die ein externer Review-Snapshot (`origin=provider_sync|connector`) **ohne erfolgreiche Bestätigung durch die Quelle** gespeichert bleibt; danach Tombstone. Anbieterregeln mit kürzerer Frist (z. B. Trustpilot-Refresh) haben Vorrang und werden vom Adapter erzwungen.
- `retention_rejected_days`: Frist ab Statuswechsel auf `rejected`, danach endgültiges Löschen (inkl. `review_events`).
- `retention_logs_days`: gilt für `audit_log` und sanitisierte Fehlerprotokolle.
- `retention_jobs_days`: abgeschlossene Jobs (`succeeded`/`dead`) werden danach gelöscht.
- `erase_mode`: Verhalten des WordPress-Privacy-Erasers für lokale Reviews einer E-Mail-Adresse (siehe [reference.md](reference.md)).
- `delete_data_on_uninstall`: in Multisite entscheidet jede Site für sich; Uninstall iteriert die Sites.

### 4.7 `access` – Zugriff & Rollen

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `moderate_roles` | array<string> | `['administrator','editor']` | nur existierende Rollen-Slugs (`wp_roles()`), Duplikate entfernt, `administrator` wird immer ergänzt | site | P2 | — |
| `settings_roles` | array<string> | `['administrator']` | wie oben, `administrator` wird immer ergänzt | site | P2 | — |

- Speichern synchronisiert die Capabilities `review_captain_moderate` bzw. `review_captain_manage_settings` an den Rollen (`WP_Role::add_cap`/`remove_cap`). Rollen sind in Multisite site-lokal.
- Nur Nutzer mit `review_captain_manage_settings` **und** `promote_users` dürfen diesen Tab ändern (Vorschlag, verhindert Rechteausweitung durch Redakteure).
- Die übrigen Capabilities (`manage_sources`, `manage_requests`, `view_reports`, `manage_trust`, `manage_privacy`) sind in 1.0 nicht über diesen Tab konfigurierbar (Default: administrator; Anpassung über Rollen-Plugins möglich). Erweiterung ist eine offene Entscheidung, siehe [structure.md](structure.md).

### 4.8 `advanced` – Erweitert

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `job_time_budget` | int (Sekunden) | `20` | 5–60 (Vorschlag) | site | P0 | — |
| `lease_seconds` | int (Sekunden) | `300` | 60–3600, muss `> job_time_budget` sein | site | P0 | — |
| `debug_logging` | bool | `false` | bool | site | P0 | — |
| `use_action_scheduler` | bool | `true` | bool; wirkt nur, wenn Action Scheduler geladen ist, sonst WP-Cron | site | P0 | — |

- `debug_logging = true` protokolliert nur sanitisierte Kontexte (keine Secrets, kein Reviewtext, keine E-Mail) und schaltet sich nach 7 Tagen automatisch ab (Vorschlag; Ablaufzeit in separater Option `review_captain_debug_until`).

## 5. Versionierung und Migration

- `review_captain_settings_version` = `1` für dieses Schema.
- Migration nur über `ReviewCaptain\Install\Migrator` (Aktivierung, `admin_init` mit Versionsvergleich, WP-CLI), nie im Frontend.
- Regeln je Versionsschritt: (1) Umbenennung: Wert übernehmen, alten Schlüssel löschen; (2) neues Feld: nicht schreiben, Default greift beim Lesen; (3) entferntes Feld: löschen; (4) Typänderung: konvertieren, sonst Default; (5) Default-Änderung: bestehende gespeicherte Werte bleiben, nur fehlende erhalten den neuen Default.
- Erstinstallation schreibt **keine** vollständige Kopie der Defaults, sondern nur `review_captain_settings_version` und ein leeres Array; so greifen spätere Default-Verbesserungen für nie geänderte Felder.
- Pro-Felder migriert ausschließlich Pro.
- Jede Migration erzeugt einen Audit-Eintrag `settings.migrated` (von → nach Version).

## 6. Konfigurations-Export/-Import (SET-018, Grundsatz)

JSON `{ "schema": "review_captain.settings.v1", "version": 1, "settings": {…} }`. P1-Felder nur nach ausdrücklicher Bestätigung, `S` gibt es nicht. Import: Version prüfen → Vorschau der Änderungen je Feld → Validierung wie beim Speichern → Übernahme mit vorheriger Sicherung (Rollback-Kopie in `review_captain_settings_backup`, Vorschlag) → Audit. Rollen aus `access` werden nur übernommen, wenn sie auf der Ziel-Site existieren.

## Abnahme

Entspricht SET-003, wenn:

- [ ] jeder Schlüssel aus §4 Typ, Validierung, Default, Scope, Datenschutzklasse, Entitlement und ändernde Capability hat;
- [ ] die Schlüssel 1:1 mit der PHP-Schemadefinition übereinstimmen (Tab- und Feldnamen, Defaults) und als „(Vorschlag)“ markierte Grenzen mit der Implementierung abgeglichen sind;
- [ ] Lese-/Schreibsemantik (Fallback auf Default, Tab-weises Speichern, Pro-Felder unverändert) durch Unit-Tests abgedeckt ist;
- [ ] Versionsmigration und Konfigurations-Export beschrieben und keine Secrets in der Option zulässig sind;
- [ ] der UI-Geltungsbereich je Feld in [inventory.md](inventory.md) und die Override-Regeln in [inheritance.md](inheritance.md) referenziert sind.
