# Sichere Defaults und Reset-Semantik (SET-005)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Werte: [option-schema.md](option-schema.md).

## 1. Sichere Defaults

| Bereich | Sicherer Default | Wo festgelegt |
|---|---|---|
| Neue lokale Reviews | warten auf Moderation (`general.new_review_status = pending`) | Einstellung |
| Einwilligung | Veröffentlichungs-Einwilligung Pflicht (`submissions.require_consent = true`) | Einstellung |
| Spam-Schutz | Nonce + Rate-Limit immer; Honeypot an | Einstellung + feste Regel |
| Versand ohne Berechtigung | **blockiert**; kein Kontakt / unbekannte oder widerrufene Erlaubnis → kein Versand | feste Regel (Pro), keine Option |
| Einladungen je Erlebnis | max. 1 je Erlebnis + Empfänger + Kampagne | feste Regel |
| Erinnerungen | aus (`requests.reminders_enabled = false`) | Einstellung |
| Automatischer Versand | aus, bis Kampagne getestet und aktiviert wurde | Kampagne (Pro) |
| Absender | leer → kein Versand | Einstellung |
| Externe Skripte (TrustBox, Trustbadge, Player) | aus; nur nach expliziter Aktivierung am Profil **und** Besucher-Consent | Profil + Frontend |
| Avatare | aus (`display.show_avatar = false`) | Einstellung |
| Quellenangabe externer Reviews | immer an, nicht abschaltbar | feste Regel |
| Gelöschte Quell-Reviews | Tombstone (`removed_review_behavior = tombstone`) | Einstellung |
| Datenlöschung bei Deinstallation | aus (`delete_data_on_uninstall = false`) | Einstellung |
| Löschanfragen | anonymisieren (`erase_mode = anonymize`) | Einstellung |
| Debug-Protokoll | aus, automatische Abschaltung | Einstellung |
| Schema-Markup | nur `local` + `review` + `approved` | feste Regel |
| Externe Verbindungen | werden nie automatisch aufgebaut (auch nicht im Setup-Assistenten) | feste Regel |

## 2. Drei getrennte Aktionen

| Aktion | Ort | Capability | Wirkung | Löscht Reviews? | Bestätigung |
|---|---|---|---|---|---|
| **Verbindung trennen** | Quellen › Profil | `manage_sources` | Token widerrufen (sofern Anbieter-API das bietet) und lokal löschen; Sync-Jobs des Profils pausiert; Profil bleibt | nein – vorhandene Reviews bleiben, unterliegen aber weiter `retention_external_days` | Dialog mit Profilname |
| **Einstellungen zurücksetzen** | Einstellungen › Datenschutz & Aufbewahrung (Umfang „nur dieser Bereich“ oder „alle Einstellungen“); je Bereich über `wp review-captain settings reset <bereich>` | `manage_settings` (+ `manage_privacy` für Tab Datenschutz, + `promote_users` für Rollen) | setzt die Kernfelder auf ihre Defaults; Add-on-Felder bleiben | nein | Kontrollkästchen „Ich möchte die Einstellungen zurücksetzen“; Audit `settings.reset` mit Vorher → Default je Feld |
| **Alle Plugin-Daten löschen** | Einstellungen › Datenschutz › Deinstallation | `manage_privacy` **und** `manage_settings` | löscht alle `review_captain_*`-Tabellen-Inhalte, Optionen, Transients, geplanten Jobs dieser Site; Plugin bleibt aktiv; Quell-Plattformen werden **nie** verändert | **ja** | Eingabe des Site-Namens + Hinweis auf CSV-Export; Audit-Eintrag vorab, danach ist auch das Audit leer |

## 3. Reset-Regeln

1. Reset löscht nur Schlüssel aus `review_captain_settings`; **nie** Reviews, Events, Profile, Jobs, Import-Batches oder Audit.
2. Reset eines Tabs setzt dessen Kernfelder auf die Defaults; Reset gesamt alle Kernfelder. Ein Bereich ohne Kernfelder (`requests`) gilt nicht als zurückgesetzt (keine Sicherung, kein Audit-Eintrag; WP-CLI meldet das).
3. Pro-Felder (`requests` und Pro-Ergänzungen in anderen Tabs) werden beim Core-Reset **nie** zurückgesetzt, auch nicht mit aktivem Add-on (Seite: „Pro-Einstellungen bleiben unverändert“). Ein Kontrollkästchen „auch Pro-Einstellungen“ gibt es nicht.
4. `access`-Reset setzt Rollen-Capabilities auf die Standard-Zuordnung (administrator, editor) zurück; administrator behält immer alle Rechte.
5. Vor jedem Reset und Import wird der vorherige Stand in `review_captain_settings_backup` gesichert; „Sicherung wiederherstellen“ (Erweitert) holt die letzte Sicherung zurück und sichert dabei den ersetzten Stand.
6. Jeder Reset erzeugt einen Audit-Eintrag `settings.reset` mit Tab-Liste (ohne Werte von P1-Feldern).
7. Lizenzablauf ist **kein** Reset: Pro-Werte bleiben unverändert gespeichert und werden nur nicht angewendet.

## 4. Deinstallation

- `uninstall.php` prüft je Site `privacy.delete_data_on_uninstall`. `false` → nichts wird gelöscht (auch nicht die Option selbst, damit eine Neuinstallation die Konfiguration behält). `true` → wie „Alle Plugin-Daten löschen“ plus Entfernen der Optionen und Capabilities.
- Multisite: Iteration über alle Sites (in Batches, mit Zeitlimit); jede Site entscheidet selbst.

## Abnahme

Entspricht SET-005, wenn:

- [ ] fehlende Consent-/Kontaktberechtigung den Versand blockiert, Erinnerungen aus sind, externe Skripte aus sind und neue Reviews auf Moderation warten;
- [ ] Reset nachweislich keine Reviewdaten löscht (Test) und Datenlöschung nur über die getrennte, bestätigte Aktion möglich ist;
- [ ] „Verbindung trennen“, „Einstellungen zurücksetzen“ und „Alle Plugin-Daten löschen“ getrennte Buttons, Capabilities, Texte und Audit-Einträge haben;
- [ ] Lizenzablauf keine Einstellung verändert.
