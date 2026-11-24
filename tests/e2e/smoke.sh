#!/usr/bin/env bash
# RC-131: Installations-/Aktivierungs-/Deaktivierungs-/Deinstallations-Smoke (Core allein, Core+Pro).
# Voraussetzung: tests/e2e/setup.sh. Ausgabe: PASS/FAIL-Zeilen, debug.log-Auswertung je Phase.
set -uo pipefail
source "$(dirname "$0")/env.sh"
OUT="$E2E_DIR/results-smoke.txt"; : >"$OUT"
pass() { echo "PASS $*" | tee -a "$OUT"; }
fail() { echo "FAIL $*" | tee -a "$OUT"; }
check() { local d="$1"; shift; if "$@" >/dev/null 2>&1; then pass "$d"; else fail "$d"; fi; }
ntables() { rc_tables | grep -c . || true; }
# Relevante Meldungen im debug.log (Umgebungsrauschen wp_update_* / wordpress.org ausgefiltert).
# wp_add_privacy_policy_content: nur ein Effekt des simulierten admin_init unter WP-CLI (is_admin() ist dort false).
log_issues() { [ -f "$(debuglog)" ] && grep -E 'PHP (Notice|Warning|Deprecated|Fatal|Parse)|E2E-TRACE' "$(debuglog)" | grep -v -E 'wp_update_(plugins|themes)|wp_version_check|wordpress\.org|wp_add_privacy_policy_content' || true; }
phase_log() { local n; n="$(log_issues | wc -l)"; if [ "$n" -eq 0 ]; then pass "$1: debug.log ohne Notices/Warnings/Deprecations"; else fail "$1: debug.log enthält $n Meldungen"; log_issues | sed -E 's/^\[[^]]*\] //; s/^/    /' | sort | uniq -c | head -20 | tee -a "$OUT"; fi; rm -f "$(debuglog)"; }
mkdir -p "$E2E_WP/wp-content/mu-plugins"; cp "$(dirname "$0")/mu-plugins/e2e-trace.php" "$E2E_WP/wp-content/mu-plugins/"

wp plugin deactivate review-captain-pro review-captain review-captain-demo-connector >/dev/null 2>&1
rm -f "$(debuglog)"

echo "== Core allein"
wp plugin activate review-captain >/dev/null 2>&1; check "Core aktiviert" wp plugin is-active review-captain
T="$(ntables)"; [ "$T" -ge 6 ] && pass "Schema-Tabellen angelegt ($T): $(rc_tables | sed 's/wp_review_captain_//' | tr '\n' ' ')" || fail "Schema-Tabellen ($T)"
check "Option review_captain_db_version gesetzt" wp option get review_captain_db_version
wp review-captain doctor --format=json >"$E2E_DIR/doctor-core.json" 2>>"$OUT"; check "doctor läuft" test -s "$E2E_DIR/doctor-core.json"
wp cron event list --fields=hook --format=csv 2>/dev/null | grep -q review_captain_ && pass "Cron-Events direkt nach Aktivierung geplant" || fail "keine review_captain_-Cron-Events nach Aktivierung per WP-CLI (erst nach admin_init)"
wp eval 'do_action("admin_init");' >/dev/null 2>&1; wp cron event list --fields=hook --format=csv 2>/dev/null | grep -q review_captain_run_jobs && pass "Cron-Events nach admin_init geplant" || fail "Cron-Events auch nach admin_init nicht geplant"
wp plugin deactivate review-captain >/dev/null 2>&1; wp plugin is-active review-captain >/dev/null 2>&1 && fail "Core deaktiviert" || pass "Core deaktiviert"
wp cron event list --fields=hook --format=csv 2>/dev/null | grep -q review_captain_ && fail "Cron-Events nach Deaktivierung noch geplant" || pass "Cron-Events nach Deaktivierung entfernt"
[ "$(ntables)" -ge 6 ] && pass "Tabellen bleiben nach Deaktivierung erhalten" || fail "Tabellen nach Deaktivierung weg"
wp plugin activate review-captain >/dev/null 2>&1; check "Reaktivierung" wp plugin is-active review-captain
phase_log "Core Aktivierung/Deaktivierung"

echo "== Core-Deinstallation ohne Löschoption (Daten bleiben)"
wp plugin uninstall review-captain --deactivate --skip-delete >/dev/null 2>>"$OUT"
[ "$(ntables)" -ge 6 ] && pass "Deinstallation ohne Löschoption: Tabellen bleiben" || fail "Deinstallation ohne Löschoption hat Tabellen gelöscht"
echo "== Core-Deinstallation mit privacy.delete_data_on_uninstall"
wp plugin activate review-captain >/dev/null 2>&1
wp eval '$s=get_option("review_captain_settings",array()); if(!is_array($s)){$s=array();} $s["privacy"]["delete_data_on_uninstall"]=true; $s["privacy"]["delete_data_confirmed_at"]=gmdate("Y-m-d H:i:s"); update_option("review_captain_settings",$s);' >/dev/null 2>>"$OUT"
wp plugin uninstall review-captain --deactivate --skip-delete >/dev/null 2>>"$OUT"
[ "$(ntables)" -eq 0 ] && pass "Deinstallation mit Löschoption: alle Tabellen entfernt" || fail "Deinstallation mit Löschoption: $(ntables) Tabellen übrig: $(rc_tables | tr '\n' ' ')"
LEFT="$(wp option list --search='review_captain*' --fields=option_name --format=csv 2>/dev/null | tail -n +2 | tr '\n' ' ')"
[ -z "$LEFT" ] && pass "Deinstallation: keine review_captain_*-Optionen übrig" || fail "Optionen übrig: $LEFT"
CAPS="$(wp eval 'echo implode(",", array_filter(array_keys(get_role("administrator")->capabilities), fn($c)=>str_contains($c,"review_captain")));' 2>/dev/null)"
[ -z "$CAPS" ] && pass "Deinstallation: Capabilities entfernt" || fail "Capabilities übrig: $CAPS"
phase_log "Core Deinstallation"

if [ ! -d "$E2E_WP/wp-content/plugins/review-captain-pro" ]; then
	echo "INFO Pro nicht bereitgestellt (deploy-plugins.sh): Phasen \"Pro ohne Core\" und \"Core+Pro\" übersprungen" | tee -a "$OUT"
	echo; echo "Summe: $(grep -c ^PASS "$OUT") PASS, $(grep -c ^FAIL "$OUT") FAIL  ($OUT)"
	[ -z "${E2E_STRICT:-}" ] || ! grep -q ^FAIL "$OUT"; exit
fi

echo "== Pro ohne Core"
wp plugin activate review-captain-pro >"$E2E_DIR/pro-without-core.txt" 2>&1
if wp plugin is-active review-captain-pro >/dev/null 2>&1; then pass "Pro ohne Core aktivierbar (Requires Plugins nicht erzwungen per WP-CLI) – kein Fatal"; else pass "Pro ohne Core: Aktivierung verweigert ($(tr '\n' ' ' <"$E2E_DIR/pro-without-core.txt" | cut -c1-160))"; fi
curl -s -o /dev/null -w '%{http_code}' "$E2E_URL/" | grep -q 200 && pass "Frontend 200 mit Pro ohne Core" || fail "Frontend nicht 200 mit Pro ohne Core"
wp plugin deactivate review-captain-pro >/dev/null 2>&1
phase_log "Pro ohne Core"

echo "== Core + Pro"
wp plugin activate review-captain >/dev/null 2>&1
wp plugin activate review-captain-pro >/dev/null 2>&1; check "Pro aktiviert neben Core" wp plugin is-active review-captain-pro
STATE="$(wp eval 'foreach(array("review.platform_sync","review.review_requests","review.advanced_widgets","review.analytics","review.suite_integrations","review.custom_connectors") as $k){ echo $k,"=",\ReviewCaptainPro\addon()->is_entitled($k)?"1":"0"," "; }' 2>/dev/null)"
echo "$STATE" | grep -q '=1' && fail "Pro ohne Lizenz gibt Rechte frei: $STATE" || pass "Pro ohne Lizenz: keine Rechte ($STATE)"
curl -s -o /dev/null -w '%{http_code}' "$E2E_URL/" | grep -q 200 && pass "Frontend 200 mit Core+Pro" || fail "Frontend nicht 200 mit Core+Pro"
T2="$(ntables)"; pass "Tabellen mit Core+Pro: $T2 ($(rc_tables | sed 's/wp_review_captain_//' | tr '\n' ' '))"
wp plugin deactivate review-captain >/dev/null 2>&1
curl -s -o /dev/null -w '%{http_code}' "$E2E_URL/" | grep -q 200 && pass "Frontend 200: Pro aktiv, Core deaktiviert" || fail "Frontend nicht 200 (Pro aktiv, Core aus)"
wp plugin activate review-captain >/dev/null 2>&1
wp plugin deactivate review-captain-pro >/dev/null 2>&1; check "Pro deaktiviert" bash -c "true"
wp plugin activate review-captain-pro >/dev/null 2>&1
wp plugin uninstall review-captain-pro --deactivate --skip-delete >/dev/null 2>>"$OUT"
# Pro behält Einstellungen und Datensätze, solange "alle Daten entfernen" nicht gewählt ist (captain-pro uninstall.php);
# Termine (Cron) und Transienten gehen immer.
LEFT="$(wp option list --search='review_captain_pro*' --fields=option_name --format=csv 2>/dev/null | tail -n +2 | tr '\n' ' ')"
echo "INFO Pro-Deinstallation ohne Löschwahl, behaltene Optionen: ${LEFT:-keine}" | tee -a "$OUT"
wp cron event list --fields=hook --format=csv 2>/dev/null | grep -q review_captain_pro && fail "Pro-Cron-Events nach Deinstallation noch geplant" || pass "Pro-Deinstallation: keine review_captain_pro-Cron-Events"
check "Core bleibt nach Pro-Deinstallation aktiv" wp plugin is-active review-captain
[ "$(ntables)" -ge 6 ] && pass "Core-Tabellen nach Pro-Deinstallation vorhanden" || fail "Core-Tabellen nach Pro-Deinstallation fehlen"
phase_log "Core+Pro"
echo; echo "Summe: $(grep -c ^PASS "$OUT") PASS, $(grep -c ^FAIL "$OUT") FAIL  ($OUT)"
# E2E_STRICT=1 (CI): Exit-Code 1 bei mindestens einer FAIL-Zeile.
[ -z "${E2E_STRICT:-}" ] || ! grep -q ^FAIL "$OUT"
