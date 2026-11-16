# ADR-0008: Plattformzugänge im MVP über Betreiber-Credentials, Captain-OAuth-App später

- **Status:** Vorgeschlagen
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
