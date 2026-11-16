# SEO-002 – SEO-Ausgabebesitz

Status: verbindlich ab 2026-11-16 (Core). Bezug: konzept.md §18, [compatibility-matrix.md](compatibility-matrix.md).

## Besitztabelle

| Ausgabe | Besitzer mit Yoast | Besitzer ohne SEO-Plugin | Review Captain |
|---|---|---|---|
| `<title>` / SEO-Titel | Yoast | WordPress/Theme | **gibt nie aus**, filtert nicht (`pre_get_document_title`, `wpseo_title` unberührt) |
| Meta-Description | Yoast | – / Theme | **nie** |
| Canonical (`rel=canonical`) | Yoast | WordPress-Core (`rel_canonical`) | **nie**; verändert keine Canonical-Filter |
| Robots-Meta | Yoast (Seiteneinstellung) | WordPress-Core (`wp_robots`) | ergänzt **nur** `noindex,follow` bei gesetztem `rc_page` ([indexing-rules.md](indexing-rules.md)); entfernt nie Direktiven |
| Open Graph / Twitter Cards | Yoast | Theme/sonstige | **nie** |
| XML-Sitemap | Yoast | WordPress-Core-Sitemap | **keine** eigenen Sitemaps, keine Einträge |
| Breadcrumbs | Yoast/Theme | Theme | **nie** |
| Indexables / Yoast-Tabellen / Yoast-Optionen | Yoast | – | **liest und schreibt nicht** |
| Basis-Schema (`WebSite`, `WebPage`, `Organization`/`Person`, `LocalBusiness`, `BreadcrumbList`, `Article`) | Yoast | Theme/keiner | **erzeugt nie** solche Entitäten; referenziert sie höchstens per `@id` |
| `Product`-Schema (Woo) | WooCommerce bzw. Yoast WooCommerce SEO | WooCommerce | **erzeugt nie** ein zweites `Product`/`AggregateRating` |
| `Review`/`AggregateRating` für geeignete lokale Reviews | Review Captain als Yoast-Graph-Piece | Review Captain (Fallback-JSON-LD) | nur gemäß [schema-eligibility.md](schema-eligibility.md) |
| Sichtbarer Review-Inhalt | Review Captain | Review Captain | serverseitig gerendert (Block/Shortcode) |

## Technische Regeln

- Review Captain registriert **keine** Callbacks auf `wp_head` für Meta-/Link-Tags. Einzige Head-nahe Ausgabe: Fallback-JSON-LD (nur ohne Schema-Besitzer) im Footer (`wp_footer`) bzw. inline nach dem Widget – Entscheidung in SEO-010, Empfehlung `wp_footer`, damit erst nach dem Rendern bekannt ist, welche Reviews sichtbar waren.
- Keine Filter auf `wpseo_*` außer der offiziellen Schema-Graph-API (`wpseo_schema_graph_pieces`) und ggf. – nur falls Yoast `wp_robots` unterdrückt – `wpseo_robots_array` zum **Hinzufügen** von `noindex` bei `rc_page` (siehe [indexing-rules.md](indexing-rules.md)).
- Keine Yoast-Metabox/Sidebar-Erweiterung in v1; Redakteure pflegen SEO weiterhin in Yoast.
- Setup-Assistent und Einstellungen ändern keine Yoast-Einstellungen; Hinweise verlinken auf die Yoast-Oberfläche.
- Review-Captain-Admin-Seiten, REST-Routen (`review-captain/v1`) und `admin-post.php`-Endpunkte erzeugen keine öffentlich indexierbaren HTML-Seiten.

## Prüfung (SEO-011, SEO-016)

Für jede Testseite mit Review-Captain-Block gilt im gerenderten HTML:

- genau ein `<title>`, höchstens eine Meta-Description, genau ein `rel=canonical`, höchstens ein Robots-Meta-Tag;
- kein `og:`/`twitter:`-Tag mit Herkunft Review Captain (Quelltext-Suche nach `rc-`/`review-captain` im `<head>` ergibt nur Stylesheet-Links);
- mit Yoast: genau ein `application/ld+json`-Block der Klasse `yoast-schema-graph`, kein weiterer von Review Captain.
