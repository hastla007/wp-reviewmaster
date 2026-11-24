#!/usr/bin/env bash
# Kopiert Core (Allowlist wie bin/build-zip.sh) und das gebaute Pro-ZIP in die Testinstanz.
# Core-Stand: E2E_CORE_REF=HEAD (git archive, Standard) oder E2E_CORE_REF=worktree.
set -euo pipefail
source "$(dirname "$0")/env.sh"
PL="$E2E_WP/wp-content/plugins"
STAGE="$E2E_DIR/stage-core"; rm -rf "$STAGE"; mkdir -p "$STAGE"
if [ "$E2E_CORE_REF" = "worktree" ]; then
	( cd "$E2E_CORE_SRC" && tar -cf - review-captain.php uninstall.php readme.txt LICENSE src templates assets blocks languages ) | tar -xf - -C "$STAGE"
else
	( cd "$E2E_CORE_SRC" && git archive "$E2E_CORE_REF" review-captain.php uninstall.php readme.txt LICENSE src templates assets blocks languages ) | tar -xf - -C "$STAGE"
fi
rm -rf "$PL/review-captain"; cp -a "$STAGE" "$PL/review-captain"
# MINVER-1: the repository requires PHP 8.5. On an older local PHP, lower the header in the *test copy only*
# (WordPress blocks activation by header); wp-config.e2e.php sets REVIEW_CAPTAIN_DEV_IGNORE_PHP_MIN for the runtime check.
LOCAL_PHP="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
if php -r 'exit(version_compare(PHP_VERSION,"8.5",">=")?0:1);'; then :; else
	sed -i "s/^ \* Requires PHP:      8\.5$/ * Requires PHP:      $LOCAL_PHP/" "$PL/review-captain/review-captain.php"
	echo "Hinweis: Testkopie mit Requires PHP $LOCAL_PHP (lokal kein PHP 8.5)"
fi
echo "Core: $(cd "$E2E_CORE_SRC" && git rev-parse --short "${E2E_CORE_REF/worktree/HEAD}") ($E2E_CORE_REF)"

# Demo-Connector (CON-016)
rm -rf "$PL/review-captain-demo-connector"
cp -a "$E2E_CORE_SRC/examples/review-captain-demo-connector" "$PL/review-captain-demo-connector"

# Pro-Add-on bauen und entpacken. Ohne Pro-Checkout (CI ohne Secret CAPTAIN_PRO_TOKEN) oder mit
# E2E_SKIP_PRO=1 nur Core; die Pro-Phasen von smoke.sh überspringen sich dann.
if [ -n "${E2E_SKIP_PRO:-}" ] || [ ! -f "$E2E_PRO_SRC/build/build.php" ]; then
	rm -rf "$PL/review-captain-pro"
	echo "Pro: übersprungen (kein Checkout unter $E2E_PRO_SRC oder E2E_SKIP_PRO gesetzt)"
	exit 0
fi
rm -rf "$E2E_DIR/dist"
( cd "$E2E_PRO_SRC" && php build/build.php --addon=review-captain-pro --out="$E2E_DIR/dist" ) >"$E2E_DIR/pro-build.log" 2>&1 || { cat "$E2E_DIR/pro-build.log"; exit 1; }
ZIP="$(ls "$E2E_DIR"/dist/review-captain-pro-*.zip | head -1)"
rm -rf "$PL/review-captain-pro"; ( cd "$PL" && unzip -q "$ZIP" )
if ! php -r 'exit(version_compare(PHP_VERSION,"8.5",">=")?0:1);'; then
	# Wie suite-e2e.sh: Pro hat keinen Testschalter, daher Minimum nur in der Testkopie senken.
	sed -i -E "s/^( \* Requires PHP: +)8\.5$/\1$LOCAL_PHP/; s/version_compare\( PHP_VERSION, '8\.5', '<' \)/version_compare( PHP_VERSION, '$LOCAL_PHP', '<' )/" "$PL/review-captain-pro/review-captain-pro.php"
fi
echo "Pro: $(basename "$ZIP") (captain-pro $(cd "$E2E_PRO_SRC" && git rev-parse --short HEAD))"
