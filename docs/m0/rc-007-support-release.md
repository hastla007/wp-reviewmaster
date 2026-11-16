# RC-007 – Supportumfang und Releasekanäle

**Status:** Entwurf – Freigabe offen  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §6, §8.11](../../konzept.md), [RC-004](rc-004-planmodell.md), TODO M8/M9

## Releasekanäle

| Produkt | Kanal | Update-Mechanismus | Versionierung |
|---|---|---|---|
| Review Captain Core | WordPress.org-Plugin-Verzeichnis (`review-captain`) | WordPress-Core-Updater | SemVer; `readme.txt` mit Changelog, `Stable tag` |
| Review Captain Pro | Captain-Pro-Vertrieb (Shop-Download + Updateserver über WP License Captain) | Captain-Pro-Updater mit signierten Paketen und gültiger Lizenz | SemVer; Mindest-Core-Version im Header |
| Vorabversionen | Beta-ZIP für Pilotkunden (OPS-006), nicht über WordPress.org | manuell | `-beta.N` |

Regeln:

- Core-Releases enthalten keinen Lizenz- oder Updatercode und keine Hinweise, die WordPress.org-Richtlinien widersprechen (Upsell nur dezent, keine gesperrten Core-Funktionen, keine Telemetrie ohne Opt-in).
- Pro darf keine Core-Version voraussetzen, die noch nicht auf WordPress.org veröffentlicht ist.
- Sicherheitsfixes: Patch-Release für die aktuelle Minor-Version; ältere Versionen werden nicht gepflegt.
- Release-Ablauf und Gate: RC-141 bis RC-143.

## Supportsprachen

- Deutsch und Englisch (Ticket, Doku).
- Plugin-UI: Englisch (Quellsprache), Deutsch (`de_DE`), Deutsch formal (`de_DE_formal`), Österreich (`de_AT`) über Übersetzungsdateien (RC-034, RC-138).

## Supportumfang

| Bereich | Core (WordPress.org-Forum) | Pro standard | Pro priority |
|---|---|---|---|
| Fehler im Plugin | ja, bestmöglich, ohne Reaktionszusage | ja | ja, bevorzugt |
| Einrichtung/Konfiguration | Doku | ja | ja |
| Anbieter-Setup (OAuth, API-Keys) | Doku | Hilfe nach Doku | Hilfe nach Doku, Screen-Session nach Vereinbarung |
| Migration aus anderen Review-Systemen | Doku | Hilfe nach Doku | aktive Unterstützung |
| Anbieter-API-Ausfall / Kontosperre | Statusinfo | Diagnose, Weiterleitung an Anbieter | wie standard, bevorzugt |
| Individuelle Anpassung / Connector-Entwicklung | nein | nein | nein (separates Angebot) |
| Rechtsberatung (Datenschutz, UWG, Marken) | nein | nein | nein |

## Reaktionsziele (Vorschlag, Werktage Mo–Fr, Zeitzone Europe/Vienna)

| Schweregrad | Beispiel | standard | priority |
|---|---|---|---|
| kritisch | Datenverlust, Sicherheitslücke, Website-Fatal durch Plugin | 1 Werktag | 4 Stunden innerhalb der Geschäftszeit |
| hoch | Sync/Anfrageversand fällt für alle Profile aus | 2 Werktage | 1 Werktag |
| normal | Konfigurationsfrage, einzelner Fehler | 2 Werktage | 1 Werktag |

Sicherheitsmeldungen über eine eigene Security-Adresse (OPS-009), Erstreaktion 2 Werktage unabhängig vom Plan.

## Offen / Freigabe

- Support-Team bestätigt Reaktionsziele und Geschäftszeiten.
- Festlegung der Security-Adresse und Veröffentlichung in `readme.txt` / `SECURITY.md`.
