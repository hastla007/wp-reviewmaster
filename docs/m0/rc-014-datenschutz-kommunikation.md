# RC-014 – Datenschutz- und Kommunikationsprüfung (Prüfvorlage)

**Status:** Entwurf als Vorlage für die fachliche/juristische Prüfung – **keine Rechtsberatung**, Freigabe durch Rechtsberatung offen  
**Stand:** 2026-11-16  
**Zielmärkte 1.0:** Deutschland, Österreich (EU-Recht; weitere Märkte nur nach eigener Prüfung)  
**Quellen:** [Konzept §7.4, §7.9, §11](../../konzept.md)

## 1. Datenflüsse

| # | Datenfluss | Daten | Zweck | Verantwortlich | Empfänger außerhalb der Site |
|---|---|---|---|---|---|
| D1 | Lokale Review-Einreichung | Rating, Text, Anzeigename, optional E-Mail, Zeitstempel; **keine IP dauerhaft** (nur kurzfristig für Rate-Limit, gehasht) | Veröffentlichung nach Moderation | Betreiber | keine |
| D2 | Sync externer Reviews | Review-Inhalte, öffentlicher Anzeigename/Avatar laut Plattform | Anzeige auf eigener Website | Betreiber (Anbieterbedingungen beachten) | Abruf beim Anbieter (Google, Apple, …) mit Betreiber-Zugangsdaten |
| D3 | Erlebnisereignis aus Quellplugin | `experience_id`, `recipient_ref`, Locale, Consent-Status – **keine E-Mail** | Einladung planen | Betreiber | keine |
| D4 | Kontaktauflösung zur Versandzeit | E-Mail, Vorname (optional) | Einladung versenden | Betreiber | Mailtransport des Betreibers (SMTP/ESP = Auftragsverarbeiter) |
| D5 | Anbieter-Einladungs-API (optional, später) | E-Mail, Name, Referenz | Einladung über Plattform | Betreiber; Plattform eigenständig oder AV laut Vertrag | Trustpilot / eTrusted |
| D6 | Versandprotokoll | Status, Zeit, Kampagne, gehashte Empfängerreferenz | Nachweis, Dedupe, Opt-out | Betreiber | keine |
| D7 | Lizenzprüfung (Pro) | Lizenzschlüssel, Site-URL, Version | Lizenz/Updates | Hersteller | Lizenzserver – **keine Review-/Kundendaten** |
| D8 | Medien-Testimonials (Pro-Folge) | Bild, Stimme, Video, Name | Veröffentlichung | Betreiber | ggf. externer Player nach Klick (TEC-013) |

## 2. Versandgrundlage Review-Einladung (zu prüfen)

Ausgangsthese für die Prüfung:

- Eine Bewertungsanfrage per E-Mail wird in der deutschen Rechtsprechung als **Werbung** eingeordnet (zu prüfen: BGH, Urteil vom 10.07.2018 – VI ZR 225/17).
- **Deutschland:** zulässig mit vorheriger ausdrücklicher Einwilligung (§ 7 Abs. 2 UWG) oder im Rahmen der Bestandskundenausnahme (§ 7 Abs. 3 UWG) – Voraussetzungen kumulativ: Adresse im Zusammenhang mit einem Verkauf erhalten, Werbung für eigene ähnliche Waren/Dienstleistungen, kein Widerspruch, klarer Hinweis auf Widerspruchsrecht bei Erhebung und bei jeder Verwendung. Ob eine Bewertungsanfrage darunter fällt, ist ausdrücklich zu prüfen.
- **Österreich:** § 174 TKG 2021 (Einwilligung bzw. eng begrenzte Ausnahme für Bestandskunden) zu prüfen.
- **DSGVO:** Rechtsgrundlage Art. 6 Abs. 1 lit. a (Einwilligung) oder lit. f (berechtigtes Interesse), wobei die wettbewerbsrechtlichen Grenzen die Interessenabwägung prägen.
- **Beschenkte / Ticketinhaber** (keine eigenen Käufer): Bestandskundenausnahme greift nicht ohne Weiteres → Versand nur mit eigener Einwilligung der Person. Das Plugin muss das abbilden (Voucher/Pass, RC-099/RC-101).

Technische Folgerungen (gelten unabhängig vom Prüfergebnis als sichere Defaults):

- `communication_allowed` hat drei Werte: `consent` (mit Zeitpunkt/Quelle), `existing_customer` (nur wenn Betreiber Ausnahme aktiv wählt und der Hinweis bei Erhebung belegt ist), `unknown`/`denied` → **kein Versand**.
- Jede Einladung enthält Abmeldelink und Hinweis auf Widerspruchsrecht; Opt-out wirkt siteweit für alle Review-Captain-Kampagnen (RC-090).
- Keine Anreize für Bewertungen, kein Review-Gating, neutrale Formulierung (siehe Konzept §7.9).
- Maximal eine Einladung je Erlebnis/Empfänger/Kampagne; Erinnerung standardmäßig aus.

## 3. Consent, Opt-out, Aufbewahrung, Löschung

| Thema | Vorschlag | Zu bestätigen |
|---|---|---|
| Consent-Nachweis | Quelle (Plugin), Zeitpunkt, Text-/Versions-ID des Einwilligungstexts, Kanal | ob Text-Version reicht oder Volltext gespeichert werden muss |
| Opt-out | gehashte E-Mail in Suppression-Liste, unbefristet solange Plugin aktiv; Hash verhindert Klartextspeicherung | Zulässigkeit unbefristeter Sperrliste |
| Versandprotokoll | 12 Monate, danach anonymisiert (Zähler bleiben) | Frist |
| Geplante, nicht gesendete Einladung | nach Ablaufdatum der Kampagne (Default 60 Tage nach Erlebnis) gelöscht | Frist |
| Lokale Reviews | bis Löschung durch Betreiber/Betroffenen; Privacy Eraser anonymisiert Autorbezug | – |
| Externe Snapshots | nach Anbieterfrist (RC-008), spätestens bei Disconnect + Frist | – |
| Logs/Audit | 90 Tage, ohne Klartext-E-Mail und ohne Reviewtext | Frist |
| Medien-Testimonials | bis Widerruf/Ablauf der Rechte (RC-015) | – |
| Löschverantwortung | Betreiber = Verantwortlicher; Plugin liefert Exporter/Eraser und Retention-Jobs (RC-047, RC-048) | – |

Die finale Matrix wird in RC-027 technisch umgesetzt.

## 4. Textbausteine (vom Plugin bereitzustellen, vom Betreiber anzupassen)

- Datenschutzhinweis-Vorschlag über `wp_add_privacy_policy_content()`: lokale Reviews, Einladungen, externe Dienste (je verbundener Anbieter), Lizenzprüfung (Pro).
- Einwilligungstext-Vorlage für Checkout/Formular „Bewertungseinladung erhalten“ (Opt-in, nicht vorausgewählt).
- Hinweistext für Bestandskundenausnahme (nur wenn Betreiber diese aktiviert).

## Offen / Freigabe

- [ ] Juristische Prüfung Abschnitt 2 für DE und AT, schriftliches Ergebnis ablegen.
- [ ] Fristen in Abschnitt 3 bestätigen.
- [ ] Muster-AV-Hinweise für Mailtransport und Anbieter-Einladungs-APIs.
