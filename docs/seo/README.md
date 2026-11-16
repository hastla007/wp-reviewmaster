# SEO und Yoast – Dokumentationsindex

Status: Spezifikation v1, Stand 2026-11-16. Grundlage: [konzept.md §18](../../konzept.md#18-yoast-seo-kompatibilität-und-seo-konzept), [TODO.md „Yoast SEO-Kompatibilität“](../../TODO.md#yoast-seo-kompatibilität-und-aufgaben), [Konventionen](../architecture/conventions.md).

Leitprinzip: **Review Captain liefert sichtbaren Inhalt, Yoast (bzw. das aktive SEO-Plugin) besitzt die SEO-Ausgabe.** Review Captain gibt keine eigenen Title-, Description-, Canonical-, Robots-, Open-Graph- oder Twitter-Tags aus und erzeugt Review-Markup nur in eng definierten, richtlinienkonformen Fällen.

| Dokument | Task | Inhalt |
|---|---|---|
| [compatibility-matrix.md](compatibility-matrix.md) | SEO-001 | Verhalten mit Yoast Free/Premium, Yoast WooCommerce SEO, Rank Math, AIOSEO, SEOPress, Schema Pro, Caching/Minify |
| [output-ownership.md](output-ownership.md) | SEO-002 | Wer gibt welche SEO-Ausgabe aus |
| [schema-eligibility.md](schema-eligibility.md) | SEO-003 | Google-Richtlinien und **verbindliche Entscheidungstabelle** für Review-Markup |
| [indexing-rules.md](indexing-rules.md) | SEO-004 | Custom Tables, keine öffentlichen CPT/Archive/Sitemaps, `rc_page` → `noindex,follow` |
| [seo-guide.md](seo-guide.md) | SEO-017 | Anleitung für Betreiber: Yoast-Konfiguration, Markupgrenzen, Erwartungen, Diagnose |
| [release-checklist.md](release-checklist.md) | SEO-017 | Prüfliste vor jedem Release |

Hooks mit SEO-Bezug (siehe Konventionen):

- `review_captain_schema_eligible` (Filter) – darf Eignung nur **verschärfen** (`true → false`), nie lockern.
