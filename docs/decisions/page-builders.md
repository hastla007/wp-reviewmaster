# Entscheidungsvorlage: Page-Builder-Unterstützung in 1.0 (RC-119)

Status: **Entschieden** (Inhaberin, 16.11.2026). Entscheidung durch: Produkt (OFFEN). Bei Annahme ggf. als ADR unter `docs/adr/` übernehmen.

## Frage

Sollen Review Captain Core/Pro 1.0 native Module für Page Builder (z. B. Elementor, Divi, Beaver Builder, WPBakery, Bricks) mitbringen?

## Vorschlag

**Nein.** In 1.0 werden alle Page Builder über den versionierten **Shortcode-Fallback** unterstützt; native Module folgen frühestens in 1.x nach Pilotbedarf.

- Shortcodes `[review_captain v="1" id="…"]` und `[review_captain_form v="1"]` funktionieren in jedem Builder, der ein Shortcode-/Text-Element bietet.
- Gutenberg bleibt nativ (Blöcke `review-captain/reviews`, `review-captain/form`).
- Die Widget-Liste zeigt zu jedem Widget den kopierbaren Shortcode; die Doku enthält je verbreitetem Builder einen kurzen Hinweis, welches Element zu verwenden ist.

## Begründung

| Kriterium | Shortcode-Fallback | Native Module |
|---|---|---|
| Aufwand 1.0 | gering (ohnehin nötig für Classic Themes) | hoch: je Builder eigene API, Editor-Vorschau, Updates |
| Wartung | ein Vertrag (`widget-v1`) | Builder-APIs ändern sich häufig; Testmatrix wächst je Builder × Version |
| Kompatibilität | breit | nur unterstützte Builder |
| Bedienkomfort | mittel: Konfiguration im Widget-Editor, Vorschau erst im Frontend bzw. Builder-Shortcode-Vorschau | hoch: Optionen direkt im Builder |
| Sicherheit/Performance | SSR, kein Extra-JS | Builder-spezifisches JS/CSS |
| Release-Risiko | niedrig | Release-Verzögerung durch Builder-QA |

## Konsequenzen

- RC-006/RC-133-Supportmatrix führt Page Builder als „unterstützt über Shortcode“ mit 2–3 geprüften Buildern (Auswahl OFFEN, Vorschlag: Elementor, Divi, Beaver Builder).
- Abnahmetest je geprüftem Builder: Shortcode rendert serverseitig, CSS wird geladen, keine doppelten Ausgaben in Builder-Vorschau, keine JS-Fehler.
- Shortcode-Attribute sind stabiler öffentlicher Vertrag (Änderung nur mit neuer `v`).
- Native Module (Pro) bleiben Kandidat für M7 mit Kriterium: ≥ X % der Pilot-/Supportfälle nutzen denselben Builder (X OFFEN).

## Alternativen

1. **Ein natives Elementor-Modul in Pro 1.0** – höchste Verbreitung, aber zusätzlicher Wartungsaufwand und Ungleichbehandlung anderer Builder.
2. **Generischer „Widget“-Shortcode-Wrapper je Builder** (nur Auswahl der Widget-ID im Builder-UI) – kleinerer Mittelweg für 1.x.

## Abnahme

Die Vorlage gilt als entschieden, wenn Produkt Vorschlag oder Alternative gewählt, die Liste der geprüften Builder festgelegt und RC-119 im TODO entsprechend aktualisiert hat.

## Entscheidung der Inhaberin (16.11.2026)

Abweichend vom Vorschlag: **natives Elementor-Widget bereits in 1.0** (neue Aufgabe **RC-119A**). Alle anderen Builder (Divi, Beaver Builder, Bricks, WPBakery) bleiben in 1.0 beim Shortcode-Fallback. Das Elementor-Widget ist eine dünne Hülle um den versionierten Shortcode-/Widget-Vertrag (`widget-v1`, SSR), lädt nur bei aktivem Elementor und kein eigenes Frontend-JS.

**Umsetzung (16.11.2026):** `src/Integrations/Elementor/ElementorModule.php` (Registrierung über `elementor/widgets/register`, eigene Panel-Kategorie, Abbildung Steuerelemente → Shortcode-Attribute) und `ReviewsWidget.php` (Steuerelemente wie die Shortcode-Attribute; Ausgabe über denselben Renderer). Tests: `tests/Unit/Integrations/ElementorModuleTest.php`. Offen: Smoke-Test mit echtem Elementor (Editor-Vorschau, Frontend, Caching).
