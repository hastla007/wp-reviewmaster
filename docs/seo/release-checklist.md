# SEO-Release-Checkliste

Status: v1, Stand 2026-11-16 (SEO-016/SEO-017). Vor **jedem** Core- und Pro-Release vollständig durchlaufen; Ergebnis (Datum, Versionen, Prüfer) im Release-Protokoll festhalten.

## 1. Richtlinien und Versionen

- [ ] Google-Review-Snippet-Richtlinien und Doku zu strukturierten Daten auf Änderungen geprüft; [schema-eligibility.md](schema-eligibility.md) aktuell.
- [ ] Getestete Yoast-Versionen (aktuelle Major + zwei vorherige) und Mindestversion in [compatibility-matrix.md](compatibility-matrix.md) eingetragen.
- [ ] Erkennungsmerkmale für Yoast Premium, Yoast WooCommerce SEO, Rank Math, AIOSEO, SEOPress, Schema Pro gegen aktuelle Versionen verifiziert.

## 2. Ausgabebesitz (SEO-011)

- [ ] Keine von Review Captain stammenden `<title>`, Meta-Description, Canonical, Robots (außer `rc_page`), OG/Twitter-Tags.
- [ ] Kein Schreibzugriff auf Yoast-Optionen, Indexables oder Tabellen (Code-Suche nach `wpseo_` außer erlaubten Filtern).

## 3. Szenarien strukturierte Daten (SEO-016)

| # | Szenario | Erwartung | ✓ |
|---|---|---|---|
| 1 | Review Captain allein, Post mit lokalen freigegebenen Reviews (`context_type=post`) | ein Fallback-JSON-LD mit `Review`-Knoten, Werte = sichtbarer Inhalt | [ ] |
| 2 | Yoast Free kompatibel, gleiches Szenario | nur Yoast-Graph, Review-Knoten darin, verbunden mit `WebPage`-`@id`; kein zweites JSON-LD | [ ] |
| 3 | Yoast Premium | wie 2 | [ ] |
| 4 | Yoast zu alt (Mindestversion unterschreiten) | kein Review-Markup, Diagnose „inkompatibel“, Reviews sichtbar | [ ] |
| 5 | WooCommerce-Produkt mit lokalen Reviews (Woo-Schema aktiv), mit und ohne Yoast WooCommerce SEO | kein Review-Captain-Markup, genau ein `Product` | [ ] |
| 6 | Widget nur mit Google/Trustpilot-Reviews | kein `Review`/`AggregateRating` | [ ] |
| 7 | Lokale Unternehmensreviews (ohne Kontext) | kein Markup; Yoast-`Organization` unverändert | [ ] |
| 8 | Gemischtes Widget lokal + extern | nur lokale geeignete Reviews einzeln, kein `AggregateRating` | [ ] |
| 9 | Testimonials | kein Markup | [ ] |
| 10 | Rank Math / AIOSEO / SEOPress / Schema Pro je einzeln aktiv | kein Review-Captain-Markup, Diagnose nennt Plugin | [ ] |
| 11 | Event-Kontext, freigegeben | Markup gemäß A3 | [ ] |
| 12 | Gleiche Review in zwei Widgets | Review-Knoten einmal | [ ] |
| 13 | Leeres/verstecktes Widget | kein Markup | [ ] |
| 14 | Filter `review_captain_schema_eligible` gibt `false` | kein Markup; gibt `true` für externe Review → trotzdem kein Markup | [ ] |
| 15 | Seite in Yoast auf `noindex` | Review Captain ändert nichts, keine Fehler | [ ] |

Alle Seiten im Rich-Results-Test und Schema Markup Validator ohne Fehler (Warnungen dokumentiert).

## 4. Indexierung (SEO-014)

- [ ] `?rc_page=2` → `noindex, follow`, mit und ohne Yoast (Yoast-Robots-Ausgabe geprüft).
- [ ] Ohne `rc_page` Robots unverändert.
- [ ] Canonical mit `rc_page` zeigt auf Seite ohne Parameter.
- [ ] Yoast-Sitemap und WP-Core-Sitemap ohne Review-Captain-URLs.
- [ ] Keine öffentlich erreichbaren Review-Einzel-/Archiv-URLs, keine Rewrite-Regeln.
- [ ] Admin-, REST-, Vorschau-, Connector-Ansichten nicht öffentlich.

## 5. Rendering, Cache, Admin

- [ ] Reviews ohne JavaScript im HTML sichtbar (SEO-015); externe Ressourcen consent-gesteuert.
- [ ] Nach Moderation mit Page-Cache (mind. ein gängiges Cache-Plugin) korrekte Ausgabe nach Cache-Leerung; Statuswechsel-Action feuert.
- [ ] HTML-Minify verändert JSON-LD nicht (valide nach Minify).
- [ ] Statusseite **Einstellungen → SEO & Yoast** zeigt Version, Integration, Konflikte, blockierte Markups mit Grund (SEO-012).
- [ ] Widget-Hinweise ohne Rich-Result-Versprechen (SEO-013).
- [ ] [seo-guide.md](seo-guide.md) entspricht dem Release-Stand.

## Freigabe

Release nur, wenn 1–5 vollständig abgehakt sind. Abweichungen nur mit dokumentierter Begründung und Ticket.
