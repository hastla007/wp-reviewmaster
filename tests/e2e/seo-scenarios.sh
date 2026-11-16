#!/usr/bin/env bash
# SEO-016: scenarios from docs/seo/release-checklist.md §3/§4 against a real WordPress instance.
# Needs: setup.sh, Core active, server running, tests/e2e/php/seo-content.php seeded,
# mu-plugin e2e-seo-switch.php copied; Yoast optional in wp-content/plugins/wordpress-seo.
set -uo pipefail
source "$(dirname "$0")/env.sh"
FAIL=0
URLS="$(wp eval-file "$(dirname "$0")/php/seo-content.php")"
url() { python3 -c 'import json,sys;print(json.loads(sys.argv[1])[sys.argv[2]])' "$URLS" "$1"; }
# check <label> <slug|url> <python expression over: blocks (list of ld+json dicts), nodes (flat list), html, robots>
check() {
	local label="$1" target="$2" expr="$3" u
	case "$target" in http*) u="$target" ;; *) u="$(url "$target")" ;; esac
	if curl -s "$u" | python3 -c '
import sys,re,json
html=sys.stdin.read(); expr=sys.argv[1]
blocks=[json.loads(m) for m in re.findall(r"<script[^>]*application/ld\+json[^>]*>(.*?)</script>",html,re.S)]
nodes=[n for b in blocks for n in b.get("@graph",[b])]
types=[n.get("@type") for n in nodes]
reviews=[n for n in nodes if n.get("@type")=="Review"]
rc_script=len(re.findall(r"data-rc-schema",html))
m=re.search(r"<meta name=.robots. content=.([^\"\x27]*)",html); robots=m.group(1) if m else ""
ok=eval(expr)
print(("PASS " if ok else "FAIL ")+sys.argv[2]+"  [types="+",".join(map(str,types))+" reviews="+str(len(reviews))+" rc_script="+str(rc_script)+(" robots="+robots if robots else "")+"]")
sys.exit(0 if ok else 1)' "$expr" "$label"; then :; else FAIL=$((FAIL+1)); fi
}
switch() { wp option update e2e_seo_switch "$1" --format=json >/dev/null; }
yoast() { [ -d "$E2E_WP/wp-content/plugins/wordpress-seo" ] || return 1; wp plugin "$1" wordpress-seo >/dev/null 2>&1; }

echo "== ohne SEO-Plugin"; yoast deactivate; switch '{}'
check "S1 lokale Post-Reviews: ein Fallback-JSON-LD mit 2 Review" s01 'rc_script==1 and len(reviews)==2 and all(r.get("reviewRating",{}).get("ratingValue") in (4,5,4.0,5.0) for r in reviews) and "AggregateRating" not in json.dumps(blocks)'
check "S6 nur Google/Trustpilot: kein Review/AggregateRating" s06 'len(reviews)==0 and "aggregateRating" not in html'
check "S7 Unternehmensreviews: kein Markup" s07 'rc_script==0 and len(reviews)==0'
check "S8 gemischt: nur die lokale Review, kein AggregateRating" s08 'len(reviews)==1 and "S08 lokal" in json.dumps(reviews,ensure_ascii=False) and "aggregateRating" not in json.dumps(blocks)'
check "S9 Testimonials: kein Markup" s09 'len(reviews)==0'
check "S11 Event-Kontext: Review mit itemReviewed Event" s11 'len(reviews)==1 and "Event" in json.dumps(nodes)'
check "S12 gleiche Review in zwei Widgets: einmal" s12 'len(reviews)==1'
check "S13 leeres Widget: kein Markup" s13 'len(reviews)==0 and rc_script==0'
switch '{"filter":false}'
check "S14 Filter false: kein Markup" s01 'len(reviews)==0'
switch '{"filter":true}'
check "S14 Filter true schaltet externe Reviews nicht frei" s06 'len(reviews)==0'
for c in RANK_MATH_VERSION AIOSEO_VERSION SEOPRESS_VERSION BSF_SP_VER; do
	switch "{\"define\":{\"$c\":\"99.0\"}}"
	check "S10 $c aktiv: kein Review-Captain-Markup" s01 'rc_script==0 and len(reviews)==0'
done
switch '{"define":{"WPSEO_VERSION":"13.0"}}'
check "S4 Yoast zu alt (13.0 < Minimum): kein Markup, Reviews sichtbar" s01 'rc_script==0 and len(reviews)==0 and "S01 lokale Bewertung eins" in html'
switch '{}'
check "Index: ?rc_page=2 -> noindex, follow" "$(url s01)?rc_page=2" '"noindex" in robots and "follow" in robots and "nofollow" not in robots'
check "Index: ohne rc_page kein noindex" s01 '"noindex" not in robots'

if yoast activate; then
	echo "== mit Yoast $(wp plugin get wordpress-seo --field=version 2>/dev/null)"
	check "S2 Yoast: nur Yoast-Graph, Review darin, kein eigenes Script" s01 'rc_script==0 and len(blocks)==1 and len(reviews)==2 and "yoast-schema-graph" in html'
	check "S6 Yoast + nur extern: kein Review" s06 'len(reviews)==0'
	check "S7 Yoast + Unternehmen: kein Review, Yoast-Graph unverändert" s07 'len(reviews)==0 and len(blocks)==1'
	check "Index Yoast: ?rc_page=2 -> noindex, follow" "$(url s01)?rc_page=2" '"noindex" in robots and "nofollow" not in robots'
	wp post meta update "$(wp post list --name=seo-s01-post --post_type=post --field=ID)" _yoast_wpseo_meta-robots-noindex 1 >/dev/null
	check "S15 Yoast noindex: Seite noindex, keine Fehler" s01 '"noindex" in robots'
	wp post meta delete "$(wp post list --name=seo-s01-post --post_type=post --field=ID)" _yoast_wpseo_meta-robots-noindex >/dev/null
	yoast deactivate
else
	echo "== Yoast nicht installiert: S2/S15 übersprungen"
fi
switch '{}'
for plug in seo-by-rank-math all-in-one-seo-pack wp-seopress; do
	[ -d "$E2E_WP/wp-content/plugins/$plug" ] || continue
	if wp plugin activate "$plug" >/dev/null 2>&1; then
		echo "== echtes Plugin $plug $(wp plugin get "$plug" --field=version 2>/dev/null)"
		check "S10 $plug aktiv: kein Review-Captain-Markup, Reviews sichtbar" s01 'rc_script==0 and len([n for n in nodes if n.get("@type")=="Review" and "review-captain" in str(n.get("@id",""))])==0 and "S01 lokale Bewertung eins" in html'
		wp plugin deactivate "$plug" >/dev/null 2>&1
	fi
done
if wp plugin activate woocommerce >/dev/null 2>&1 && wp eval 'exit(defined("WC_VERSION")?0:1);' >/dev/null 2>&1; then
	WOO="$(wp eval-file "$(dirname "$0")/php/seo-woo.php" 2>/dev/null | tail -1)"
	echo "== WooCommerce $(wp plugin get woocommerce --field=version 2>/dev/null)"
	check "S5 Woo-Produkt: genau ein Product (Woo), kein Review-Captain-Markup" "$WOO" 'rc_script==0 and types.count("Product")==1 and "S05 lokale Produktbewertung" in html'
	if yoast activate; then
		check "S5 Woo-Produkt + Yoast: genau ein Product, kein Review-Captain-Markup" "$WOO" 'rc_script==0 and types.count("Product")==1'
		yoast deactivate
	fi
	wp plugin deactivate woocommerce >/dev/null 2>&1
else
	echo "== WooCommerce nicht installiert: S5 übersprungen"
fi
grep -iE "review-captain|review captain" "$(debuglog)" 2>/dev/null | grep -v E2E-TRACE | tail -5 && { echo "FAIL debug.log enthält Review-Captain-Meldungen"; FAIL=$((FAIL+1)); } || echo "PASS debug.log ohne Review-Captain-Meldungen"
echo "$FAIL FAILED"; exit $(( FAIL > 0 ))
