#!/usr/bin/env bash
# Gemeinsame Variablen/Funktionen für die E2E-Skripte. Wird per `source` geladen.
# Alles liegt unter $E2E_DIR (Standard /tmp/claude-0/wp-e2e); nichts wird global installiert.
E2E_DIR="${E2E_DIR:-/tmp/claude-0/wp-e2e}"
E2E_WP="${E2E_WP:-$E2E_DIR/wp}"
E2E_PORT="${E2E_PORT:-8899}"
E2E_URL="http://127.0.0.1:$E2E_PORT"
E2E_CORE_SRC="${E2E_CORE_SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
E2E_PRO_SRC="${E2E_PRO_SRC:-/home/user/captain-pro}"
# Welcher Stand des Core getestet wird: HEAD (git archive, Standard) oder "worktree" (Arbeitsstand).
E2E_CORE_REF="${E2E_CORE_REF:-HEAD}"
E2E_WPCLI_BIN="$E2E_DIR/composer/vendor/wp-cli/wp-cli/php/boot-fs.php"
export COMPOSER_MAX_PARALLEL_HTTP=2

wp() { php -d memory_limit=1G "$E2E_WPCLI_BIN" --path="$E2E_WP" --allow-root "$@"; }
dbfile() { echo "$E2E_WP/wp-content/database/.ht.sqlite"; }
debuglog() { echo "$E2E_WP/wp-content/debug.log"; }
server_start() {
	if ! curl -s -o /dev/null "$E2E_URL/wp-login.php"; then
		( cd "$E2E_WP" && nohup php -d memory_limit=512M -S "127.0.0.1:$E2E_PORT" -t "$E2E_WP" >"$E2E_DIR/php-server.log" 2>&1 & )
		for _ in 1 2 3 4 5 6 7 8 9 10; do curl -s -o /dev/null "$E2E_URL/wp-login.php" && break; sleep 0.5; done
	fi
}
# Tabellen von Review Captain aus der SQLite-Datei lesen.
rc_tables() { php -r '$p=new PDO("sqlite:".$argv[1]);foreach($p->query("SELECT name FROM sqlite_master WHERE type=\"table\" AND name LIKE \"%review_captain%\" ORDER BY name") as $r){echo $r[0],"\n";}' "$(dbfile)"; }
