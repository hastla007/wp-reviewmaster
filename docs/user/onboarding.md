# Review Captain – Schnellstart (OPS-001)

Status: Entwurf, Stand 2026-11-16. Noch nicht mit echten Nutzern getestet. Screenshots folgen nach Fertigstellung der Adminseiten.

In etwa 10 Minuten zeigen Sie Ihre ersten Bewertungen auf Ihrer Website an – ohne Konto bei einem externen Dienst.

## 1. Installieren

1. WordPress-Admin → **Plugins → Installieren** → nach „Review Captain“ suchen → **Jetzt installieren** → **Aktivieren**.
   Alternativ ZIP hochladen: **Plugins → Installieren → Plugin hochladen**.
2. Voraussetzungen: PHP 8.1 und WordPress 6.5 oder neuer (vorläufige Angabe). Erfüllt Ihre Website sie nicht, erscheint ein Hinweis und das Plugin bleibt inaktiv.
3. Nach der Aktivierung öffnet sich **Review Captain → Dashboard** mit der Einrichtungs-Checkliste. Jeder Schritt lässt sich überspringen und später fortsetzen.

## 2. Einrichtungsassistent

| Schritt | Was Sie tun | Dauer |
|---|---|---|
| Marke & Sprache | Namen und Sprache prüfen (vorausgefüllt) | 1 min |
| Erste Reviews | eine Bewertung von Hand eintragen, ein Bewertungsformular einbinden **oder** eine CSV-Datei importieren | 2–5 min |
| Quelle | Link zu Ihrem Google-/Trustpilot-/App-Store-Profil hinterlegen, damit Besucher Sie dort bewerten können | 2 min |
| Erstes Widget | Bewertungen auswählen und auf einer Seite einfügen | 3 min |
| Datenschutz | Hinweistext und Aufbewahrungsfristen prüfen | 2 min |

Review Captain verbindet sich dabei mit **keinem** externen Dienst. Automatische Synchronisierung mit Google, Trustpilot usw. bietet Review Captain Pro.

## 3. Erste Bewertung eintragen

1. **Review Captain → Reviews → Neu hinzufügen**.
2. Art wählen:
   - **Eigenes Review/Testimonial** – eine Rückmeldung, die Sie direkt erhalten haben (mit Erlaubnis der Person).
   - **Externe Bewertung (Auszug)** – eine Bewertung, die auf einer Plattform steht. Pflicht: Plattform, Originalbewertung, Datum und Link zur Bewertung. Die Quelle wird auf Ihrer Website immer angezeigt.
3. Status **Freigegeben** wählen, wenn sie sofort sichtbar sein soll → **Speichern**.

## 4. Bewertungen von Besuchern sammeln

1. Seite bearbeiten → Block **„Review Captain: Bewertungsformular“** einfügen (klassische Editoren/Page Builder: Shortcode `[review_captain_form v="1"]`).
2. Neue Einsendungen landen unter **Reviews → Prüfung ausstehend**. Sie erhalten eine E-Mail mit Link (ohne Bewertungstext).
3. Prüfen → **Freigeben** oder **Ablehnen**.

## 5. Widget veröffentlichen

1. **Review Captain → Widgets → Neues Widget**.
2. Quellen wählen → Auswahl (z. B. „alle freigegebenen“) → Layout Raster oder Liste → Vorschau prüfen → **Speichern**.
3. Einbinden:
   - **Block-Editor:** Block **„Review Captain: Reviews“** einfügen und das Widget auswählen.
   - **Klassisch/Page Builder:** Shortcode aus der Widget-Liste kopieren, z. B. `[review_captain v="1" id="12"]`, und in ein Text-/Shortcode-Element einfügen.
4. Seite veröffentlichen und ansehen. In der Widget-Liste erscheint „Verwendet auf“.

## 6. Prüfen, ob alles läuft

- **Review Captain → Diagnose** zeigt Version, Hintergrundaufgaben und eventuelle Probleme.
- Bei wenig Besucherverkehr laufen Hintergrundaufgaben (z. B. Löschfristen) verzögert. Empfehlung für Hoster: echten Server-Cron einrichten.

## 7. Mit Review Captain Pro (optional)

Lizenz unter **Review Captain → Lizenz** aktivieren, dann **Quellen → Quelle hinzufügen → API-Verbindung**. Anleitungen je Plattform: [platform-setup.md](platform-setup.md).

## Abnahme

Entspricht OPS-001, wenn:

- [ ] Installationshilfe, Erststart-Assistent, Verbindungsschritte und Widget-Veröffentlichung beschrieben sind;
- [ ] die Anleitung mit mindestens drei Pilotnutzern (OPS-006) ohne Hilfe durchlaufen wurde und die Dauerangaben gemessen sind ([../ux/metrics.md](../ux/metrics.md));
- [ ] Block-/Menünamen und Mindestversionen mit der fertigen Software übereinstimmen und Screenshots ergänzt sind.
