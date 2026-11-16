# ADR-0006: Deutsch als Quellsprache der Oberflächentexte (Arbeitsannahme)

- **Status:** Vorgeschlagen
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Core-Implementierung; Entscheidung durch Produktverantwortung offen
- **Betrifft:** RC-034, RC-138, RC-143; `languages/`, alle `__()`-Aufrufe
- **Ersetzt:** –

## Kontext

Der erste Core-Stand verwendet deutsche Msgids (`__( 'Freigegeben', 'review-captain' )`). Das Konzept nennt einen de_DE/de_AT-Übersetzungsworkflow. Das WordPress.org-Plugin-Verzeichnis und translate.wordpress.org gehen von **englischen** Quelltexten aus. Die Verteilung über das öffentliche Verzeichnis ist noch nicht entschieden (RC-007, RC-143).

## Betrachtete Optionen

1. **Deutsche Msgids beibehalten.** Schnellster Weg für den deutschsprachigen Markt. de_DE ist ohne Übersetzung vollständig. Für andere Sprachen braucht es eine en_US-„Übersetzung“. Für WordPress.org ungeeignet.
2. **Auf englische Msgids umstellen, de_DE/de_AT als Übersetzung.** Entspricht dem WordPress-Standard und ist Voraussetzung für WordPress.org. Kostet eine einmalige Umstellung aller Strings, die skriptgestützt anhand der POT-Datei machbar ist.

## Entscheidung

Bis zur Produktentscheidung bleibt es bei **Option 1**. Alle Strings laufen trotzdem über gettext mit der Textdomain `review-captain`. Damit ist die spätere Umstellung auf Option 2 ein mechanischer Schritt: POT erzeugen, Msgids ersetzen und eine de_DE-PO aus den bisherigen Texten generieren.

## Konsequenzen

- Positiv: Der deutschsprachige Pilot braucht keine Übersetzungsdatei.
- Negativ: Eine Veröffentlichung auf WordPress.org verlangt vorher Option 2.
- Folgearbeiten: Entscheidung bei RC-007/RC-143. Bei Option 2 prüft `bin/check-plugin-meta.php` zusätzlich, dass die Msgids ASCII sind bzw. englisch.

## Abnahme

- [ ] Produktentscheidung zur Distribution (WordPress.org ja/nein) dokumentiert
- [ ] Bei Option 2: alle Msgids englisch, `languages/review-captain-de_DE.po` und `-de_AT.po` vollständig

## Referenzen

- https://developer.wordpress.org/plugins/internationalization/
- `bin/make-pot.php`, `bin/make-pot.sh`
