# UX-007 – Designsystem und Editor-Handoff

**Status:** Entwurf – umgesetzt im [Widget-Prototyp](../prototypes/widgets.html); Abnahme durch Design offen  
**Stand:** 2026-11-16

## Prinzipien

1. **Theme first:** Widgets erben Schrift und Grundfarbe vom Theme; Review Captain setzt nur Tokens mit niedriger Spezifität (`:where()`), die Themes/Blockeditor überschreiben können.
2. **Admin = WordPress-nativ:** keine eigene Admin-Optik; WordPress-Komponenten und Admin-Farbschema.
3. **Quelle vor Optik:** Quelle, Skala und Datum sind nie per Option ausblendbar (externe Reviews).

## Design-Tokens (CSS Custom Properties, Präfix `--rc-`)

| Token | Hell | Dunkel (`prefers-color-scheme`) | Zweck |
|---|---|---|---|
| `--rc-text` | `#1d2327` | `#f0f0f1` | Fließtext |
| `--rc-text-muted` | `#50575e` | `#c3c4c7` | Meta, Quelle (Kontrast ≥ 4.5:1 auf `--rc-bg`) |
| `--rc-bg` | `#ffffff` | `#1d2327` | Kartenhintergrund |
| `--rc-surface` | `#f6f7f7` | `#2c3338` | Leisten, Medienplatzhalter |
| `--rc-border` | `#dcdcde` | `#50575e` | Rahmen |
| `--rc-accent` | `#2271b1` | `#72aee6` | Links, Buttons |
| `--rc-focus` | `#043959` | `#9ec2e6` | Fokusrahmen 3px |
| `--rc-star` | `#b26200` | `#f0b849` | gefüllte Sterne (≥ 3:1 Nicht-Text-Kontrast) |
| `--rc-star-empty` | `#c3c4c7` | `#646970` | leere Sterne |
| `--rc-radius` | `8px` | | Karten |
| `--rc-gap` / `--rc-pad` | `16px` | | Abstände (4er-Raster: 4/8/12/16/24/32) |
| `--rc-fs-sm` / `--rc-fs` / `--rc-fs-lg` | `.875rem` / `1rem` / `1.25rem` | | Typografie relativ zum Theme |

Block-Editor: Tokens werden über `block.json` `supports` (color, spacing, typography) und `theme.json`-Presets abbildbar; der Block schreibt Werte als Inline-Custom-Properties auf den Wrapper.

## Komponenten

- **Sterne:** Unicode-/SVG-Sterne mit `aria-hidden`, daneben immer Textalternative „4 von 5 Sternen“. Halbe Sterne nur als SVG mit Clip, nie nur Farbe. Externe Skalen (z. B. 1–10) werden in Originalskala als Zahl angezeigt; Sterne nur bei 5er-Skala.
- **Eigene vs. externe Sterne:** gleiche Komponente, aber eigene Reviews tragen Badge „auf dieser Website“ (gestrichelter Rahmen), externe ein Quellenlabel mit Link (RC-016).
- **Quellenbadge:** Textlabel; Logo nur, wenn RC-016 es erlaubt. `rel="nofollow noopener"` für externe Links, `target` nicht erzwingen.
- **Verifizierungsbadge:** „Kauf verifiziert“, „Teilnahme bestätigt“ – nur bei entsprechendem Status (RC-041); kein Badge für „ungeprüft“.
- **Fokuszustände:** `:focus-visible` 3px `--rc-focus`, Offset 2px; Trefferfläche ≥ 44×44 px für Buttons.
- **Bewegung:** keine Autoplay-Animation; Carousel nur manuell; `prefers-reduced-motion` deaktiviert Scroll-Animationen.
- **Medien:** Platzhalter mit Poster und Hinweis „lädt erst nach Klick“; Transkript-Link Pflicht.
- **Floating Badge:** links unten, mobil 72 px Abstand nach unten (Sticky-Leisten), schließbar (Sitzung), `aside` mit Label.

## Editor-Handoff (Gutenberg)

| Steuerung | Ort | Werte |
|---|---|---|
| Widget wählen/anlegen | Block-Platzhalter | Dropdown vorhandener Widgets + „Neu anlegen“ (öffnet Widget-Editor) |
| Layout | Inspector „Layout“ | Grid, Liste (Core); Carousel, Badge, Trustbar (Pro) |
| Anzahl, Spalten | Inspector „Layout“ | 1–24, Spalten auto/1–4 |
| Filter | Inspector „Inhalt“ | Quelle, Mindestbewertung (nur Anzeigefilter, keine Einladungslogik), Sprache, Kontext |
| Anzeige | Inspector „Anzeige“ | Datum, Autor-Modus, Avatar; Quelle **nicht** abschaltbar |
| Farben/Abstände | Block-Supports | Theme-Presets |
| Vorschau | Editor-Canvas | Server-Side-Render (ServerSideRender) |

## Offen / Freigabe

- Design prüft Kontrastwerte im Dunkelmodus mit echten Themes (Twenty Twenty-Five, Astra, Storefront).
