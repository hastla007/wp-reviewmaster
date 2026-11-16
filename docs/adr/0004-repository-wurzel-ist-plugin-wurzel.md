# ADR-0004: Repository-Wurzel = Plugin-Wurzel

- **Status:** Angenommen
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Core-Maintainer Review Captain
- **Betrifft:** RC-028 (Pfade), Build/Release (M8); `conventions.md` (Identität, Repository-Layout)
- **Ersetzt:** –

## Kontext

Das Repository `hastla007/wp-reviewmaster` enthält Review Captain Core. Optionen waren, das Plugin in einem Unterordner (`plugin/review-captain/`) oder direkt in der Wurzel zu führen. Entwickler:innen sollen das Repo direkt nach `wp-content/plugins/review-captain/` klonen bzw. symlinken können; Pfadkonstanten (`REVIEW_CAPTAIN_DIR`) und Autoloader sollen ohne Build funktionieren. Das Pro-Add-on liegt getrennt in `captain-pro/addons/review-captain-pro`.

## Betrachtete Optionen

1. **Unterordner** – trennt Doku/Tooling sauber, aber Klonen in `plugins/` funktioniert nicht direkt; Pfade in CI und Tests werden länger.
2. **Wurzel = Plugin** – `review-captain.php` liegt in der Wurzel; Nicht-Release-Dateien werden beim Packen ausgeschlossen.

## Entscheidung

Option 2. `review-captain.php`, `uninstall.php`, `readme.txt`, `src/`, `templates/`, `assets/`, `blocks/`, `languages/` liegen in der Repository-Wurzel. `docs/`, `tests/`, `bin/`, `examples/` (außer ausdrücklich ausgelieferter Beispiele), `konzept.md`, `TODO.md`, Konfigurationsdateien für CI/Linter sind **nicht** Teil des Release-ZIPs. Ausschluss über `.distignore` (gelesen von `wp dist-archive` bzw. dem Build-Skript in `bin/`) und `.gitattributes export-ignore`. Der Plugin-Ordner im ZIP heißt `review-captain/` unabhängig vom Repo-Namen `wp-reviewmaster`.

## Konsequenzen

- Positiv: direktes Entwickeln im WordPress-Plugins-Ordner; keine Build-Pflicht für PHP.
- Negativ: Release-Packen muss Ausschlüsse zuverlässig anwenden; Gefahr, dass `docs/` oder Testfixtures versehentlich ausgeliefert werden.
- Da der Repo-Name vom Slug abweicht, muss ein lokaler Klon als `review-captain` benannt bzw. gelinkt werden, damit `plugin_basename()` und Textdomain-Pfade stimmen.

## Abnahme

- [ ] Release-ZIP enthält genau den Ordner `review-captain/` mit Hauptdatei `review-captain/review-captain.php`.
- [ ] ZIP enthält weder `docs/`, `tests/`, `bin/`, `konzept.md`, `TODO.md` noch `.git*`/CI-Dateien (automatischer Check im Build).
- [ ] Plugin aktiviert fehlerfrei aus einem Klon unter `wp-content/plugins/review-captain/`.

## Referenzen

- `docs/architecture/conventions.md` Repository-Layout
