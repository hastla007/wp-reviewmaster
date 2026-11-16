# SEO-001 – Kompatibilitätsmatrix SEO-/Schema-Plugins

Status: v1, Stand 2026-11-16. Mindestversionen sind **Vorschläge**; die finale Festlegung ist offen (siehe [Offene Entscheidungen](#offene-entscheidungen)).

## Grundregeln

1. Review Captain gibt **nie** Head-Metadaten aus (siehe [output-ownership.md](output-ownership.md)) – unabhängig davon, welches SEO-Plugin aktiv ist.
2. Es gibt immer höchstens **einen Schema-Besitzer** pro Seite. Review Captain ist entweder Zulieferer (Yoast-Graph) oder – nur ohne jedes erkannte SEO-/Schema-Plugin – Fallback-Ausgeber.
3. Erkennung ohne harte Klassen-/Tabellenabhängigkeit: über Konstanten/Funktionen/`class_exists` mit Autoload `false`, nie über direkte SQL-Abfragen auf fremde Tabellen.
4. Bei Unsicherheit (unbekannte Version, Erkennung uneindeutig) → **kein** Review-Markup; sichtbare Ausgabe bleibt unverändert.
5. Andere Plugins werden nie automatisch deaktiviert oder umkonfiguriert; Konflikte erscheinen in **Einstellungen → SEO & Yoast** als Diagnose.

## Erkennung (Vorschlag für SEO-005)

| Plugin | Erkennungsmerkmal (Beispiele, bei Implementierung gegen aktuelle Versionen prüfen) |
|---|---|
| Yoast SEO (Free) | Konstante `WPSEO_VERSION` |
| Yoast SEO Premium | Konstante `WPSEO_PREMIUM_VERSION` (zusätzlich zu Free) |
| Yoast WooCommerce SEO | Konstante `WPSEO_WOO_VERSION` bzw. Klasse `Yoast_WooCommerce_SEO` |
| WooCommerce | Konstante `WC_VERSION` / Funktion `WC()` |
| Rank Math | Konstante `RANK_MATH_VERSION` |
| All in One SEO (AIOSEO) | Konstante `AIOSEO_VERSION` |
| SEOPress | Konstante `SEOPRESS_VERSION` |
| Schema Pro | Konstante `BSF_SP_VER` (zu verifizieren) |
| Weitere | Filter `review_captain_schema_eligible` erlaubt Site-/Agentur-Code, Markup zusätzlich zu sperren |

## Matrix

| Umgebung | Head-Tags (Title, Canonical, Robots, OG) | Review-Schema-Ausgabe | Diagnose |
|---|---|---|---|
| **Kein SEO-/Schema-Plugin** | WordPress-Core (Canonical) bzw. Theme | Eigenes JSON-LD (Fallback), nur für geeignete Reviews ([schema-eligibility.md](schema-eligibility.md)) | „Fallback-Schema aktiv“ |
| **Yoast SEO Free, kompatible Version** | Yoast | Als Graph-Piece über die offizielle Yoast-Schema-API (`wpseo_schema_graph_pieces`), verbunden mit Yoasts `WebPage`/Haupt-Entität über `@id`; **kein** eigenes JSON-LD | „Yoast-Graph-Integration aktiv“ |
| **Yoast SEO Premium** | Yoast | wie Free (Premium ändert die Schema-API nicht); Yoast-Redirects, interne Verlinkung etc. bleiben unberührt | wie Free |
| **Yoast SEO, inkompatible/zu alte Version** | Yoast | **keines** (kein paralleles JSON-LD) | „Yoast-Version nicht kompatibel – Reviews sichtbar, Markup gesperrt“ |
| **WooCommerce ohne Yoast** | Core/Theme | WooCommerce gibt eigenes `Product`-Schema inkl. eigener WC-Bewertungen aus → für `context_type=product` bei Woo-Produkten **kein** Review-Captain-Markup; andere Kontexte gemäß Tabelle | „WooCommerce besitzt Produkt-Schema“ |
| **WooCommerce + Yoast (ohne Yoast WooCommerce SEO)** | Yoast | Woo-Produkt-Kontext: **kein** Markup (WooCommerce-Schema aktiv); andere Kontexte über Yoast-Graph | wie oben |
| **WooCommerce + Yoast WooCommerce SEO** | Yoast | Woo-Produkt-Kontext: **kein** Markup (Yoast WooCommerce SEO integriert das Woo-`Product` in den Yoast-Graph; kein zweites `Product`/`AggregateRating`) | „Produkt-Schema durch Yoast WooCommerce SEO“ |
| **Rank Math** | Rank Math | **keines** (anderes Schema-Plugin = Besitzer) | „Rank Math besitzt strukturierte Daten“ |
| **AIOSEO** | AIOSEO | **keines** | analog |
| **SEOPress** | SEOPress | **keines** | analog |
| **Schema Pro** (allein oder mit Yoast) | Yoast bzw. Core | **keines** – Schema Pro gilt als Besitzer, auch neben Yoast, um doppelte `Review`-Knoten zu vermeiden | „Schema Pro aktiv“ |
| **Mehrere SEO-Plugins gleichzeitig** | nicht beeinflusst | **keines** | „Mehrere SEO-Plugins erkannt – bitte eines verwenden“ |
| **Gutenberg / Classic / Page Builder** | – | Markup nur für tatsächlich serverseitig gerenderte Block-/Shortcode-Instanzen dieses Requests | – |

Zukünftige Integrationen mit Rank Math/AIOSEO/SEOPress-APIs sind möglich, aber nicht Teil von v1.

## Caching und Minifizierung

| Komponente | Verhalten / Anforderung |
|---|---|
| Page-Cache (WP Rocket, LiteSpeed, W3TC, Varnish, Host-Cache) | Ausgabe ist vollständig serverseitig; Schema und sichtbare Reviews stehen im selben HTML und werden gemeinsam gecacht. Nach Moderation (freigeben/ablehnen/entfernen) löst Review Captain `clean_post_cache` für bekannte Einbettungsseiten bzw. die Action `review_captain_review_status_changed_v1` aus, an die Cache-Plugins/Site-Code anknüpfen können. |
| Seiten mit `rc_page` | erhalten `noindex,follow` – Cache-Plugins müssen Query-Strings als eigene Cache-Variante behandeln oder nicht cachen (Standard der meisten Plugins). |
| HTML-Minify | JSON-LD in `<script type="application/ld+json">` darf nicht verändert werden; Review Captain gibt valides, kompakt serialisiertes JSON (`wp_json_encode`, `JSON_UNESCAPED_SLASHES`) aus. Bei Fallback-Ausgabe: Script-Tag hat das Attribut `data-rc-schema` zum Ausschluss aus JS-Verzögerung/Kombination. |
| JS-Verzögerung/Combine | Kein Frontend-JavaScript nötig; `application/ld+json` ist kein ausführbares Script und muss von „Delay JS“-Funktionen ausgenommen sein (Hinweis im [seo-guide.md](seo-guide.md)). |
| CSS-Optimierung (Unused CSS) | Klassen mit Präfix `rc-` werden serverseitig gerendert und sind im HTML sichtbar; keine dynamisch nachgeladenen Klassen. |
| Objekt-Cache | Eignungsentscheidung wird pro Request berechnet, nicht persistent gecacht (sonst veraltet nach Moderation). |

## Mindestversionen (Vorschlag)

| Komponente | Vorschlag | Begründung |
|---|---|---|
| Yoast SEO | **aktuelle Major − 2** (Entscheidung 16.11.2026; Stand: ≥ 25.0, `SeoEnvironment::YOAST_MIN_VERSION`, beim Freeze neu setzen); **Support-/Testzusage: aktuelle Major + zwei vorherige Majors** | Yoast veröffentlicht ca. alle zwei Wochen; ältere Versionen werden nicht getestet und erhalten im Zweifel kein Markup |
| Yoast WooCommerce SEO | aktuelle Major zum Release-Zeitpunkt | nur Erkennung, keine API-Nutzung |
| WooCommerce | gemäß Review-Captain-Woo-Adapter (offen) | nur Erkennung des Produkt-Schemas |
| WordPress | 6.5 (Arbeitsannahme RC-006) | `wp_robots`-API seit 5.7 vorhanden |

## Offene Entscheidungen

| ID | Frage | Vorschlag |
|---|---|---|
| SEO-OPEN-1 | Finale Yoast-Mindestversion | **Entschieden 16.11.2026:** aktuelle Major − 2, derzeit 25.0 |
| SEO-OPEN-2 | Schema Pro neben Yoast als Besitzer werten? | **Entschieden 16.11.2026:** ja (konservativ) |
| SEO-OPEN-3 | Spätere Graph-Integration für Rank Math/AIOSEO/SEOPress | nach v1, eigener Task |
| SEO-OPEN-4 | Erkennungsmerkmale Schema Pro / Yoast WooCommerce SEO | bei SEO-005 gegen aktuelle Plugin-Versionen verifizieren |
