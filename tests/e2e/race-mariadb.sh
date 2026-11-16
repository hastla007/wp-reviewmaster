#!/usr/bin/env bash
# TEC-005: race tests with parallel PHP processes against MySQL/MariaDB.
# Usage: tests/e2e/race-mariadb.sh /path/to/wp-load.php [workers]
# The WordPress site must use MySQL/MariaDB (InnoDB) and have Review Captain active. Disposable sites only.
set -euo pipefail
WP_LOAD="${1:?path to wp-load.php}"
WORKERS="${2:-8}"
SCRIPT="$(cd "$(dirname "$0")" && pwd)/php/race-mariadb.php"
export RC_RACE_DIR="${RC_RACE_DIR:-$(mktemp -d)}"
rm -f "$RC_RACE_DIR"/race-*.log

php "$SCRIPT" "$WP_LOAD" setup
for mode in claim enqueue dedupe cas; do
	start=$(php -r 'printf("%.3f", microtime(true) + 2);')
	for i in $(seq 1 "$WORKERS"); do
		php "$SCRIPT" "$WP_LOAD" "$mode" "w$i" "$start" &
	done
	wait
	echo "$mode: $WORKERS workers done"
done
php "$SCRIPT" "$WP_LOAD" verify
