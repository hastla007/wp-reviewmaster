# Widget- und Shortcode-Vertrag v1

| | |
|---|---|
| Status | **verbindlich für die parallele Core-Implementierung**; Attributlisten nur per ADR ändern |
| Shortcodes | `[review_captain v="1" …]`, `[review_captain_form v="1" …]` |
| Blöcke | `review-captain/reviews`, `review-captain/form` (dynamisch, serverseitig gerendert) |
| Registry | `review_captain_widgets_v1` (Pro-Layouts, RC-118) |
| Bezug | TODO RC-049–RC-055, RC-039, RC-117, RC-118, SEO-015; konzept.md 8.6, 9; conventions.md „Öffentliche Ausgabe“ |

## 1. Review-Widget `[review_captain]`

### Attribute (vollständig, keine weiteren in v1)

```
[review_captain v="1" layout="grid|list" platform="" kind="review|testimonial|all" min_rating="" limit="" context_type="" context_id="" show_source="1" show_date="1" profile=""]
```

| Attribut | Typ / Werte | Default | Leer `""` bedeutet | Validierung, Verhalten bei ungültigem Wert |
|---|---|---|---|---|
| `v` | `1` | `1` | – | fehlt → als `1` behandelt (Editor-Vorschau zeigt Hinweis „v="1" ergänzen“). Anderer Wert → keine öffentliche Ausgabe; Nutzer mit `review_captain_moderate` sehen Hinweis `rc.widget.unsupported_version` |
| `layout` | `grid` \| `list` | `grid` | Default | unbekannt → `grid` (`rc.widget.invalid_attribute`, nur im Debug-Log/Editor). Zusätzliche Werte nur, wenn per `review_captain_widgets_v1` registriert (Pro) |
| `platform` | kommagetrennte Liste aus `local`, `google`, `trustpilot`, `trusted_shops`, `app_store`, `google_play`, `other` | `""` | alle Plattformen | unbekannte Einträge werden verworfen; bleibt nach Verwerfen **kein** gültiger Eintrag übrig → leeres Ergebnis (nicht „alle“) |
| `kind` | `review` \| `testimonial` \| `all` | `review` | Default | unbekannt → `review` |
| `min_rating` | Zahl 1–5 (Dezimalpunkt, max. 1 Nachkommastelle) | `""` | kein Filter | Vergleich gegen `norm5` (review-schema-v1.md „Skalen“), `>=`. Datensätze ohne Rating werden bei gesetztem Filter ausgeschlossen. Ungültig/außerhalb → ignoriert |
| `limit` | Ganzzahl | `10` | Default | auf 1–50 begrenzt (clamp); nicht numerisch → Default |
| `context_type` | `post` \| `product` \| `event` \| `voucher` \| `app` \| `form` | `""` | kein Kontextfilter | unbekannt → Kontextfilter ignoriert |
| `context_id` | String ≤ 64, `[A-Za-z0-9_.:-]` | `""` | kein Filter auf ID | nur wirksam mit gültigem `context_type`; sonst ignoriert. Mit `context_type`, aber ohne `context_id` → Filter auf alle Datensätze dieses Typs |
| `show_source` | `1` \| `0` | `1` | Default | gilt nur für `platform=local` und Testimonials. **Externe Reviews zeigen Quelle immer** (Attribution nicht abschaltbar) |
| `show_date` | `1` \| `0` | `1` | Default | `true`/`false` werden als `1`/`0` akzeptiert; sonst Default |
| `profile` | kommagetrennte Liste positiver Ganzzahlen (`profiles.id`) | `""` | alle Profile | ungültige Einträge verworfen; kein gültiger übrig → leeres Ergebnis. UND-Verknüpfung mit `platform` |

Attributnamen sind case-insensitiv (WordPress-Standard `shortcode_atts` mit Kleinbuchstaben); Werte werden getrimmt.

### Normalisierte Attributstruktur

Shortcode und Block werden auf **dieselbe** Struktur normalisiert (`ReviewCaptain\Frontend\WidgetAttributes::from_shortcode()` / `::from_block()`), danach rendert derselbe Renderer:

```json
{
  "v": 1,
  "layout": "grid",
  "platform": ["local", "google"],
  "kind": "review",
  "min_rating": 4.0,
  "limit": 6,
  "context_type": "product",
  "context_id": "123",
  "show_source": true,
  "show_date": true,
  "profile": []
}
```

### Datenauswahl

1. Nur öffentlich sichtbare Datensätze (Sichtbarkeitsregel, review-schema-v1.md).
2. Filter: `kind` (`all` = beide), `platform`, `profile`, `context_type`/`context_id`, `min_rating`.
3. Sortierung fest: `COALESCE(published_at, created_at) DESC, id DESC`.
4. `LIMIT limit`. Keine Pagination in v1.
5. Kein Frontend-API-Aufruf; keine externen Ressourcen außer consent-gesteuerten Avataren (Default: keine Avatare laden).

Caching: Ergebnis-HTML im Objekt-Cache (Gruppe `review_captain`, Schlüssel = Hash der normalisierten Attribute + Datenversion `review_captain_data_version`, die bei jeder Review-Änderung erhöht wird). Keine personenbezogenen Daten im Cache außer bereits öffentlichen.

### Ausgabe (SSR, ohne JavaScript)

```html
<div class="rc-widget rc-widget--grid" data-rc-v="1">
  <ul class="rc-widget__items" role="list">
    <li class="rc-card rc-card--review rc-card--platform-google">
      <article class="rc-card__inner">
        <div class="rc-rating" role="img" aria-label="4 von 5 Sternen">
          <span class="rc-rating__stars" aria-hidden="true">★★★★☆</span>
          <span class="rc-rating__value">4 / 5</span>
        </div>
        <p class="rc-card__title">Schnelle Lieferung</p>
        <div class="rc-card__body"><p>Alles wie beschrieben, gerne wieder.</p></div>
        <footer class="rc-card__meta">
          <span class="rc-card__author">M. K.</span>
          <time class="rc-card__date" datetime="2026-11-10T09:12:00Z">10. November 2026</time>
          <a class="rc-card__source" href="https://…" rel="nofollow noopener noreferrer" target="_blank">Google<span class="screen-reader-text"> (öffnet in neuem Tab)</span></a>
        </footer>
      </article>
    </li>
    <li class="rc-card rc-card--testimonial">
      <article class="rc-card__inner">
        <p class="rc-card__label">Erfahrungsbericht</p>
        …
      </article>
    </li>
  </ul>
</div>
```

| Regel | Umsetzung |
|---|---|
| Escaping | Text mit `esc_html`, Absätze per `wpautop(esc_html(...))`, URLs `esc_url`, Attribute `esc_attr`; nie rohes HTML aus Reviews |
| Sterne | `aria-label` mit Originalskala; Sterne nicht nur über Farbe |
| Quelle extern | Label aus `meta.attribution.label` bzw. Plattformname, Link `source_url`, Attributionshinweis wenn gesetzt |
| Testimonial | Label „Erfahrungsbericht“, nie Plattformlogo |
| Übersetzt | Hinweis „übersetzt“ bei `meta.translated` |
| Datum | `published_at` (Fallback `created_at`) im Website-Datumsformat; `show_date="0"` blendet aus |
| Leerzustand | `<div class="rc-widget rc-widget--empty"><p class="rc-empty">…</p></div>` mit Text aus Einstellung „Darstellung“ |
| Schema-Markup | nicht im Widget-Template; SEO-Modul entscheidet separat (nur `platform=local`, `kind=review`, `approved`, sichtbar) |

Templates (Theme-Override unter `{theme}/review-captain/…`): `templates/widget/grid.php`, `templates/widget/list.php`, `templates/widget/card-review.php`, `templates/widget/card-testimonial.php`, `templates/widget/empty.php`. Template-Variablen: `$attributes` (normalisiert), `$items` (Array von View-Models ohne `author_email`, `meta.moderation_note`).

### Block `review-captain/reviews`

`block.json`-Attribute (1:1-Abbildung, `render_callback` = gleicher Renderer):

| Block-Attribut | Typ | ↔ Shortcode |
|---|---|---|
| `layout` | string, Default `grid` | `layout` |
| `platform` | string (kommagetrennt) | `platform` |
| `kind` | string, Default `review` | `kind` |
| `minRating` | string | `min_rating` |
| `limit` | number, Default 10 | `limit` |
| `contextType` | string | `context_type` |
| `contextId` | string | `context_id` |
| `showSource` | boolean, Default true | `show_source` |
| `showDate` | boolean, Default true | `show_date` |
| `profile` | string (kommagetrennt) | `profile` |

Der Block speichert implizit `v = 1` (Block-Version über `block.json` `version`/Attribut-Migration).

### Beispiele

```
[review_captain v="1"]
[review_captain v="1" layout="list" platform="local" limit="5"]
[review_captain v="1" kind="testimonial" context_type="event" context_id="OCC-55" show_date="0"]
[review_captain v="1" platform="google,trustpilot" min_rating="4" limit="12" show_source="1"]
[review_captain v="1" kind="all" context_type="product" context_id="123" profile="3,7"]
```

## 2. Formular `[review_captain_form]`

### Attribute (vollständig)

```
[review_captain_form v="1" context_type="" context_id=""]
```

| Attribut | Werte | Default | Validierung |
|---|---|---|---|
| `v` | `1` | `1` | wie oben |
| `context_type` | `post` \| `product` \| `event` \| `voucher` \| `app` \| `form` | `""` (kein Kontext) | unbekannt → kein Kontext |
| `context_id` | String ≤ 64, `[A-Za-z0-9_.:-]` | `""` | nur mit `context_type`; bei `post`/`product`: muss existierender, veröffentlichter Beitrag des passenden Typs sein (`product` nur wenn WooCommerce aktiv), sonst kein Kontext. Andere Typen: nur Formatprüfung (keine fremden Tabellen) |

Block `review-captain/form` mit Attributen `contextType`, `contextId`.

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

Templates: `templates/form/form.php`, `templates/form/message.php`.

## 3. Registry `review_captain_widgets_v1`

Pro registriert zusätzliche Layouts (Karussell, Badge, Trustbar …) über `do_action( 'review_captain_widgets_v1', WidgetRegistry $registry )`. Registrierte Layouts erweitern nur die zulässigen Werte von `layout`; Attribute, Datenauswahl, Sichtbarkeit und Escaping bleiben Core-Vertrag. Bei fehlendem Pro fällt ein Pro-Layout auf `grid` zurück (Inhalte bleiben sichtbar, RC-061).

## Abnahme

- [ ] Shortcode-Attribute exakt wie oben; Unit-Test je Attribut für Default, gültige Werte, ungültige Werte (Fallback laut Tabelle).
- [ ] Block und Shortcode erzeugen für äquivalente Attribute identisches HTML (Snapshot-Test).
- [ ] Ausgabe ohne JavaScript vollständig lesbar; alle Nutzerinhalte escaped (XSS-Testfälle mit `<script>`, Attribut-Injection).
- [ ] Externe Reviews zeigen Quelle auch bei `show_source="0"`.
- [ ] Unbekanntes `v` erzeugt keine öffentliche Ausgabe.
- [ ] Formular: Nonce, Honeypot, Mindestzeit, Rate-Limit, Kontext-Signatur und Feldvalidierung je mit Negativtest; gespeicherter Datensatz ist `pending`, `unverified`, `local_submission`.
- [ ] Keine Rating-Vorauswahl, Consent-Checkbox nicht vorausgewählt.
