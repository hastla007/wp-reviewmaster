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

Sie verbinden Ihr **eigenes Google-Cloud-Projekt** (Entscheidung 16.11.2026). Einmaliger Aufwand ca. 20–40 Minuten plus Wartezeit auf die Freischaltung durch Google. Alle Angaben zu Google-Oberflächen sind **ungeprüft** (Stand der öffentlichen Google-Doku; Klickpfade ändern sich) und werden mit dem Testzugang RC-009 bestätigt.

**Voraussetzungen**

- Google-Konto mit **Inhaber- oder Verwalterrecht** für einen **verifizierten** Unternehmensstandort.
- Zugang zur Google Cloud Console (kostenlos; für die Business Profile APIs ist laut Google kein Zahlungsmittel nötig – ungeprüft).
- Review Captain Pro mit aktivem Recht `review.platform_sync`.

**Schritt 1 – Projekt anlegen.** In der Google Cloud Console ein neues Projekt anlegen, z. B. „Bewertungen Website“.

**Schritt 2 – Zugang zu den Business Profile APIs beantragen.** Google schaltet die APIs nicht automatisch frei: Über das Antragsformular für den Business-Profile-API-Zugang die **Projekt-Nummer** angeben und den Zweck beschreiben („eigene Bewertungen auf der eigenen Website anzeigen“). Bis zur Freischaltung steht das Kontingent auf 0 – Verbindungen schlagen mit einer Kontingent-/Berechtigungsmeldung fehl. Die Bearbeitung kann einige Tage dauern (ungeprüft).

**Schritt 3 – APIs aktivieren.** Nach der Freischaltung im Projekt die Business-Profile-APIs aktivieren (u. a. „My Business Account Management API“, „My Business Business Information API“ sowie die API für Bewertungen; genaue Liste zeigt Review Captain unter Quellen → Google → „Was muss aktiviert sein?“).

**Schritt 4 – OAuth-Zustimmungsbildschirm.** Nutzertyp **Extern**, App-Name (z. B. Ihr Firmenname), Support-E-Mail; Scope `https://www.googleapis.com/auth/business.manage` hinzufügen; sich selbst als Testnutzer eintragen.
*Wichtig:* Solange die App im Status „Testen“ ist, laufen Anmeldungen laut Google nach **7 Tagen** ab. Damit die Verbindung dauerhaft bleibt, den Status auf **„In Produktion“** setzen. Für ein Projekt, das nur Ihr eigenes Konto nutzt, zeigt Google dann ggf. einen Hinweis „nicht überprüfte App“, den Sie bei der Anmeldung bestätigen können; eine Verifizierung ist dafür in der Regel nicht nötig (ungeprüft, abhängig von Googles Richtlinien).

**Schritt 5 – OAuth-Client anlegen.** Anmeldedaten → „OAuth-Client-ID“ → Anwendungstyp **Webanwendung**. Als **autorisierte Weiterleitungs-URI** genau die Adresse eintragen, die Review Captain unter Quellen → Google anzeigt (Kopieren-Knopf; endet auf Ihrer Domain). Client-ID und Client-Secret kopieren.

**Schritt 6 – In Review Captain verbinden.** Quellen → Quelle hinzufügen → Google → „Eigenes Google-Projekt“ → Client-ID und Client-Secret einfügen → **„Mit Google anmelden“** → Konto wählen, Zugriff erlauben → Standort(e) wählen → **Testabruf**. Zugangsdaten werden verschlüsselt gespeichert und nie angezeigt.

**Kosten/Limits:** Business Profile APIs laut Google ohne Nutzungsgebühr; Kontingente pro Projekt (ungeprüft). Review Captain ruft nur in den eingestellten Abständen ab.

**Häufige Fehler**

| Meldung/Symptom | Ursache | Lösung |
|---|---|---|
| Kontingent 0 / „PERMISSION_DENIED“ trotz richtiger Anmeldung | API-Zugang noch nicht freigeschaltet (Schritt 2) | Freischaltung abwarten bzw. Antrag prüfen |
| „redirect_uri_mismatch“ | Weiterleitungs-URI weicht ab (http/https, www, Schrägstrich) | exakt die angezeigte Adresse eintragen |
| Verbindung nach einer Woche „abgelaufen“ | App im Status „Testen“ | Status „In Produktion“ setzen und neu verbinden |
| Kein Standort zur Auswahl | Konto hat nur Nutzerrecht oder Standort nicht verifiziert | Inhaber/Verwalter-Recht geben bzw. verifizieren |
| „Zugriff widerrufen“ | in den Google-Kontoeinstellungen entfernt | „Neu verbinden“ |

**Trennen:** Quellen → Google → „Verbindung trennen“ und zusätzlich in Ihrem Google-Konto unter Sicherheit → Drittanbieter-Zugriff die App entfernen; optional das Cloud-Projekt löschen.

*Später möglich:* Eine zentrale Captain-App, bei der Sie nur „Verbinden“ klicken, wird nach 1.0 geprüft ([RC-026A](../decisions/captain-google-app.md)). Die Places-API (öffentlich, max. 5 Bewertungen) ist kein 1.0-Weg (RC-069).

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
