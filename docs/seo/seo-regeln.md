# SEO- und Yoast-Regeln – SEO-001 bis SEO-004

**Status:** Entwurf – Google-Richtlinien vor Release erneut prüfen (⚠)  
**Stand:** 2026-11-16  
**Grundlage:** [Konzept §18](../../konzept.md), [ADR 0002](../adr/0002-custom-tables-statt-cpt.md)

## SEO-001 – Kompatibilitätsmatrix

| Umgebung | Verhalten | Status 1.0 |
|---|---|---|
| Yoast SEO (Free/Premium), aktuelle + vorherige Major | Schema-Piece über Filter `wpseo_schema_graph_pieces`; keine Head-Ausgabe | unterstützt |
| Yoast WooCommerce SEO | erkennt vorhandenen `Product`-Knoten, ergänzt nichts Konkurrierendes | unterstützt |
| Kein SEO-Plugin | eigenes JSON-LD nur nach Eignungsprüfung (SEO-010) | unterstützt |
| Rank Math, SEOPress, AIOSEO, The SEO Framework | als „konkurrierender Schema-Besitzer“ erkannt → Review Captain gibt **kein** eigenes Schema aus; Hinweis in Statusseite | sicherer Fallback |
| Separate Schema-Plugins (z. B. Schema Pro) | wie oben | sicherer Fallback |
| Gutenberg / Shortcode | Schema nur bei gerendertem Widget | unterstützt |
| Caching/Minifizierung | JSON-LD als `script type="application/ld+json"` im Body bzw. Yoast-Graph; kein JS nötig | unterstützt |

Erkennung über Konstanten/Funktionen (`WPSEO_VERSION`, `RANK_MATH_VERSION`, `SEOPRESS_VERSION`, `AIOSEO_VERSION`, `THE_SEO_FRAMEWORK_VERSION`), nicht über Klassen-/Tabellenzugriffe.

## SEO-002 – Ausgabebesitz

| Ausgabe | Besitzer | Review Captain |
|---|---|---|
| Title, Meta-Description, Canonical, Robots, Open Graph, Twitter Cards | Yoast / SEO-Plugin / Theme | gibt nichts aus |
| XML-Sitemap | Yoast / WP-Core | keine eigenen URLs (keine öffentlichen Review-URLs) |
| Basisschema (WebSite, WebPage, Organization, Product) | Yoast / WooCommerce | verknüpft sich über `@id`, erzeugt keine Duplikate |
| Review-Schema für eigene, zulässige Reviews | Review Captain (als Piece im Yoast-Graph oder Fallback) | nur nach SEO-003 |
| Sichtbare Widgets | Review Captain | SSR-HTML |

## SEO-003 – Schema-Eignungsmatrix ⚠

| Review-Art | Beispiel | Review/AggregateRating-Markup | Begründung |
|---|---|---|---|
| Synchronisierte/manuelle **externe** Reviews (Google, Trustpilot, Apps …) | Widget mit Google-Reviews | **nein** | nicht auf der eigenen Website abgegeben; Google-Richtlinien schließen Markup für Inhalte Dritter aus |
| Eigene Reviews über **die eigene Organisation/LocalBusiness**, auf eigener Website | „Bewertungen unserer Firma“ | **nein** | Google zeigt selbstbezogene Bewertungen für Organization/LocalBusiness nicht als Rich Result; Markup unterlassen |
| Eigene **Produktreviews** (lokal, nicht Woo-nativ) auf der Produktseite | lokales Review mit `context_type=product` | **ja, wenn** keine WooCommerce-eigene AggregateRating vorhanden und Reviews sichtbar | zulässig für Produkte; Konflikt mit Woo vermeiden (SEO-008) |
| WooCommerce-native Produktreviews | Woo-Tab „Bewertungen“ | **nicht durch Review Captain** | Besitzer WooCommerce/Yoast |
| Eigene Reviews für **Kurse/Events** | `context_type=event` mit Pass Captain | **ja, wenn** Seite das Event als Hauptgegenstand hat und Typ von Google für Reviews unterstützt wird (⚠ Course, Event, SoftwareApplication prüfen) | sonst nein |
| **Gemischte** Widgets (eigene + externe) | Proof-Wall | **nur für eigene Reviews im Widget**, AggregateRating nur über eigene | externe nie einrechnen |
| Plattformübergreifender Gesamtscore | Trustbar-Summe | **nein** | |
| Testimonials | Erfahrungsberichte | **nein** | kuratierte Auswahl, kein Review im Sinne der Richtlinie |
| Seite `noindex` | – | **nein** | kein Nutzen, vermeidet Signale |

## SEO-004 – URL- und Indexierungsregeln

- Speicherung in Custom Tables (ADR 0002) → keine CPT-Archive, keine Einzelseiten, keine Taxonomien, keine Yoast-Indexables.
- Öffentliche Ausgabe nur über Blocks/Shortcodes auf Betreiberseiten; diese behalten Yoast-Kontrolle über Canonical/Robots.
- Pagination/„Mehr laden“ im Widget über Query-Parameter `rc_page_{widget}` mit `rel="nofollow"`-Links; Seiten mit diesem Parameter erhalten `noindex,follow` über `wp_robots` **nur wenn kein SEO-Plugin aktiv** – sonst Filter `wpseo_robots`, damit Yoast der Besitzer bleibt; Canonical bleibt auf der Basis-URL.
- Filter-/Sortierparameter im Frontend: keine in 1.0 (nur Admin-definierte Widgets).
- Vorschau-, Admin-, AJAX-/REST-Routen: `X-Robots-Tag: noindex` für REST-Antworten der Plugin-Namespace; Admin ohnehin nicht indexierbar.
- Sitemap: keine Einträge.
