#!/usr/bin/env bash
# P6-6: E2E-Lauf für die CI (Job "e2e" in .github/workflows/ci.yml), lokal genauso aufrufbar.
# Frische WordPress-Instanz (WP_CONSTRAINT, SQLite) mit Core und – falls E2E_PRO_SRC ein Checkout von
# captain-pro ist – Pro, dann:
#   1. smoke.sh (WP-CLI, ohne Browser): Aktivieren/Deaktivieren/Deinstallieren Core, Pro ohne Core, Core+Pro, debug.log
#   2. php/content.php + functional.cjs (Playwright/Chromium): Formular, Moderation, Rendering, CSV, Datenschutz,
#      mit aktivem Pro neben dem Core. Ohne auflösbares Modul `playwright` (NODE_PATH) übersprungen, mit Hinweis.
# Exit-Code 1, sobald eine Prüfung FAIL meldet. Ergebnisse: $E2E_DIR/results-*.{txt,json}.
set -euo pipefail
cd "$(dirname "$0")/../.."
source tests/e2e/env.sh
export E2E_STRICT=1 E2E_DIR E2E_WP E2E_URL
notice() { if [ -n "${GITHUB_ACTIONS:-}" ]; then echo "::notice::$*"; else echo "Hinweis: $*"; fi; }
failed=()

tests/e2e/setup.sh --fresh
server_start
curl -s -o /dev/null "$E2E_URL/wp-login.php" || { echo "Webserver unter $E2E_URL antwortet nicht"; cat "$E2E_DIR/php-server.log" || true; exit 1; }
HAS_PRO=0; [ -d "$E2E_WP/wp-content/plugins/review-captain-pro" ] && HAS_PRO=1
[ "$HAS_PRO" = 1 ] || notice "E2E ohne Review Captain Pro (kein Pro-Checkout): nur Core geprüft."

echo "=== smoke.sh (WordPress $(wp core version))"
tests/e2e/smoke.sh || failed+=("smoke.sh")

echo "=== functional.cjs"
if node -e 'require.resolve("playwright")' >/dev/null 2>&1; then
	PLUGINS=(review-captain review-captain-demo-connector); [ "$HAS_PRO" = 1 ] && PLUGINS+=(review-captain-pro)
	wp plugin activate "${PLUGINS[@]}"
	wp eval-file tests/e2e/php/content.php >/dev/null
	rm -f "$E2E_DIR/results-functional.json"
	node tests/e2e/functional.cjs || failed+=("functional.cjs (Abbruch)")
	if [ -f "$E2E_DIR/results-functional.json" ]; then
		python3 -I -c '
import json, sys
d = json.load(open(sys.argv[1]))
bad = [r["id"] for r in d.get("results", []) if r.get("ok") is False]
print("functional: %d Prüfungen, FAIL: %s" % (len(d.get("results", [])), ", ".join(bad) or "keine"))
sys.exit(1 if bad or d.get("error") else 0)' "$E2E_DIR/results-functional.json" || failed+=("functional.cjs")
	fi
	# Notices/Warnings, die erst im Browserlauf entstehen (Frontend, Admin, Export, Datenschutz).
	if [ -f "$(debuglog)" ] && grep -E 'PHP (Notice|Warning|Deprecated|Fatal|Parse)' "$(debuglog)" | grep -v -E 'wp_update_(plugins|themes)|wp_version_check|wordpress\.org' | grep -E 'review-captain' ; then
		failed+=("debug.log nach functional.cjs")
	fi
else
	notice "functional.cjs übersprungen: Node-Modul playwright nicht gefunden (NODE_PATH)."
fi

if [ "${#failed[@]}" -gt 0 ]; then
	echo "E2E FEHLGESCHLAGEN: ${failed[*]}"
	exit 1
fi
echo "E2E OK (WordPress $(wp core version), Pro: $HAS_PRO)"
