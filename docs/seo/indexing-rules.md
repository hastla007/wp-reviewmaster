# SEO-004 – URL- und Indexierungsregeln

Status: verbindlich ab 2026-11-16 (Core v1). Bezug: konzept.md §18 „Indexierung und Seitenstruktur“, [output-ownership.md](output-ownership.md).

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
| Filter-/Sortierparameter | v1 kennt nur `rc_page`. Künftige öffentliche Parameter (z. B. `rc_sort`, `rc_rating`) werden in die Liste `noindex`-auslösender Parameter aufgenommen, bevor sie ausgeliefert werden. |
| Admin, REST, `admin-post.php`, Editor-Vorschau, Connector-/Diagnoseansichten | nicht öffentlich bzw. ohnehin nicht indexierbar (Login, JSON, Redirect). Keine öffentlichen Test-/Vorschau-URLs. |
| Danke-/Statusseiten nach Einreichung | Redirect zurück auf die Ursprungsseite. v1 implementiert die `noindex`-Regel nur für `rc_page`; nutzt die Einreichung einen Statusparameter, ist er vor Auslieferung in die Liste aufzunehmen (offen: SEO-OPEN-10). |

## Robots-Regel für `rc_page`

- **Nur wenn** `rc_page` im Request gesetzt ist (`isset( $_GET['rc_page'] )`, unabhängig vom Wert), ergänzt Review Captain über den WordPress-Filter `wp_robots`:

```php
add_filter( 'wp_robots', static function ( array $robots ): array {
    if ( ! isset( $_GET['rc_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return $robots;
    }
    $robots['noindex'] = true;
    $robots['follow']  = true;
    unset( $robots['index'] );
    return $robots;
} );
```

- Ohne `rc_page` verändert Review Captain die Robots-Direktiven **nie**.
- Review Captain entfernt keine bestehenden `noindex`/`nofollow`-Direktiven und setzt nie `nofollow`.
- **Yoast-Hinweis:** Gibt Yoast das Robots-Meta-Tag selbst aus und unterdrückt dabei die Core-Ausgabe von `wp_robots`, muss SEO-014 dies im Test erkennen (SEO-016-Szenario „Yoast + rc_page“). Fallback-Adapter: gleiche Logik zusätzlich über `wpseo_robots_array` – ausschließlich zum Hinzufügen von `noindex`/`follow`, nie zum Entfernen.
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
| Seite in Yoast auf `noindex` gesetzt, `?rc_page=2` | bleibt `noindex` |
| Yoast-Sitemap | enthält keine Review-Captain-URLs |
| `/?post_type=…`/Rewrite-Test | keine Review-Captain-Objekt-URLs auflösbar |
