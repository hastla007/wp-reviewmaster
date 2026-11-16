# RC-009 bis RC-013 – Anbieterzugänge vorbereiten

**Status:** Checklisten bereit – Freischaltungen sind externe Aktionen und noch nicht erfolgt  
**Stand:** 2026-11-16  
**Quellen:** [RC-008 Provider-Matrix](rc-008-provider-matrix.md), [Konzept §4](../../konzept.md)

Jede Checkliste endet mit einem **Nachweis**, der im Abschnitt „Ergebnisprotokoll“ eingetragen wird (Datum, Verantwortliche Person, Ablageort der Zugangsdaten im Passwortmanager – niemals Secrets in dieses Repository).

Allgemeine Regeln:

- Zugangsdaten nur im Team-Passwortmanager; im Repo stehen nur Ressourcennamen/IDs, die nicht geheim sind.
- Testkonten/-apps gehören dem Unternehmen, nicht einer Einzelperson.
- Minimale Rechte; Schreibrechte (Antworten, Einladungen) erst bei Bedarf.
- Redirect-URIs für OAuth: Testsite `https://<testsite>/wp-admin/admin.php?page=review-captain-sources&rc_oauth=callback` (endgültige Route in TEC-010 festlegen).

## RC-009 – Google Business Profile

- [ ] Google-Cloud-Projekt „review-captain-dev“ im Unternehmenskonto anlegen.
- [ ] Business-Profile-APIs aktivieren (Account Management, Business Information, und die für Reviews nötige API) ⚠ genaue API-Liste gegen Doku prüfen.
- [ ] Zugriffsantrag für die Business-Profile-APIs bei Google stellen; Projekt-Nummer und Use-Case „WordPress-Plugin zeigt eigene Reviews des Standortinhabers an“ angeben.
- [ ] OAuth-Zustimmungsbildschirm konfigurieren (Typ extern, App-Name „Review Captain“, Datenschutz-URL, Support-Mail), Scope `business.manage`.
- [ ] Bewertung: Erfordert der Scope eine Google-App-Verifizierung? Falls ja, Verifizierungsprozess einplanen (Vorlaufzeit!).
- [ ] OAuth-Client (Web) mit Redirect-URI der Testsite anlegen.
- [ ] Test-Standort: verifizierter Unternehmensstandort mit mindestens 3 echten Reviews, an dem das Testkonto Inhaber/Verwalter ist.
- [ ] Architekturentscheidung (RC-026): zentraler Captain-OAuth-Client vs. betreibereigener Client. Empfehlung: **zentraler Client** für einfache Einrichtung, zusätzlich Option „eigener Client“ für Agenturen.
- **Nachweis:** erfolgreicher Aufruf „Accounts auflisten“ und „Reviews eines Standorts auflisten“ mit Testkonto, Screenshot/Protokoll ohne Tokens.

## RC-010 – Trustpilot

- [ ] Klären, welcher Trustpilot-Tarif API-Zugriff (öffentlich/privat) und Invitation API enthält; schriftliche Antwort ablegen.
- [ ] Partner-/Integrationsanfrage für den Plugin-Use-Case stellen (Plugin, das Reviews auf der eigenen Website des Business anzeigt und Einladungen nach echter Erfahrung versendet).
- [ ] Test-Business-Unit mit API-Key und (falls freigegeben) OAuth-Client.
- [ ] Endpunkte dokumentieren: Business-Unit-Suche, öffentliche Reviews, private Reviews, Summary, Invitation.
- [ ] Cache-/Löschregel (24 h) und Markenrichtlinie für Widgets/Logos schriftlich bestätigen.
- **Nachweis:** API-Aufruf Reviews + Summary der Test-Business-Unit; Freigabeschreiben für den Plugin-Use-Case.
- **Bis Nachweis:** Plattform bleibt in 1.0 auf TrustBox/Profil-Link ([RC-002](rc-002-scope-1-0.md)).

## RC-011 – Trusted Shops / eTrusted

- [ ] Berechtigten Testshop/-kanal (Pilotkunde oder eigener Händlervertrag) identifizieren.
- [ ] API-Client im eTrusted Control Centre anlegen; Client-ID/Secret im Passwortmanager.
- [ ] Abgrenzung dokumentieren: lesende Reviews-API ↔ Event-/Invite-API ↔ Widgets/Trustbadge (jeweils eigenes Recht und eigener Adaptermodus).
- [ ] Vertragliche Zulässigkeit der Anzeige gecachter Reviews auf der Website und Cachefrist klären.
- **Nachweis:** Token-Abruf und Review-Liste des Testkanals; schriftliche Klärung der Cachefrist.
- **Bis Nachweis:** Trustbadge/Widget/Profil-Link.

## RC-012 – Apple App Store Connect

- [ ] Test-App im Unternehmens-Developer-Account (veröffentlicht, mit Reviews in mindestens einem Territory).
- [ ] App-Ressourcen-ID (Apple ID der App) notieren.
- [ ] API-Key-Verfahren festlegen: **Team-Key** mit minimaler Rolle. Rolle für reines Lesen von Reviews ermitteln ⚠ (Kandidaten: „Customer Support“, „Developer“); Antworten erfordern Customer-Support-Rechte.
- [ ] Issuer-ID, Key-ID, `.p8` im Passwortmanager; Rotationsverfahren (Key widerrufen → neuen Key in Plugin hinterlegen) beschreiben.
- **Nachweis:** Abruf `customerReviews` der Test-App mit erzeugtem JWT.

## RC-013 – Google Play

- [ ] Test-App/Paketname mit Reviews (Produktions-Track).
- [ ] Google-Cloud-Projekt (gleiches wie RC-009 möglich) mit aktivierter Android Publisher API.
- [ ] Service Account anlegen und in der Play Console unter „Nutzer und Berechtigungen“ nur für die Test-App einladen; minimale Berechtigung (Reviews ansehen; Antworten erst später) ⚠.
- [ ] Alternative OAuth-Variante für Betreiber ohne Service-Account-Kenntnisse bewerten; Empfehlung 1.0: **Service-Account-JSON-Upload** (verschlüsselt gespeichert), OAuth als Folgeoption.
- [ ] Zeitfenster-Einschränkung von `reviews.list` verifizieren und im Setup-Text erklären ([RC-008](rc-008-provider-matrix.md)).
- **Nachweis:** Abruf `reviews.list` der Test-App.

## Ergebnisprotokoll

| Task | Datum | Verantwortlich | Ergebnis / Ressourcen-ID | Ablage Secrets |
|---|---|---|---|---|
| RC-009 | – | – | offen | – |
| RC-010 | – | – | offen | – |
| RC-011 | – | – | offen | – |
| RC-012 | – | – | offen | – |
| RC-013 | – | – | offen | – |
