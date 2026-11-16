# ADR-0008: Plattformzugänge im MVP über Betreiber-Credentials, Captain-OAuth-App später

- **Status:** **Angenommen** (Inhaberin, 16.11.2026): Betreiber-Credentials für alle Anbieter, für Google mit eigenem Google-Cloud-Projekt des Kunden; Captain-App zurückgestellt ([captain-google-app.md](../decisions/captain-google-app.md))
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Claude (Vorschlag) / Produkt + Core-Maintainer (Entscheidung offen)
- **Betrifft:** RC-026, RC-065, RC-070, RC-074, RC-078, RC-080; [provider-auth.md](../architecture/provider-auth.md) §8, [secrets.md](../architecture/secrets.md)
- **Ersetzt:** –

## Kontext

`provider-auth.md` §8 vergleicht zwei Wege für OAuth-Anbieter (vor allem Google Business Profile): eine zentral registrierte Captain-OAuth-App oder Zugangsdaten des Betreibers (eigenes Cloud-Projekt bzw. eigener API-Client). Das Konzept schließt einen zentralen Review-Proxy im MVP aus (Konzept §7.9). Review Captain Pro (captain-pro, Branch `claude/review-captain-pro`) setzt die Adapter bereits mit Betreiber-Credentials um (`src/Platforms/Auth/`). Ein echter Anbieterzugang lag bei der Umsetzung nicht vor (RC-009 … RC-013 offen).

## Betrachtete Optionen

1. **Captain-OAuth-App** – Ein-Klick-Einrichtung. Braucht aber eine zentrale Redirect-/Code-Tausch-Station unter einer Captain-Domain (Betrieb, AVV, Verfügbarkeit), Google-App-Verifizierung und API-Zugangsantrag durch Captain. Das API-Kontingent teilen sich alle Kunden, und ein Leak trifft alle.
2. **Betreiber-Credential** – höherer Einrichtungsaufwand, aber kein zentraler Dienst, eigenes Kontingent je Betreiber, Vorfälle isoliert. Passt zur Regel „kein Proxy“.
3. **Hybrid** – Betreiber-Credential als Standard, Captain-App später als optionales Komfortmerkmal.

## Entscheidung (Vorschlag)

Option 3: Im MVP gelten **ausschließlich Betreiber-Credentials** für alle fünf Anbieter. Google bekommt eine geführte Schritt-für-Schritt-Anleitung mit Prüfassistent (OPS-002). Eine Captain-OAuth-App für Google Business Profile wird nur über ein eigenes späteres ADR eingeführt. Bedingungen: Broker ohne Token-Speicherung, signierte Site↔Broker-Kommunikation, AVV, Kontingentkonzept, Google-Verifizierung.

## Konsequenzen

- Positiv: kein zentraler Dienst vor dem Verkaufsstart; entspricht dem gebauten Pro-Code; kein geteiltes Kontingent.
- Negativ: Einrichtung für Google ist aufwendig, mehr Supportfälle beim Onboarding; Ein-Klick-Versprechen nicht möglich.
- Migration: Eine spätere Captain-App kommt als zusätzlicher Verbindungsweg dazu, bestehende Verbindungen bleiben.
- Folgearbeiten: OPS-002 (Setup-Anleitung je Anbieter), Prüfassistent in der Quellen-Seite, Verifikation der minimalen Rollen bei App Store Connect/Play Console (RC-012/RC-013).

## Abnahme

- [ ] Produktverantwortung und Core-Maintainer stimmen zu (Status → Angenommen).
- [ ] `provider-auth.md` §8 verweist auf dieses ADR, Status dort „entschieden“.
- [ ] Verkaufstexte versprechen keine Ein-Klick-Verbindung für Google.

## Referenzen

- [provider-auth.md](../architecture/provider-auth.md) §8; Konzept §7.9 „Authentifizierung und Eigentümerschaft der Verbindungen“
- captain-pro `addons/review-captain-pro/src/Platforms/Auth/`

## Entscheidung der Inhaberin (16.11.2026)

Der Vorschlag (nur Betreiber-Credentials im MVP) wird **nicht** angenommen. Für **Google Business Profile** gibt es bereits in 1.0 eine **Captain-OAuth-App** („Verbinden“-Knopf ohne eigene Google-Cloud-Einrichtung beim Kunden). Für die übrigen Anbieter (App Store Connect, Google Play, Trustpilot, eTrusted) bleiben Betreiber-Zugänge, weil deren APIs keine Fremd-App vorsehen.

Pflichtbedingungen aus diesem ADR gelten weiter und sind Release-Voraussetzung für die App (neue Aufgabe **RC-026A**): Broker ohne Token-Speicherung (Tokens nur auf der Kundenseite, verschlüsselt), signierte Site↔Broker-Kommunikation, Auftragsverarbeitungsvertrag, Kontingentkonzept, abgeschlossene Google-OAuth-Verifizierung (inkl. Prüfung sensibler Scopes), Datenschutzerklärung des Brokers. Betreiber-Credentials bleiben als Alternative verfügbar.

Folgen: RC-002 (Google in 1.0 nativ) hängt zusätzlich an der Google-Verifizierung der App; Zeitplan beachten (Verifizierung kann Wochen dauern).

## Präzisierung (16.11.2026, später am Tag)

Die Inhaberin stellt klar: In 1.0 sollen Google-Bewertungen nur **zum Anzeigen** per API geholt werden. Deshalb gilt für 1.0 **Google Places API** (Place Details, API-Schlüssel des Betreibers, laut Google-Doku höchstens 5 Bewertungen je Ort, Pflicht zur Quellenangabe, Vorgaben zum Zwischenspeichern vor Umsetzung prüfen) – ohne Broker-Server, ohne Google-Verifizierung, ohne AVV. Die **Captain-OAuth-App (RC-026A)** und der vollständige Abruf über die Business Profile API werden **nach 1.0** umgesetzt, falls alle Bewertungen oder Antworten gebraucht werden. Die übrigen Anbieter bleiben bei Betreiber-Zugängen. Die Abschnitte oben zur Captain-App gelten damit erst für die spätere Version.

## Endgültige Entscheidung (16.11.2026)

Nach Rückfrage gilt der ursprüngliche Vorschlag: **jeder Kunde legt sein eigenes Google-Cloud-Projekt an** und beantragt die Freischaltung der Business Profile APIs selbst (geführte Anleitung: [platform-setup.md](../user/platform-setup.md#google-business-profile)). Die beiden Zwischenstände oben (Captain-App in 1.0; Places-API in 1.0) sind damit **überholt**. Die Captain-App bleibt als späterer Prüfpunkt RC-026A mit allen Infos in [captain-google-app.md](../decisions/captain-google-app.md).
