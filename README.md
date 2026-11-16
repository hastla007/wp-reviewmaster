# WP Review Captain

Projekt- und Implementierungsgrundlage für das Review- und Social-Proof-Plugin der Captain Suite.

- [Produktkonzept](konzept.md): Umfang, Plattformen, Free/Pro, Suite-Integration, Screens und Roadmap-Entscheidungen.
- [Detailliertes To-do](TODO.md): taskweise Umsetzung von Scope-Freeze und API-Freigaben bis Core, Pro, fünf Plattformen, Suite-Adaptern und Release-Gates.

- [Ablauf und Mockup-Screenshots](konzept.md#17-abläufe-und-screenshot-mockups): Suite-Diagramme und UI-Beispiele.
- [Technische Konventionen](docs/architecture/conventions.md) und [Spezifikationen](docs/): Verträge, Architektur, Einstellungen, SEO, Connectoren.
- Release: [Änderungsprotokoll](CHANGELOG.md), [Release-Checkliste](docs/release/RELEASE-CHECKLIST.md), [Bekannte Einschränkungen](docs/release/KNOWN-LIMITATIONS.md).

Die Aufgabenliste ist nach Abhängigkeiten geordnet. Nicht bestätigte Anbieterzugänge und Folgefunktionen sind als Entscheidung bzw. gesonderter Release-Schritt gekennzeichnet.

## Entwicklung

Die Repository-Wurzel ist zugleich das WordPress-Plugin `review-captain` (Free Core).

```bash
composer install
vendor/bin/phpunit          # Unit-/Integrationstests (SQLite, ohne WordPress-Installation)
composer lint               # PHP-Syntaxprüfung
php bin/check-plugin-meta.php
bin/build-zip.sh            # Release-ZIP nach build/
bin/make-pot.sh             # POT-Datei (benötigt WP-CLI)
```
