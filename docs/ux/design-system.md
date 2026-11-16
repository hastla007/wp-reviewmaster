# Designsystem und Editor-Handoff (UX-007)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Gilt für Core; Pro-Widgets erweitern dieselben Tokens.

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

| Klasse | Element |
|---|---|
| `rc-widget`, `rc-widget--grid`, `rc-widget--list` | Container (Grid: CSS-Grid mit `--rc-columns`) |
| `rc-card`, `rc-card--testimonial`, `rc-card--external` | ein Review |
| `rc-card__header`, `__title`, `__body`, `__footer`, `__meta` | Teile |
| `rc-rating` | Bewertung (Sterne + Text) |
| `rc-stars`, `rc-star`, `rc-star--full\|--half\|--empty` | Sterne (inline-SVG) |
| `rc-source`, `rc-source--{platform}` | Quellenbadge (immer Text, optional Logo) |
| `rc-author`, `rc-avatar`, `rc-date` | Meta |
| `rc-verified` | Verifizierungslabel („Kauf verifiziert“) |
| `rc-empty` | Leerzustand |
| `rc-cta`, `rc-cta__button` | Bewertungsprofil-CTA |
| `rc-form`, `rc-form__field`, `rc-form__error`, `rc-form__notice` | Einreichungsformular |
| `rc-embed-placeholder` | Consent-Platzhalter für Anbieter-Embeds |
| `rc-visually-hidden` | Screenreader-Text |

## 3. Frontend-Tokens (CSS Custom Properties `--rc-*`)

Definiert auf `.rc-widget, .rc-form, .rc-cta` (nicht `:root`, um Themes nicht zu beeinflussen). Defaults erben vom Theme, wo möglich (`theme.json`-Presets als Fallback-Kette).

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
| `--rc-space-1…4` | `0.25rem / 0.5rem / 1rem / 1.5rem` | Abstände |
| `--rc-gap` | `var(--rc-space-3)` | Grid-Abstand |
| `--rc-columns` | `3` | Grid-Spalten (Widget-Option); mobil automatisch 1 |
| `--rc-radius` | `4px` | Ecken |
| `--rc-star-size` | `1.1em` | Sterngröße |
| `--rc-card-padding` | `var(--rc-space-3)` | |
| `--rc-transition` | `150ms ease` | nur Hover/Fokus; 0 bei reduced motion |

Theme-Anpassung: `.rc-widget { --rc-color-star: #e0a800; }` im Theme-CSS bzw. über `theme.json` `styles.css`. Eine Admin-Oberfläche für Tokens ist in 1.0 nicht vorgesehen (OFFEN für Pro).

## 4. Bausteine

- **Typografie:** erbt vom Theme; Titel `font-weight: 600`, kein festes Font-Loading (keine externen Fonts).
- **Sterne:** inline-SVG je Stern, `aria-hidden="true"`; daneben `<span class="rc-visually-hidden">4,5 von 5 Sternen</span>`; sichtbar zusätzlich Zahl „4,5 / 5“ (Originalskala). Halbe Sterne per Clip, leere Sterne als Kontur. Andere Skalen (z. B. 1–10) werden **nicht** in Sterne umgerechnet, sondern als Zahl mit Skala ausgegeben (Vorschlag; Sterne nur bei `rating_scale_max = 5`).
- **Quellenbadges:** immer Plattformname als Text + Link „auf {Plattform} ansehen“ (`rel="noopener nofollow"`, neues Fenster nur mit Hinweis). Logos nur als lokal ausgelieferte, markenrechtlich freigegebene SVGs (RC-016); bis dahin nur Text.
- **Fokuszustände:** `:focus-visible { outline: 2px solid var(--rc-color-focus); outline-offset: 2px; }` für Links, Buttons, Formularfelder, Karussell-Steuerung.
- **Reduced motion:** `@media (prefers-reduced-motion: reduce) { --rc-transition: 0s; animation: none; }`; Einstellung `display.reduced_motion = true` deaktiviert zusätzlich Auto-Rotation/Animationen generell. Keine Autoplay-Medien.
- **Layoutstabilität:** Avatare mit festen `width/height`, `loading="lazy"`; keine nachladenden Inhalte ohne reservierten Platz.
- **Kontrast:** WCAG 2.2 AA (Text 4,5:1, UI/Grafik 3:1); Status nie nur über Farbe.
- **Asset-Budget:** `frontend.css` ≤ 10 KB unkomprimiert (Vorschlag), nur eingereiht, wenn Widget/Block/Shortcode auf der Seite gerendert wird und `display.load_css = true`. Kein Frontend-JS im Core.

## 5. Editor-Handoff (Blöcke)

Blöcke `review-captain/reviews` und `review-captain/form`, dynamisch (serverseitig gerendert), Editorskripte ohne Build-Pflicht (`wp.element`, `wp.blocks`, `wp.components`, `wp.serverSideRender`).

| Panel (InspectorControls) | Steuerelement (`wp.components`) | Attribut |
|---|---|---|
| Widget | `SelectControl` gespeicherte Widgets / „Ad-hoc“ | `widgetId` |
| Quelle & Auswahl (ad-hoc) | `CheckboxControl` je Plattform, `SelectControl` Mindestbewertung, `RangeControl` Anzahl | `platforms`, `minRating`, `limit` |
| Layout | `SelectControl` Grid/Liste, `RangeControl` Spalten 1–4 | `layout`, `columns` |
| Anzeige | `ToggleControl` Sterne/Datum/Autor/Avatar (Standard = Einstellungen, Hinweis „aus Einstellungen“) | `show*` (nur gesetzt, wenn abweichend) |
| Formular (`form`) | `SelectControl` Kontexttyp, `TextControl` Kontext-ID, `ToggleControl` Titel, `TextareaControl` Datenschutztext | `contextType`, `contextId`, `allowTitle`, `privacyNotice` |

- Vorschau per `ServerSideRender` (REST `review-captain/v1`, Capability-geprüft); Platzhalter bei leerer Auswahl = Frontend-Leerzustand.
- Block-Supports: `align` (wide/full), `spacing.margin`; keine Farb-Supports in 1.0 (Tokens via Theme).
- Hinweis im Panel: „Externe Bewertungen werden angezeigt, erhalten aber kein Bewertungs-Markup.“
- Attributnamen = Contract `docs/contracts/widget-v1.md` (dort maßgeblich); Shortcode `[review_captain v="1" …]` nutzt dieselben Namen in snake_case.

## Abnahme

Entspricht UX-007, wenn:

- [ ] Typografie, Farben/Tokens, Abstände, Sterne, Quellenbadges, Fokuszustände, reduced motion und Block-Steuerungen dokumentiert sind;
- [ ] Admin ausschließlich native WP-Komponenten nutzt und eigenes CSS das Präfix `rc-admin-` trägt;
- [ ] Frontend-Klassen `rc-` und Tokens `--rc-*` mit Defaults und Kontrastwerten festgelegt und mit dem Widget-Contract abgeglichen sind;
- [ ] die Kontrastwerte mit einem Prüfwerkzeug bestätigt sind (RC-137).
