#!/usr/bin/env bash
# RC-131/RC-133: install WooCommerce (and optionally Yoast) into the E2E instance from Packagist.
# downloads.wordpress.org is blocked in the sandbox; Packagist only has WooCommerce up to 5.9.1 (source
# package, needs the jetpack autoloader). Elsewhere prefer: wp plugin install woocommerce --activate.
set -uo pipefail
source "$(dirname "$0")/env.sh"
export COMPOSER_ALLOW_SUPERUSER=1
WOO_VERSION="${WOO_VERSION:-5.9.1}"; YOAST_VERSION="${YOAST_VERSION:-27.0}"
PL="$E2E_WP/wp-content/plugins"
mkdir -p "$E2E_DIR/woo" "$E2E_DIR/yoast"
if [ ! -f "$PL/woocommerce/vendor/autoload_packages.php" ]; then
	( cd "$E2E_DIR/woo" && echo '{"config":{"allow-plugins":true}}' > composer.json && composer require -q --no-interaction --no-plugins --ignore-platform-reqs "woocommerce/woocommerce:$WOO_VERSION" )
	rm -rf "$PL/woocommerce"; mkdir -p "$PL/woocommerce"
	( cd "$E2E_DIR/woo/vendor/woocommerce/woocommerce" && tar --exclude=./tests --exclude=./.git -cf - . ) | tar -xf - -C "$PL/woocommerce"
	rm -rf "$E2E_DIR/woo/vendor/woocommerce/woocommerce/.git"
	( cd "$PL/woocommerce" && mkdir -p tests/php/src tests/Tools tests/legacy/unit-tests/rest-api/Helpers \
		&& composer config --no-plugins allow-plugins.automattic/jetpack-autoloader true \
		&& composer config --no-plugins allow-plugins.composer/installers true \
		&& composer install -q --no-dev --no-interaction --ignore-platform-reqs )
fi
if [ ! -f "$PL/wordpress-seo/src/generated/container.php" ]; then
	( cd "$E2E_DIR/yoast" && echo '{"config":{"allow-plugins":true}}' > composer.json && composer require -q --no-interaction --no-plugins "yoast/wordpress-seo:$YOAST_VERSION" )
	rm -rf "$PL/wordpress-seo"; cp -a "$E2E_DIR/yoast/vendor/yoast/wordpress-seo" "$PL/"
	( cd "$PL/wordpress-seo" && composer dump-autoload -q --no-dev --no-scripts )
fi
ls "$PL/woocommerce/vendor/autoload_packages.php" "$PL/wordpress-seo/src/generated/container.php"
