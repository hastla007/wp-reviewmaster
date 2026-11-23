#!/usr/bin/env bash
# RC-131 Multisite-Smoke (nur WP-CLI) auf einer separaten Instanz $E2E_DIR/wp-ms mit eigener SQLite-Datei.
set -uo pipefail
export E2E_WP="${E2E_DIR:-/tmp/claude-0/wp-e2e}/wp-ms"
source "$(dirname "$0")/env.sh"
OUT="$E2E_DIR/results-multisite.txt"; : >"$OUT"
pass() { echo "PASS $*" | tee -a "$OUT"; }; fail() { echo "FAIL $*" | tee -a "$OUT"; }
SQL() { php "$(dirname "$0")/php/sql.php" "$(dbfile)" "$1"; }
rm -rf "$E2E_WP"; mkdir -p "$E2E_WP"
cp -a "$E2E_DIR/wp/." "$E2E_WP/"
rm -rf "$E2E_WP/wp-content/database" "$E2E_WP/wp-content/debug.log" "$E2E_WP/wp-content/mu-plugins/e2e-license-fake.php" "$E2E_WP/wp-content/plugins/wordpress-seo"
mkdir -p "$E2E_WP/wp-content/database"
sed -i "s#$E2E_DIR/wp/wp-content/plugins/sqlite-database-integration#$E2E_WP/wp-content/plugins/sqlite-database-integration#" "$E2E_WP/wp-content/db.php"
rm -f "$E2E_WP/wp-config-e2e-extra.php"
wp core multisite-install --url=http://127.0.0.1:8898 --title=MS --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email --skip-config >/dev/null 2>&1
cat >"$E2E_WP/wp-config-e2e-extra.php" <<'PHP'
<?php
define( 'WP_ALLOW_MULTISITE', true ); define( 'MULTISITE', true ); define( 'SUBDOMAIN_INSTALL', false );
define( 'DOMAIN_CURRENT_SITE', '127.0.0.1:8898' ); define( 'PATH_CURRENT_SITE', '/' ); define( 'SITE_ID_CURRENT_SITE', 1 ); define( 'BLOG_ID_CURRENT_SITE', 1 );
PHP
wp core is-installed --network >/dev/null 2>&1 && pass "Multisite installiert (SQLite)" || { fail "Multisite-Installation"; exit 1; }
wp site create --slug=zwei --title=Zwei >/dev/null 2>&1
rm -f "$(debuglog)"
wp plugin activate review-captain --network >/dev/null 2>&1 && pass "Core netzwerkweit aktiviert" || fail "Netzwerkaktivierung"
for b in "" "2_"; do n="$(SQL "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'wp_${b}review_captain_%'")"; [ "$n" -ge 6 ] && pass "Tabellen wp_${b}review_captain_*: $n" || fail "Tabellen wp_${b}review_captain_*: $n"; done
wp site create --slug=drei --title=Drei >/dev/null 2>&1
n="$(SQL "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'wp_3_review_captain_%'")"; [ "$n" -ge 6 ] && pass "Neue Site nach Netzwerkaktivierung erhält Tabellen ($n)" || fail "Neue Site 3 ohne Tabellen ($n)"
wp --url=127.0.0.1:8898/zwei/ review-captain doctor 2>/dev/null | tee -a "$OUT"
wp plugin activate review-captain-pro --network >/dev/null 2>&1 && pass "Pro netzwerkweit aktiviert" || fail "Pro netzwerkweit"
wp --url=127.0.0.1:8898/zwei/ eval 'echo (int)\ReviewCaptainPro\addon()->is_entitled("review.analytics");' 2>/dev/null | grep -q 0 && pass "Pro ohne Lizenz auf Site 2: keine Rechte" || fail "Pro-Rechte auf Site 2"
for s in "" "zwei/" "drei/"; do wp --url="127.0.0.1:8898/$s" eval '$s=get_option("review_captain_settings",array()); if(!is_array($s)){$s=array();} $s["privacy"]["delete_data_on_uninstall"]=true; $s["privacy"]["delete_data_confirmed_at"]=gmdate("Y-m-d H:i:s"); update_option("review_captain_settings",$s);' >/dev/null 2>&1; done
wp plugin deactivate review-captain-pro --network >/dev/null 2>&1; wp plugin uninstall review-captain-pro --skip-delete >/dev/null 2>&1
wp plugin deactivate review-captain --network >/dev/null 2>&1; wp plugin uninstall review-captain --skip-delete >/dev/null 2>&1
n="$(SQL "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'wp_%review_captain_%'")"; [ "$n" -eq 0 ] && pass "Netzwerk-Deinstallation entfernt alle Tabellen" || fail "Nach Netzwerk-Deinstallation $n Tabellen übrig: $(SQL "SELECT name FROM sqlite_master WHERE name LIKE 'wp_%review_captain_%'" | tr '\n' ' ')"
L="$(grep -E 'PHP (Notice|Warning|Deprecated|Fatal)|database error' "$(debuglog)" 2>/dev/null | grep -v -E '_load_textdomain_just_in_time|wp_update_|wordpress\.org' | sed -E 's/^\[[^]]*\] //' | sort | uniq -c)"
[ -z "$L" ] && pass "debug.log (Multisite) ohne Meldungen außer bekanntem Textdomain-Notice" || { fail "debug.log (Multisite)"; echo "$L" | head -10 | tee -a "$OUT"; }
