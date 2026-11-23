# Widget- und Shortcode-Vertrag v1

| | |
|---|---|
| Status | **verbindlich**, an den implementierten Stand angeglichen (`src/Widgets/WidgetDefinition.php`, `src/Widgets/WidgetRegistry.php`, `src/Frontend/*`, `blocks/*/block.json`), Stand 2026-11-16. Wo dieses Dokument und der Code abweichen, gilt der Code. Attributlisten nur per ADR ändern |
| Shortcodes | `[review_captain v="1" …]`, `[review_captain_form v="1" …]`, `[review_captain_cta v="1" profile="…"]` |
| Blöcke | `review-captain/reviews`, `review-captain/form` (dynamisch, serverseitig gerendert) |
| Registry | `review_captain_widgets_v1` (zusätzliche Widget-Typen, RC-118) |
| Bezug | TODO RC-049–RC-055, RC-039, RC-117, RC-118, SEO-015; konzept.md 8.6, 9; conventions.md „Öffentliche Ausgabe“ |

Alle drei Shortcodes und beide Blöcke werden auf **eine** normalisierte Struktur `ReviewCaptain\Widgets\WidgetDefinition` abgebildet (`WidgetDefinition::from_atts()` für Shortcodes, `WidgetDefinition::from_block_attributes()` bzw. `block_to_atts()` für Blöcke) und von `WidgetRegistry::render()` an den Renderer des Widget-Typs übergeben.

## 1. Review-Widget `[review_captain]`

### Attribute (vollständig für v1)

```
[review_captain v="1" type="reviews" layout="grid|list" columns="3" platform="" kind="all|review|testimonial" min_rating="" limit="6" context_type="" context_id="" show_source="1" show_date="" show_author="" show_summary="1" paginate="0" profile="" new_tab="0" heading_level="3"]
```

Defaults exakt nach `WidgetDefinition`. Ungültige Werte fallen auf den Default zurück und werden in `WidgetDefinition::$notices` (nur für Admins/Debug) vermerkt.

| Attribut | Typ / Werte | Default | Leer `""` bedeutet | Validierung, Verhalten bei ungültigem Wert |
|---|---|---|---|---|
| `v` | Vertragsversion, Format `^\d+(\.\d+)*$` | `1` | `1` | Major ≠ `1` oder ungültiges Format → `compatible = false`: keine Ausgabe außer einem HTML-Kommentar „Widget-Vertragsversion … wird nicht unterstützt“ |
| `type` | Widget-Typ (`sanitize_key`): `reviews` \| `form` \| `cta` (Core) oder ein über `review_captain_widgets_v1` registrierter Typ | `reviews` (beim Shortcode `[review_captain]`) | Default | unbekannter Typ → nur HTML-Kommentar „unbekannter Widget-Typ“. `[review_captain_form]`/`[review_captain_cta]` und die Blöcke setzen den Typ fest |
| `layout` | `grid` \| `list` | `grid` | Default | unbekannt → `grid` |
| `columns` | Ganzzahl 1–4 | `3` | Default | außerhalb/nicht numerisch → `3`; wird als CSS-Variable `--rc-columns` ausgegeben |
| `platform` | **kommagetrennte Liste** aus `local`, `google`, `trustpilot`, `trusted_shops`, `app_store`, `google_play`, `other` (Groß-/Kleinschreibung egal, Leerzeichen erlaubt) | `""` | alle Plattformen | unbekannte Einträge werden verworfen, Duplikate entfernt. Bleibt **kein** gültiger Eintrag übrig, entfällt der Filter (= alle Plattformen) |
| `kind` | `review` \| `testimonial` \| `all` | `all` | Default | unbekannt → `all` |
| `min_rating` | Zahl 1–5 (Dezimalpunkt oder -komma) | `""` | kein Filter | Vergleich auf normalisierter Skala (`(min_rating − 1) / 4` gegen `(rating_value − scale_min) / (scale_max − scale_min)`), `>=`. Ungültig/außerhalb → ignoriert |
| `limit` | Ganzzahl 1–50 (`WidgetDefinition::MAX_LIMIT`) | `6` (`DEFAULT_LIMIT`) | Default | außerhalb/nicht numerisch → `6` (kein Clamp); bei `paginate="1"` = Einträge pro Seite |
| `context_type` | `post` \| `product` \| `event` \| `voucher` \| `app` \| `form` \| `course` \| `organization` (`WidgetDefinition::CONTEXT_TYPES`) | `""` | kein Kontextfilter | unbekannt → ignoriert |
| `context_id` | String ≤ 64, `[A-Za-z0-9_.:-]`, oder `current` (= ID des aktuellen Beitrags) | `""` | kein Filter auf ID | nur wirksam mit gültigem `context_type`, sonst ignoriert. Mit `context_type`, aber ohne `context_id` → alle Datensätze dieses Typs |
| `show_source` | `1`/`true`/`yes`/`on` \| `0`/`false`/`no`/`off` | `1` | Default | gilt nur für lokale Datensätze. **Externe Reviews zeigen Quelle immer** (Attribution nicht abschaltbar, SET-014) |
| `show_date` | wie `show_source` | leer = Einstellung `display.show_date` (Default `true`) | Einstellung | ungültig → Einstellung |
| `show_author` | wie `show_source` | leer = Einstellung `display.show_author` (Default `true`) | Einstellung | ungültig → Einstellung |
| `show_summary` | wie `show_source` | `1` | Default | Zusammenfassung je Plattform über der Liste; nicht bei `kind="testimonial"` und nicht ohne Treffer |
| `paginate` | wie `show_source` | `0` | Default | `1` → Links „Vorherige/Nächste Seite“ über den Query-Parameter `rc_page` (2–1000); `rc_page`-Seiten sind `noindex` ([indexing-rules.md](../seo/indexing-rules.md)) |
| `profile` | **eine** positive Ganzzahl (`profiles.id`) | `""` | alle Profile | nicht numerisch/≤ 0 → ignoriert. UND-Verknüpfung mit `platform` |
| `new_tab` | wie `show_source` | `0` | Default | nur für den CTA-Typ ausgewertet (`target="_blank"` + Hinweis „öffnet in neuem Tab“) |

Attributnamen werden kleingeschrieben, Werte getrimmt. Unbekannte Attribute bleiben (als String) in `WidgetDefinition::$raw` für Pro-Widget-Typen erhalten und werden vom Core ignoriert.

Ergänzung (Stand 2026-11-16, QA F-07): `heading_level` – Ganzzahl 2–6, Standard `3`; Überschriftenebene der Kartentitel (`<h3 class="rc-card__title">`), damit die Gliederung unter der Seitenüberschrift stimmt. Ungültige Werte → Standard + Hinweis. Block-Attribut `headingLevel` (integer, Standard 3).

### Normalisierte Struktur (`WidgetDefinition::to_array()`)

```json
{
  "type": "reviews",
  "v": "1",
  "layout": "grid",
  "columns": 3,
  "platforms": ["local", "google"],
  "kind": "all",
  "min_rating": 4.0,
  "limit": 6,
  "context_type": "product",
  "context_id": "123",
  "show_source": true,
  "show_date": null,
  "show_author": null,
  "show_summary": true,
  "paginate": false,
  "profile_id": null,
  "new_tab": false
}
```

`show_date`/`show_author` sind dreiwertig (`null` = Einstellung verwenden).

### Datenauswahl (`WidgetDefinition::query_args()`, `Renderer::select_reviews()`)

1. Nur `status = approved` und `removed_at IS NULL` (zusätzlich nach der Abfrage erneut geprüft).
2. Filter: `kind` (`all` = beide), `platform` (Liste), `profile_id`, `context_type`/`context_id`, `min_rating`.
3. Sortierung fest: `COALESCE(published_at, created_at) DESC, id DESC`.
4. `LIMIT limit`, bei `paginate` mit `OFFSET (rc_page − 1) × limit`.
5. Kein Frontend-API-Aufruf; keine externen Ressourcen.

### Ausgabe (SSR, ohne JavaScript)

Templates (Theme-Override unter `{theme}/review-captain/…`, siehe `Frontend\TemplateLoader`): `templates/reviews-grid.php` (Container für `grid` und `list`), `templates/review-card.php` (eine Karte), `templates/review-form.php`, `templates/cta.php`. Template-Variable ist jeweils `$rc` (vorbereitete, escapte Daten). Struktur (gekürzt):

```html
<div id="rc-widget-1" class="rc-widget rc-widget--grid rc-widget--reduced-motion" style="--rc-columns: 3">
  <ul class="rc-summary" aria-label="Zusammenfassung je Plattform">
    <li class="rc-summary__item rc-source--google">…</li>
  </ul>
  <div class="rc-widget__items">
    <article class="rc-card rc-card--review rc-card--external" data-rc-review="…uuid…">
      <header class="rc-card__header">
        <div class="rc-rating" role="img" aria-label="4 von 5 Sternen">
          <span class="rc-stars" aria-hidden="true"><svg class="rc-star rc-star--full">…</svg>…</span>
          <span class="rc-rating__value" aria-hidden="true">4 / 5</span>
        </div>
        <h3 class="rc-card__title">Schnelle Lieferung</h3>
      </header>
      <div class="rc-card__body"><p>Alles wie beschrieben, gerne wieder.</p></div>
      <footer class="rc-card__footer rc-card__meta">
        <span class="rc-author">M. K.</span>
        <time class="rc-date" datetime="2026-11-10T09:12:00+00:00">10. November 2026</time>
        <span class="rc-source rc-source--google">Google</span>
        <a class="rc-source__link" href="https://…" rel="nofollow noopener noreferrer ugc">…</a>
      </footer>
    </article>
  </div>
  <nav class="rc-pagination" aria-label="Weitere Bewertungen">…</nav>
</div>
```

Leerzustand: `<p class="rc-empty">` mit dem Text aus `display.empty_text` (Fallback „Es sind noch keine Bewertungen vorhanden.“). Die vollständige Klassenliste steht in [design-system.md](../ux/design-system.md).

| Regel | Umsetzung |
|---|---|
| Escaping | Text mit `esc_html`, Body über `wp_kses` mit Allowlist, URLs `esc_url`, Attribute `esc_attr`; nie rohes HTML aus Reviews |
| Sterne | `aria-label` mit Originalskala; Sterne nicht nur über Farbe; abschaltbar über `display.show_stars` |
| Quelle extern | Plattformlabel + Link `source_url` mit Link-Attributen aus `Security\EmbedPolicy` |
| Testimonial | Badge „Erfahrungsbericht“, nie Plattformlogo |
| Datum | `published_at` (Fallback `created_at`), Format nach `display.date_style` (`absolute` \| `relative`) |
| CSS | `assets/css/frontend.css` nur, wenn `display.load_css = true` |
| Schema-Markup | nicht im Widget-Template; SEO-Modul entscheidet separat (nur `platform=local`, `kind=review`, `approved`, sichtbar; abschaltbar über `display.schema_enabled`) |

### Block `review-captain/reviews`

`block.json`-Attribute; `render_callback` = derselbe Renderer. Block-Attribute sind camelCase und werden über `WidgetDefinition::block_to_atts()` auf die Shortcode-Attribute abgebildet (Boolesche Werte → `1`/`0`, Arrays → Kommaliste; `minRating`/`profile` mit Wert `0` gelten als „nicht gesetzt“).

| Block-Attribut | Typ, Default (`block.json`) | ↔ Shortcode |
|---|---|---|
| `v` | string, `"1"` | `v` |
| `layout` | string (`grid`\|`list`), `grid` | `layout` |
| `columns` | number, `3` | `columns` |
| `platform` | string (kommagetrennt), `""` | `platform` |
| `kind` | string (`all`\|`review`\|`testimonial`), `all` | `kind` |
| `minRating` | number, `0` (= kein Filter) | `min_rating` |
| `limit` | number, `6` | `limit` |
| `contextType` | string, `""` | `context_type` |
| `contextId` | string, `""` | `context_id` |
| `profile` | number, `0` (= kein Filter) | `profile` |
| `showSource` | boolean, `true` | `show_source` |
| `showDate` | boolean, ohne Default (= Einstellung) | `show_date` |
| `showAuthor` | boolean, ohne Default (= Einstellung) | `show_author` |
| `showSummary` | boolean, `true` | `show_summary` |
| `paginate` | boolean, `false` | `paginate` |

`WidgetDefinition::BLOCK_MAP` kennt zusätzlich `type` und `newTab` (↔ `type`, `new_tab`); diese sind in `blocks/reviews/block.json` nicht deklariert und werden vom Block-Renderer überschrieben (`type`) bzw. nur von Pro-Blöcken genutzt.

### Beispiele

```
[review_captain v="1"]
[review_captain v="1" layout="list" platform="local" limit="5"]
[review_captain v="1" kind="testimonial" context_type="event" context_id="OCC-55" show_date="0"]
[review_captain v="1" platform="google,trustpilot" min_rating="4" limit="12" columns="4" show_summary="0"]
[review_captain v="1" kind="review" context_type="product" context_id="current" profile="3" paginate="1"]
```

### Bewertungs-CTA `[review_captain_cta]`

```
[review_captain_cta v="1" profile="3" new_tab="1"]
```

Zeigt einen Button „Bewerte uns auf {Plattform}“ mit dem Link des Profils (`profile` = `profiles.id`, Pflicht). Fehlt das Profil, hat es die Plattform `local` oder keine gültige https-Adresse, sehen Nutzer mit `review_captain_moderate` einen Hinweis, alle anderen nur einen HTML-Kommentar. Link-Attribute `rel="nofollow noopener noreferrer"` (Betreiberinhalt, kein `ugc`); `new_tab="1"` ergänzt `target="_blank"` und einen Screenreader-Hinweis.

### Trusted-Shops-Siegel `[review_captain_trustbadge]` (RC-077)

```
[review_captain_trustbadge tsid="X…"]
```

Offizielles Trusted-Shops-Trustbadge als Embed mit Klick-Freigabe (RC-003 F15, TEC-013). `tsid` = Trusted-Shops-ID (`X` + 32 Hex-Zeichen); ohne Attribut gilt die Einstellung *Darstellung → Trusted-Shops-ID*. Ausgabe ist nur ein Platzhalter mit Hinweis „Beim Laden werden Daten an widgets.trustedshops.com übertragen“, Button und Link zum öffentlichen Trusted-Shops-Profil. Erst der Klick lädt `https://widgets.trustedshops.com/js/{TSID}.js` (`assets/js/frontend-embed.js` prüft Host und Pfad erneut). Ungültige ID oder per `review_captain_embed_hosts` entfernter Host → keine Ausgabe. Kein API-Zugriff, keine Daten von Trusted Shops gespeichert, kein Schema-Markup.

## 2. Formular `[review_captain_form]`

### Attribute (vollständig)

```
[review_captain_form v="1" context_type="" context_id="" context_ids=""]
```

| Attribut | Werte | Default | Validierung |
|---|---|---|---|
| `v` | `1` | `1` | wie oben |
| `context_type` | wie beim Review-Widget (`WidgetDefinition::CONTEXT_TYPES`) | `""` (kein Kontext) | unbekannt → kein Kontext |
| `context_id` | String ≤ 64, `[A-Za-z0-9_.:-]` | `""` | nur mit `context_type`; bei `post`/`product`: muss existierender, veröffentlichter Beitrag des passenden Typs sein (`product` nur wenn WooCommerce aktiv), sonst kein Kontext. Andere Typen: nur Formatprüfung (keine fremden Tabellen) |

Block `review-captain/form` mit Attributen `v` (Default `"1"`), `contextType`, `contextId` (Default jeweils `""`).

> **Implementierter Stand (maßgeblich, `src/Frontend/FormHandler.php`, `templates/review-form.php`):** Aktion `review_captain_submit` (`FormHandler::ACTION`, Nonce-Aktion ebenso, Nonce-Feld `_rc_nonce`). Felder: `rc_rating` (1–5, keine Vorauswahl), `rc_title` (≤ 150 Zeichen, nur bei `submissions.allow_title`), `rc_body` (Länge `submissions.min_text_length`…`max_text_length`, Default 20…5000), `rc_name` (≤ 100, öffentlich), `rc_email` (Pflicht, nie öffentlich), `rc_consent` (Pflicht bei `submissions.require_consent`), Honeypot `rc_website` (bei `submissions.honeypot`), signierter Zeitstempel `rc_ts` (mind. 3 s, max. 24 h), Kontext `rc_context_type`/`rc_context_id` mit Signatur `rc_context_sig`, Rücksprung `rc_return`, Formularinstanz `rc_form`. Rate-Limit `submissions.rate_limit_per_hour` (Default 5) je IP-Hash (IPv6: /64-Präfix; Transient, IP wird nicht gespeichert); Fehlversuche bis zum Dreifachen. Status neuer Einreichungen nach `general.new_review_status` (Default `pending`). Ergebnis per Redirect (303) mit `rc_submitted=1|2` bzw. `rc_error=<codes>` und optional `rc_draft=<token>` (Entwurf ohne E-Mail, 10 min Transient). Template: `templates/review-form.php`. Die folgende Tabelle beschreibt den ursprünglichen Entwurf; abweichende Feld- und Parameternamen gelten als überholt.


Ergänzungen Schema v2 (M7, standardmäßig aus):

- `context_ids` (nur mit `submissions.multi_product_enabled` und `context_type`): kommagetrennte Liste (≤ 20, je `[A-Za-z0-9_.:-]{1,64}`). Das Formular zeigt je Kontext Sterne + Text (`rc_items[<id>][rating|body]`) statt `rc_rating`/`rc_body`; die Liste wird als `rc_context_ids` mit Signatur `rc_context_ids_sig` übertragen. Je bewertetem Kontext entsteht ein Review; nicht bewertete Positionen werden übersprungen; Kontexte außerhalb der signierten Liste werden ignoriert. Doppelte Bewertung derselben Person (E-Mail, Groß-/Kleinschreibung egal) für denselben Kontext → Fehler `duplicate` (UNIQUE `submission_key`). Fehler: `items_empty`, `item_rating`, `item_body_short`, `item_body_long`.
- Foto (`submissions.photos_enabled`, nur Einzelformular): Feld `rc_photo` (`enctype="multipart/form-data"`), Fehler `photo_upload`, `photo_size`, `photo_type`, `photo_invalid`, `photo_unsupported`.
- Nach einem Fehler zeigt der Redirect auf `#rc-form-<n>-errors`; die Fehlerbox hat `role="alert" tabindex="-1" autofocus` (QA F-06).

### Produkt-Q&A `[review_captain_qa]` (Schema v2, `submissions.qa_enabled`)

`[review_captain_qa v="1" context_type="product" context_id="123|current" limit="10"]` – listet freigegebene Fragen mit Antwort des Anbieters und ein Frageformular (admin-post `review_captain_question`, Nonce-Feld `_rc_q_nonce`, Honeypot, signierter Zeitstempel und Kontext, Rate-Limit `submissions.rate_limit_per_hour`). Felder `rc_question` (10–1000), `rc_name` (öffentlich), `rc_email` (optional, nie öffentlich), `rc_consent`. Fragen sind keine Reviews: kein Rating, keine Zählung in Übersichten, **kein Schema-Markup**, keine Review-Events. Template `templates/questions.php`.

### Felder

| Feld (`name`) | Typ | Pflicht | Regel |
|---|---|---|---|
| `rc_rating` | Radio 1–5 in `<fieldset>` mit `<legend>` | ja | **keine Vorauswahl** |
| `rc_title` | Text ≤ 255 | nein | |
| `rc_body` | Textarea | ja | Länge gemäß Einstellung (Vorschlag 10–5000 Zeichen) |
| `rc_author_name` | Text ≤ 191 | ja | öffentlicher Anzeigename |
| `rc_author_email` | E-Mail ≤ 191 | Einstellung (Default Pflicht) | nie öffentlich; für Moderation und Privacy-Export/-Löschung |
| `rc_consent_publish` | Checkbox | ja | nicht vorausgewählt; Text + Link zur Datenschutzerklärung aus Einstellungen |
| `rc_website` | Honeypot (Text, per CSS ausgeblendet, `tabindex="-1"`, `autocomplete="off"`) | – | gefüllt → `rc.form.spam` |
| `rc_ctx` | hidden | – | signierter Kontext: `base64url(json{context_type, context_id, post_id, rendered_at})` + `.` + HMAC-SHA256 mit `wp_salt('nonce')`; Manipulation → `rc.form.context_invalid` |
| `_rc_nonce` | hidden | – | `wp_nonce_field( 'review_captain_submit_review', '_rc_nonce' )` |
| `action` | hidden | – | `review_captain_submit_review` |

### Verarbeitung

- Ziel: `admin-post.php` (`admin_post_review_captain_submit_review` und `admin_post_nopriv_review_captain_submit_review`). Kein REST-Schreibendpunkt.
- Prüfreihenfolge: Nonce (`rc.form.invalid_nonce`) → Honeypot (`rc.form.spam`, Antwort wie Erfolg, nichts gespeichert) → Mindestzeit seit `rendered_at` 3 s (`rc.form.spam`) → Rate-Limit (Vorschlag 3 Einreichungen / 10 min je `HMAC(IP)`, Transient; IP wird nicht gespeichert; `rc.form.rate_limited`) → Kontext-Signatur → Feldvalidierung (`rc.form.invalid_input`).
- Gespeichert: `kind=review`, `origin=local_submission`, `platform=local`, `status=pending` (Einstellung „Einreichungen & Moderation“), `verification=unverified`, `consent_publish=1`, `consent_at=now`, `published_at=now`, `language` aus `determine_locale()`, `author_user_id` wenn eingeloggt, Kontext aus `rc_ctx`.
- Nach Commit: `review_captain_review_created_v1`.
- Antwort: `wp_safe_redirect()` auf die Permalink-URL aus `rc_ctx` mit `?rc_form=submitted#rc-form` bzw. `?rc_form=error&rc_code=<code>#rc-form`. Keine Eingabewerte oder PII in der URL; v1 befüllt Felder nach Fehler nicht erneut (HTML5-Validierung `required`/`maxlength` reduziert Fehlversuche).
- Seiten-Caching: Für nicht eingeloggte Besucher ist die Nonce identisch und ≤ 24 h gültig; Seiten mit Formular sollten nicht länger als 12 h gecacht werden (Hinweis in Doku/Diagnose).

### Ausgabe (Auszug)

```html
<form id="rc-form" class="rc-form" method="post" action="https://example.test/wp-admin/admin-post.php">
  <fieldset class="rc-form__rating">
    <legend>Ihre Bewertung</legend>
    <input type="radio" id="rc-rating-1" name="rc_rating" value="1" required><label for="rc-rating-1">1 Stern</label>
    …
    <input type="radio" id="rc-rating-5" name="rc_rating" value="5"><label for="rc-rating-5">5 Sterne</label>
  </fieldset>
  …
  <p class="rc-form__hp" aria-hidden="true"><label for="rc-website">Website</label><input type="text" id="rc-website" name="rc_website" tabindex="-1" autocomplete="off"></p>
  <input type="hidden" name="action" value="review_captain_submit_review">
  <input type="hidden" name="rc_ctx" value="eyJjb250ZXh0X3R5cGUiOiJwb3N0Ii….c2ln">
  <input type="hidden" id="_rc_nonce" name="_rc_nonce" value="…">
  <button type="submit" class="rc-form__submit">Bewertung senden</button>
</form>
```

Template: `templates/review-form.php` (Formular, Erfolgs- und Fehlermeldungen).

## 3. Registry `review_captain_widgets_v1`

Pro und andere Add-ons registrieren zusätzliche **Widget-Typen** (Karussell, Badge, Trustbar …) über `do_action( 'review_captain_widgets_v1', WidgetRegistry $registry )` (einmal pro Request, `WidgetRegistry::boot()` auf `init`):

```php
add_action( 'review_captain_widgets_v1', function ( \ReviewCaptain\Widgets\WidgetRegistry $registry ): void {
    $registry->register( 'carousel', fn( \ReviewCaptain\Widgets\WidgetDefinition $d ): string => '…', array( 'label' => 'Karussell' ) );
} );
```

- `register( string $type, callable $render, array $args = array() ): bool` – Typ `^[a-z0-9_-]{1,40}$`; `false`, wenn ungültig, bereits vergeben oder ein Core-Typ (`WidgetRegistry::CORE_TYPES` = `reviews`, `form`, `cta`; Core-Typen sind nicht ersetzbar).
- Aufruf über `[review_captain v="1" type="carousel" …]`; der Renderer erhält die normalisierte `WidgetDefinition` (Pro-spezifische Attribute in `$d->raw`) und muss escaptes HTML liefern.
- Weitere API: `has( string $type ): bool`, `types(): array<string, string>` (Typ → Label), `render( WidgetDefinition $d ): string`.
- Ist ein Typ nicht registriert (z. B. Pro inaktiv), wird nur ein HTML-Kommentar ausgegeben. Ausblick (nicht in v1): Rückfall unbekannter Pro-Typen auf `grid` (RC-061).
- `layout` bleibt in v1 auf `grid`/`list` beschränkt; Registrierungen erweitern die Typen, nicht die Layoutwerte.

## Abnahme

- [ ] Shortcode-Attribute exakt wie oben (Defaults aus `WidgetDefinition`); Unit-Test je Attribut für Default, gültige Werte, ungültige Werte (Fallback laut Tabelle).
- [ ] Block und Shortcode erzeugen für äquivalente Attribute identisches HTML (Snapshot-Test).
- [ ] Ausgabe ohne JavaScript vollständig lesbar; alle Nutzerinhalte escaped (XSS-Testfälle mit `<script>`, Attribut-Injection).
- [ ] Externe Reviews zeigen Quelle auch bei `show_source="0"`.
- [ ] Unbekanntes `v` erzeugt keine öffentliche Ausgabe (nur HTML-Kommentar).
- [ ] Block-Attribute (camelCase) und Shortcode-Attribute (snake_case) ergeben dieselbe `WidgetDefinition::to_array()`.
- [ ] Formular: Nonce, Honeypot, Mindestzeit, Rate-Limit, Kontext-Signatur und Feldvalidierung je mit Negativtest; gespeicherter Datensatz ist `pending`, `unverified`, `local_submission`.
- [ ] Keine Rating-Vorauswahl, Consent-Checkbox nicht vorausgewählt.
