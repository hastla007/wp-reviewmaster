=== Review Captain ===
Contributors: captainsuite
Tags: reviews, testimonials, social proof, bewertungen, schema
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.5
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

== External services ==

Der kostenlose Core stellt keine Verbindung zu externen Diensten her: Er führt keine serverseitigen HTTP-Anfragen aus, lädt im Frontend keine Schriften, Skripte, Bilder oder Avatare von Drittanbietern und sendet keine Nutzungs- oder Telemetriedaten. Bewertungen externer Plattformen pflegst du im Core manuell; die Links zu Profilen und Originalbewertungen sind normale Links, die erst beim Anklicken durch den Besucher aufgerufen werden.

Für Erweiterungen enthält der Core eine Klick-zum-Laden-Richtlinie (feste Host-Liste: youtube-nocookie.com, player.vimeo.com, widget.trustpilot.com, widgets.trustedshops.com). Der Core selbst bettet darüber nichts ein. Nutzt ein Add-on sie, lädt erst der Klick des Besuchers den Inhalt vom jeweiligen Anbieter; vorher wird nichts übertragen.

Das separat lizenzierte Add-on Review Captain Pro verbindet sich mit dem Lizenzserver seines Anbieters und – nur nach Einrichtung durch dich – mit Plattform-APIs (Google, Trustpilot, Trusted Shops, Apple, Google Play). Diese Dienste beschreibt die readme.txt von Review Captain Pro.

== Frequently Asked Questions ==

= Werden Google- oder Trustpilot-Bewertungen automatisch importiert? =

Nicht im kostenlosen Core. Dort pflegst du ausgewählte Auszüge manuell mit Quelle und Link. Offizielle API-Verbindungen sind Teil von Review Captain Pro und setzen eigene Anbieterzugänge voraus.

= Erzeugt das Plugin Sterne in Google-Suchergebnissen? =

Review Captain gibt strukturierte Daten nur für zulässige, auf dieser Website gesammelte Bewertungen aus. Ob Google Rich Results anzeigt, entscheidet Google; es gibt keine Garantie.

= Was passiert bei der Deinstallation? =

Standardmäßig bleiben alle Daten erhalten. Nur wenn unter „Einstellungen › Datenschutz & Aufbewahrung“ die Löschoption aktiviert ist, werden Tabellen und Optionen entfernt.

== Screenshots ==

1. Dashboard mit Erststart-Checkliste und Moderationsübersicht.
2. Bewertungsliste mit Statusfiltern, Massenmoderation und Pflichtbegründung.
3. Bewertung bearbeiten: externe Bewertung mit Quelle, Originalskala und Link.
4. Quellen: Profile der Plattformen mit Profil- und Bewertungslink.
5. Widgets: Shortcode-Generator für `[review_captain v="1"]` und Hinweise zum Block.
6. Frontend: Bewertungsraster mit Plattformzusammenfassung und Quellenangabe je Karte.
7. Frontend: Bewertungsformular mit Einwilligung.
8. Einstellungen: Tabs von „Allgemein“ bis „Erweitert“, darunter „Einreichungen & Moderation“ und „Datenschutz & Aufbewahrung“.
9. Import/Export: CSV-Prüflauf mit Vorschau und Rollback.
10. Diagnose: Jobs, Connectoren und SEO-Status mit JSON-Export.

== Changelog ==

= 0.1.0-dev =
* Erste Entwicklungsversion des kostenlosen Core (noch nicht veröffentlicht).
* Mindestversionen angehoben (Entscheidung 16.11.2026): PHP 8.5; empfohlen MySQL 9.7 LTS oder MariaDB 12.3 LTS. Ältere Datenbanken lösen nur eine Warnung in Diagnose und `wp review-captain doctor` aus.
* Lokale Bewertungen und Erfahrungsberichte mit Moderation, Formular (Einwilligung, Honeypot, Rate-Limit), manuell gepflegte externe Bewertungen mit Pflicht-Quellenangabe.
* Block und Shortcode `[review_captain v="1"]`, serverseitig gerendert; neutraler Bewertungs-Link ohne Review-Gating.
* Strukturierte Daten nur für zulässige lokale Bewertungen, Yoast-SEO-Integration, `noindex` für Paginierungsparameter.
* CSV-Import mit Prüflauf, Dedupe und Rollback; CSV-Export mit Formelschutz; Konfigurationsexport ohne Secrets.
* Datenschutz-Export/-Löschung, Aufbewahrungsfristen, Deinstallation nur mit Opt-in, Multisite-Trennung.
* Hintergrundjobs mit Lease, Retry und Dead-Letter, WP-CLI `wp review-captain`, Verschlüsselung mit libsodium.
* Öffentliche Verträge für Provider und Connectoren, Demo-Connector und Connector-SDK.
* Sicherheitsprüfung November 2026: Rate-Limit für IPv6, Bereinigung von Import-Uploads, Rollen-Tab, Moderationskonflikte, keine E-Mail-Vorbefüllung.
* Vollständige Liste: CHANGELOG.md im Repository.

== Upgrade Notice ==

= 0.1.0-dev =
Entwicklungsversion zum Testen. Nicht auf Live-Websites einsetzen; Datenbankschema und Einstellungen können sich bis 1.0 noch ändern.
