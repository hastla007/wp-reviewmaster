# Entscheidungsvorlage: Sentiment-/KI-Funktionen (RC-129)

| | |
|---|---|
| Status | **Vorlage – Entscheidung des Inhabers offen** (Stand 2026-11-16) |
| Bezug | TODO RC-129 („nur als separates Produktdecision-Dokument mit Datenfluss, Einwilligung und menschlicher Kontrolle; kein automatisches Veröffentlichen“); konzept.md §3; [ops/privacy-checklist.md](../ops/privacy-checklist.md) |
| Rahmen | DSGVO (Art. 6, 13, 22, 28, 44 ff.), EU AI Act (Transparenzpflichten für KI-generierte Inhalte), UWG/Omnibus-Richtlinie (keine irreführenden Bewertungen). Rechtliche Einordnung ist **nicht geprüft** – vor Umsetzung Datenschutzberatung einbinden. |

## Mögliche Funktionen

| Funktion | Nutzen | Risiko |
|---|---|---|
| F1 Stimmungs-/Themenanalyse (intern) | Dashboard: häufige Themen („Lieferung“, „Beratung“), Trend | gering bis mittel (Verarbeitung von Texten) |
| F2 Antwortentwürfe für Betreiber | spart Zeit beim Beantworten | mittel (falsche Zusagen, Ton) |
| F3 Moderationshilfe (Spam/Beleidigung/personenbezogene Daten markieren) | schnellere Moderation | mittel (Fehlklassifikation) |
| F4 Zusammenfassung „Kunden sagen …“ öffentlich | Conversion | **hoch** (Irreführung, Verzerrung, AI-Act-Kennzeichnung) |
| F5 Automatisches Ablehnen/Freigeben | – | **nicht zulässig** (menschliche Kontrolle, Art. 22 DSGVO-Nähe, Gating-Gefahr) |

## Datenfluss (Vorschlag für F1–F3)

1. Auslöser nur durch Moderierende (Button „Analyse vorschlagen“) oder ausdrücklich aktivierten Hintergrundjob.
2. Daten minimieren: nur `title`, `body`, `rating_value`, `language`; **kein** Name, keine E-Mail, keine IDs außer einer zufälligen Anfrage-ID. Vorab einfache PII-Maskierung (E-Mail, Telefon, URLs).
3. Verarbeitung entweder lokal (kleines Modell auf dem Server, kein Drittland) oder über einen Anbieter mit Auftragsverarbeitungsvertrag (Art. 28), EU-Region, ohne Training auf Kundendaten. API-Schlüssel nur über den bestehenden Secret-Speicher (`profiles.credentials_enc`-Muster).
4. Ergebnis wird als **Vorschlag** gespeichert (eigene Tabelle `review_ai_suggestions`, Aufbewahrung 30 Tage), nie in `reviews` geschrieben.
5. Keine Übertragung externer Plattform-Reviews, deren Nutzungsbedingungen eine Weiterverarbeitung ausschließen (Provider-Matrix prüfen).
6. Audit: `ai.suggestion_created` (Review-ID, Modell, Dauer), `ai.suggestion_applied` (wer) – ohne Texte.

## Einwilligung und Information

- Betreiber: Opt-in je Funktion in den Einstellungen (Standard aus), Hinweis auf Datenübermittlung und Anbieter.
- Autorinnen/Autoren: Datenschutzhinweis im Formular ergänzen („Texte können zur Moderationsunterstützung automatisiert ausgewertet werden“); bei Anbieter außerhalb der EU ggf. Einwilligung erforderlich – Rechtsprüfung offen. Bestandsbewertungen nur nach Prüfung der Rechtsgrundlage.
- Öffentliche KI-Texte (F4) müssen als KI-generiert gekennzeichnet werden.

## Menschliche Kontrolle (verbindlich, falls umgesetzt)

- **Kein automatisches Veröffentlichen, Ablehnen, Antworten oder Löschen.** Jede Statusänderung bleibt eine Moderationsentscheidung über `ReviewRepository::change_status()` mit Benutzer-ID.
- Antwortentwürfe (F2) werden nur nach Bearbeitung/Bestätigung gespeichert bzw. an Plattformen gesendet.
- Moderationshilfe (F3) setzt höchstens einen Hinweis bzw. internen Tag (RC-124), nie `flagged`/`rejected`.
- Abschaltbar ohne Datenverlust; Vorschläge werden bei Deaktivierung gelöscht.

## Empfehlung

1. **Kein KI-Feature in Core 1.x.** F4 und F5 ausdrücklich nicht umsetzen.
2. Falls Nachfrage belegt: F1 (interne Themenanalyse) als erstes Pro-Experiment, bevorzugt lokal/EU-gehostet, mit obigem Datenfluss; danach F3 als reine Markierung.
3. Vor Umsetzung: Datenschutz-Folgenabschätzung (Art. 35) prüfen, AVV-Anbieter auswählen, Formulartext anpassen.

## Offene Entscheidungen des Inhabers

- Grundsätzlich KI-Funktionen im Produkt (ja/nein, Pro-Stufe)?
- Lokales Modell vs. EU-Anbieter (Kosten, Qualität)?
- Wer verantwortet Rechtsprüfung und DSFA?
