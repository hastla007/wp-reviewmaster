# Änderungshistorie und Audit-Semantik der Einstellungen (SET-019)

Status: Entwurf zur Abnahme, Stand 2026-11-16; alter/neuer Wert, eigene Aufbewahrung und Filter umgesetzt mit SET-RC-17 (23.11.2026, siehe §5). Tabelle `{prefix}review_captain_audit_log` (Spalten werden in `ReviewCaptain\Install\Schema` festgelegt; hier die logischen Felder).

## 1. Logischer Eintrag

| Feld | Inhalt |
|---|---|
| `occurred_at` | UTC `Y-m-d H:i:s` |
| `actor_user_id` | WP-User-ID (0 = System/Cron/CLI) |
| `actor_context` | `admin` · `cli` · `cron` · `rest` · `migration` |
| `action` | siehe §2 |
| `object_type` / `object_id` | `settings` / `<tab>.<key>` bzw. `profile` / ID |
| `context` | JSON, nur Werte nach §3 |
| IP-Adresse | **nicht** gespeichert (Vorschlag; OFFEN, ob gekürzte IP für Sicherheitsereignisse nötig ist) |

## 2. Aktionen

| `action` | Auslöser |
|---|---|
| `settings.changed` | Speichern eines Tabs mit mindestens einer Änderung; ein Eintrag je geändertem Feld |
| `settings.reset` | Reset eines Tabs/gesamt |
| `settings.imported` | Konfigurationsimport |
| `settings.exported` | Konfigurationsexport (ohne Inhalt; `with_contacts` = mit Kontaktangaben, P1-8) |
| `settings.migrated` | Einstellungsmigration (`from`, `to`, `fields`; `Settings\SettingsMigrator`) |
| `roles.capabilities_changed` | Änderung `access.*` (zusätzlich zu `settings.changed`: Liste der Rollen +/-) |
| `data.erase_all` | „Alle Plugin-Daten löschen“ (vor Ausführung) |

## 3. Was protokolliert wird

| Klasse | Alt-/Neuwert im Audit |
|---|---|
| P0 | vollständig (Zahlen, Booleans, Enums); Freitexte (`empty_text`, `privacy_notice`) nur als `"changed": true` + Länge |
| P1 | maskiert: `sender_email` → `a***@example.com`, `brand_name`/`sender_name` → `"changed": true` |
| P2 | vollständig (sind Zahlen/Enums/Rollen) – **immer** protokolliert |
| S | existiert nicht in den Einstellungen; Credentials-Änderungen am Profil nur als `profile.credentials_changed` ohne Wert |

Nie im Audit: Secrets, Tokens, Reviewtext, Autor-E-Mail, vollständige Freitexte.

**Mindestens auditpflichtig (SET-019):** Absender (`requests.sender_*`), Frequenz (`daily_limit`, `cooldown_days`, `reminders_enabled`, Sendefenster), Berechtigungen (`access.*`), Aufbewahrung/Löschung (`privacy.*` außer `privacy_notice`-Inhalt), Moderationsdefault (`new_review_status`), Einwilligung/Spam (`submissions.require_consent`, `honeypot`), Debug-Logging.
Alle übrigen Felder werden ebenfalls protokolliert (einheitliche Logik), sind aber nicht Release-blockierend.

## 4. Anzeige und Aufbewahrung

- Einstellungsseite: Link „Änderungsverlauf“ je Tab → Liste (Zeit in Site-Zeitzone, Nutzer-Anzeigename, Feld, alt → neu), sichtbar mit `review_captain_view_reports`.
- Aufbewahrung: `privacy.retention_logs_days` (Default 90). OFFEN: eigene, längere Frist für Audit vs. Fehlerlogs.
- Privacy-Exporter: Audit-Einträge eines Nutzers werden als „Aktionen im Review-Captain-Admin“ exportiert (nur Zeit, Aktion, Objekt). Eraser setzt `actor_user_id` auf 0 statt zu löschen (Nachvollziehbarkeit bleibt, Person entfernt).

## 5. Umsetzung (SET-RC-17, 23.11.2026)

Gilt vor den Vorschlägen oben, wo diese abweichen.

- **Ein Eintrag je Vorgang**, nicht je Feld: `settings.changed` (Speichern eines Tabs, auch per WP-CLI), `settings.reset`, `settings.imported`, `settings.restored` tragen `fields` (Liste `tab.key`) und `changes` (Liste `{field, from, to}`). `roles.capabilities_changed` je Capability, deren Rollen sich wirklich ändern (`from` = Rollen vorher, `roles` = nachher). `object_type` ist `settings` bzw. `capability`.
- **Werte im Protokoll** (`Audit\SettingsChanges::value()`): Zahlen, Ja/Nein, Auswahl, Uhrzeit und Rollenlisten vollständig; E-Mail-Adressen maskiert (`a***@example.com`, auch in Textfeldern); Felder mit `privacy` `personal` oder `config` (z. B. `requests.sender_name`) nur als `[verborgen]`; `general.brand_name` ist seit P1-8 ebenfalls `privacy = config` und erscheint nur als `[verborgen]`; übrige Texte (`empty_text`, `privacy_notice` …) auf 40 Zeichen gekürzt; Geheimnisse (Typ `secret` oder Schlüssel mit `secret|token|key|password|passwd`) nie, nur `[verborgen]`. `AuditLog::sanitize_context()` lässt in `changes` nur flache Datensätze zu und filtert deren Schlüssel mit derselben Sperrliste.
- **Aufbewahrung:** `privacy.retention_audit_days` (Standard 365, Filter `review_captain_retention_audit_days`) für `object_type` `settings`/`capability`; alle anderen Einträge weiter nach `retention_logs_days` (`Maintenance\Retention`).
- **Anzeige:** Werkzeuge › Diagnose › „Letzte Protokolleinträge“ mit Filter „Alle | Einstellungen“ (`?audit=settings`); die Einstellungsansicht zeigt je Eintrag „Feld: alt → neu“. Der Diagnose-Export enthält wie bisher nur Ereignis und Kontext-Schlüssel, keine Werte.
- Offen aus §4: Link „Änderungsverlauf“ je Tab, Privacy-Exporter/-Eraser für Audit-Einträge eines Nutzers.

## Abnahme

Entspricht SET-019, wenn:

- [ ] sicherheits- und versandrelevante Änderungen (Absender, Frequenz, Berechtigung, Aufbewahrung) mit Nutzer und Zeitpunkt nachvollziehbar sind;
- [ ] ein Test belegt, dass weder Secrets noch Reviewtext noch unmaskierte E-Mail-Adressen im Audit landen;
- [ ] Aufbewahrung, Anzeige und Privacy-Export/-Löschung des Audits beschrieben sind.
