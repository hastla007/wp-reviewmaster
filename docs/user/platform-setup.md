# Plattformen einrichten (OPS-002, Entwurf)

Status: **Entwurf**, Stand 2026-11-16. Anbieterangaben ungeprüft (siehe [../providers/matrix.md](../providers/matrix.md)); konkrete Klickpfade bei den Anbietern werden nach den Testzugängen (RC-009 … RC-013) ergänzt. API-Verbindungen erfordern **Review Captain Pro**; Profil-Links funktionieren in der kostenlosen Version.

## Für alle Plattformen

- Sie können nur **eigene** Profile/Apps verbinden, für die Ihr Konto Verwaltungsrechte hat.
- Review Captain liest Bewertungen nur über offizielle Schnittstellen – kein Auslesen von Webseiten.
- Zugangsdaten werden verschlüsselt gespeichert und nie angezeigt oder protokolliert.
- **Trennen:** Quellen → Profil → **Verbindung trennen**. Der lokale Zugang wird gelöscht; widerrufen Sie den Zugriff zusätzlich im Konto des Anbieters. Gespeicherte Bewertungen bleiben bis zum Ablauf der Aufbewahrungsfrist erhalten.
- **Ohne API-Zugang:** Profil-Link mit Button „Auf {Plattform} bewerten“, offizielles Anbieter-Widget (wird erst nach Zustimmung der Besucher geladen) oder handverlesene Auszüge.

Jeder Abschnitt hat dieselbe Gliederung: Voraussetzungen · Kosten/Limits · Verbinden · Profil wählen · Häufige Fehler · Trennen.

## Google Business Profile

- **Voraussetzungen:** Google-Konto mit Inhaber-/Verwalterrecht für einen **verifizierten** Unternehmensstandort. Ggf. Freigabe des Google-API-Zugangs (Klärung RC-009: eigenes Cloud-Projekt des Betreibers oder OAuth-App des Plugin-Anbieters – OFFEN).
- **Kosten/Limits:** API laut Konzept ohne Zusatzgebühr; Kontingente durch Google. (ungeprüft)
- **Verbinden:** Quellen → Quelle hinzufügen → Google → API-Verbindung → „Mit Google anmelden“ → Zugriff erlauben → Standort(e) wählen → Testabruf.
- **Häufige Fehler:** Standort nicht verifiziert · Konto ist nur „Nutzer“ ohne Verwaltungsrecht · Zugriff in Google-Kontoeinstellungen widerrufen → „Neu verbinden“.
- **Google Places (öffentlich, max. 5 Bewertungen, kostenpflichtig):** nur falls RC-069 umgesetzt wird.

## Trustpilot

- **Voraussetzungen:** Trustpilot-Business-Konto mit API-Zugang (tarifabhängig), API-Key/Secret aus dem Trustpilot-Business-Bereich, Business-Unit. (ungeprüft)
- **Kosten/Limits:** tarifabhängig; angezeigte Inhalte müssen spätestens alle 24 Stunden aktualisiert werden – Review Captain erzwingt das. (laut Doku, ungeprüft)
- **Verbinden:** Quellen → Trustpilot → API-Verbindung → Schlüssel eingeben → anmelden → Business-Unit wählen → Testabruf.
- **Ohne API:** TrustBox-Widget: Business-Unit-ID und Vorlagen-ID am Profil eintragen ([../providers/widget-fallbacks.md](../providers/widget-fallbacks.md)).
- **Häufige Fehler:** Tarif ohne API · Schlüssel einer anderen Business-Unit.

## Trusted Shops / eTrusted

- **Voraussetzungen:** Trusted-Shops-Vertrag mit passendem Produkt, API-Client (Client-ID/Secret) im eTrusted-Kontrollzentrum, Channel. (ungeprüft)
- **Verbinden:** Quellen → Trusted Shops → API-Verbindung → Client-ID/Secret → Channel wählen → Testabruf.
- **Ohne API:** Trustbadge bzw. Review-Widget über Shop-/Widget-ID (nach Zustimmung der Besucher).
- **Häufige Fehler:** API-Client ohne Lese-Berechtigung · falscher Channel.

## Apple App Store

- **Voraussetzungen:** Apple-Developer-Programm, Zugriff auf App Store Connect mit Recht zum Erstellen von API-Schlüsseln; App.
- **Verbinden:** in App Store Connect unter Benutzer und Zugriff → Integrationen einen API-Schlüssel mit minimaler Rolle erstellen (Rolle wird in RC-012 festgelegt), `.p8`-Datei herunterladen, Issuer-ID und Key-ID notieren → in Review Captain Quellen → App Store → Daten eingeben, `.p8` hochladen → App wählen → Testabruf. (ungeprüft)
- **Häufige Fehler:** Schlüssel widerrufen · falsche Issuer-ID · App gehört zu anderem Team.
- **Hinweis:** Die `.p8`-Datei kann bei Apple nur einmal heruntergeladen werden; bewahren Sie sie sicher auf.

## Google Play

- **Voraussetzungen:** Play-Console-Zugriff mit Recht, Nutzer/Service-Accounts einzuladen; Google-Cloud-Projekt; Paketname der App.
- **Verbinden:** Service-Account im Cloud-Projekt anlegen, Android-Publisher-API aktivieren, Service-Account in der Play Console mit minimalen Rechten für Bewertungen einladen, JSON-Schlüssel erzeugen → Review Captain Quellen → Google Play → JSON hochladen, Paketname → Testabruf. (ungeprüft)
- **Wichtig:** Google stellt über die Schnittstelle vermutlich nur Bewertungen der letzten Tage bereit (ungeprüft); ältere Bewertungen können nicht nachträglich importiert werden. Lassen Sie die Synchronisierung daher dauerhaft aktiv.
- **Häufige Fehler:** Service-Account in der Play Console nicht eingeladen · API im Cloud-Projekt nicht aktiviert.

## Abnahme

Entspricht OPS-002, wenn:

- [ ] pro Quelle eine aktuelle Anleitung zu Kontozugriff, API-Rechten, Tokens/Schlüsseln, Profilwahl, Kosten/Limits, Fehlerbehebung und Trennen vorliegt;
- [ ] alle „(ungeprüft)“-Angaben mit Testzugängen bestätigt und mit Datum versehen sind;
- [ ] Klickpfade mit der fertigen Software übereinstimmen.
