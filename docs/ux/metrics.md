# Usability-Kennzahlen (UX-008)

Status: Entwurf zur Abnahme, Stand 2026-11-16.

## Grundsatz

Review Captain sendet **keine Telemetrie** (Core nie; Pro nur Lizenzprüfung ohne Nutzungsdaten). Kennzahlen entstehen (a) in moderierten Usability-Tests und Pilotbetrieb, (b) optional aus **lokal** gespeicherten Zeitstempeln, die der Betreiber freiwillig im Diagnosebericht teilt.

## Kennzahlen

| Kennzahl | Definition | Erhebung | Zielwert (Vorschlag) |
|---|---|---|---|
| K1 Zeit bis zum ersten Review | Aktivierung → erstes gespeichertes Review | lokal: `activated_at`, `first_review_at`; Test: Stoppuhr | ≤ 5 min |
| K2 Zeit bis zur ersten Verbindung | Start Wizard „Quelle hinzufügen“ → erfolgreicher Verbindungstest (Pro) bzw. gespeicherter Profil-Link (Core) | lokal: `first_source_started_at`, `first_source_ok_at`; Test | Core ≤ 3 min, Pro ≤ 10 min (ohne Anbieter-Wartezeit) |
| K3 Zeit bis zum ersten Widget | Aktivierung → erstes Widget auf veröffentlichter Seite gerendert | lokal: `first_widget_rendered_at` (einmalig beim ersten Frontend-Render, ohne Besucherdaten) | ≤ 10 min |
| K4 Einrichtungsabbrüche | Schritt begonnen, aber nach 7 Tagen nicht erledigt und nicht übersprungen | lokal aus `review_captain_onboarding`; Pilot-Interviews | < 30 % je Schritt |
| K5 Erfolgsquote Kernaufgaben | Anteil Testpersonen, die „Quelle verbinden“ und „Bewertungsleiste veröffentlichen“ ohne Hilfe schaffen (UX-006) | moderierter Test, 5–8 Personen je Zielgruppe | ≥ 80 % |
| K6 Supportanfragen Einrichtung | Tickets zu Installation/Verbindung pro aktiver Pilotsite | Supportsystem (OPS-008) | sinkend |

## Datenschutz

- Lokale Zeitstempel: nur Zeit + Schrittname, **keine** Nutzer-ID, IP, URL oder Reviewinhalte; in Option `review_captain_onboarding` (Vorschlag), mit „Einrichtung zurücksetzen“ löschbar.
- Im Diagnosebericht nur als relative Dauern (z. B. „K2: 6 min“), opt-in je Export.
- Bewusst **nicht** erfasst: Klickpfade, Heatmaps, Seitenaufrufe, Verweildauer, Besucherdaten im Frontend, Anzahl Besucher von Widgets.
- Testpersonen: Einwilligung, Aufzeichnung nur mit Zustimmung, Auswertung pseudonym.

## Abnahme

Entspricht UX-008, wenn:

- [ ] Zeit bis zur ersten Verbindung, Zeit bis zum ersten Widget und Einrichtungsabbrüche definiert sind und entweder lokal ohne personenbezogene Telemetrie messbar oder bewusst nur im Test erhoben werden;
- [ ] die bewusst nicht erfassten Daten dokumentiert sind;
- [ ] Zielwerte mit Produkt abgestimmt sind und in UX-006/OPS-006 verwendet werden.
