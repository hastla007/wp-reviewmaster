# Optionsschema der Einstellungen (SET-003)

Status: an den implementierten Stand angeglichen, Stand 2026-11-16. Maßgeblich sind `src/Settings/SettingsSchema.php` (Felder, Typen, Defaults, Grenzen) und `src/Settings/Settings.php` (Lesen/Schreiben); Typen, Defaults und Wertebereiche unten sind **exakt** aus dem Code übernommen. Wo dieses Dokument und der Code abweichen, gilt der Code; nicht umgesetzte Zusatzregeln früherer Fassungen sind als „Ausblick (nicht in v1)“ markiert.
Bezug: [konventionen](../architecture/conventions.md), [Inventar](inventory.md), [Vererbung](inheritance.md), [Defaults/Reset](defaults-reset.md), [Pro-Matrix](pro-matrix.md).

## 1. Speicherort

| Element | Wert |
|---|---|
| Option (Werte) | `review_captain_settings` – ein verschachteltes Array `[tab][key]` |
| Option (Schema-Version) | `review_captain_settings_version` – Ganzzahl, Start `1`, aktuell `2` (§5) |
| Autoload | `review_captain_settings`: ja (klein, wird im Frontend für `display` gebraucht); `…_version`: nein |
| Schreiben | eigenes Formular je Tab über `SettingsPage::save_tab()` (Nonce + Capability), Sanitisierung über `Settings::sanitize()` → `SettingsSchema::sanitize_value()` |
| Erweiterung durch Pro | Filter `review_captain_settings_schema` (Add-ons ergänzen Felder; Core-Felder können weder entfernt noch umdefiniert werden, Einträge ohne `type`/`default` werden verworfen, fehlende Schlüssel erhalten die Standardwerte, `owner` dabei `pro`). Add-on-Felder (`owner = pro`) werden nur geschrieben, wenn der Filter `review_captain_pro_field_writable` (`bool $writable, string $tab, string $key`) für **dieses Feld** `true` liefert; seine Vorgabe ist die Antwort des älteren, pauschalen Filters `review_captain_pro_settings_writable` (Entscheidung der Inhaberin 24.11.2026: Schreibrecht je Feld). Der Core selbst definiert seit SET-RC-06 **kein** Add-on-Feld und kennt keine Lizenzschlüssel; für verkaufte Pro-Funktionen zeigt er nur graue Hinweiszeilen (§4.3, [pro-matrix.md](pro-matrix.md)) |
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
  - `array` mit `choices`: nur erlaubte Werte; mit `min_items`: weniger Einträge → Default; `max_items` kürzt;
  - `email_list` (SET-RC-09): Array oder durch Zeilenumbruch/Komma/Semikolon getrennter Text → Liste gültiger, eindeutiger Adressen, höchstens `max_items`; verworfene Einträge meldet die Seite als `invalid`;
  - `textarea`: `sanitize_textarea_field`; `string`: `sanitize_text_field`; mit `max_length` auf so viele Zeichen gekürzt.
  - `color` (SET-RC-22): leer oder Hex-Farbe `#rgb`/`#rrggbb` (klein geschrieben), sonst Default.
  Diese Sanitisierung gilt für Import, Rücksicherung und programmatische Aufrufe. **Einstellungsseite und WP-CLI prüfen strenger (SET-RC-19):** `SettingsSchema::validate_value()` korrigiert nicht, sondern meldet einen Fehler (keine ganze Zahl, außerhalb `min`/`max`, unbekannte Auswahl, Uhrzeit nicht `HH:MM`, ungültige E-Mail, Text länger als `max_length`); das Feld behält seinen gespeicherten Wert, alle gültigen Felder des Tabs werden gespeichert. Danach `SettingsSchema::cross_check()`: `submissions.min_text_length < max_text_length`, `requests.send_window_start ≠ send_window_end`, `sources.sync_window_start ≠ sync_window_end`, `advanced.lease_seconds > job_time_budget` (Filter `review_captain_settings_cross_check`); schlägt eine Prüfung fehl, behalten alle beteiligten Felder ihren gespeicherten Wert – auch beim Import (`ConfigTransfer::preview()` liefert sie unter `kept`, Seite und WP-CLI zeigen sie an). Die Seite zeigt eine Fehlerliste mit Sprungmarken und setzt `aria-invalid` am Feld. Geänderte Felder werden als Audit-Ereignis `settings.changed` mit altem und neuem Wert protokolliert ([audit.md](audit.md)). Speichern verlangt Capability `review_captain_manage_settings` und Nonce; Tab `privacy` zusätzlich `review_captain_manage_privacy`, Tab `access` zusätzlich `promote_users` (`SettingsPage::can_write_tab()`).
- **Add-on-Felder ohne aktives Add-on bzw. ohne freigeschaltete Funktion:** sind nicht registriert und werden nicht angezeigt (an ihrer Stelle steht höchstens eine graue Hinweiszeile); ihre gespeicherten Werte werden beim Speichern anderer Tabs, beim Zurücksetzen und beim Import **nicht** verändert und beim Lesen **nicht** entfernt (`Settings::merge()` behält unbekannte Schlüssel). Erlaubt das Add-on das Schreiben, übernimmt der Import seine Felder wie das Speichern (Export/Import-Rundlauf); das Zurücksetzen lässt sie auch dann unverändert. Registriert ein Add-on seine Felder, verweigert aber das Schreiben (`review_captain_pro_field_writable` für das Feld), erscheinen sie deaktiviert mit dem neutralen Vermerk „Von einer Erweiterung verwaltet; derzeit nicht änderbar.“
- **Booleans** werden als `true`/`false` gespeichert (nicht `'1'`/`''`). Ein nicht angehakter Checkbox-Wert im abgeschickten Tab bedeutet `false`.
- **Felddefinition** (`SettingsSchema::fields()`): Schlüssel `type` (`string`\|`int`\|`bool`\|`enum`\|`array`\|`time`\|`email`\|`textarea`\|`color`), `default`, `choices`, `min`/`max`, `owner` (`core`\|`pro`, Default `core`; für Felder aus dem Filter `pro`), `privacy` (`none`\|`personal`\|`config`, Default `none`), `advanced` (progressive Offenlegung, Default `false`), `scope` (`site`\|`network`, Default `site`), `internal` (vom Plugin selbst geschrieben: nicht angezeigt, nicht exportiert/importiert, nicht über Eingaben setzbar; derzeit nur `privacy.delete_data_confirmed_at`). Nur für Add-on-Felder: `label`, `description` (Beschriftung und Hilfetext, wenn `SettingsFields` keine hat), `section` (Abschnittsüberschrift) und `replaces_hint` (Kennung oder Liste von Kennungen der Hinweiszeilen, die das Feld ersetzt). Einen Lizenzschlüssel (`entitlement`) trägt kein Feld mehr (SET-RC-06). Die Datenschutzklassen P0–P2 unten sind eine Dokumentationssicht; im Code markiert `privacy = config` die Kontaktangaben des Betreibers (P1: `general.brand_name`, `submissions.notify_recipients`, die Absenderfelder `requests.sender_name`/`sender_email` des Add-ons). Solche Felder exportiert der Konfigurations-Export nur mit „Mit Kontaktangaben exportieren“ bzw. `--with-contacts` (P1-8, §6); Add-on-Felder schließen sich mit demselben Attribut an. `privacy = personal` wird nie exportiert.
- **Strings** werden getrimmt; Mehrzeilentexte mit `sanitize_textarea_field`, außer wo anders angegeben.
- **Uhrzeiten** `HH:MM` 24h in **Website-Zeitzone** (`wp_timezone()`); Start > Ende bedeutet Fenster über Mitternacht. Start = Ende wird beim Speichern zurückgewiesen (SET-RC-19).

## 3. Datenschutzklassen

| Klasse | Bedeutung | Folge |
|---|---|---|
| `P0` | keine personenbezogenen Daten | normal exportierbar (Konfigurations-Export), Audit mit Alt-/Neuwert |
| `P1` | kann personenbezogene Daten des Betreibers enthalten (z. B. Absender-E-Mail, Markenname = Personenname) | Konfigurations-Export nur nach Bestätigung; Audit maskiert |
| `P2` | steuert Verarbeitung personenbezogener Daten Dritter (Einwilligung, Fristen, Löschung, Rollen) | Änderung immer auditpflichtig; Hilfetext mit Datenschutzbezug Pflicht |
| `S` | Secret | **nicht zulässig** in dieser Option |

## 4. Felder

Spalten: **Typ** · **Default** · **Validierung** · **Scope** · **Klasse** · **Entitlement** (`—` = Core, ohne Lizenz; alle Felder dieser Liste außer §4.3 gehören dem Core) · **Capability** (zum Ändern; immer zusätzlich Nonce).
Standard-Capability ist `review_captain_manage_settings`; Abweichungen sind angegeben.

### 4.1 `general` – Allgemein

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `brand_name` | string | `''` (= `get_bloginfo('name')`) | `sanitize_text_field`; `privacy = config` | site | P1 | — |
| `date_format` | string | `''` (= WP-Option `date_format`) | `sanitize_text_field` | site | P0 | — |
| `new_review_status` | enum `pending`\|`approved` | `pending` | Enum | site | P2 | — |
| `show_pro_hints` | bool | `true` | bool | site | P0 | — |

Hinweis: Zeitzone ist **keine** eigene Option; maßgeblich ist die WordPress-Zeitzone (Einstellungen → Allgemein), der Tab zeigt sie nur an.
Ebenso gibt es keine eigene Sprache: maßgeblich sind Website- bzw. Benutzersprache von WordPress. `default_language` wurde nie gelesen und ist seit SET-RC-05 entfernt; gespeicherte Altwerte bleiben in der Option liegen (`Settings::merge` behält unbekannte Schlüssel), werden aber nirgends ausgewertet.
`show_pro_hints` (SET-RC-06) schaltet alle Pro-Hinweise ab: die grauen Hinweiszeilen am Ende der Tabs, den Kasten „In Review Captain Pro“ in der Übersicht (Filter `review_captain_show_pro_info` hat Vorrang) und den Vermerk „API-Verbindung in Review Captain Pro“ auf der Quellen-Seite. Am Funktionsumfang ändert er nichts.
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
`sync_window_*`, `max_pages_per_run` und `pause_after_auth_errors` liest nur Review Captain Pro (automatischer Abgleich); sie stehen deshalb in der Ausnahmeliste der Release-Prüfung (siehe [release-gate.md](release-gate.md)).
`removed_review_behavior` wertet `Providers\SyncService` aus (SET-RC-05): Status `removed` immer; bei `tombstone` zusätzlich sofortiges Leeren von `title`, `body`, `author_name`, `rating_value` (NULL), `meta.avatar_url`, `meta.reply` und personenbezogenen Meta-Schlüsseln, Löschen des Fotos, `meta.tombstone = {reason, at}` und Verlaufseintrag `content_removed` (`ReviewRepository::scrub_removed()`); bei `hide` bleibt der Inhalt bis `retention_external_days`. Adapter dürfen über den Filter `review_captain_removed_review_behavior` nur verschärfen (`hide` → `tombstone`).

### 4.3 `requests` – Anfragen & Zustellung (Felder von Review Captain Pro)

Der Core definiert in diesem Tab **keine Felder** (SET-RC-06). Ohne Add-on stehen dort nur graue Hinweiszeilen (oder, wenn `general.show_pro_hints` aus ist, der Satz „Hier erscheinen Einstellungen von Erweiterungen.“), kein Formular und keine Speichern-Schaltfläche. Review Captain Pro meldet die folgenden Felder über `review_captain_settings_schema` an, solange seine Funktion „Bewertungsanfragen“ aktiv ist (SET-RC-07), mit `owner = pro`, eigener Beschriftung, Abschnitt und `replaces_hint`. Die Werte liegen in derselben Option unter `[requests]`; der Core verändert oder leert sie nie, auch nicht ohne Add-on. Die Prüfung `send_window_start ≠ send_window_end` bleibt in `SettingsSchema::cross_check()` und greift, sobald das Add-on die Felder angemeldet hat.

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Ersetzt Hinweis |
|---|---|---|---|---|---|---|
| `sender_name` | string | `''` (= `general.brand_name`) | `sanitize_text_field` | site | P1 (`privacy = config`) | `requests.invitations`, `requests.sender` |
| `sender_email` | email | `''` (kein Versand, solange leer) | `sanitize_email` + `is_email` | site | P1 (`privacy = config`) | `requests.sender` |
| `send_window_start` | time `HH:MM` | `'09:00'` | wie Sync-Fenster | site | P0 | `requests.send_window` |
| `send_window_end` | time `HH:MM` | `'18:00'` | wie Sync-Fenster | site | P0 | `requests.send_window` |
| `daily_limit` | int | `200` | 1–100 000 | site | P0 | `requests.limits` |
| `cooldown_days` | int | `90` | 0–3650 | site | P2 | `requests.limits` |
| `reminders_enabled` | bool | `false` | bool | site | P2 | `requests.reminders` |

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
| `tags_enabled` | bool | `false` | bool | site | P0 | — |
| `reports_enabled` | bool | `false` | bool | site | P1 | — |
| `report_threshold` | int (erweitert) | `1` | 1–20 | site | P0 | — |
| `report_rate_limit_per_hour` | int (erweitert) | `5` | 1–100, je IP-Hash | site | P0 | — |
| `photos_enabled` | bool | `false` | bool | site | P2 | — |
| `photo_max_kb` | int | `2048` | 100–10 240 | site | P0 | — |
| `multi_product_enabled` | bool | `false` | bool | site | P1 | — |
| `qa_enabled` | bool | `false` | bool | site | P2 | — |
| `feedback_campaigns_enabled` | bool | `false` | bool | site | P0 | — |
| `corrections_enabled` | bool | `false` | bool | site | P2 | — |
| `consent_text` | textarea | `''` (= heutiger Einwilligungstext) | `max_length` 2000; Platzhalter `{privacy_link}` | site | P2 | — |
| `name_label` | string | `''` (= „Name (wird veröffentlicht)“) | `max_length` 100 | site | P0 | — |
| `email_hint` | string | `''` (= heutiger Hinweis) | `max_length` 300 | site | P2 | — |
| `submit_label` | string | `''` (= „Bewertung senden“) | `max_length` 60 | site | P0 | — |
| `notify_recipients` | email_list | `[]` (= `admin_email`) | gültige Adressen, `max_items` 10; `privacy = config` | site | P1 | — |
| `notify_subject` | string | `''` (= Standardbetreff) | `max_length` 200; `{site}`, `{moderation_url}`, `{status}` | site | P0 | — |
| `notify_body` | textarea | `''` (= Standardtext) | `max_length` 2000; `{site}`, `{moderation_url}`, `{status}` | site | P0 | — |
| `notify_questions` | bool | `true` | bool | site | P0 | — |
| `report_reasons` | array | alle 5 Gründe | `choices` = `ReportStore::REASONS`, `min_items` 1 | site | P0 | — |
| `report_notify` | enum `none`\|`recipients` | `recipients` | Enum | site | P0 | — |
| `report_confirmation` | string | `''` (= „Danke für Ihre Meldung. …“) | `max_length` 300 | site | P0 | — |
| `correction_link_hours` | int (erweitert) | `1` | 1–72 | site | P0 | — |
| `min_seconds` | int (erweitert) | `3` | 1–120; Filter `review_captain_form_min_seconds` (0–120) gewinnt | site | P0 | — |
| `hold_words` | textarea (Liste) | `''` | `sanitize_textarea_field`; ein Eintrag je Zeile oder Komma, höchstens 500 | site | P0 | — |
| `hold_links` | bool | `false` | bool | site | P0 | — |
| `block_email_domains` | textarea (Liste) | `''` | wie `hold_words`; Filter `review_captain_blocked_email_domains` gewinnt | site | P2 | — |
| `photo_max_edge` | int (erweitert) | `1600` | 400–4000; Filter `review_captain_photo_max_edge` gewinnt | site | P0 | — |
| `trusted_proxy_header` | enum (erweitert) `none`\|`x-forwarded-for`\|`x-real-ip`\|`cf-connecting-ip` | `none` | Enum; Filter `review_captain_trusted_proxy_header` gewinnt | site | P1 | — |
| `captcha_provider` | enum `none`\|`pow` | `none` | Enum; unbekannte Werte gelten als `none`. Schwierigkeit: Filter `review_captain_pow_max_number_v1`; externe Anbieter nur über `review_captain_spam_checks_v1` (nur nach Rechtsprüfung) | site | P0 | — |

- Moderationsregeln (SET-RC-10): `hold_words`/`hold_links` setzen eine neue Einreichung nur auf `pending` (nie löschen); Verlauf `held_for_moderation` mit Regelkennung. Filter `review_captain_submission_hold_reasons` kann Gründe ergänzen oder entfernen. `block_email_domains` lehnt mit Fehlercode `email_blocked` (neutraler Text) ab, ohne zu speichern.
- `trusted_proxy_header` (SET-RC-29): `Security\RateLimiter::client_ip()` liest die Kopfzeile nur, wenn gesetzt; aus einer Liste gilt die letzte gültige IP, sonst `REMOTE_ADDR`. Gilt für Bewertungen, Meldungen und Fragen.
- `captcha_provider` (SET-RC-11): `Security\PowCaptcha::check()` läuft in Bewertungs-, Melde- und Frageformular nach Nonce, Spam-Falle und Ausfüllzeit; `pow` prüft das Feld `_rc_pow` gegen den Bereich `review|<Kontexttyp>|<Kontext-ID>`, `question|…|…` bzw. `report|<Review-UUID>` (Verfahren `Spam\ProofOfWork\ProofOfWork`, Kopie aus Form Captain, siehe `docs/spam-proof-of-work.md`), danach der Filter `review_captain_spam_checks_v1`. Ablehnung mit Code `pow_missing`, `pow_invalid`, `pow_replayed` bzw. `spam_check`, ohne zu speichern. Anders als Form Captain ohne Ausnahme für Besucher ohne JavaScript.
- `photo_max_edge` (SET-RC-27): `photo_max_count` gibt es nicht – das Datenmodell (`review_media`, URL je Review-UUID, eine Karte = ein Foto) ist auf ein Foto ausgelegt.
- `enabled = false`: Formular-Block/-Shortcode rendert einen neutralen Hinweis statt des Formulars; `admin-post.php`-Handler lehnt ab.
- `require_consent = true`: Pflicht-Checkbox „Veröffentlichung erlauben“, setzt `consent_publish`/`consent_at`. Bei `false` wird `consent_publish = 0` gespeichert und **das Review kann trotzdem nur nach Moderation veröffentlicht werden**; der Tab zeigt eine Warnung.
- `honeypot = false` zeigt eine Warnung; Nonce und Rate-Limit bleiben immer aktiv.
- `notify_admin`: E-Mail an `notify_recipients` (leer = `admin_email`) ohne Reviewtext und ohne Autor-E-Mail, nur Link zur Moderation (`Moderation\Notifier`). Dieselben Empfänger erhalten bei `report_notify = recipients` eine Mail je angenommener Meldung und bei `notify_questions` eine Mail je neuer Frage. Filter: `review_captain_notify_recipients`, `review_captain_notification`, `review_captain_moderation_url`.
- `consent_text`, `name_label`, `email_hint`, `submit_label`, `report_confirmation` (SET-RC-04/-26): leer = übersetzter Standardtext; Filter `review_captain_public_text` (`$text`, `"tab.key"`) gewinnt. `consent_text` und `email_hint` sind Rechtstexte (Rechtsprüfung offen).
- `report_reasons`: Gründe außerhalb der Auswahl lehnt `ReportHandler` als `invalid` ab; Filter `review_captain_report_reasons`.
- **M7 / Post-MVP (Schema v2), alle standardmäßig aus** – sie verändern den 1.0-Umfang nicht:
  - `tags_enabled` (RC-124): interne Tags im Review-Editor und Tag-Filter in der Liste; nie öffentlich.
  - `reports_enabled` (RC-124): Link „Melden“ (`<details>`-Formular, ohne JavaScript) unter freigegebenen Reviews; admin-post `review_captain_report` mit Nonce, Grund aus fester Liste, Rate-Limit `report_rate_limit_per_hour` (und eine Meldung je Adresse/Review/Stunde). Ab `report_threshold` offenen Meldungen wird das Review `flagged` (ausgeblendet). Filter „Nur mit offenen Meldungen“ in der Liste.
  - `photos_enabled`/`photo_max_kb` (RC-121): optionales Foto (JPG/PNG/WebP, Inhaltsprüfung per finfo, max. 40 MP), Neukodierung mit GD (entfernt Exif/GPS, längste Kante 1600 px); ohne GD werden Fotos abgelehnt. Nur im Einzelformular.
  - `multi_product_enabled` (RC-123): `[review_captain_form v="1" context_type="product" context_ids="11,12"]` – signierte Liste, je bewertetem Kontext ein Review; je Person (E-Mail) und Kontext höchstens ein Review (`reviews.submission_key`, UNIQUE) – gilt bei aktivem Schalter auch für das Einzelformular mit Kontext.
  - `qa_enabled` (RC-122): Shortcode `[review_captain_qa]` und Admin-Seite „Fragen & Antworten“.
  - `feedback_campaigns_enabled` (RC-126): Admin-Seite „Feedback-Links“ (QR-SVG, wa.me-Link), Capability `review_captain_manage_settings`.
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
| `show_photos` | bool | `false` | bool | site | P2 | — |
| `verification_notice_show` | bool | `true` | bool | site | P0 | — |
| `verification_notice_title` | string | `''` (= „So prüfen wir Bewertungen“) | `max_length` 80 | site | P0 | — |
| `verification_notice` | textarea | `''` (= neutraler Entwurf je vorkommender Prüfstufe) | `max_length` 3000 | site | P0 | — |
| `verification_notice_position` | enum `below`\|`above` | `below` | Enum | site | P0 | — |
| `label_purchase_verified` | string | `''` (= „Verifizierter Kauf“) | `max_length` 60 | site | P0 | — |
| `label_attendance_confirmed` | string | `''` (= „Teilnahme bestätigt“) | `max_length` 60 | site | P0 | — |
| `label_unverified` | string | `''` (= „Ungeprüft“) | `max_length` 60 | site | P0 | — |
| `label_external` | string | `''` (= „Externe Plattformbewertung“) | `max_length` 60 | site | P0 | — |
| `label_testimonial` | string | `''` (= „Erfahrungsbericht dieser Website“) | `max_length` 60 | site | P0 | — |
| `show_unverified_label` | bool | `false` | bool | site | P0 | — |
| `star_color` | color | `''` (= Theme/CSS) | Hex `#rgb`/`#rrggbb` | site | P0 | — |
| `accent_color` | color | `''` (= Theme/CSS) | Hex `#rgb`/`#rrggbb` | site | P0 | — |
| `rating_display` | enum `stars`\|`number`\|`both` | `both` | Enum | site | P0 | — |
| `decimal_places` | int | `1` | 0–2 | site | P0 | — |
| `schema_contexts` | array (`product`, `post`, `event`) | alle drei | `sanitize_key`; beim Lesen Schnittmenge mit `SchemaEligibility::ELIGIBLE_CONTEXTS` | site | P0 | — |
| `schema_min_reviews` | int | `1` | 1–50; Filter `review_captain_schema_min_reviews` gewinnt | site | P0 | — |
| `widget_limit` | int | `6` | 1–50 | site | P0 | — |
| `widget_layout` | enum `grid`\|`list` | `grid` | Enum | site | P0 | — |
| `widget_columns` | int | `3` | 1–4 | site | P0 | — |
| `widget_heading_level` | int | `2` | 2–6 | site | P0 | — |
| `widget_new_tab` | bool | `false` | bool | site | P0 | — |

- Farben (SET-RC-22) werden als `--rc-color-star`/`--rc-color-accent` per `wp_add_inline_style` an `review-captain-frontend` gehängt (`FrontendModule::inline_css()`), leer = nichts.
- `rating_display`: Sterne nur für Skala 1–5; ohne Sterne immer Zahl; `show_stars = false` blendet Sterne immer aus. `decimal_places` gilt für Durchschnitte (`Renderer::format_average()`).
- `schema_*` (SET-RC-23): neue Gründe `context_disabled` (E4) und `below_minimum` (Seitenebene, `SeoModule::last_reason()`); `SeoStatus` zeigt Einschränkungen an. Erfahrungsberichte erhalten nie Markup (E2), daher kein Schalter.
- `widget_*` (SET-RC-28): `WidgetDefinition::global_defaults()` (Filter `review_captain_widget_defaults` gewinnt) gilt für alle Attribute ohne eigenen Wert; Block-Attribute `limit`, `layout`, `columns`, `headingLevel`, `newTab` haben deshalb keinen Default in `block.json`, Elementor-Steuerelemente stehen auf „Standard“.
- Quellenlabel/-link externer Reviews ist **nicht abschaltbar** und hat daher keinen Schalter.
- `show_avatar = true` nutzt für lokale Reviews `get_avatar()`; ob dabei Gravatar kontaktiert wird, folgt der WP-Einstellung „Avatare anzeigen“. Der Tab weist darauf hin. Externe Avatar-URLs werden in 1.0 nicht geladen (OFFEN für Pro).
- `reduced_motion = true`: keine automatischen Animationen/Auto-Rotation; zusätzlich gilt immer `@media (prefers-reduced-motion: reduce)`.
- `load_css = false`: `assets/css/frontend.css` wird nicht eingereiht; Markup und Klassen bleiben gleich (Theme stylt selbst).
- `show_photos` (RC-121, Schema v2): Fotos nur bei `status = approved` und diesem Schalter; Moderierende sehen sie immer im Editor.
- `verification_notice*` (SET-RC-01): `<details class="rc-verification">` (Vorlage `verification-notice.php`) in `reviews-grid.php` und `review-form.php`, über oder unter dem Inhalt. Der Entwurf nennt nur Stufen aus `ReviewRepository::levels_in_use()` (freigegebene, nicht entfernte Reviews; zwischengespeichert je Cache-Generation), am Formular zusätzlich `unverified`. Filter `review_captain_verification_notice` gewinnt. Rechtstext, Rechtsprüfung offen.
- `label_*`, `show_unverified_label` (SET-RC-02): nur Anzeige-Texte der Badges `rc-verified`, `rc-verified--attendance`, `rc-badge--external`, `rc-badge--testimonial`, `rc-badge--unverified`; die Stufe (`reviews.verification`) bleibt unverändert und wird nie aus Einstellungen gesetzt.

### 4.6 `privacy` – Datenschutz & Aufbewahrung

Capability zum Ändern: `review_captain_manage_settings` **und** `review_captain_manage_privacy`.

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `retention_external_days` | int | `30` | 1–3650 | site | P2 | — |
| `retention_rejected_days` | int | `90` | 1–3650; auch für zurückgezogene Bewertungen (P2-14) | site | P2 | — |
| `retention_logs_days` | int | `90` | 1–3650 | site | P2 | — |
| `retention_jobs_days` | int | `30` | 1–3650 | site | P0 | — |
| `retention_audit_days` | int | `365` | 1–3650; Filter `review_captain_retention_audit_days` gewinnt | site | P2 | — |
| `retention_contact_days` | int | `365` für neue Installationen; bestehende behalten `0` (= unbegrenzt, Migration Schritt 2) | 0–3650; gilt für Bewertungen und Produktfragen | site | P2 | — |
| `retention_approved_days` | int | `0` (= unbegrenzt) | 0–3650 | site | P2 | — |
| `retention_corrections_days` | int | `90` | 1–3650 | site | P2 | — |
| `erase_mode` | enum `anonymize`\|`delete` | `anonymize` | Enum | site | P2 | — |
| `delete_data_on_uninstall` | bool | `false` | bool; Einschalten nur mit Bestätigungswort `LÖSCHEN` (auch `LOESCHEN`, `DELETE`, Groß-/Kleinschreibung egal) | site | P2 | — |
| `delete_data_confirmed_at` | string (intern) | `''` | UTC `Y-m-d H:i:s` der Bestätigung; nur vom Plugin gesetzt | site | P2 | — |
| `privacy_notice` | textarea | `''` (= Standardtext mit Link zur WP-Datenschutzseite) | `sanitize_textarea_field` (Klartext; Ausgabe mit `wpautop`, nur `p`/`br`) | site | P0 | — |

- `retention_external_days`: Höchstdauer, die ein externer Review-Snapshot (`origin=provider_sync|connector`) **ohne erfolgreiche Bestätigung durch die Quelle** gespeichert bleibt; danach Tombstone. Anbieterregeln mit kürzerer Frist (z. B. Trustpilot-Refresh) haben Vorrang und werden vom Adapter erzwungen.
- `retention_rejected_days`: Frist ab Statuswechsel auf `rejected`, danach endgültiges Löschen (inkl. `review_events`). Gilt ebenso für abgelehnte Produktfragen und (P2-14, 24.11.2026) für von der Autorin bzw. dem Autor zurückgezogene Bewertungen: `CorrectionService::withdraw()` archiviert mit Grund `author_withdrawn`; ist das der letzte Statuswechsel und liegt er länger als die Frist zurück, löscht `Maintenance\Retention` die Bewertung samt Verlauf (Ergebnis `withdrawn_deleted`). Von der Moderation archivierte Bewertungen bleiben.
- `retention_logs_days`: gilt für `audit_log` und sanitisierte Fehlerprotokolle, **außer** Einträgen zu Einstellungen und Rollen (`object_type` `settings`/`capability`).
- `retention_audit_days` (SET-RC-17): Frist für diese Einstellungs- und Rolleneinträge (`AuditLog::SETTINGS_OBJECT_TYPES`), getrennt von `retention_logs_days`.
- `retention_jobs_days`: abgeschlossene Jobs (`succeeded`/`dead`) werden danach gelöscht.
- `erase_mode`: Verhalten des WordPress-Privacy-Erasers für lokale Reviews einer E-Mail-Adresse (siehe [reference.md](reference.md)).
- `delete_data_on_uninstall`: in Multisite entscheidet jede Site für sich; Uninstall iteriert die Sites. Gelöscht wird nur, wenn zusätzlich `delete_data_confirmed_at` gesetzt ist (SET-RC-20, `Uninstaller::should_delete()`). Ausschalten leert den Zeitpunkt; ein Import kann die Löschung nie einschalten. Vor SET-RC-20 eingeschaltete Websites behalten ihre Daten, bis jemand das Wort eintippt.
- `retention_contact_days` (SET-RC-08): `Maintenance\Retention` setzt `author_email` und `submission_key` von Reviews mit `created_at` älter als die Frist auf NULL (`ReviewRepository::remove_contact()`, Verlauf `contact_removed`, Audit `retention.contacts_removed`). Dieselbe Frist leert seit P4-6 `questions.author_email` (Frage, Antwort und Name bleiben; `QuestionRepository::remove_contact_before()`, Audit `retention.question_contacts_removed`). Standard nach Entscheidung E5 (24.11.2026, Rechtsprüfung offen): 365 Tage für neue Installationen; bestehende Installationen behalten 0 = unbegrenzt (Einstellungsmigration Schritt 2, §5), bis der Betreiber umstellt. Solange 0 gilt, mahnen Website-Zustand, Dashboard und Reiter Datenschutz (S9). Vorschau: `Retention::preview()` im Reiter („betrifft N Bewertungen“ bzw. „N Produktfragen“).
- `retention_approved_days`: löscht freigegebene lokale Reviews (`kind = review`) nach `created_at` + Frist endgültig; 0 = nie.
- `retention_corrections_days` (SET-RC-21): Frist für entschiedene Korrekturfassungen (`CorrectionService::purge_old()`).

### 4.7 `access` – Zugriff & Rollen

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `moderate_roles` | array<string> | `['administrator','editor']` | `sanitize_key`, Duplikate entfernt; beim Speichern nur existierende Rollen (`wp_roles()`), `administrator` wird immer ergänzt | site | P2 | — |
| `sources_roles` | array<string> | `['administrator']` | wie oben | site | P2 | — |
| `requests_roles` | array<string> | `['administrator']` | wie oben | site | P2 | — |
| `reports_roles` | array<string> | `['administrator']` | wie oben | site | P2 | — |
| `trust_roles` | array<string> | `['administrator']` | wie oben | site | P2 | — |
| `privacy_roles` | array<string> | `['administrator']` | wie oben | site | P2 | — |
| `settings_roles` | array<string> | `['administrator']` | wie oben | site | P2 | — |

- SET-RC-16: je Capability eine Rollenliste (`Capabilities::ROLE_KEYS`: `moderate_roles` → `review_captain_moderate`, `sources_roles` → `manage_sources`, `requests_roles` → `manage_requests`, `reports_roles` → `view_reports`, `trust_roles` → `manage_trust`, `privacy_roles` → `manage_privacy`, `settings_roles` → `manage_settings`). Die Seite zeigt sie als Matrix Rolle × Recht. Speichern synchronisiert die Capabilities an den Rollen (`WP_Role::add_cap`/`remove_cap`); je Capability, deren Rollen sich wirklich ändern, ein Audit-Eintrag `roles.capabilities_changed` (vorher/nachher). Rollen sind in Multisite site-lokal.
- Nur Nutzer mit `review_captain_manage_settings` **und** `promote_users` dürfen diesen Tab ändern (`SettingsPage::can_write_tab()`). Zusätzlich gilt für jede Spalte: Nur wer die Capability selbst hat, kann sie vergeben oder entziehen (`SettingsPage::keep_unheld_role_keys()`, auch bei Import, Reset und Rücksicherung); der Administrator behält immer alle Rechte.
- Abwärtskompatibilität: `moderate_roles`/`settings_roles` behalten Namen und Werte. Fehlt eine Liste in der gespeicherten Option (Installationen vor SET-RC-16), füllt `SettingsPage::migrate_access()` sie einmalig aus den Rollen, die die Capability heute haben – so entzieht ein späterer Import, Reset oder ein Speichern nie ein Recht, das außerhalb des Plugins vergeben wurde. Kein Audit-Eintrag, weil sich kein Recht ändert.

### 4.8 `advanced` – Erweitert

| Schlüssel | Typ | Default | Validierung | Scope | Klasse | Entitlement |
|---|---|---|---|---|---|---|
| `job_time_budget` | int (Sekunden, erweitert) | `20` | 5–120 | site | P0 | — |
| `lease_seconds` | int (Sekunden, erweitert) | `300` | 60–3600; muss `> job_time_budget` sein (Prüfung beim Speichern) | site | P0 | — |
| `debug_logging` | bool (erweitert) | `false` | bool | site | P0 | — |
| `use_action_scheduler` | bool (erweitert) | `true` | bool; wirkt nur, wenn Action Scheduler geladen ist, sonst WP-Cron | site | P0 | — |

- `debug_logging = true` (SET-RC-05): `Jobs\Runner` schreibt bei jedem fehlgeschlagenen Job eine Zeile `Review Captain [debug]: job <id> (<typ>, attempt <n>) failed: <rc.code> (<Ausnahmeklasse|WP_Error|result>)` per `error_log()`; nie Fehlermeldung oder Payload (keine Secrets, kein Reviewtext, keine E-Mail). Filter `review_captain_debug_log_line`. Ausblick (nicht in v1): automatische Abschaltung nach 7 Tagen über eine Option `review_captain_debug_until`.

## 5. Versionierung und Migration

- `review_captain_settings_version` = `2` (`SettingsSchema::VERSION`) für dieses Schema; wird bei jedem Speichern und nach jeder Einstellungsmigration geschrieben.
- **Einstellungsmigration** (`ReviewCaptain\Settings\SettingsMigrator`, seit 24.11.2026): `Settings::all()` prüft einmal je Anfrage und Website die gespeicherte Version, `Installer::install_site()` zusätzlich vor dem Schreiben der DB-Version. Eine **neue Installation** (weder Einstellungen noch Einstellungsversion noch `review_captain_db_version`/`review_captain_installed_at`) speichert nur die Version, es gelten die aktuellen Defaults. Eine **bestehende** Installation durchläuft jeden Schritt ab ihrer Version (fehlend = 1); geänderte Werte werden gespeichert und als `settings.migrated` (`from`, `to`, `fields`) protokolliert. Eine Wiederherstellung (`ConfigTransfer::rollback()`) einer älteren Sicherung durchläuft dieselben Schritte.
- **Schritt 2** (E5, P2-13): fehlt `privacy.retention_contact_days`, wird `0` gespeichert (bestehende Websites behalten „unbegrenzt“); steht `submissions.notify_admin` auf aus, werden `submissions.report_notify` = `none` und `submissions.notify_questions` = aus gespeichert. Neue Installationen behalten die neuen Defaults (365 Tage, Meldungen an die Empfänger, Fragen-Mails an).
- Migration nur über `ReviewCaptain\Install\Migrator` (Aktivierung, `admin_init` mit Versionsvergleich, WP-CLI), nie im Frontend.
- Regeln je Versionsschritt: (1) Umbenennung: Wert übernehmen, alten Schlüssel löschen; (2) neues Feld: nicht schreiben, Default greift beim Lesen; (3) entferntes Feld: löschen; (4) Typänderung: konvertieren, sonst Default; (5) Default-Änderung: bestehende gespeicherte Werte bleiben, nur fehlende erhalten den neuen Default.
- Erstinstallation schreibt **keine** Kopie der Defaults (die Option existiert erst nach dem ersten Speichern). Achtung: Speichern schreibt die vollständigen effektiven Werte (inkl. Defaults) aller Tabs; spätere Default-Änderungen greifen daher nur für Felder, die nie gespeichert wurden.
- Pro-Felder migriert ausschließlich Pro.
- Neue Schritte: `SettingsSchema::VERSION` erhöhen und in `SettingsMigrator::migrate()` einen reinen Schritt (ohne I/O) ergänzen, mit Test für neue und bestehende Installation.

## 6. Konfigurations-Export/-Import (SET-018, Grundsatz)

JSON `{ "schema": "review_captain.settings.v1", "schema_version": 2, "with_contacts": false, "settings": {…} }`. P1-Felder (`privacy = config`: Markenname, Empfänger der Benachrichtigungen, Absenderangaben des Add-ons) nur nach ausdrücklicher Bestätigung: Kästchen „Mit Kontaktangaben exportieren“ unter „Konfiguration herunterladen“ bzw. `wp review-captain settings export --with-contacts`, Standard aus (P1-8, 24.11.2026); der Audit-Eintrag `settings.exported` vermerkt `with_contacts`. Ohne Kontaktangaben fehlen die Felder in der Datei, ein Import lässt die gespeicherten Werte dann unverändert; enthält eine Datei sie, übernimmt der Import sie wie jedes andere Feld. `privacy = personal`, `export = false` und Geheimnisse werden nie exportiert, `S` gibt es nicht. Der Diagnose-Export enthält keine Kontaktangaben. Import: Version prüfen → Vorschau der Änderungen je Feld → Validierung wie beim Speichern → Übernahme mit vorheriger Sicherung (Rollback-Kopie in `review_captain_settings_backup`) → Audit. Rollen aus `access` werden nur übernommen, wenn sie auf der Ziel-Site existieren.

## Abnahme

Entspricht SET-003, wenn:

- [ ] jeder Schlüssel aus §4 Typ, Validierung, Default, Scope, Datenschutzklasse, Entitlement und ändernde Capability hat;
- [x] die Schlüssel 1:1 mit der PHP-Schemadefinition übereinstimmen (Tab- und Feldnamen, Defaults, Grenzen; abgeglichen 2026-11-16);
- [ ] Lese-/Schreibsemantik (Fallback auf Default, Tab-weises Speichern, Pro-Felder unverändert) durch Unit-Tests abgedeckt ist;
- [ ] Versionsmigration und Konfigurations-Export beschrieben und keine Secrets in der Option zulässig sind;
- [ ] der UI-Geltungsbereich je Feld in [inventory.md](inventory.md) und die Override-Regeln in [inheritance.md](inheritance.md) referenziert sind.
