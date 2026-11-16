# Betriebs- und Incident-Runbook (OPS-004)

Status: Entwurf, Stand 2026-11-16. Zuständigkeiten/Kontakte: **OFFEN** (OPS-003, OPS-009). Platzhalter: `{Incident-Lead}`, `{Security-Kontakt}`, `{Support}`.

## 0. Allgemeiner Ablauf

1. **Erkennen & einstufen:** S1 (Datenleck, Datenverlust, Sicherheitslücke) · S2 (Funktion für viele Sites gestört, z. B. Anbieter-Sync) · S3 (Einzelfall).
2. **Eindämmen** (Schritte unten), **Kommunizieren** (S1 sofort intern, Kunden-Hinweis innerhalb vereinbarter Frist; Datenschutz-Meldepflichten prüfen – Betreiber sind Verantwortliche, wir unterstützen), **Beheben**, **Nachbereiten** (Postmortem ohne personenbezogene Daten, Folgeaufgaben in TODO).
3. Jede Maßnahme mit Zeit und Person protokollieren (Incident-Ticket). Keine Secrets, Tokens, Reviewtexte oder Kunden-E-Mails in Tickets/Chats kopieren.

Hilfsmittel auf der Kundensite: Diagnose-Seite und Diagnosebericht (ohne Secrets), `audit_log`, WP-CLI-Wartungsbefehle (RC-036; Befehlsnamen werden mit der Implementierung ergänzt).

## 1. Token-/Credential-Leak

Beispiele: Token in Log/Ticket/Screenshot, kompromittierte Site, geleakter OAuth-Client-Secret des Plugin-Anbieters.

| # | Schritt |
|---|---|
| 1 | Umfang bestimmen: welche Plattform(en), Kunden-Token oder Anbieter-Client (eigene OAuth-App)? |
| 2 | **Kunden-Token:** Kunde trennt die Verbindung (Quellen › Trennen → lokaler Token gelöscht, Widerruf beim Anbieter, wo API vorhanden) **und** widerruft den Zugriff zusätzlich im Anbieterkonto (Google-Kontoberechtigungen, Play-Console-Service-Account-Key löschen, App-Store-Connect-API-Key widerrufen, Trustpilot/eTrusted-Client neu erzeugen). |
| 3 | **Anbieter-Client-Secret (eigene OAuth-App):** Secret beim Anbieter rotieren, neues Secret über Pro-Update/Konfiguration verteilen; alle Kunden zum Neu-Verbinden auffordern; {Security-Kontakt} informieren. |
| 4 | Fundstelle bereinigen (Log, Ticket, Backup soweit möglich). Prüfen, ob die Sanitisierung versagt hat → Bug mit Priorität S1. |
| 5 | Anbieter-Zugriffslogs (falls verfügbar) auf Missbrauch prüfen; Kunde informieren. |
| 6 | Neu verbinden, Verbindungstest, Audit-Eintrag prüfen. |

## 2. Fehlerhafter Sync (falsche/fehlende/doppelte Reviews)

| # | Schritt |
|---|---|
| 1 | Betroffene Quelle pausieren (Quellen › Profil › Pausieren) – vorhandene Reviews bleiben sichtbar. |
| 2 | Diagnose: letzte Läufe, Fehlercodes, Anzahl importiert/aktualisiert/entfernt; Diagnosebericht anfordern. |
| 3 | Ursache eingrenzen: Anbieteränderung (→ [api-change-routine.md](api-change-routine.md)), Mapping-Bug, falsches Profil, Dedupe-Problem. |
| 4 | Falsch angezeigte Reviews lokal ausblenden (nicht löschen), bis behoben. |
| 5 | Fix ausliefern; Reconciliation-Lauf (RC-036) für das Profil anstoßen; Ergebnis mit Kunde prüfen. |
| 6 | Fixture-Test ergänzen (RC-083). |

## 3. Datenlöschanforderung (Betroffenenanfrage)

| # | Schritt |
|---|---|
| 1 | Der **Betreiber** ist Verantwortlicher; wir unterstützen. Identität prüft der Betreiber. |
| 2 | Werkzeuge › Personenbezogene Daten exportieren/löschen (WP-Core) mit der E-Mail-Adresse; Review-Captain-Exporter/-Eraser laufen mit (`erase_mode`). |
| 3 | Reviewtexte mit personenbezogenen Angaben Dritter manuell prüfen und bearbeiten/löschen. |
| 4 | Externe Plattform-Reviews: die Person muss die Löschung **beim Anbieter** beantragen; lokal kann das Review sofort ausgeblendet werden; nach Löschung beim Anbieter entfernt der nächste Sync (Tombstone). |
| 5 | Pro: Einladungs-/Empfängerdaten und Opt-out-Sperre (Sperreintrag bleibt als minimaler Hash, damit keine erneute Einladung erfolgt – Rechtsgrundlage mit Datenschutzberatung klären). |
| 6 | Backups: Hinweis an Betreiber, dass Backups nach ihrer Frist rotieren müssen. |

## 4. Anbieter-API-Ausfall

| # | Schritt |
|---|---|
| 1 | Status des Anbieters prüfen (Statusseite/Developer-Forum). Fehlercodes `rc.temp.unavailable`, `rc.net.timeout`, `rc.quota.exceeded`. |
| 2 | Nichts tun auf Kundenseite: automatische Retries mit Backoff; Widgets zeigen den letzten zulässigen Cache. |
| 3 | Dauert der Ausfall > 24 h: Hinweis im Support/Statuskanal; prüfen, ob Cache-Fristen (z. B. Trustpilot 24 h) zum Ausblenden führen und Kunden informieren. |
| 4 | Bei dauerhafter Änderung/Abschaltung → [api-change-routine.md](api-change-routine.md). |

## 5. Update-Rollback

| # | Schritt |
|---|---|
| 1 | Symptom und betroffene Version erfassen; Update-Verteilung stoppen (WordPress.org: neue Version mit Fix; Pro: Update-Kanal pausieren). |
| 2 | Kunde: vorherige Version installieren (WordPress.org-Versionsarchiv bzw. Pro-Kundenkonto). **Vorher Backup.** |
| 3 | Schema-Kompatibilität: Migrationen sind vorwärts gerichtet; ein Downgrade über eine Schemaänderung hinweg ist nur unterstützt, wenn die Release-Notes es ausdrücklich sagen. Sonst: Backup-Restore statt Downgrade. |
| 4 | `review_captain_db_version`/`review_captain_settings_version` prüfen (Diagnose). |
| 5 | Hotfix-Release; Regressionstest ergänzen. |

## 6. Möglicher Datenverlust

| # | Schritt |
|---|---|
| 1 | **Stoppen:** weitere Schreibvorgänge vermeiden – Quellen pausieren, Aufbewahrungsjobs anhalten (Einstellungen › Erweitert, OFFEN: Notaus „Hintergrundjobs anhalten“). |
| 2 | Umfang bestimmen: Tabellen-Zeilenzahlen, `audit_log` (Reset, Löschaktionen, Rollbacks), `review_events`. |
| 3 | Ursache: Nutzeraktion (Löschen/Rollback/Uninstall mit Datenlöschung), Aufbewahrungsjob, Migration, Fremdplugin. |
| 4 | Wiederherstellung: Backup-Restore der `review_captain_*`-Tabellen (Site-spezifisches Präfix in Multisite beachten); externe Reviews können per Neu-Sync wiederhergestellt werden; lokale Einreichungen nur aus Backup. Danach immer `wp review-captain reconcile --fix --mode=restore` ausführen (ein frischer Restore ist sonst nicht erkennbar, QA F-08). |
| 5 | Nachbereitung: S1-Postmortem, Test, ggf. zusätzliche Bestätigung in der UI. |

## Abnahme

Entspricht OPS-004, wenn:

- [ ] Token-Leak, fehlerhafter Sync, Datenlöschanforderung, API-Ausfall, Update-Rollback und möglicher Datenverlust klare, nummerierte Schritte haben;
- [ ] Rollen und Kontakte (OPS-003/OPS-009) eingetragen sind;
- [ ] WP-CLI-Befehlsnamen und der Notaus für Hintergrundjobs nach Implementierung ergänzt sind;
- [ ] das Runbook einmal als Übung (Tabletop) für Token-Leak und Datenverlust durchgespielt wurde.
