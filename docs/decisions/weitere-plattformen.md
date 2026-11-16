# Entscheidungsvorlage: Weitere Plattformen priorisieren (RC-128)

| | |
|---|---|
| Status | **Entschieden** (Inhaberin, 16.11.2026) |
| Bezug | TODO RC-128; konzept.md §3 (Wettbewerbslücken), [providers/matrix.md](../providers/matrix.md), [contracts/provider-v1.md](../contracts/provider-v1.md), [ops/api-change-routine.md](../ops/api-change-routine.md) |
| Hinweis | Alle Angaben zu Plattformen stammen aus **öffentlich zugänglichen Informationen** zum Recherchezeitpunkt und sind **ungeprüft** (keine Partnerverträge, keine Testzugänge). Vor jeder Umsetzung sind API-Bedingungen, Kosten und Nutzungsrechte bei der Plattform zu verifizieren und datiert festzuhalten. |

## Kriterienkatalog (Abnahme RC-128: jede neue Plattform braucht alle vier Pflichtkriterien)

| # | Kriterium | Pflicht | Nachweis |
|---|---|---|---|
| K1 | **Nachfragebeleg** | ja | ≥ 5 Supportanfragen/Pilotkunden oder Wettbewerbsanalyse mit Zielsegment |
| K2 | **Offizielle Zugriffsmethode** | ja | dokumentierte API oder offizielles Widget/Feed; **kein Scraping**, keine inoffiziellen Endpunkte |
| K3 | **Nutzungsrecht** | ja | API-/Markenbedingungen erlauben Speichern (Dauer!), Anzeige auf der Website des Kunden und Attribution; Löschpflichten erfüllbar |
| K4 | **Kostenentscheidung** | ja | API-Kosten/Tarif (Kunde oder wir), Quoten, Freigabeprozess (App-Review) bewertet |
| K5 | Datenumfang | nein | Rating-Skala, Text, Datum, Antwortfunktion, Löschsignal, Profil-ID |
| K6 | Authentifizierung | nein | OAuth-App-Prüfung durch Plattform, Token-Lebensdauer, Partnerstatus |
| K7 | Wartungsaufwand | nein | Häufigkeit von Breaking Changes, Sandbox vorhanden |
| K8 | Recht/Policy | nein | Verbot von Review-Gating, Vorgaben zur Darstellung (Logo, Link, Sterne) |
| K9 | Zielgruppe | nein | Branche (Gastro, Arbeitgeber, Dienstleister), Region DACH |

Bewertung: K1–K4 = Ausschluss bei „nein“; K5–K9 je 0–2 Punkte, Priorität nach Summe und Aufwand.

## Kandidaten (öffentliche Angaben, ungeprüft)

| Plattform | Zielgruppe | Offizielle Methode (öffentlich, ungeprüft) | Bekannte Einschränkungen (ungeprüft) | Vorläufige Einschätzung |
|---|---|---|---|---|
| **Yelp** | Gastronomie, lokale Dienstleister (stark USA, schwächer DACH) | Yelp Fusion API (Business Search/Reviews) | öffentlich dokumentiert: nur wenige Review-Auszüge je Unternehmen, Anzeige-/Speicherauflagen, kommerzielle Nutzung tarifpflichtig | K3 kritisch (Speicherdauer, Auszüge); geringe DACH-Nachfrage → niedrig |
| **Facebook (Meta) Seiten-Empfehlungen** | lokale Unternehmen | Graph API (Page Ratings/Recommendations) mit Seiten-Token | App-Review und Berechtigungen nötig; Empfehlungen (ja/nein) statt Sterne-Skala; API-Änderungen häufig | K6/K7 aufwändig; mittel, wenn Nachfrage belegt |
| **ProvenExpert** | DACH-Dienstleister, Agenturen | öffentlich dokumentierte API/Widgets für Profil-Kennzahlen | Detailumfang (einzelne Bewertungen) und Tarifbindung ungeprüft | DACH-relevant → **hoch prüfen** |
| **kununu** | Arbeitgeberbewertungen (DACH) | offizielle Widgets/Siegel; Partner-/Employer-Branding-Programme | öffentliche Review-API nicht bekannt (ungeprüft); Inhalte = Arbeitgeber, nicht Produkt/Service → eigener `kind`/Kontext | eher als Trust-Badge/Embed (Pro) statt Sync |
| **Tripadvisor** | Hotellerie, Gastro, Freizeit | Tripadvisor Content API (Partnerprogramm, Antrag) | Freigabe nötig, Darstellungs- und Cache-Regeln, ggf. Traffic-Anforderungen | mittel für Hotellerie-Segment |
| Trusted Shops, Trustpilot, Google | – | bereits Launch-Provider ([providers/matrix.md](../providers/matrix.md)) | – | – |

## Empfehlung

1. Für 1.x keine neue Plattform zusagen. Zuerst **K1 (Nachfrage)** für ProvenExpert und Tripadvisor über Pilotkunden erheben.
2. ProvenExpert als ersten Kandidaten prüfen (DACH-Fokus, Zielgruppe deckt sich mit Kernkunden); kununu nur als Badge/Embed.
3. Yelp und Facebook nur bei belegtem Bedarf (Nutzungsrechte bzw. App-Review-Aufwand).
4. Jede Aufnahme über den bestehenden Provider-Vertrag (`ProviderInterface`) und mit Eintrag in der API-Änderungsroutine.

## Offene Entscheidungen des Inhabers

- Wer prüft die API-Bedingungen (Name, Datum) – Voraussetzung K2/K3?
- Wer trägt API-Kosten (K4): Kunde mit eigenem Schlüssel oder wir?
- Reihenfolge nach Nachfrageerhebung.

## Entscheidung der Inhaberin (16.11.2026)

Empfehlung angenommen: keine neue Plattform für 1.x zusagen; Bedarf bei Pilotkunden erheben; ProvenExpert erster Kandidat, kununu nur Badge, Yelp/Facebook nur bei Bedarf; Pflichtkriterien K1–K4.
