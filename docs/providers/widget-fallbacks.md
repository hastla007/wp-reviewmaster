# Offizielle Widget-Fallbacks: TrustBox und Trustbadge (RC-073, RC-077)

Status: Entwurf, Stand 2026-11-16. Anbieterregeln **nicht verifiziert** (siehe [matrix.md](matrix.md)).

> **Keine Garantie.** Review Captain bettet nur den offiziellen Code des Anbieters ein. Inhalt, Verfügbarkeit, Tracking-Verhalten, Darstellung und Tarifzulässigkeit liegen beim Anbieter und können sich jederzeit ändern. Review Captain prüft nicht, ob Ihr Tarif das Widget erlaubt, und übernimmt keine Haftung für Inhalte oder Datenverarbeitung des Anbieters.

## 1. Grundregeln

1. **Kein Laden vor Einwilligung.** Anbieter-Skripte/iframes werden nie im initialen HTML ausgeliefert. Statt dessen rendert der Server einen Platzhalter (`.rc-embed-placeholder`) mit Anbietername, Kurzhinweis zur Datenübertragung, Link zur Datenschutzerklärung und Button „Inhalt von {Anbieter} laden“.
2. **Aktivierung zweistufig:** (a) Betreiber aktiviert das Embed am Quellprofil (Default aus), (b) Besucher willigt ein – entweder per Klick auf den Platzhalter oder über eine angebundene Consent-Lösung.
3. **Consent-Anbindung (Vorschlag):** WP Consent API (`wp_has_consent( 'marketing' )` bzw. konfigurierbare Kategorie), falls installiert; sonst nur Klick-zum-Laden. Kein eigener Cookie-Banner im Core. Die Einwilligung per Klick gilt nur für die aktuelle Seitenansicht (kein Cookie), Merken nur über die Consent-Lösung.
4. **Nur offizieller Code, keine Manipulation.** Der Betreiber fügt kein freies HTML ein. Das Plugin erzeugt den Embed aus **strukturierten Feldern** (z. B. Business-Unit-ID, Template-ID, Sprache, Höhe bzw. Trusted-Shops-ID/Channel) und einer festen Skript-URL-Allowlist des Anbieters. Grund: kein beliebiges Skript im Frontend (XSS), nachvollziehbare Ausgabe.
5. **Getrennt von synchronisierten Daten.** Embed-Inhalte werden nicht gespeichert, nicht dedupliziert, nicht in Summen eingerechnet und erhalten kein Schema-Markup.
6. **Immer ein Ausweg:** neben dem Platzhalter steht ein normaler Profil-Link (funktioniert ohne Skript und ohne Consent).
7. **Asset nur bei Bedarf:** ein kleines Loader-Skript (≤ 2 KB, Vorschlag) wird nur auf Seiten mit Platzhalter eingereiht; der Core bleibt ansonsten ohne Frontend-JS. Ohne JS bleibt der Profil-Link.
8. **Datenschutztext:** der Vorschlagstext für die Datenschutzerklärung (WP-Datenschutzleitfaden via `wp_add_privacy_policy_content`) nennt den Anbieter, sobald ein Embed aktiviert ist.

## 2. RC-073 – Trustpilot TrustBox

| Feld am Profil | Validierung |
|---|---|
| Embed aktiv | bool, Default aus |
| Business-Unit-ID | `^[a-f0-9]{24}$` (Annahme, ungeprüft) |
| Template-ID | aus Liste der vom Betreiber genutzten TrustBox-Vorlagen, Freitext alphanumerisch |
| Sprache/Locale | Locale-Muster |
| Höhe, Theme | Zahl px / `light\|dark` |
| Profil-URL | `https://`, Host-Allowlist `*.trustpilot.com` |

Ausgabe nach Consent: offizielles Bootstrap-Skript von der Trustpilot-Domain (exakte URL aus Anbieterdoku, Allowlist) + `div.trustpilot-widget` mit `data-*`-Attributen. Platzhalter vorher: „Trustpilot-Bewertungen anzeigen – dabei werden Daten an Trustpilot übertragen.“
Hinweis im Admin: „Ob TrustBox-Vorlagen für Ihren Trustpilot-Tarif verfügbar sind, entscheidet Trustpilot.“

## 3. RC-077 – Trusted Shops Trustbadge / eTrusted-Widgets

| Feld am Profil | Validierung |
|---|---|
| Embed-Typ | `trustbadge` \| `review_widget` |
| Embed aktiv | bool, Default aus |
| Trusted-Shops-ID (TSID) bzw. Channel-/Widget-ID | alphanumerisch, Länge zu prüfen |
| Position Trustbadge | Anbieteroptionen, plus Hinweis auf Überlagerung (siehe schwebendes Badge, konzept.md §9) |
| Profil-URL | `https://`, Host-Allowlist Trusted-Shops/eTrusted-Domains |

- **Trennbar:** Trustbadge (siteweit schwebend) und Review-Widget (an Stelle des Blocks/Shortcodes) sind getrennte Schalter; Deaktivieren entfernt die Ausgabe vollständig.
- Der Trustbadge ist ein globales Element: Ausgabe nur auf ausgewählten Seiten/Typen (Vorschlag: Seitenliste oder „alle außer Kasse“), nie im Admin, nie in Vorschau/Feeds.
- Käuferschutz-/Siegel-Aussagen ausschließlich aus dem Anbieter-Embed – Review Captain erzeugt selbst keine Siegelgrafik (Markenregeln RC-016).

## 4. Tests

- [ ] Ohne Consent: kein Request an Anbieter-Domains (Netzwerkprotokoll im Browser).
- [ ] Mit Consent/Klick: nur Allowlist-Domains geladen.
- [ ] Ungültige IDs: kein Embed, Admin-Fehler, Frontend nur Profil-Link.
- [ ] Ohne JS: Profil-Link sichtbar, kein Layoutfehler.
- [ ] Deaktivieren: keine Reste im HTML; Datenschutz-Vorschlagstext aktualisiert.

## Abnahme

Entspricht RC-073 und RC-077, wenn:

- [ ] der offizielle Embed-Code ohne Einwilligung nicht automatisch geladen wird (Test „kein Request vor Consent“);
- [ ] TrustBox und Trustbadge/Review-Widget am Profil konfigurierbar, getrennt abschaltbar und datenschutzbewusst sind;
- [ ] der Haftungs-/Garantie-Hinweis im Admin sichtbar ist;
- [ ] Skript-URLs, ID-Formate und Tarifbedingungen gegen die aktuelle Anbieterdoku geprüft sind.
