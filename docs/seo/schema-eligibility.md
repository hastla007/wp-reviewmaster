# SEO-003 – Schema-Eignungsmatrix

Status: **verbindlich** ab 2026-11-16. Die [Entscheidungstabelle](#entscheidungstabelle-verbindlich) wird im Code 1:1 umgesetzt (SEO-007, SEO-009, SEO-010). Änderungen nur per ADR und mit Aktualisierung der Tests in SEO-016.

## Richtliniengrundlage (Google Search Central, Stand der Recherche)

| Regel | Quelle / Folge für Review Captain |
|---|---|
| **Self-serving reviews:** Seit September 2019 zeigt Google keine Review-Rich-Results mehr für `LocalBusiness` und `Organization` (und deren Untertypen), wenn die Entität, die das Markup ausgibt, die Reviews über sich selbst selbst kontrolliert/einbettet („self-serving“) – unabhängig davon, ob die Reviews direkt oder über ein Widget eines Drittanbieters eingebunden sind. | Google-Blog „Making Review Rich Results more helpful“ (16.09.2019); Review-Snippet-Dokumentation. → Reviews über das eigene Unternehmen erhalten **nie** Markup. Yoasts Organization-/LocalBusiness-Knoten bleibt unangetastet. |
| **Keine Drittplattform-Reviews:** Review-Markup darf keine Bewertungen enthalten, die von anderen Websites/Plattformen stammen oder aggregiert wurden. | Review-Snippet-Richtlinien. → Google, Trustpilot, Trusted Shops, App Store, Google Play, `other` werden sichtbar angezeigt, aber **nie** ausgezeichnet. |
| **Unterstützte Typen:** Review-Snippets nur für bestimmte `itemReviewed`-Typen (u. a. `Product`, `Course`, `Event`, `Book`, `Recipe`, `SoftwareApplication`, `Movie`, `CreativeWorkSeason/Series`, `Episode`, `Game`, `MediaObject`, `MusicPlaylist/Recording`; `LocalBusiness`/`Organization` nur nicht-self-serving). | → v1 erlaubt nur Kontexte `product`, `post`, `event`. |
| **Selbst gesammelt, auf der Seite sichtbar:** Reviews müssen für Nutzer auf der Seite mit dem Markup sichtbar sein und von Nutzern stammen, die auf dieser Website bewertet haben. | → nur `platform=local`, nur tatsächlich gerenderte Reviews. |
| **Übereinstimmung:** Werte im Markup (Rating, Skala, Anzahl, Text, Autor, Datum) müssen dem sichtbaren Inhalt entsprechen. | → Markup wird aus genau den gerenderten Datensätzen erzeugt. |
| **Keine Garantie:** Strukturierte Daten machen Rich Results möglich, garantieren sie aber nicht. | → UI und Doku versprechen keine Sterne. |

Die Richtlinien ändern sich; vor jedem Release prüfen ([release-checklist.md](release-checklist.md)).

## Entscheidungstabelle (verbindlich)

Für **jede einzelne Review**, die in einer Block-/Shortcode-Instanz gerendert wird, prüft der Core in dieser Reihenfolge. Die **erste** zutreffende Sperre bestimmt das Ergebnis (Diagnose zeigt diesen Grund). Nur wenn alle Bedingungen E1–E8 erfüllt sind, ist die Review **schema-geeignet**.

| Nr. | Bedingung | Erfüllt, wenn | Sonst → Ergebnis / Grund (Empfehlung Diagnose-Schlüssel) |
|---|---|---|---|
| E1 | Plattform | `platform = 'local'` | gesperrt: externe Plattform (`external_platform`) |
| E2 | Art | `kind = 'review'` | gesperrt: Testimonial (`not_review`) |
| E3 | Status | `status = 'approved'` | gesperrt: nicht freigegeben (`not_approved`) |
| E4 | Kontext | `context_type ∈ {'product', 'post', 'event'}` | gesperrt: Kontext ungeeignet; `context_type` leer/NULL = Review über Unternehmen/Organisation → `self_serving`; `voucher`, `app`, `form` → `context_not_supported` |
| E5 | Sichtbarkeit | Review wird im aktuellen Request serverseitig im HTML gerendert (nicht ausgeblendet, nicht nur per Pagination auf anderer Seite, nicht im Editor/Vorschau/REST) | gesperrt: nicht sichtbar (`not_rendered`) |
| E5b | Hauptobjekt der Seite | Die Seite ist die Einzelseite des bewerteten Objekts (`is_singular()` und `context_id` = ID des Hauptobjekts). Übersichts-, Archiv- und fremde Seiten erhalten kein Review-Markup, auch wenn das Widget dort Reviews zeigt (QA-Befund F-09 vom 16.11.2026; löst SEO-OPEN-5) | gesperrt: `not_main_entity` |
| E6 | WooCommerce | **nicht** (`context_type = 'product'` **und** Kontext ist ein WooCommerce-Produkt **und** WooCommerce-Produkt-Schema ist aktiv) | gesperrt: WooCommerce besitzt Produkt-Schema (`woo_product_schema`) |
| E7 | Schema-Besitzer | kein anderes Schema-/SEO-Plugin als Besitzer erkannt (Rank Math, AIOSEO, SEOPress, Schema Pro, mehrere SEO-Plugins) **und** – falls Yoast aktiv – Yoast-Version kompatibel | gesperrt: anderer Schema-Besitzer (`foreign_schema_owner`) bzw. Yoast inkompatibel (`yoast_incompatible`) |
| E8 | Filter | `apply_filters( 'review_captain_schema_eligible', true, $review, $context )` ergibt `true` | gesperrt durch Filter (`filtered`) |

Ergänzende Regeln:

- **Organization/LocalBusiness-Kontext → nie.** Es gibt keinen Konfigurationsweg, Markup für Unternehmens-/Standortreviews zu aktivieren.
- **E8 darf nur verschärfen:** Der Filter wird nur für Reviews aufgerufen, die E1–E7 bereits bestanden haben; jeder Rückgabewert außer strikt `true` sperrt. Eine von E1–E7 gesperrte Review kann daher nie per Filter freigeschaltet werden.
- **Ausgabeweg:** Yoast aktiv und kompatibel → Graph-Piece über Yoast-API. Kein SEO-/Schema-Plugin → eigenes JSON-LD (Fallback). Yoast inkompatibel oder anderer Besitzer → E7 sperrt.
- **Admin-Einstellung** „Review-Markup ausgeben“ (Standard: an) kann alles abschalten (wirkt wie E8), aber nie gesperrte Reviews freischalten.
- `origin` (lokale Einreichung, manuell, CSV, Connector) ist **kein** Kriterium. Für `manual`, `csv_import` und `connector` gilt `platform=local` nur, wenn die Review nachweislich auf **dieser Website** abgegeben wurde; der Import-Assistent und der Connector-Vertrag setzen sonst `other` (siehe [../connectors/contract-v1.md](../connectors/contract-v1.md)).

### Pseudocode (Referenz)

```php
function is_schema_eligible( Review $r, RenderContext $ctx ): bool {
    if ( 'local'    !== $r->platform ) return false;                       // E1
    if ( 'review'   !== $r->kind )     return false;                       // E2
    if ( 'approved' !== $r->status )   return false;                       // E3
    if ( ! in_array( $r->context_type, [ 'product', 'post', 'event' ], true ) ) return false; // E4 (NULL = self-serving)
    if ( ! $ctx->is_rendered( $r ) )   return false;                       // E5
    if ( 'product' === $r->context_type && $ctx->is_woo_product( $r->context_id ) && $ctx->woo_schema_active() ) return false; // E6
    if ( $ctx->foreign_schema_owner() || ( $ctx->yoast_active() && ! $ctx->yoast_compatible() ) ) return false;            // E7
    return true === apply_filters( 'review_captain_schema_eligible', true, $r, $ctx );                                    // E8
}
```

## Szenario-Matrix (SEO-003 Ergebnis)

| Szenario | Ergebnis | Grund |
|---|---|---|
| Google-/Trustpilot-/Trusted-Shops-/App-Store-/Play-Reviews im Widget | kein Markup | E1 |
| Manuell kuratierte externe Review (`platform=other`) | kein Markup | E1 |
| Lokale Review über das Unternehmen (ohne Kontext) | kein Markup | E4 `self_serving` |
| Lokale Standort-/Service-Review ohne Produkt/Post/Event-Kontext | kein Markup | E4 `self_serving` |
| Testimonial (Text, mit oder ohne Sterne) | kein Markup | E2 |
| Lokale Produktreview, Nicht-Woo-Produkt (z. B. eigenes Produkt-Post), freigegeben, sichtbar | Markup möglich | E1–E8 erfüllt |
| Lokale Produktreview zu Woo-Produkt, WooCommerce-Schema aktiv | kein Markup | E6 (WooCommerce-/Yoast-Woo-Knoten bleiben allein) |
| Lokale Kurs-/Event-Review (`event`), freigegeben, sichtbar | Markup möglich | E1–E8 |
| Lokale Review zu Beitrag/Seite (`post`), z. B. Kursseite | Markup möglich | E1–E8 (Typ siehe Ausgabeaufbau) |
| Review `pending`/`flagged`/`removed` | kein Markup | E3 |
| Review existiert, aber Widget zeigt Seite 2 (`rc_page=2`) | nur die auf dieser Seite gerenderten | E5; Seite trägt zudem `noindex` |
| Widget im Editor/REST-Vorschau | kein Markup | E5 |
| Gemischtes Widget (lokal + extern) | nur lokale geeignete Reviews einzeln, **kein** AggregateRating | E1 + Ausgabeaufbau A2 |
| Plattformübergreifender Gesamtscore (Trust-Badge) | kein Markup | nicht-Review-Ausgabe, E1 |
| Rank Math/AIOSEO/SEOPress/Schema Pro aktiv | kein Markup | E7 |
| Yoast zu alt | kein Markup, Reviews sichtbar | E7 |

## Ausgabeaufbau (für geeignete Reviews)

| Nr. | Regel |
|---|---|
| A1 | Pro geeigneter Review ein `Review`-Knoten mit `@id` `{canonical}#/schema/review-captain/review/{uuid}`, `author` (`Person`, nur Anzeigename), `datePublished` (`published_at`), `reviewBody`, ggf. `name` (Titel), `reviewRating` (`ratingValue`, `bestRating`=`rating_scale_max`, `worstRating`=`rating_scale_min`), `itemReviewed` (Referenz). Keine E-Mail, keine User-ID. |
| A2 | `AggregateRating` **nur**, wenn **alle** in der Widget-Instanz gerenderten Reviews geeignet sind und denselben `(context_type, context_id)` haben; `ratingCount`/`ratingValue` aus genau diesen sichtbaren Reviews **und** identisch mit einer im Widget sichtbar angezeigten Zusammenfassung. Sonst kein `AggregateRating`. |
| A3 | `itemReviewed`: vorhandenen Knoten referenzieren, wenn das Schema-Plugin einen passenden Knoten mit `@id` liefert (Yoast-Graph). Sonst minimaler eigener Knoten mit `@id` `{canonical}#/schema/review-captain/item/{context_type}-{context_id}` und `name` (Kontextlabel): `product` → `Product`, `event` → `Event`, `post` → Typ laut Einstellung/Filter, Standard `CreativeWork` (gültiges schema.org, aber kein von Google für Review-Snippets unterstützter Typ – siehe SEO-OPEN-6). |
| A4 | Es werden keine `Organization`, `LocalBusiness`, `WebSite`, `WebPage`, `BreadcrumbList` erzeugt. Mit Yoast wird der Item-Knoten über `mainEntityOfPage`/`isPartOf` an Yoasts `WebPage`-`@id` angebunden. |
| A5 | Ein Review-Knoten erscheint höchstens einmal pro Seite, auch wenn dieselbe Review in mehreren Widgets gerendert wird (Dedupe per `uuid`). |
| A6 | Ohne Rating (`rating_value` NULL) wird `reviewRating` weggelassen; die Review bleibt geeignet. |

## Offene Entscheidungen

| ID | Frage | Vorschlag |
|---|---|---|
| SEO-OPEN-5 | Muss `context_id` zum aktuell angezeigten Objekt passen (z. B. Produktreview A auf Seite von Produkt B)? | als E8-Standardregel im Core verschärfen: nur wenn Kontext = Hauptobjekt der Seite oder Widget explizit auf diesen Kontext gefiltert ist |
| SEO-OPEN-6 | `itemReviewed`-Typ für `post`-Kontext | Einstellung je Widget/Post („Course“, „SoftwareApplication“ …); bis dahin `CreativeWork` ohne Rich-Result-Erwartung |
| SEO-OPEN-7 | `Event`-Minimalknoten ohne `startDate`/`location` | nur referenzieren, wenn Event-Plugin (z. B. Pass Captain) einen Knoten liefert; sonst minimal und ohne Rich-Result-Erwartung |
| SEO-OPEN-8 | A2 (AggregateRating) in v1 aktivieren oder nur Einzel-Reviews | Empfehlung: A2 wie beschrieben; rechtliche/SEO-Prüfung vor Release |
| SEO-OPEN-9 | Woo-Produkt ohne aktives Woo-Schema (Schema per Filter deaktiviert) | E6 greift nicht → Markup möglich; Erkennung „Woo-Schema aktiv“ in SEO-008 präzisieren |
