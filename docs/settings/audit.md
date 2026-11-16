# Änderungshistorie und Audit-Semantik der Einstellungen (SET-019)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Tabelle `{prefix}review_captain_audit_log` (Spalten werden in `ReviewCaptain\Install\Schema` festgelegt; hier die logischen Felder).

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
| `settings.updated` | Speichern eines Tabs mit mindestens einer Änderung; ein Eintrag je geändertem Feld |
| `settings.reset` | Reset eines Tabs/gesamt |
| `settings.imported` | Konfigurationsimport |
| `settings.exported` | Konfigurationsexport (ohne Inhalt) |
| `settings.migrated` | Versionsmigration |
| `roles.capabilities_changed` | Änderung `access.*` (zusätzlich zu `settings.updated`: Liste der Rollen +/-) |
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

## Abnahme

Entspricht SET-019, wenn:

- [ ] sicherheits- und versandrelevante Änderungen (Absender, Frequenz, Berechtigung, Aufbewahrung) mit Nutzer und Zeitpunkt nachvollziehbar sind;
- [ ] ein Test belegt, dass weder Secrets noch Reviewtext noch unmaskierte E-Mail-Adressen im Audit landen;
- [ ] Aufbewahrung, Anzeige und Privacy-Export/-Löschung des Audits beschrieben sind.
