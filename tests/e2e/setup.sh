#!/usr/bin/env bash
# Legt eine frische WordPress-Testinstanz mit SQLite an und stellt Core + Pro bereit (nicht aktiviert).
# Quellen: Packagist (johnpbloch/wordpress-core, wp-cli/wp-cli-bundle), GitHub WordPress/sqlite-database-integration (Tag).
# Aufruf: tests/e2e/setup.sh [--fresh]   (--fresh löscht $E2E_WP komplett)
set -euo pipefail
source "$(dirname "$0")/env.sh"
SQLITE_TAG="${SQLITE_TAG:-v3.1.3}"
WP_CONSTRAINT="${WP_CONSTRAINT:-^7.0}"

mkdir -p "$E2E_DIR/composer" "$E2E_DIR/dl"
if [ ! -f "$E2E_WPCLI_BIN" ]; then
	cat >"$E2E_DIR/composer/composer.json" <<JSON
{ "name": "local/wp-e2e", "require": { "johnpbloch/wordpress-core": "$WP_CONSTRAINT", "wp-cli/wp-cli-bundle": "^2.11" },
  "config": { "allow-plugins": false } }
JSON
	( cd "$E2E_DIR/composer" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-plugins )
fi
# SQLite-Integration: downloads.wordpress.org/wpackagist sind in dieser Umgebung gesperrt (Proxy 403),
# daher offizielles GitHub-Repo des WordPress-Projekts, fester Release-Tag.
if [ ! -d "$E2E_DIR/dl/sqlite-src" ]; then
	git clone -q --depth 1 --branch "$SQLITE_TAG" https://github.com/WordPress/sqlite-database-integration.git "$E2E_DIR/dl/sqlite-src"
fi

[ "${1:-}" = "--fresh" ] && rm -rf "$E2E_WP"
if [ ! -f "$E2E_WP/wp-settings.php" ]; then
	mkdir -p "$E2E_WP"
	cp -a "$E2E_DIR/composer/vendor/johnpbloch/wordpress-core/." "$E2E_WP/"
	P="$E2E_WP/wp-content/plugins/sqlite-database-integration"
	mkdir -p "$P"
	cp -a "$E2E_DIR/dl/sqlite-src/packages/plugin-sqlite-database-integration/." "$P/"
	rm -f "$P/wp-includes/database"
	cp -a "$E2E_DIR/dl/sqlite-src/packages/mysql-on-sqlite/src" "$P/wp-includes/database"
	sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$P#" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$P/db.copy" >"$E2E_WP/wp-content/db.php"
	mkdir -p "$E2E_WP/wp-content/database"
	cp "$(dirname "$0")/wp-config.e2e.php" "$E2E_WP/wp-config.php"
	wp core install --url="$E2E_URL" --title=E2E --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email
	wp rewrite structure '/%postname%/' --hard >/dev/null
fi

"$(dirname "$0")/deploy-plugins.sh"
echo "WordPress: $E2E_WP  ($(wp core version))  URL: $E2E_URL"
