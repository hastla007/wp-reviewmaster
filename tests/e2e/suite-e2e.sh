#!/usr/bin/env bash
# RC-106: Suite-E2E-Lauf (Core + Pro + WooCommerce + Voucher/Pass/Form/Affiliate Captain) in einer eigenen Instanz.
#
#   E2E_DIR=/tmp/claude-0/wp-e2e/suite tests/e2e/suite-e2e.sh setup   # WordPress 7.x, Plugins, Testlizenz, Basis-Snapshot
#   E2E_DIR=/tmp/claude-0/wp-e2e/suite tests/e2e/suite-e2e.sh run     # Snapshot zurückspielen, alle Szenarien
#
# Quellen: WooCommerce-ZIP (E2E_WOO_ZIP, offizielles GitHub-Release, da downloads.wordpress.org gesperrt ist),
# Suite-Kerne per git archive (E2E_SUITE_REF, Standard origin/main) aus E2E_SUITE_SRC (deploy-suite.sh).
# Unter PHP < 8.5 werden nur in den Testkopien der Header "Requires PHP" und die harten PHP-8.5-Prüfungen der
# Suite-Plugins gesenkt; der Core nutzt seinen Testschalter REVIEW_CAPTAIN_DEV_IGNORE_PHP_MIN (wp-config.e2e.php).
set -uo pipefail
cd "$(dirname "$0")/../.." || exit 1
source tests/e2e/env.sh
PL="$E2E_WP/wp-content/plugins"
OUT="$E2E_DIR/results-suite.txt"
E2E_WOO_ZIP="${E2E_WOO_ZIP:-$E2E_DIR/dl/woocommerce-11.3.0.zip}"
E2E_WOO_URL="${E2E_WOO_URL:-https://github.com/woocommerce/woocommerce/releases/download/11.3.0/woocommerce.zip}"
export E2E_SUITE_REF="${E2E_SUITE_REF:-origin/main}"

lower_php_min() {
	local l f
	l="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
	php -r 'exit(version_compare(PHP_VERSION,"8.5",">=")?0:1);' && return 0
	for f in review-captain-pro/review-captain-pro.php form-captain/form-captain.php pass-captain/pass-captain.php voucher-captain/voucher-captain.php affiliate-captain/affiliate-captain.php; do
		[ -f "$PL/$f" ] || continue
		sed -i -E "s/^( \* Requires PHP: +)8\.5$/\1$l/; s/version_compare\( PHP_VERSION, '8\.5', '<' \)/version_compare( PHP_VERSION, '$l', '<' )/; s/define\( 'FORM_CAPTAIN_MIN_PHP', '8\.5' \)/define( 'FORM_CAPTAIN_MIN_PHP', '$l' )/" "$PL/$f"
	done
	echo "Hinweis: Testkopien mit PHP-Minimum $l (lokal kein PHP 8.5)"
}

setup() {
	WP_CONSTRAINT="${WP_CONSTRAINT:-^7.0}" tests/e2e/setup.sh --fresh || exit 1
	mkdir -p "$E2E_DIR/dl"
	[ -f "$E2E_WOO_ZIP" ] || curl -sSLf -o "$E2E_WOO_ZIP" "$E2E_WOO_URL" || exit 1
	rm -rf "$PL/woocommerce"; ( cd "$PL" && unzip -q "$E2E_WOO_ZIP" )
	tests/e2e/deploy-suite.sh
	lower_php_min
	mkdir -p "$E2E_WP/wp-content/mu-plugins"
	cp tests/e2e/mu-plugins/e2e-trace.php tests/e2e/mu-plugins/e2e-license-fake.php tests/e2e/mu-plugins/e2e-mail-capture.php "$E2E_WP/wp-content/mu-plugins/"
	printf "<?php\n// Offline-Sandbox: keine externen HTTP-Aufrufe (WordPress.org gesperrt).\ndefine( 'WP_HTTP_BLOCK_EXTERNAL', true );\n" >"$E2E_WP/wp-config-e2e-extra.php"
	wp plugin activate woocommerce review-captain review-captain-pro >/dev/null
	wp eval '\ReviewCaptainPro\addon()->license()->activate("E2E-TEST-KEY-0001","production");'
	wp plugin activate voucher-captain pass-captain form-captain affiliate-captain >/dev/null
	# Pass Captain legt sein Schema hinter GET_LOCK an; die SQLite-Emulation kennt GET_LOCK nicht → nachholen.
	wp eval 'if ( "" === (string) get_option( "wpcap_db_version", "" ) ) { var_dump( PassCaptain\Installer::install() ); }'
	wp eval '\ReviewCaptainPro\Requests\Schema::maybe_install(); do_action( "admin_init" );' --user=admin >/dev/null 2>&1
	wp plugin list --fields=name,status,version
	cp "$(dbfile)" "$E2E_DIR/base.sqlite"
	echo "Basis-Snapshot: $E2E_DIR/base.sqlite"
}

run() {
	[ -f "$E2E_DIR/base.sqlite" ] || { echo "erst: $0 setup"; exit 1; }
	cp "$E2E_DIR/base.sqlite" "$(dbfile)"; : >"$(debuglog)"; : >"$OUT"
	server_start
	step() { echo "== $*" | tee -a "$OUT"; wp eval-file "tests/e2e/php/suite/$1.php" --user=admin 2>&1 | grep -v 'wp_update_plugins' | tee -a "$OUT"; }
	for s in voucher pass woo form affiliate optout; do step "$s"; done
	# S7: Suite-Plugins deaktiviert, dann fehlend (Ordner entfernt), dann wieder da.
	E2E_PHASE=prepare step missing prepare
	wp plugin deactivate voucher-captain pass-captain form-captain affiliate-captain woocommerce >/dev/null 2>&1
	E2E_PHASE=check E2E_LABEL=deaktiviert step missing check-deaktiviert
	wp plugin activate woocommerce voucher-captain pass-captain form-captain affiliate-captain >/dev/null 2>&1
	mkdir -p "$E2E_DIR/parked"; for p in voucher-captain pass-captain form-captain affiliate-captain; do mv "$PL/$p" "$E2E_DIR/parked/"; done
	E2E_PHASE=check E2E_LABEL=fehlt step missing check-fehlt
	curl -s -o /dev/null -w "Startseite ohne Suite-Ordner: HTTP %{http_code}\n" "$E2E_URL/" | tee -a "$OUT"
	for p in voucher-captain pass-captain form-captain affiliate-captain; do mv "$E2E_DIR/parked/$p" "$PL/"; done
	E2E_PHASE=resume step missing resume
	echo "== debug.log (Meldungen, gezählt)" | tee -a "$OUT"
	grep -E '^\[' "$(debuglog)" | sed -E 's/^\[[^]]+\] //' | cut -c1-240 | sort | uniq -c | sort -rn | head -40 | tee -a "$OUT"
	echo "Ergebnis: $(grep -c '^PASS' "$OUT") PASS, $(grep -c '^FAIL' "$OUT") FAIL → $OUT"
}

pro() {
	# Nur Pro neu bauen und in die Instanz kopieren (Arbeitsstand von $E2E_PRO_SRC), Basis-Snapshot bleibt.
	rm -rf "$E2E_DIR/dist"
	( cd "$E2E_PRO_SRC" && php build/build.php --addon=review-captain-pro --out="$E2E_DIR/dist" ) >"$E2E_DIR/pro-build.log" 2>&1 || { cat "$E2E_DIR/pro-build.log"; exit 1; }
	rm -rf "$PL/review-captain-pro"; ( cd "$PL" && unzip -q "$E2E_DIR"/dist/review-captain-pro-*.zip )
	lower_php_min
	# Laufender php -S behält sonst alten Opcode-Cache der ersetzten Dateien.
	pkill -f "S 127.0.0.1:$E2E_PORT" 2>/dev/null; sleep 1; server_start
	echo "Pro: $(cd "$E2E_PRO_SRC" && git rev-parse --short HEAD)$(cd "$E2E_PRO_SRC" && git diff --quiet || echo ' + Arbeitsstand')"
}

case "${1:-run}" in
	setup) setup ;;
	run) run ;;
	pro) pro ;;
	*) echo "Aufruf: $0 setup|run|pro"; exit 2 ;;
esac
