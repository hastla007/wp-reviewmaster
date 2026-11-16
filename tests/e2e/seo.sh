#!/usr/bin/env bash
# SEO-016 (teilweise): JSON-LD der Testseiten ausgeben – ohne SEO-Plugin und (falls installiert) mit Yoast.
# Yoast: Packagist yoast/wordpress-seo (offizielles Yoast-dist-Repo), Version passend zur WP-Version (27.0 für WP 6.9).
set -uo pipefail
source "$(dirname "$0")/env.sh"
dump() { curl -s "$E2E_URL/$1/" | python3 -c '
import sys,re,json
h=sys.stdin.read(); n=0
for m in re.finditer(r"<script[^>]*application/ld\+json[^>]*>(.*?)</script>",h,re.S):
    n+=1; d=json.loads(m.group(1)); g=d.get("@graph",[d])
    print("  script:", re.match(r"<script[^>]*>",m.group(0)).group(0))
    for x in g: print("    ", x.get("@type"), x.get("@id"), ("itemReviewed=" + json.dumps(x["itemReviewed"])) if "itemReviewed" in x else "", "aggregateRating" if "aggregateRating" in x else "")
print("  ld+json-Blöcke:", n)'; }
YOAST_ACTIVE=0; wp plugin is-active wordpress-seo >/dev/null 2>&1 && YOAST_ACTIVE=1
wp plugin deactivate wordpress-seo >/dev/null 2>&1
echo "== ohne SEO-Plugin"; for p in rc-produkt rc-shortcodes rc-blocks; do echo " /$p/"; dump $p; done
if [ -d "$E2E_WP/wp-content/plugins/wordpress-seo" ]; then
	wp plugin activate wordpress-seo >/dev/null 2>&1 && { echo "== mit Yoast $(wp plugin get wordpress-seo --field=version 2>/dev/null)"; for p in rc-produkt rc-shortcodes; do echo " /$p/"; dump $p; done; }
	[ "$YOAST_ACTIVE" = 1 ] || wp plugin deactivate wordpress-seo >/dev/null 2>&1
else echo "== Yoast nicht installiert (siehe README)"; fi
