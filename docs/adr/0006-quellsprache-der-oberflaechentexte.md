# ADR-0006: Quellsprache der Oberflächentexte – Englisch, Deutsch als Übersetzung

- **Status:** Angenommen (Option 2), umgesetzt 23.11.2026 mit SET-RC-14
- **Datum:** 2026-11-16, Entscheidung S4/E1/E2 der Inhaberin
- **Autor:in / Entscheider:** Core-Implementierung; Entscheidung durch die Inhaberin (S4)
- **Betrifft:** RC-034, RC-138, RC-143; `languages/`, alle `__()`-Aufrufe
- **Ersetzt:** –

## Kontext

Der erste Core-Stand verwendet deutsche Msgids (`__( 'Freigegeben', 'review-captain' )`). Das Konzept nennt einen de_DE/de_AT-Übersetzungsworkflow. Das WordPress.org-Plugin-Verzeichnis und translate.wordpress.org gehen von **englischen** Quelltexten aus. Die Verteilung über das öffentliche Verzeichnis ist noch nicht entschieden (RC-007, RC-143).

## Betrachtete Optionen

1. **Deutsche Msgids beibehalten.** Schnellster Weg für den deutschsprachigen Markt. de_DE ist ohne Übersetzung vollständig. Für andere Sprachen braucht es eine en_US-„Übersetzung“. Für WordPress.org ungeeignet.
2. **Auf englische Msgids umstellen, de_DE/de_AT als Übersetzung.** Entspricht dem WordPress-Standard und ist Voraussetzung für WordPress.org. Kostet eine einmalige Umstellung aller Strings, die skriptgestützt anhand der POT-Datei machbar ist.

## Entscheidung

**Option 2** (Entscheidung S4 der Inhaberin, umgesetzt in SET-RC-14): alle Msgids sind englisch. Übersetzungen: `de_DE` mit „du“, `de_DE_formal` mit „Sie“ (keine Mischung in einer Datei, auch das öffentliche Formular folgt der Sprachdatei, E2); `de_AT` (du) und `de_CH` (Sie, „ss“ statt „ß“) erzeugt `bin/derive-de-variants.php`. `en_US` entfällt. `php bin/compile-translations.php --check` prüft mit `bin/check-i18n-language.php`, dass keine deutschen Quelltexte zurückkommen und jede Datei bei ihrer Anrede bleibt.

Ursprüngliche Arbeitsannahme bis 23.11.2026: **Option 1**. Alle Strings laufen trotzdem über gettext mit der Textdomain `review-captain`. Damit ist die spätere Umstellung auf Option 2 ein mechanischer Schritt: POT erzeugen, Msgids ersetzen und eine de_DE-PO aus den bisherigen Texten generieren.

## Konsequenzen

- Positiv: Englische Msgids wie in allen anderen Captain-Plugins; WordPress.org und translate.wordpress.org möglich; du und Sie sind echte, getrennte Übersetzungen.
- Negativ: Deutschsprachige Websites brauchen die mitgelieferten `.mo`/`.json`-Dateien; jeder neue Text muss in `de_DE` und `de_DE_formal` übersetzt werden (`compile-translations --check` schlägt sonst fehl).
- (Zu Option 1, bis 23.11.2026: Der Pilot brauchte keine Übersetzungsdatei, eine Veröffentlichung auf WordPress.org hätte Option 2 verlangt.)

## Abnahme

- [ ] Produktentscheidung zur Distribution (WordPress.org ja/nein) dokumentiert
- [x] Bei Option 2: alle Msgids englisch, `languages/review-captain-de_DE.po` und `-de_AT.po` vollständig (SET-RC-14, 23.11.2026; dazu `de_DE_formal` und `de_CH`)

## Referenzen

- https://developer.wordpress.org/plugins/internationalization/
- `bin/make-pot.php`, `bin/make-pot.sh`
