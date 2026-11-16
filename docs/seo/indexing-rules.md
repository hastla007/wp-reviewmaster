# SEO-004 – URL- und Indexierungsregeln

Status: verbindlich ab 2026-11-16 (Core v1), an den implementierten Stand angeglichen (`src/Seo/Robots.php`, `Robots::PARAMS`). Bezug: konzept.md §18 „Indexierung und Seitenstruktur“, [output-ownership.md](output-ownership.md).

## Entscheidungen

| Thema | Entscheidung |
|---|---|
| Datenhaltung | Reviews in **Custom Tables** (`{prefix}review_captain_reviews` u. a., siehe Konventionen). **Kein** Custom Post Type. |
| Öffentliche Einzelseiten | **Keine.** Es gibt keine Permalinks, Rewrite-Regeln oder Query-Vars für einzelne Reviews. |
| Archive | **Keine** Review-Archive, Taxonomien, Autoren- oder Datumsarchive. |
| Sitemaps | **Keine** eigenen Sitemaps; keine Einträge in Yoast- oder WordPress-Core-Sitemaps (keine Filter auf `wpseo_sitemap_*` / `wp_sitemaps_*` nötig, da keine Objekte existieren). |
| Ausgabeort | Nur auf vorhandenen, redaktionell gepflegten Seiten/Beiträgen/Produkten via serverseitig gerendertem Block `review-captain/reviews` bzw. Shortcode `[review_captain v="1"]`. |
| Indexierbarkeit der Einbettungsseite | bestimmt allein Yoast/WordPress (Seiteneinstellung). |
| Canonical | bleibt bei Yoast bzw. WordPress-Core und zeigt auf die redaktionelle Seite **ohne** `rc_page`. Review Captain setzt/ändert kein Canonical. |
| Pagination im Widget | Query-Parameter `rc_page` (Ganzzahl ≥ 2). Seite 1 wird ohne Parameter verlinkt. |
| Filter-/Sortierparameter | v1 hat keine öffentlichen Filter-/Sortierparameter. Künftige öffentliche Parameter (z. B. `rc_sort`, `rc_rating`) werden in `Robots::PARAMS` aufgenommen, bevor sie ausgeliefert werden. |
| Admin, REST, `admin-post.php`, Editor-Vorschau, Connector-/Diagnoseansichten | nicht öffentlich bzw. ohnehin nicht indexierbar (Login, JSON, Redirect). Keine öffentlichen Test-/Vorschau-URLs. |
| Danke-/Statusseiten nach Einreichung | Redirect zurück auf die Ursprungsseite mit den Statusparametern `rc_submitted`, `rc_error`, `rc_form` und ggf. `rc_draft` ([widget-v1.md](../contracts/widget-v1.md) §2). Alle vier lösen ebenfalls `noindex, follow` aus (SEO-OPEN-10 damit erledigt). |

## Robots-Regel für `rc_*`-Parameter

`noindex`-auslösende Parameter (`ReviewCaptain\Seo\Robots::PARAMS`, exakt):

| Parameter | Herkunft |
|---|---|
| `rc_page` | Widget-Pagination (`paginate="1"`) |
| `rc_submitted` | Formular: Einreichung erfolgreich (`1` = wartet auf Moderation, `2` = direkt freigegeben) |
| `rc_error` | Formular: Fehlercodes der Einreichung |
| `rc_form` | Formular: Instanznummer für Meldungen/Anker |
| `rc_draft` | Formular: Token für den kurzlebigen Entwurf nach Validierungsfehler |

- **Nur wenn** mindestens einer dieser Parameter im Request gesetzt ist (`isset( $_GET[...] )`, unabhängig vom Wert; `Robots::has_rc_params()`), ergänzt Review Captain über den WordPress-Filter `wp_robots` (Priorität 20, `Robots::filter_wp_robots()`) `noindex` und – sofern nicht bereits `nofollow` gesetzt ist – `follow`, und entfernt `index`:

```php
public static function filter_wp_robots( $robots ): array {
    $robots = is_array( $robots ) ? $robots : array();
    if ( ! self::has_rc_params() ) {
        return $robots;
    }
    $robots['noindex'] = true;
    if ( empty( $robots['nofollow'] ) ) {
        $robots['follow'] = true;
    }
    unset( $robots['index'] );
    return $robots;
}
```

- Zusätzlich ist immer der Yoast-Adapter `wpseo_robots_array` (Priorität 20, `Robots::filter_yoast_robots()`) registriert: setzt `index = 'noindex'` und `follow = 'follow'` nur, wenn `follow` noch nicht gesetzt ist – ausschließlich hinzufügend, nie entfernend.
- Ohne einen dieser Parameter verändert Review Captain die Robots-Direktiven **nie**.
- Review Captain entfernt keine bestehenden `noindex`/`nofollow`-Direktiven und setzt nie `nofollow`.
- **Yoast-Hinweis:** Gibt Yoast das Robots-Meta-Tag selbst aus und unterdrückt dabei die Core-Ausgabe von `wp_robots`, greift der oben genannte Adapter; SEO-016-Szenario „Yoast + rc_page“ prüft beides.
- Links zwischen Widget-Seiten sind normale `<a href>`-Links (crawlbar, `follow`), kein `rel=nofollow`.
- Schema auf `rc_page`-Seiten: nur für die dort gerenderten Reviews (E5) – unkritisch, da die Seite `noindex` trägt; keine Duplikat-Schema-Ausgabe der Seite 1.

## Serverseitiges Rendering (SEO-015)

- Sichtbare Reviewtexte, Autor, Datum, Quelle und Rating stehen ohne Client-JavaScript im HTML.
- Externe Embeds/Ressourcen (z. B. Plattform-Logos von Fremdservern, offizielle Widgets) laden nur nach erforderlicher Zustimmung; Standard sind lokale Assets.
- Quellenkennzeichnung (z. B. „via Google“) ist sichtbarer Text, nicht nur ein Bild.

## Prüfung

| Test | Erwartung |
|---|---|
| Seite mit Widget ohne Parameter | Robots unverändert (Yoast-Einstellung), ein Canonical auf die Seite |
| `?rc_page=2` | `noindex, follow`, Canonical weiterhin ohne Parameter |
| `?rc_page=abc` | ebenfalls `noindex, follow`; Widget zeigt Seite 1 |
| `?rc_submitted=1&rc_form=1` bzw. `?rc_error=email&rc_form=1` | `noindex, follow` |
| Seite mit `nofollow`, `?rc_page=2` | `noindex, nofollow` (kein `follow` ergänzt) |
| Seite in Yoast auf `noindex` gesetzt, `?rc_page=2` | bleibt `noindex` |
| Yoast-Sitemap | enthält keine Review-Captain-URLs |
| `/?post_type=…`/Rewrite-Test | keine Review-Captain-Objekt-URLs auflösbar |
