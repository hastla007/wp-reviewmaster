# WP Review Captain

Projekt- und Implementierungsgrundlage für das Review- und Social-Proof-Plugin der Captain Suite.

- [Produktkonzept](konzept.md): Umfang, Plattformen, Free/Pro, Suite-Integration, Screens und Roadmap-Entscheidungen.
- [Ablauf und Mockup-Screenshots](konzept.md#17-abläufe-und-screenshot-mockups): Suite-Diagramme und UI-Beispiele.
- [Detailliertes To-do](TODO.md): taskweise Umsetzung von Scope-Freeze und API-Freigaben bis Core, Pro, fünf Plattformen, Suite-Adaptern und Release-Gates.
- [Dokumentation](docs/README.md): Entscheidungsunterlagen (M0), UX (M0.5), Datenmodell und Verträge (M1), Technik (M1.5), Connectoren, SEO, ADRs.

Die Aufgabenliste ist nach Abhängigkeiten geordnet. Nicht bestätigte Anbieterzugänge und Folgefunktionen sind als Entscheidung bzw. gesonderter Release-Schritt gekennzeichnet. Status: `[ ]` offen, `[~]` Entwurf liegt vor (Freigabe/externe Aktion offen), `[x]` abgeschlossen.

## Plugin-Code (Review Captain Core)

Der kostenlose Core liegt im Repository-Root (`review-captain.php`, `src/`, `blocks/`, `assets/`). Doku, Tests und Konzept werden beim Build ausgeschlossen.

```bash
composer install
```

```bash
vendor/bin/phpunit
```

```bash
WP_MULTISITE=1 vendor/bin/phpunit
```

```bash
vendor/bin/phpcs
```

```bash
bash bin/build-zip.sh
```

Für die Tests ist eine MySQL/MariaDB-Datenbank nötig (Standard: `wp_tests`, Benutzer/Passwort `wp`/`wp` auf `127.0.0.1`, anpassbar per `WP_TESTS_DB_*`-Umgebungsvariablen). Die Skalierungsmessung läuft mit `vendor/bin/phpunit --testsuite perf`.
