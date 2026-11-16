# RC-016 – Marken- und Trustmark-Regeln

**Status:** Checkliste bereit – Anbieterrichtlinien müssen je Logo/Badge an der Primärquelle geprüft und mit Datum eingetragen werden  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §5, §8.10, §9](../../konzept.md), [Provider-Matrix](../providers/matrix.md)

## Allgemeine Regeln (gelten für alle Quellen)

1. **Nur offizielle Assets** aus dem Brand-/Presse-Bereich des Anbieters, unverändert (keine Farb-, Form-, Proportionsänderung, keine Kombination zu einem neuen Zeichen).
2. **Keine Partner-/Empfehlungsanmutung:** Logos kennzeichnen die Quelle einer Bewertung, sie suggerieren keine Zertifizierung oder Partnerschaft von Review Captain.
3. **Keine Anbieterlogos im Plugin-Namen, -Icon, -Banner oder in Shop-Screenshots** ohne schriftliche Freigabe (WordPress.org-Richtlinie zu Markennamen beachten).
4. **Logos nicht im Plugin-ZIP bündeln**, wenn die Lizenz des Anbieters das nicht erlaubt; Fallback: neutrales Textlabel („Google“, „Trustpilot“ …) plus Link. Die Entscheidung je Anbieter steht in der Tabelle.
5. **Trustbadges/Gütesiegel** von Trusted Shops o. ä. ausschließlich über den offiziellen Embed (mit Consent-Gate, TEC-013), nie als statisches Bild nachgebaut.
6. **Auszeichnungen/Awards/Zertifikate** (Pro-Folge, RC-111): Pflichtfelder Herausgeber, gültig ab/bis, Asset oder Embed, Verifizierungslink, Nachweis der Nutzungsberechtigung; abgelaufene Siegel werden automatisch ausgeblendet.
7. Externe Reviews bleiben **immer** mit Quellenlabel und Link zur Originalquelle versehen; diese Attribution ist im UI nicht abschaltbar.

## Checkliste je Anbieter

| Anbieter | Asset | Darf im Plugin gebündelt werden? | Pflicht-Attribution | Geprüft am / von |
|---|---|---|---|---|
| Google (Business Profile) | Google-Logo / „Google“-Wortmarke | ❓ Richtlinie prüfen; bis dahin Textlabel | Quellenangabe „Google“ + Link zum Profil | – |
| Trustpilot | Trustpilot-Logo, Sterne-Grafik, TrustBox | ❓; Sterne-Grafik nur in offiziellen Farben/Form | Logo/Name + Link je Review/Summary | – |
| Trusted Shops | Trustbadge, Logo | nein – nur offizieller Embed | Trustbadge-Embed bzw. Name + Link | – |
| Apple App Store | „Download on the App Store“-Badge | ❓ Apple-Marketing-Richtlinien; Badge nur als Link zur App | „App Store“ + Link zur App-Seite | – |
| Google Play | „Get it on Google Play“-Badge | ❓ Google-Play-Badge-Richtlinien | „Google Play“ + Link zur App-Seite | – |

Für jede Zeile: Richtlinien-URL, Version/Datum und Ergebnis im Ergebnisprotokoll festhalten.

## Prüffragen vor Release (in RC-134/RC-140 übernehmen)

- [ ] Werden Logos ausschließlich neben Inhalten der jeweiligen Quelle angezeigt?
- [ ] Sind Sterne-Darstellungen externer Quellen nicht mit eigenen Sternen verwechselbar (eigene Sterne neutral gestaltet)?
- [ ] Erzeugt ein Badge keinen Eindruck einer Prüfung, die nicht stattgefunden hat?
- [ ] Sind abgelaufene Siegel und widerrufene Freigaben technisch ausgeblendet?
- [ ] Enthalten WordPress.org-Assets (Banner/Icon/Screenshots) keine fremden Marken ohne Freigabe?

## Ergebnisprotokoll

| Anbieter | Richtlinien-URL | Datum | Ergebnis |
|---|---|---|---|
| – | – | – | offen |
