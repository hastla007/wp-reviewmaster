# Designsystem und Editor-Handoff (UX-007)

Status: Frontend-Abschnitte (§2–§4) an den implementierten Stand angeglichen (`assets/css/frontend.css`, `templates/*.php`, `src/Frontend/Renderer.php`), Stand 2026-11-16; wo dieses Dokument und der Code abweichen, gilt der Code. Gilt für Core; Pro-Widgets erweitern dieselben Tokens.

## 1. Admin: ausschließlich native WP-Admin-Komponenten

Kein eigenes Admin-Designsystem, keine SaaS-Optik. Eigenes Admin-CSS nur für Layout-Lücken, Präfix `rc-admin-`, geladen nur auf Review-Captain-Seiten (`admin_enqueue_scripts` mit Screen-Prüfung).

| Bedarf | WordPress-Komponente |
|---|---|
| Seitenrahmen | `.wrap`, `h1.wp-heading-inline`, `.page-title-action`, `hr.wp-header-end` |
| Hinweise | `.notice.notice-{success,info,warning,error}`, `is-dismissible`; `settings_errors()` |
| Listen | `WP_List_Table` (Views, Bulk Actions, Suche, Pagination, Screen Options) |
| Formulare | Settings API, `table.form-table`, `p.description`, `.regular-text`, `.small-text` |
| Tabs | `h2.nav-tab-wrapper` + `a.nav-tab(.nav-tab-active)` |
| Karten/Kästen | `.postbox`, `.postbox-header`, `.inside`; KPI-Raster `rc-admin-grid` (CSS-Grid, ≥ 782 px mehrspaltig) |
| Buttons | `.button`, `.button-primary`, `.button-secondary`, `.button-link-delete` (destruktiv) |
| Icons | Dashicons (`dashicons-star-filled`, `-yes-alt`, `-warning`, `-update`) mit Textlabel |
| Status-Pills | `rc-admin-status rc-admin-status--{ok,warning,error,neutral}` – Text + Icon, nie nur Farbe |
| Aufklappen | `<details class="rc-admin-advanced">` |
| Farben | Admin-Farbschema respektieren: `var(--wp-admin-theme-color, #2271b1)`; Statusfarben WP-Palette (`#00a32a` ok, `#dba617` warn, `#d63638` error) nur als Rand/Icon, Text bleibt `#1d2327` |

Fokus: WP-Standard (`box-shadow: 0 0 0 2px var(--wp-admin-theme-color)`), nie entfernen. Admin-JS optional (Kopieren-Button, Vorschau), alle Aktionen ohne JS möglich.

## 2. Frontend: Klassen `rc-*`

BEM-artig, keine IDs zum Stylen, keine `!important`. Markup ist Template-überschreibbar (`templates/` → Theme `review-captain/…`); Klassen sind Teil des öffentlichen Vertrags (Änderung = Breaking Change).

Klassen in `assets/css/frontend.css` bzw. den Core-Templates (vollständig für v1; „nur Markup“ = im Markup vorhanden, aber ohne eigene Regel in `frontend.css`, als Hook für Themes):

| Klasse | Element | Quelle |
|---|---|---|
| `rc-widget`, `rc-widget--grid`, `rc-widget--list` | Container; Grid über `--rc-columns` (Inline-Style aus Attribut `columns`), mobil automatisch einspaltig (min. 16rem je Spalte) | `reviews-grid.php` |
| `rc-widget--reduced-motion` | gesetzt bei `display.reduced_motion = true`; setzt `--rc-transition: 0s` | `Renderer` |
| `rc-widget__items` | Raster/Liste der Karten | `reviews-grid.php` |
| `rc-summary`, `rc-summary__item` | Zusammenfassung je Plattform (Attribut `show_summary`) | `reviews-grid.php` |
| `rc-pagination`; `rc-pagination__prev`, `rc-pagination__next` (nur Markup) | Seitenlinks (Attribut `paginate`) | `reviews-grid.php` |
| `rc-empty` | Leerzustand | `reviews-grid.php` |
| `rc-card` | eine Karte (`<article>`, `data-rc-review` = UUID) | `review-card.php` |
| `rc-card--review`, `rc-card--testimonial`, `rc-card--external`, `rc-card--local` (nur Markup) | Modifikatoren nach `kind` und Herkunft | `Renderer::card_data()` |
| `rc-card__header`, `rc-card__title`, `rc-card__body`, `rc-card__footer`; `rc-card__meta` (nur Markup, zusammen mit `__footer`) | Teile | `review-card.php` |
| `rc-rating`, `rc-rating__value` | Bewertung (Sterne + Zahl „4 / 5“) | `review-card.php` |
| `rc-stars`, `rc-star`, `rc-star--full\|--half\|--empty`, `rc-star__outline`, `rc-star__fill` | Sterne (inline-SVG, Kontur + Füllung) | `review-card.php` |
| `rc-badge`, `rc-badge--testimonial` (Modifikator nur Markup) | Badge „Erfahrungsbericht“ | `Renderer` |
| `rc-verified` | Verifizierungslabel („Kauf verifiziert“) | `Renderer` |
| `rc-source`, `rc-source--{platform}` (Modifikator nur Markup); `rc-source__link` (nur Markup) | Quellenbadge (immer Text) und Link zur Quelle | `review-card.php` |
| `rc-author`, `rc-avatar`, `rc-date` (`rc-date` nur Markup) | Meta | `review-card.php` |
| `rc-cta`, `rc-cta__button`; `rc-cta--{platform}`, `rc-cta--missing` (nur Markup) | Bewertungsprofil-CTA `[review_captain_cta]` | `cta.php`, `Renderer` |
| `rc-form`, `rc-form__form`, `rc-form__field`, `rc-form__radio`, `rc-form__hint`, `rc-form__required`, `rc-form__error`, `rc-form__errors`, `rc-form__notice`, `rc-form__notice--success`, `rc-form__hp`, `rc-form__submit` | Einreichungsformular | `review-form.php` |
| `rc-form--disabled`, `rc-form__field--rating`, `rc-form__field--consent`, `rc-form__privacy`, `rc-form__actions` (nur Markup) | Formular-Modifikatoren/-Teile | `review-form.php` |
| `rc-embed-placeholder`; `rc-embed-placeholder__notice`, `__load`, `__fallback` (nur Markup) | Consent-Platzhalter für Anbieter-Embeds | `Security\EmbedPolicy` |
| `rc-visually-hidden` | Screenreader-Text | CSS |

## 3. Frontend-Tokens (CSS Custom Properties `--rc-*`)

Definiert auf `.rc-widget, .rc-form, .rc-cta` (nicht `:root`, um Themes nicht zu beeinflussen); `--rc-columns` nur auf `.rc-widget`. Defaults erben vom Theme, wo möglich (`theme.json`-Presets als Fallback-Kette). Werte exakt nach `assets/css/frontend.css`:

| Token | Default | Zweck |
|---|---|---|
| `--rc-font-family` | `inherit` | Schrift = Theme |
| `--rc-font-size` | `1rem` | Grundgröße |
| `--rc-font-size-sm` | `0.875rem` | Meta, Quelle, Datum |
| `--rc-line-height` | `1.5` | |
| `--rc-color-text` | `inherit` | Text |
| `--rc-color-muted` | `#50575e` | Meta-Text (≥ 4,5:1 auf Weiß) |
| `--rc-color-surface` | `var(--wp--preset--color--base, #fff)` | Kartenhintergrund |
| `--rc-color-border` | `#dcdcde` | Kartenrand (dekorativ) |
| `--rc-color-accent` | `var(--wp--preset--color--primary, #2271b1)` | Links, CTA |
| `--rc-color-accent-contrast` | `#fff` | Text auf Akzent |
| `--rc-color-star` | `#b8860b` | gefüllter Stern (≥ 3:1 auf Weiß, WCAG 1.4.11) |
| `--rc-color-star-empty` | `#8c8f94` | leerer Stern als **Kontur**, nicht nur Farbe |
| `--rc-color-focus` | `var(--rc-color-accent)` | Fokusrahmen |
| `--rc-color-error` | `#d63638` | Formularfehler (+ Icon/Text) |
| `--rc-color-success` | `#00703c` | Erfolgsmeldung des Formulars (+ Text) |
| `--rc-space-1…4` | `0.25rem / 0.5rem / 1rem / 1.5rem` | Abstände |
| `--rc-gap` | `var(--rc-space-3)` | Grid-Abstand |
| `--rc-columns` | `3` (auf `.rc-widget`; vom Template per Inline-Style aus Attribut `columns` 1–4 überschrieben) | Grid-Spalten; mobil automatisch 1 |
| `--rc-radius` | `4px` | Ecken |
| `--rc-star-size` | `1.1em` | Sterngröße |
| `--rc-card-padding` | `var(--rc-space-3)` | |
| `--rc-transition` | `150ms ease` | nur Hover/Fokus; 0 bei reduced motion |

Theme-Anpassung: `.rc-widget { --rc-color-star: #e0a800; }` im Theme-CSS bzw. über `theme.json` `styles.css`. Eine Admin-Oberfläche für Tokens ist in 1.0 nicht vorgesehen (OFFEN für Pro).

## 4. Bausteine

- **Typografie:** erbt vom Theme; Titel `font-weight: 600`, kein festes Font-Loading (keine externen Fonts).
- **Sterne:** inline-SVG je Stern (`rc-star__outline` + `rc-star__fill`) in `<span class="rc-stars" aria-hidden="true">`; der Container `rc-rating` trägt `role="img"` und `aria-label` mit der Originalskala; sichtbar zusätzlich Zahl „4,5 / 5“ (`rc-rating__value`, `aria-hidden`). In `forced-colors: active` werden Kontur und Füllung mit `CanvasText` gezeichnet. Halbe Sterne per Clip, leere Sterne als Kontur. Andere Skalen (z. B. 1–10) werden **nicht** in Sterne umgerechnet, sondern als Zahl mit Skala ausgegeben (Vorschlag; Sterne nur bei `rating_scale_max = 5`).
- **Quellenbadges:** immer Plattformname als Text (`rc-source`) + Link zur Quelle (`rc-source__link`, `rel="nofollow noopener noreferrer ugc"` aus `EmbedPolicy`; CTA ohne `ugc`; neues Fenster nur mit Hinweis „öffnet in neuem Tab“). Logos nur als lokal ausgelieferte, markenrechtlich freigegebene SVGs (RC-016); bis dahin nur Text.
- **Fokuszustände:** `:focus-visible { outline: 2px solid var(--rc-color-focus); outline-offset: 2px; }` für Links in `.rc-widget`/`.rc-cta`/`.rc-form`, Formularfelder, Buttons und den Laden-Button im Embed-Platzhalter.
- **Reduced motion:** `@media (prefers-reduced-motion: reduce)` setzt `--rc-transition: 0s` und `animation: none; transition: none` für alle Kindelemente von `.rc-widget`, `.rc-form`, `.rc-cta`; Einstellung `display.reduced_motion = true` (Default) ergänzt zusätzlich `rc-widget--reduced-motion` (`--rc-transition: 0s`). Keine Autoplay-Medien.
- **Layoutstabilität:** Avatare mit festen `width/height`, `loading="lazy"`; keine nachladenden Inhalte ohne reservierten Platz.
- **Kontrast:** WCAG 2.2 AA (Text 4,5:1, UI/Grafik 3:1); Status nie nur über Farbe.
- **Asset-Budget:** `frontend.css` ≤ 10 KB unkomprimiert (aktuell ca. 7,5 KB), nur eingereiht, wenn Widget/Block/Shortcode auf der Seite gerendert wird und `display.load_css = true`. Kein Frontend-JS im Core.

## 5. Editor-Handoff (Blöcke)

Blöcke `review-captain/reviews` und `review-captain/form`, dynamisch (serverseitig gerendert), Editorskripte ohne Build-Pflicht (`wp.element`, `wp.blocks`, `wp.components`, `wp.serverSideRender`).

Implementierte Panels (`blocks/reviews/editor.js`, `blocks/form/editor.js`):

| Panel (InspectorControls) | Steuerelement (`wp.components`) | Attribut |
|---|---|---|
| Datenquelle | `SelectControl` Plattform (eine oder alle), `SelectControl` Typ, `SelectControl` Mindestbewertung (keine/2–5), `RangeControl` Anzahl, `ToggleControl` „Blättern erlauben“ | `platform`, `kind`, `minRating`, `limit`, `paginate` |
| Kontext | `SelectControl` Kontexttyp, `TextControl` Kontext-ID | `contextType`, `contextId` |
| Layout | `SelectControl` Raster/Liste, `RangeControl` Spalten 1–4 (nur bei Raster) | `layout`, `columns` |
| Anzeige | `ToggleControl` Quelle bei Website-Bewertungen, `SelectControl` Datum/Autor („Wie in den Einstellungen“/Anzeigen/Ausblenden), `ToggleControl` Zusammenfassung je Plattform | `showSource`, `showDate`, `showAuthor`, `showSummary` |
| SEO-Hinweis | Text | – |
| Formular (`form`) | `SelectControl` „Worauf bezieht sich die Bewertung?“, `TextControl` Kontext-ID | `contextType`, `contextId` |

Ausblick (nicht in v1): Auswahl gespeicherter Widgets (`widgetId`), Mehrfachauswahl von Plattformen im Editor (`platforms`; der Shortcode akzeptiert bereits Kommalisten), Steuerung von Sternen/Avatar je Block sowie `allowTitle`/`privacyNotice` am Formularblock (in v1 globale Einstellungen `submissions.allow_title`, `privacy.privacy_notice`).

- Vorschau per `ServerSideRender` (REST `review-captain/v1`, Capability-geprüft); Platzhalter bei leerer Auswahl = Frontend-Leerzustand.
- Block-Supports: `align` (wide/full), `spacing.margin`; keine Farb-Supports in 1.0 (Tokens via Theme).
- Hinweis im Panel: „Externe Bewertungen werden angezeigt, erhalten aber kein Bewertungs-Markup.“
- Attributnamen = Contract [widget-v1.md](../contracts/widget-v1.md) (dort maßgeblich, inkl. Zuordnung camelCase ↔ snake_case); Shortcode `[review_captain v="1" …]` nutzt dieselben Namen in snake_case.

## Abnahme

Entspricht UX-007, wenn:

- [ ] Typografie, Farben/Tokens, Abstände, Sterne, Quellenbadges, Fokuszustände, reduced motion und Block-Steuerungen dokumentiert sind;
- [ ] Admin ausschließlich native WP-Komponenten nutzt und eigenes CSS das Präfix `rc-admin-` trägt;
- [ ] Frontend-Klassen `rc-` und Tokens `--rc-*` mit Defaults und Kontrastwerten festgelegt und mit dem Widget-Contract abgeglichen sind;
- [ ] die Kontrastwerte mit einem Prüfwerkzeug bestätigt sind (RC-137).
