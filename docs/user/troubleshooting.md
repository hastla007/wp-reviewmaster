# Fehlerbehebung (RC-140)

Stand 2026-11-16. Für Betreiber/innen und Support. Erster Schritt immer: **Review Captain → Diagnose** (Diagnosebericht enthält keine Zugangsdaten) bzw. `wp review-captain doctor`.

## Diagnose-Werkzeuge

| Werkzeug | Zweck |
|---|---|
| `wp review-captain doctor` | Schema, Job-Takt, Runner, Verschlüsselung, Datenbankversion (MINVER-1) |
| `wp review-captain jobs stats` / `jobs list` / `jobs retry <id>` | Hintergrundjobs, Dead-Letter |
| `wp review-captain reconcile` (`--fix`, nach Restore `--fix --mode=restore`) | Konsistenz nach Update, Restore oder Klonen ([reconciliation.md](../architecture/reconciliation.md)) |
| Einstellungen → SEO & Yoast | erkannter Schema-Besitzer, Gründe für fehlendes Markup |

## Häufige Probleme

| Symptom | Ursache | Lösung |
|---|---|---|
| Plugin aktiviert sich nicht, Hinweis „benötigt mindestens PHP 8.5“ | PHP zu alt (MINVER-1) | PHP beim Hoster auf 8.5 oder neuer umstellen |
| `doctor`: „Datenbank … empfohlen mindestens …“ (Warnung) | MySQL < 9.7 bzw. MariaDB < 12.3 | Plugin läuft weiter; Update beim Hoster einplanen |
| `doctor`: „Job-Takt ist nicht eingeplant“ | Cron-Event fehlt (z. B. nach Migration) | Adminbereich einmal aufrufen oder Plugin deaktivieren/aktivieren |
| Jobs laufen nur bei Seitenaufrufen / verspätet | WP-Cron statt System-Cron | System-Cron: `*/5 * * * * wp review-captain jobs run` und `DISABLE_WP_CRON` setzen ([jobs.md](../architecture/jobs.md)) |
| Formular: „Das Formular wurde zu schnell abgeschickt“ | Zeitfalle gegen Spam (`rc.form.spam`) | einige Sekunden warten; Caching-Plugin darf das Formular nicht über Stunden cachen |
| Formular: „Die Sitzung ist abgelaufen“ (`rc.form.invalid_nonce`) | gecachte Seite mit alter Nonce | Seite mit Formular vom Seiten-Cache ausnehmen oder Cache-Laufzeit < 12 h |
| Neue Bewertung erscheint nicht | wartet auf Moderation (Standard) | Reviews → Ausstehend → Freigeben |
| Keine Sterne/Rich Results bei Google | Markup nur für lokale, freigegebene Reviews mit Bezug zur Seite; nie für Google/Trustpilot-Reviews, Testimonials oder Unternehmensreviews; anderes SEO-Plugin besitzt das Schema | Einstellungen → SEO & Yoast prüfen; Google entscheidet selbst über Snippets ([seo-guide.md](../seo/seo-guide.md)) |
| Doppelte strukturierte Daten | zweites Schema-Plugin ohne Erkennung | Plugin melden; Markup mit Filter `review_captain_schema_eligible` abschalten |
| Seite `?rc_page=2` ist `noindex` | gewollt (Paginierung) | – |
| Quelle „nicht verfügbar“ (`rc.source.unavailable`) | Connector/Pro inaktiv oder Lizenz abgelaufen | Pro/Connector aktivieren; Jobs werden stündlich wieder aufgenommen |
| Verbindung abgelaufen (`rc.auth.expired`, `rc.auth.revoked`) | Token beim Anbieter widerrufen oder abgelaufen | Quellen → Profil → neu verbinden |
| `rc.crypto.decrypt_failed` | WordPress-Salts oder `REVIEW_CAPTAIN_ENCRYPTION_KEY` geändert | alten Schlüssel wiederherstellen oder Verbindungen neu herstellen ([secrets.md](../architecture/secrets.md)) |
| Nach Backup-Restore laufen Jobs doppelt/hängen | Leases aus dem Backup | `wp review-captain reconcile --fix --mode=restore` |
| CSV-Import: Zeilen übersprungen | Pflichtfelder/Quelle fehlen, Duplikate (Dedupe) | Prüflauf-Bericht lesen; Vorlage aus dem Export verwenden ([migration.md](../migration.md)) |
| Englische statt deutscher Oberfläche | Website-/Benutzersprache | Einstellungen → Allgemein → Sprache; formell: „Deutsch (Sie)“ |

## Support kontaktieren

Mit Diagnosebericht, Versionen (WordPress, PHP, Datenbank, Review Captain, Pro, andere Captain-Plugins) und Schritten zur Reproduktion. Nie Passwörter, Tokens oder API-Schlüssel senden. Wege und Reaktionsziele: [support-process.md](../ops/support-process.md).
