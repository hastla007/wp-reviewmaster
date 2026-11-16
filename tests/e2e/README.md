# End-to-End-Prüfungen in einer echten WordPress-Instanz

Skripte für die QA-Läufe aus `docs/qa/2026-11-e2e.md` (RC-131, RC-136/TEC-014, RC-137/RC-120, RC-139, CON-016, SEO-016).
Sie ändern keinen Produktcode; alles Laufzeitmaterial liegt unter `$E2E_DIR` (Standard `/tmp/claude-0/wp-e2e`).

## Voraussetzungen

- PHP ≥ 8.1 (Testkopie: `deploy-plugins.sh` senkt unter PHP 8.5 den `Requires PHP`-Kopf nur in der Kopie, `wp-config.e2e.php` setzt `REVIEW_CAPTAIN_DEV_IGNORE_PHP_MIN`) mit `pdo_sqlite`, `sodium`, `zip`; Composer; `git`, `unzip`, `curl`, `bc`, Python 3 mit Pillow (nur `shrink-png.py`).
- Node.js mit Playwright/Chromium (`NODE_PATH` auf das Modulverzeichnis, hier `/opt/node-tools/node_modules`).
- `axe-core` lokal: `mkdir -p $E2E_DIR/node && cd $E2E_DIR/node && npm init -y && npm install axe-core@4`.
- Kein MySQL nötig: Datenbank ist SQLite über das offizielle Plugin *SQLite Database Integration*.
- Checkout von `captain-pro` (Standard `/home/user/captain-pro`, Variable `E2E_PRO_SRC`).

Quellen (nur offizielle Wege, keine Phars, keine globalen Installationen):

| Was | Woher | Bemerkung |
| --- | --- | --- |
| WordPress | Packagist `johnpbloch/wordpress-core` (`WP_CONSTRAINT`, Standard `^6.7` → 6.9.x) | |
| WP-CLI | Packagist `wp-cli/wp-cli-bundle`, gestartet als `php …/wp-cli/php/boot-fs.php` | `vendor/bin/wp` ist nur ein Shell-Wrapper |
| SQLite-Integration | GitHub `WordPress/sqlite-database-integration`, Tag `v3.1.3` | wpackagist.org und downloads.wordpress.org sind in der Sandbox per Proxy gesperrt (403) |
| Yoast SEO (optional) | Packagist `yoast/wordpress-seo` (Yoast-dist), z. B. 27.0 für WP 6.9 (28.x verlangt WP 7.0) | danach im Pluginordner `composer dump-autoload --no-dev --no-scripts` |

## Ablauf

```bash
source tests/e2e/env.sh            # Variablen + Funktion `wp` (WP-CLI gegen $E2E_WP)
tests/e2e/setup.sh --fresh         # WordPress + SQLite installieren, Core (git archive HEAD) + Pro (build.php) + Demo-Connector kopieren
tests/e2e/install-woo.sh           # WooCommerce 5.9.1 + Yoast 27.0 über Packagist (RC-131/RC-133)
tests/e2e/deploy-suite.sh          # Form/Pass/Voucher/Affiliate Captain aus lokalen Checkouts (RC-131)
server_start                       # php -S 127.0.0.1:8899 -t $E2E_WP im Hintergrund
tests/e2e/smoke.sh                 # RC-131: Aktivieren/Deaktivieren/Deinstallieren Core, Pro, Core+Pro, debug.log
tests/e2e/multisite.sh             # RC-131: Multisite-Smoke auf eigener Instanz $E2E_DIR/wp-ms
wp plugin activate review-captain review-captain-pro review-captain-demo-connector
wp eval-file tests/e2e/php/content.php            # Testseiten (Shortcodes, Blöcke, Produkt, Performance) + CTA-Profil
NODE_PATH=/opt/node-tools/node_modules node tests/e2e/functional.cjs   # Formular, Moderation, Rendering, CSV, Datenschutz
wp eval-file tests/e2e/php/connector-status.php   # CON-016 Registry-Status (E2E_CONNECTOR_ACTION=enqueue|poll)
tests/e2e/seo.sh                                  # SEO-016 JSON-LD ohne/mit Yoast
cp tests/e2e/mu-plugins/e2e-seo-switch.php $E2E_WP/wp-content/mu-plugins/ && tests/e2e/seo-scenarios.sh  # SEO-016 Checkliste §3/§4 mit Assertions
# optional Pro mit simulierter Lizenz (siehe unten), dann:
wp eval-file tests/e2e/php/content-pro.php        # Pro-Widgets + schwebendes Badge
NODE_PATH=/opt/node-tools/node_modules node tests/e2e/a11y-mobile.cjs  # axe, Tastatur, iPhone/Pixel, Screenshots
python3 -I tests/e2e/shrink-png.py docs/qa/screenshots
NODE_PATH=/opt/node-tools/node_modules node tests/e2e/admin-crawl.cjs <label>  # alle Adminseiten Core+Pro
tests/e2e/backup-restore.sh                       # RC-139
wp review-captain dev seed --reviews=20000 --jobs=2000 --yes
NODE_PATH=/opt/node-tools/node_modules node tests/e2e/perf.cjs         # RC-136/TEC-014 Zeiten
```

Ergebnisse: `$E2E_DIR/results-*.{txt,json}`, Downloads unter `$E2E_DIR/artifacts/`. Für Lesezugriffe auf die Datenbank
gibt es `php tests/e2e/php/sql.php <sqlite-datei> "<SQL>"` (`wp db query` braucht den mysql-Client).

`E2E_CORE_REF=worktree tests/e2e/deploy-plugins.sh` testet statt `HEAD` den Arbeitsstand des Core.

## Hilfs-MU-Plugins (`mu-plugins/`, nur Testinstanz)

- `e2e-trace.php`: schreibt bei `_doing_it_wrong`/Deprecations eine Zeile `E2E-TRACE … <= Datei:Zeile` ins `debug.log`, damit Notices dem Plugin-Code zugeordnet werden können. Wird von `smoke.sh` kopiert.
- `e2e-license-fake.php`: **simulierter Lizenzserver** für Review Captain Pro. Nutzt den dokumentierten Dev-Weg (`REVIEW_CAPTAIN_PRO_LICENSE_DEV`, Filter `review_captain_pro_license_server_url` / `…_public_keys`) und signiert wie `packages/license-client/testing/client-test-kit.php`. Aktivieren: Datei nach `wp-content/mu-plugins/` kopieren, dann
  `wp eval '\ReviewCaptainPro\addon()->license()->activate("E2E-TEST-KEY-0001","production");'`.
  Rechte begrenzen: `wp option update e2e_fake_wplc_entitlements '["review.platform_sync"]' --format=json` und `…->license()->refresh(true)`.
  Beweist nicht, dass der echte Server (wp-license) gleich antwortet; dafür gibt es `captain-pro/tests/e2e/run.sh` (braucht MySQL).

## Grenzen

- SQLite-Emulation ≠ MySQL/MariaDB: Zeiten sind nur relativ aussagekräftig; MySQL-Funktionen ohne Übersetzung (z. B. `TIMESTAMPDIFF(HOUR, …)`) schlagen nur hier fehl.
- `php -S` ist ein Einzelprozess-Server ohne Rewrite-Regeln; Nebenläufigkeit wird nicht geprüft.
- Kein Internet: Update-Prüfungen von WordPress.org erzeugen `wp_update_plugins()`-Warnungen; `smoke.sh` filtert sie heraus.
