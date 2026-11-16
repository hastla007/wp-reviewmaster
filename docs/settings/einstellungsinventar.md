# Einstellungsinventar (UX-009 / SET-001)

**Status:** Entwurf – Grundlage für SET-003 (Optionsschema) und die Implementierung SET-007 ff.  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §8.9](../../konzept.md), [TODO „Plugin-Einstellungen“](../../TODO.md), [RC-014](../m0/rc-014-datenschutz-kommunikation.md)

Legende Geltungsbereich: **G** global · **P** pro Quellprofil · **K** pro Kampagne · **F** pro Formular · **W** pro Widget.  
Speicherort: Option-Gruppe in `wp_options` (je Site), Schlüssel `review_captain_{gruppe}` als Array; Objekt-Einstellungen (P/K/F/W) liegen am Objekt (eigene Tabellen), nicht auf der Einstellungsseite.  
Capability: siehe RC-029 (`rc_manage_settings` für alle globalen Einstellungen, sofern nicht anders angegeben).  
Datenschutzklasse: **0** keine personenbezogenen Daten · **1** Betreiberkontakt (z. B. Absenderadresse) · **S** Secret (nie exportiert).

## Allgemein (`review_captain_general`) – Core

| Schlüssel | Nutzerfrage | Bereich | Default | Klasse | Override |
|---|---|---|---|---|---|
| `brand_name` | Welcher Name erscheint in Vorlagen und Widgets? | G | Website-Titel | 0 | K |
| `default_locale` | In welcher Sprache werden Vorlagen erstellt? | G | WP-Sprache | 0 | K |
| `date_format` | Wie werden Datumsangaben angezeigt? | G | WP-Datumsformat | 0 | W |
| `new_review_type_default` | Welcher Typ wird bei manuell erfassten Reviews vorausgewählt? | G | `service` | 0 | – |

Zeitzone wird **nicht** dupliziert, sondern aus WordPress gelesen (Anzeige als Info).

## Quellen & Synchronisierung (`review_captain_sync`) – Werte im Core gespeichert, genutzt von Pro

| Schlüssel | Nutzerfrage | Bereich | Default | Klasse | Override |
|---|---|---|---|---|---|
| `sync_interval_hours` | Wie oft sollen Bewertungen abgerufen werden? | G | 12 (Min. 1, Max. 24 wegen Cachefrist) | 0 | P (nur kürzer/gleich) |
| `sync_lookback_days` | Wie weit zurück beim ersten Import? | G | 365 | 0 | P |
| `sync_max_items_per_run` | Wie viele Bewertungen pro Abruf höchstens? | G | 200 | 0 | P |
| `sync_pause_on_auth_error` | Abrufe bei Anmeldefehler pausieren? | G | an (fest) | 0 | – |
| `sync_quota_warn_percent` | Ab welchem Kontingentverbrauch warnen? | G | 80 | 0 | P |
| `deleted_review_behavior` | Was passiert mit auf der Plattform gelöschten Bewertungen? | G | `hide_and_purge` (fest, Info) | 0 | – |

Zugangsdaten, Profile und Scopes: **nur** auf der Quellen-Seite (P), nie hier.

## Anfragen & Zustellung (`review_captain_requests`) – Core-Speicher, Pro-Funktion

| Schlüssel | Nutzerfrage | Bereich | Default | Klasse | Override |
|---|---|---|---|---|---|
| `sender_name` | Wer steht als Absender in der Einladung? | G | `brand_name` | 1 | K |
| `sender_email` | Von welcher Adresse wird gesendet? | G | leer (Versand blockiert bis gesetzt + Testmail) | 1 | K |
| `reply_to` | Wohin gehen Antworten? | G | leer | 1 | K |
| `test_recipient` | An wen gehen Testmails? | G | Admin-E-Mail | 1 | – |
| `send_window_start` / `send_window_end` | Zu welchen Uhrzeiten darf gesendet werden? | G | 09:00 / 19:00 (Website-Zeitzone) | 0 | K (nur enger) |
| `daily_send_limit` | Wie viele Einladungen höchstens pro Tag? | G | 200 | 0 | – |
| `cooldown_days` | Mindestabstand zwischen zwei Einladungen an dieselbe Person (kampagnenübergreifend) | G | 30 | 0 | K (nur länger) |
| `reminders_default` | Erinnerungen bei neuen Kampagnen voreinstellen? | G | aus | 0 | K |
| `existing_customer_basis` | Bestandskundenregel als Versandgrundlage erlauben? | G | aus | 0 | K (nur aus) |
| `bounce_behavior` | Was tun bei unzustellbarer Adresse? | G | `suppress` | 0 | – |
| `transport` | Über welchen Weg wird gesendet? | G | `wp_mail` mit Warnhinweis, wenn kein SMTP-Plugin erkannt | 0 | K |

Opt-out-/Suppression-Liste: siteweit, nicht konfigurierbar abschaltbar (Daten in eigener Tabelle, Capability `rc_manage_requests`).

## Einreichungen & Moderation (`review_captain_submissions`) – Core

| Schlüssel | Nutzerfrage | Bereich | Default | Klasse | Override |
|---|---|---|---|---|---|
| `default_status` | Werden neue Bewertungen sofort veröffentlicht? | G | `pending` | 0 | F (nur strenger) |
| `honeypot_enabled` | Spam-Falle verwenden? | G | an | 0 | – |
| `rate_limit_per_hour` | Wie viele Einreichungen pro Stunde und Absender? | G | 3 | 0 | F |
| `min_text_length` / `max_text_length` | Wie lang darf eine Bewertung sein? | G | 0 / 5000 | 0 | F |
| `require_email` | E-Mail-Adresse verlangen? | G | aus | 0 | F |
| `consent_text_id` | Welcher Einwilligungstext wird angezeigt? | G | Standardtext v1 | 0 | F |
| `allow_reporting` | Dürfen Besucher Bewertungen melden? | G | aus (Pro-Folge RC-124) | 0 | W |
| `notify_moderators` | Moderatoren bei neuer Bewertung benachrichtigen? | G | an | 0 | – |
| `upload_types` / `upload_max_mb` | Welche Dateien dürfen hochgeladen werden? | G | keine (Foto-Reviews Pro-Folge) | 0 | F |

## Darstellung (`review_captain_display`) – Core

| Schlüssel | Nutzerfrage | Bereich | Default | Klasse | Override |
|---|---|---|---|---|---|
| `show_date` | Datum anzeigen? | G | an | 0 | W |
| `author_mode` | Wie wird der Name angezeigt? | G | `first_name_initial` | 0 | W |
| `show_avatar` | Profilbilder anzeigen? | G | aus (externe Bilder nur nach Einstellung) | 0 | W |
| `empty_state` | Was zeigt ein Widget ohne Bewertungen? | G | `hide` | 0 | W |
| `load_css` | Plugin-CSS laden? | G | an | 0 | – |
| `source_attribution` | Quelle anzeigen? | G | an (**fest**, nicht änderbar für externe Reviews) | 0 | – |

## Datenschutz & Aufbewahrung (`review_captain_privacy`) – Core, Capability `rc_manage_privacy`

| Schlüssel | Nutzerfrage | Bereich | Default | Klasse |
|---|---|---|---|---|
| `retention_requests_days` | Wie lange bleiben Versandnachweise? | G | 365, danach anonymisiert | 0 |
| `retention_pending_requests_days` | Wann verfallen nicht gesendete Einladungen? | G | 60 | 0 |
| `retention_logs_days` | Wie lange bleiben technische Protokolle? | G | 90 | 0 |
| `retention_rejected_reviews_days` | Wann werden abgelehnte Bewertungen gelöscht? | G | 30 | 0 |
| `external_snapshot_max_hours` | Wie lange dürfen externe Bewertungen ohne Aktualisierung gezeigt werden? | G | 24 (fest je Anbieter, RC-008) | 0 |
| `retention_media_after_revocation_days` | Wann werden Medien nach Widerruf gelöscht? | G | 30 | 0 |
| `delete_data_on_uninstall` | Beim Löschen des Plugins alle Daten entfernen? | G | aus | 0 |
| `external_services_consent_mode` | Wie werden externe Inhalte (Embeds, Avatare) geladen? | G | `click_to_load` | 0 |

## Zugriff & Rollen (`review_captain_access`) – Core, Capability `manage_options`

| Schlüssel | Nutzerfrage | Bereich | Default |
|---|---|---|---|
| `role_map` | Welche Rollen dürfen was? | G | siehe RC-029 Standardzuordnung |
| `network_mode` (Multisite, Netzwerk-Option) | Werden Einstellungen pro Website oder netzwerkweit vorgegeben? | Netzwerk | `per_site` |

## Erweitert (`review_captain_advanced`) – Core

| Schlüssel | Nutzerfrage | Bereich | Default |
|---|---|---|---|
| `job_runner` | Womit laufen Hintergrundaufgaben? | G | `auto` (Action Scheduler wenn vorhanden, sonst eigener Runner) |
| `job_time_budget_seconds` | Wie lange darf ein Durchlauf dauern? | G | 20 |
| `system_cron_mode` | Nutzen Sie einen echten System-Cron? | G | aus (Info zu WP-Cron) |
| `debug_logging_until` | Erweiterte Protokollierung (befristet) | G | leer; max. 24 h |

## Bewusst entfernte Duplikate

- „Zeitzone“ (WordPress-Einstellung), „Standardquelle“ aus Konzept §8.9 (gehört ins Widget), „Protokollaufbewahrung“ im Anfragen-Tab (zentral unter Datenschutz), „CSS-Variablen“ (Block-Supports statt Option), „Diagnose“ als Tab (eigene read-only Seite).
