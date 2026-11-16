=== Review Captain ===
Contributors: captainsuite
Tags: reviews, testimonials, social proof, bewertungen, schema
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0-dev
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Authentische, sauber attribuierte Kundenbewertungen und Erfahrungsberichte in WordPress – ohne Scraping, ohne Lizenzpflicht.

== Description ==

Review Captain sammelt Bewertungen auf deiner Website und zeigt sie zusammen mit manuell gepflegten Auszügen externer Plattformen an. Jede externe Bewertung behält Herkunft, Originalskala und Link zur Quelle.

* Lokale Bewertungsformulare mit Moderation, Einwilligung, Honeypot und Rate-Limit
* Manuell kuratierte externe Bewertungen (Google, Trustpilot, Trusted Shops, App Store, Google Play) mit Pflicht-Quellenangabe
* Website-eigene Text-Erfahrungsberichte, klar von Plattformbewertungen getrennt
* Block „Review Captain: Bewertungen“ und Shortcode `[review_captain v="1"]`, serverseitig gerendert, ohne Pflicht-JavaScript
* Neutraler „Bewerte uns“-Link zu deinen Profilen – ohne Review-Gating
* Strukturierte Daten nur für zulässige, auf dieser Website gesammelte Bewertungen; Yoast-SEO-kompatibel
* CSV-Import mit Prüflauf und Rollback, CSV-Export mit Formelschutz
* WordPress-Datenschutz-Export/-Löschung, Aufbewahrungsfristen, Multisite-Trennung
* Öffentlicher Connector-Vertrag für eigene Integrationen

Offizielle API-Synchronisierung, automatische Bewertungsanfragen und erweiterte Widgets bietet das separat lizenzierte Add-on Review Captain Pro. Der Core funktioniert vollständig ohne Pro, WooCommerce oder externe Konten.

== Installation ==

1. Plugin hochladen und aktivieren.
2. „Review Captain › Dashboard“ öffnen und der Erststart-Checkliste folgen.
3. Für zuverlässige Hintergrundjobs einen System-Cron einrichten: `wp review-captain jobs run` alle 5 Minuten.

== Frequently Asked Questions ==

= Werden Google- oder Trustpilot-Bewertungen automatisch importiert? =

Nicht im kostenlosen Core. Dort pflegst du ausgewählte Auszüge manuell mit Quelle und Link. Offizielle API-Verbindungen sind Teil von Review Captain Pro und setzen eigene Anbieterzugänge voraus.

= Erzeugt das Plugin Sterne in Google-Suchergebnissen? =

Review Captain gibt strukturierte Daten nur für zulässige, auf dieser Website gesammelte Bewertungen aus. Ob Google Rich Results anzeigt, entscheidet Google; es gibt keine Garantie.

= Was passiert bei der Deinstallation? =

Standardmäßig bleiben alle Daten erhalten. Nur wenn unter „Einstellungen › Datenschutz & Aufbewahrung“ die Löschoption aktiviert ist, werden Tabellen und Optionen entfernt.

== Changelog ==

= 0.1.0-dev =
* Erste Entwicklungsversion des kostenlosen Core.
