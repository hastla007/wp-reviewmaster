# ADR-0009: Lizenzgrenze für kundenspezifische Connectoren

- **Status:** **Angenommen** (Entscheidung der Inhaberin am 16.11.2026)
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Claude (Vorschlag) / Produkt (Entscheidung offen, hängt an RC-005)
- **Betrifft:** CON-006, RC-005; [connectors/licensing.md](../connectors/licensing.md)
- **Ersetzt:** –

## Kontext

`connectors/licensing.md` beschreibt die Aufteilung als Arbeitsgrundlage: öffentlicher Vertrag und Basis-Registry im kostenlosen Core, Manager und automatisierte Laufzeit in Pro unter `review.custom_connectors`, offizielle Captain-Adapter unter `review.suite_integrations`. So ist es gebaut: Core-Registry im Core-Branch, `CustomConnectors`- und `SuiteIntegrations`-Feature im Pro-Add-on (captain-pro). Offen ist die kommerzielle Bestätigung (RC-005). Dazu kommt eine Frage aus captain-pro (TODO, Abschnitt Koordination): Setzt ein Connector das Pro-Add-on technisch voraus, wenn die Laufzeit nur dort existiert?

## Betrachtete Optionen

1. **Wie gebaut** – Vertrag und Registry frei, Laufzeit Pro. Ein Connector ohne Pro wird registriert und angezeigt, verarbeitet aber nichts automatisch.
2. **Laufzeit im Core** – Connectoren laufen ohne Lizenz. Pro schaltet nur Manager/Diagnose frei. Damit verliert Pro einen Teil seines Mehrwerts.
3. **Connector als eigenes Produkt** – je Projekt-Connector eigener Produktschlüssel (wie in captain-pro für BoatingSchool/LearnBoating entschieden, Entscheidung 17).

## Entscheidung (Vorschlag)

Option 1 als Standard, ergänzt um Option 3 für vom Hersteller verkaufte Projekt-Connectoren. Der Core prüft nie Rechte (Konzept §6). Ohne `review.custom_connectors` zeigt die Integrationsseite registrierte Connectoren mit Status „bereit, Verarbeitung benötigt Review Captain Pro“. Daten werden weder gelöscht noch verworfen.

## Konsequenzen

- Positiv: entspricht dem gebauten Code; klare Wertgrenze; Entwickler können gegen den freien Vertrag entwickeln und testen.
- Negativ: Ein Drittanbieter-Connector braucht für den Produktivbetrieb Pro; das muss im Developer-Guide stehen.
- Folgearbeiten: Hinweistext in Core-Integrationsseite prüfen; `connectors/developer-guide.md` um die Abhängigkeit ergänzen; Katalog-Freigabe mit RC-005.

## Abnahme

- [ ] Produktverantwortung bestätigt (mit RC-005).
- [ ] `connectors/licensing.md` verweist auf dieses ADR.
- [ ] CON-016 (E2E) prüft das Verhalten ohne und mit Recht.

## Referenzen

- [connectors/licensing.md](../connectors/licensing.md); captain-pro TODO (Abschnitt „Koordination“, Connectoren der Projekte)
