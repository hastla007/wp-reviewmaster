#!/usr/bin/env bash
# RC-139: Backup (Kopie der SQLite-Datei) → Änderungen → Restore → reconcile → keine doppelten Jobs/Reviews.
set -uo pipefail
source "$(dirname "$0")/env.sh"
OUT="$E2E_DIR/results-backup.txt"; : >"$OUT"
SQL() { php "$(dirname "$0")/php/sql.php" "$(dbfile)" "$1"; }
log() { echo "$*" | tee -a "$OUT"; }
state() { log "  Reviews: $(SQL 'SELECT COUNT(*) FROM wp_review_captain_reviews') | Jobs: $(SQL "SELECT status||'='||COUNT(*) FROM wp_review_captain_jobs GROUP BY status" | tr '\n' ' ')"; }
dups() {
	local j r u; j="$(SQL 'SELECT COUNT(*) FROM (SELECT idempotency_key FROM wp_review_captain_jobs GROUP BY idempotency_key HAVING COUNT(*)>1)')"
	r="$(SQL 'SELECT COUNT(*) FROM (SELECT uuid FROM wp_review_captain_reviews GROUP BY uuid HAVING COUNT(*)>1)')"
	u="$(SQL "SELECT COUNT(*) FROM (SELECT title,author_name,body FROM wp_review_captain_reviews WHERE title LIKE 'RC139%' GROUP BY title,author_name,body HAVING COUNT(*)>1)")"
	log "  Duplikate: Job-Idempotenzschlüssel=$j, Review-UUIDs=$r, inhaltsgleiche RC139-Reviews=$u"
}
T=$(date +%s)
log "== Ausgangslage: ein Job 'läuft' während des Backups (Lease aktiv), ein Review vorhanden"
wp eval "\$q=\ReviewCaptain\Plugin::instance()->get('jobs'); \$id=\$q->enqueue('dev.noop', array('v'=>1), 'rc139:inflight:$T'); global \$wpdb; \$wpdb->query(\$wpdb->prepare(\"UPDATE {\$q->table()} SET status='running', lease_owner='w-backup', lease_expires_at=%s WHERE id=%d\", gmdate('Y-m-d H:i:s', time()+600), \$id)); \$r=new \ReviewCaptain\Domain\Review(); \$r->title='RC139 vor Backup'; \$r->body='Review, das schon im Backup steckt.'; \$r->author_name='Bea Backup'; \$r->rating_value=5.0; \$r->status=\ReviewCaptain\Domain\ReviewStatus::APPROVED; \ReviewCaptain\Plugin::instance()->reviews()->insert(\$r);" >/dev/null 2>&1
state
cp "$(dbfile)" "$E2E_DIR/backup.sqlite"; log "  Backup: $E2E_DIR/backup.sqlite ($(stat -c %s "$E2E_DIR/backup.sqlite") B)"
log "== Änderungen nach dem Backup"
wp eval "\$r=new \ReviewCaptain\Domain\Review(); \$r->title='RC139 nach Backup'; \$r->body='Review, das nach dem Backup entstand.'; \$r->author_name='Nora Neu'; \$r->rating_value=4.0; \ReviewCaptain\Plugin::instance()->reviews()->insert(\$r); \ReviewCaptain\Plugin::instance()->get('jobs')->enqueue('dev.noop', array('v'=>1), 'rc139:after:$T');" >/dev/null 2>&1
wp review-captain jobs run 2>/dev/null | tee -a "$OUT"
state
log "== Restore (Datei zurückkopieren)"
cp "$E2E_DIR/backup.sqlite" "$(dbfile)"
state
log "== wp review-captain reconcile (ohne --mode, nur Bericht)"
wp review-captain reconcile --format=json 2>/dev/null | tee -a "$OUT"; echo | tee -a "$OUT"
log "== wp review-captain reconcile --fix (ohne --mode)"
wp review-captain reconcile --fix --format=json 2>/dev/null | tee -a "$OUT"; echo | tee -a "$OUT"
state
log "== wp review-captain reconcile --fix --mode=restore"
wp review-captain reconcile --fix --mode=restore --format=json 2>/dev/null | tee -a "$OUT"; echo | tee -a "$OUT"
state
log "== Jobs abarbeiten, Idempotenz erneut prüfen"
wp review-captain jobs run 2>/dev/null | tee -a "$OUT"
wp eval "echo 'enqueue inflight erneut → ', var_export(\ReviewCaptain\Plugin::instance()->get('jobs')->enqueue('dev.noop', array('v'=>1), 'rc139:inflight:$T'), true), PHP_EOL;" 2>/dev/null | tee -a "$OUT"
wp review-captain reconcile --fix --mode=restore --format=json 2>/dev/null >/dev/null
state; dups
log "  Doctor:"; wp review-captain doctor 2>/dev/null | sed 's/^/    /' | tee -a "$OUT"
