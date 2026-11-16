# SEO-Guide für Betreiber: Review Captain mit Yoast SEO

Status: v1, Stand 2026-11-16 (SEO-017). Technische Grundlage: [output-ownership.md](output-ownership.md), [schema-eligibility.md](schema-eligibility.md), [indexing-rules.md](indexing-rules.md), [compatibility-matrix.md](compatibility-matrix.md).

## In einem Satz

Review Captain zeigt Ihre Bewertungen als normalen Seiteninhalt an; Titel, Beschreibung, Canonical, Indexierung, Sitemap und das Grund-Schema Ihrer Seite pflegen Sie weiterhin in Yoast SEO.

## Was Review Captain tut – und was nicht

| Review Captain … | … tut nicht |
|---|---|
| rendert Reviews serverseitig in Block/Shortcode (crawlbar, ohne JavaScript) | erzeugt keine eigenen Review-Seiten, Archive oder Sitemaps |
| ergänzt für geeignete eigene Reviews einen `Review`-Baustein im Yoast-Schema-Graph | gibt keine Title-, Description-, Canonical-, Robots-, Open-Graph-Tags aus |
| setzt `noindex, follow` nur auf Widget-Folgeseiten (`?rc_page=2` …) | ändert keine Yoast-Einstellungen und keine Yoast-Daten |
| zeigt Konflikte unter **Einstellungen → SEO & Yoast** | schaltet keine anderen Plugins ab |

## Empfohlene Yoast-Konfiguration

1. **Seite, auf der Reviews stehen:** in Yoast normal pflegen (SEO-Titel, Beschreibung). Indexierung nach Ihrer Strategie – Review Captain ändert sie nicht.
2. **Schema-Einstellungen (Yoast → Darstellung in der Suche / Website-Darstellung):** Organisation oder Person wie gewohnt; Review Captain fügt dort **keine** Bewertungen hinzu.
3. **Produktseiten mit WooCommerce:** Produktbewertungen sind Sache von WooCommerce bzw. Yoast WooCommerce SEO. Review Captain gibt dort kein zusätzliches Produkt-Schema aus.
4. **Nur ein SEO-Plugin aktiv lassen.** Mit Rank Math, AIOSEO, SEOPress oder Schema Pro gibt Review Captain kein Review-Markup aus (die Reviews bleiben sichtbar).

## Welche Reviews bekommen strukturierte Daten?

Nur Reviews, die **alle** Bedingungen erfüllen:

- auf **Ihrer Website** abgegeben (Plattform „lokal“) – keine Google-, Trustpilot-, Trusted-Shops-, App-Store- oder Play-Store-Bewertungen;
- echte Review (kein Testimonial), **freigegeben**;
- bezogen auf ein **Produkt, einen Beitrag/eine Seite (z. B. Kurs) oder ein Event** – nicht auf Ihr Unternehmen allgemein;
- auf der aktuellen Seite **sichtbar** angezeigt;
- kein WooCommerce-Produkt mit aktivem WooCommerce-Schema;
- kein anderes Schema-Plugin aktiv, Yoast (falls aktiv) in kompatibler Version.

**Warum keine Sterne für Unternehmensbewertungen?** Google zeigt seit 2019 keine Review-Rich-Results für „selbstbezogene“ Bewertungen von Organisationen und lokalen Unternehmen. **Warum keine Sterne für Google-/Trustpilot-Reviews?** Google erlaubt kein Markup für Bewertungen, die von anderen Plattformen stammen. Diese Reviews werden trotzdem sichtbar und mit Quelle angezeigt.

**Keine Garantie:** Auch geeignete strukturierte Daten führen nicht zwingend zu Sternen in den Suchergebnissen. Darüber entscheidet allein Google.

## Caching und Optimierung

- Nach Freigabe oder Löschung einer Review den Seiten-Cache leeren, falls Ihr Cache-Plugin das nicht automatisch tut.
- In „JavaScript verzögern/kombinieren“-Funktionen `application/ld+json` ausnehmen (meist Standard).
- URLs mit `rc_page` müssen nicht in den Cache-Ausschluss; sie tragen `noindex`.

## Diagnose: Einstellungen → SEO & Yoast

| Anzeige | Bedeutung | Was tun |
|---|---|---|
| „Yoast-Graph-Integration aktiv“ | alles in Ordnung | – |
| „Fallback-Schema aktiv“ | kein SEO-Plugin erkannt, Review Captain gibt eigenes JSON-LD aus | optional |
| „Yoast-Version nicht kompatibel“ | Yoast zu alt | Yoast aktualisieren |
| „Anderer Schema-Besitzer erkannt (…)“ | Rank Math/AIOSEO/SEOPress/Schema Pro aktiv | gewünscht? Dann nichts tun; sonst nur ein SEO-Plugin verwenden |
| „Mehrere SEO-Plugins erkannt“ | Doppelte Ausgabe wahrscheinlich | eines deaktivieren |
| Blockierte Reviews mit Grund (externe Plattform, Unternehmensreview, nicht freigegeben, WooCommerce-Schema …) | erwartetes Verhalten | Grund lesen, siehe oben |

## Prüfen mit Google-Tools

1. Seite im **Rich-Results-Test** bzw. **Schema Markup Validator** prüfen: genau ein Graph (bei Yoast), keine doppelten `Product`-/`Organization`-Knoten.
2. Quelltext: genau ein `rel=canonical`, ein `<title>`.
3. `?rc_page=2` aufrufen: Robots-Meta enthält `noindex, follow`.
4. Search Console → Verbesserungen → Review-Snippets beobachten; Warnungen zu fehlenden Eigenschaften bei `CreativeWork`/`Event` sind möglich und kein Fehler von Review Captain (siehe Offene Punkte in [schema-eligibility.md](schema-eligibility.md)).

## Häufige Fragen

- **Brauche ich eine Yoast-Metabox für Reviews?** Nein.
- **Kann ich Markup für externe Reviews erzwingen?** Nein, bewusst nicht – das würde gegen die Google-Richtlinien verstoßen.
- **Funktioniert Review Captain ohne Yoast?** Ja. Ohne SEO-Plugin gibt es ein eigenes, minimales JSON-LD für geeignete Reviews.
- **Kann ein Entwickler Markup weiter einschränken?** Ja, über den Filter `review_captain_schema_eligible` (nur Einschränken, nie Erweitern).
