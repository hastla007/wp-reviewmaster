# Einstellungs-Release-Gate (SET-024)

Status: Entwurf, Stand 2026-11-16. Teil des Release-Gates „Core v1.0“ (TODO.md). Ein Release ist **No-Go**, solange eine 1.0-Einstellung einen der Punkte nicht erfüllt.

## 1. Kriterien je Einstellung

| # | Kriterium | Nachweis |
|---|---|---|
| G1 | Default im Schema definiert und dokumentiert | [option-schema.md](option-schema.md) + Unit-Test „fehlende Option → Default“ |
| G2 | Validierung serverseitig (Typ, Bereich, Enum, Querbeziehungen) | Unit-Test gültig/ungültig/Grenzwerte |
| G3 | Rechteprüfung: Capability **und** Nonce beim Speichern | Integrationstest mit Rolle ohne Recht (SET-021) |
| G4 | Migration: Verhalten bei Upgrade, fehlender Option, Typwechsel | Migrator-Test (SET-022) |
| G5 | Export-/Löschregel: Datenschutzklasse, Konfig-Export ja/nein, Reset-/Uninstall-Verhalten | [inventory.md](inventory.md), [defaults-reset.md](defaults-reset.md) |
| G6 | verständlicher Hilfetext (deutsch, englisch, deutsch formal), übersetzbar | [reference.md](reference.md), POT enthält String |
| G7 | Audit-Verhalten festgelegt (voll/maskiert/nur „geändert“) | [audit.md](audit.md) |
| G8 | Owner und Entitlement festgelegt; Lizenzablauf verändert nichts | [pro-matrix.md](pro-matrix.md) |
| G9 | Override-Art festgelegt | [inheritance.md](inheritance.md) |
| G10 | Barrierefreiheit: Label, Beschreibung per `aria-describedby`, Fehler zugeordnet | manuelle Prüfung (RC-137) |

## 2. Statusliste (Core 1.0)

Ausfüllen vor Release Candidate. `D` = Doku vorhanden, `T` = Test vorhanden, `–` = offen.

| Feld | G1 | G2 | G3 | G4 | G5 | G6 | G7 | G8 | G9 | G10 |
|---|---|---|---|---|---|---|---|---|---|---|
| general.* (4) | D | D | D | D | D | D | D | D | D | – |
| sources.* (5) | D | D | D | D | D | D | D | D | D | – |
| requests.* (7, Pro) | D | D (Pro) | D | D (Pro) | D | D | D | D | D | – |
| submissions.* (8) | D | D | D | D | D | D | D | D | D | – |
| display.* (8) | D | D | D | D | D | D | D | D | D | – |
| privacy.* (7) | D | D | D | D | D | D | D | D | D | – |
| access.* (2) | D | D | D | D | D | D | D | D | D | – |
| advanced.* (4) | D | D | D | D | D | D | D | D | D | – |

Tests (`T`) sind für alle Zeilen noch offen.

## 3. Übergreifende Prüfungen

- [ ] Neuinstallation ohne gespeicherte Option liefert überall Defaults; keine PHP-Notices.
- [ ] Unberechtigte Nutzer können Einstellungen weder lesen (Seite/REST) noch speichern.
- [ ] Multisite: Speichern auf Site A ändert Site B nicht; Rollen bleiben site-lokal.
- [ ] Konfigurationsexport enthält keine Secrets und P1-Felder nur nach Bestätigung.
- [ ] Reset löscht keine Reviews; „Alle Daten löschen“ nur mit Bestätigung.
- [ ] Pro deaktiviert/Lizenz abgelaufen: `requests`-Werte unverändert.
- [ ] Hilfetexte enthalten keine internen Begriffe (TTL, Backoff, Lease, Entitlement).

## 4. Automatische Prüfung: keine toten Einstellungen (SET-RC-05)

`tests/Unit/Settings/SettingsReadersTest.php` läuft mit der Unit-Suite und schlägt fehl, wenn

- ein Core-Feld (`owner = core`) keine Beschriftung in `Admin\SettingsFields::texts()` hat, oder
- ein Core-Feld in `src/` außerhalb von `Settings/SettingsSchema.php` und `Admin/SettingsFields.php` nirgends gelesen wird (Muster `'tab', 'key'` oder Tab und Schlüssel als Literale in derselben Datei).

Ausnahmeliste (nur von Review Captain Pro gelesen, automatischer Abgleich): `sources.sync_window_start`, `sources.sync_window_end`, `sources.max_pages_per_run`, `sources.pause_after_auth_errors`. Pro-eigene Felder (`owner = pro`) prüft der Test nicht. Neue Ausnahmen nur mit Begründung in dieser Liste und im Test (`PRO_ONLY`).

Mit SET-RC-05 entfernt bzw. verdrahtet: `general.default_language` (entfernt, WordPress-Sprache gilt; gespeicherte Altwerte bleiben unausgewertet in der Option), `sources.removed_review_behavior` (wirkt im Abgleich), `advanced.debug_logging` (Zeile im PHP-Fehlerprotokoll bei fehlgeschlagenen Jobs).

## Abnahme

Entspricht SET-024, wenn diese Checkliste in die Release-Checkliste (RC-142) übernommen ist und vor dem Core-1.0-Release jede Zeile in §2 in allen Spalten `D`+`T` (bzw. geprüft) ist.
