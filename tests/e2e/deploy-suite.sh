#!/usr/bin/env bash
# RC-131: deploy the suite plugins (git archive of a ref, default origin/main) into the E2E instance.
# Sources: E2E_SUITE_SRC (default /home/user/hastla007) with wp-formmaster, wp-passmaster, wp-couponmaster,
# wpaffiliatemaster. Only reads the checkouts.
set -uo pipefail
source "$(dirname "$0")/env.sh"
SRC="${E2E_SUITE_SRC:-/home/user/hastla007}"
REF="${E2E_SUITE_REF:-HEAD}"
PL="$E2E_WP/wp-content/plugins"
for pair in wp-formmaster:form-captain wp-passmaster:pass-captain wp-couponmaster:voucher-captain wpaffiliatemaster:affiliate-captain; do
	repo="${pair%%:*}"; slug="${pair##*:}"
	[ -d "$SRC/$repo/.git" ] || { echo "SKIP $repo (kein Checkout)"; continue; }
	rm -rf "$PL/$slug"; mkdir -p "$PL/$slug"
	( cd "$SRC/$repo" && git archive "$REF" ) | tar -x -C "$PL/$slug"
	if [ -f "$PL/$slug/composer.json" ] && grep -q '"autoload"' "$PL/$slug/composer.json" && [ ! -d "$PL/$slug/vendor" ]; then
		( cd "$PL/$slug" && COMPOSER_ALLOW_SUPERUSER=1 composer install -q --no-dev --no-interaction --ignore-platform-reqs >/dev/null 2>&1 )
	fi
	main="$(grep -l "Plugin Name:" "$PL/$slug"/*.php 2>/dev/null | head -1)"
	echo "$slug: $(cd "$SRC/$repo" && git rev-parse --short "$REF") $(basename "$main")"
done
