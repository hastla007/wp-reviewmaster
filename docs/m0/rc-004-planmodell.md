# RC-004 – Pro-Planmodell

**Status:** Planstruktur **freigegeben** (Entscheidung der Inhaberin am 16.11.2026); **Preise offen** (Inhaberin legt sie später fest)  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §6 Lizenzvertragsvorschlag](../../konzept.md), [RC-005](rc-005-entitlements.md), [RC-007](rc-007-support-release.md)

## Planstruktur (übernommen aus der Suite-Lizenzregel)

> **Abgleich 2026-11-16:** Suite-weit gelten die Plan-Schlüssel `single` / `business` / `agency` (Commit e948151 im Core-Branch, captain-pro `docs/TARIFE.md`). Alle Preise sind netto zzgl. USt.; für Review Captain Pro ist noch **kein** Preis genannt (captain-pro TODO E1/E9).

| Plan-ID | Live-Websites | Supportlevel | Funktionsumfang | Preis |
|---|---|---|---|---|
| `single` | 1 | `standard` | alle Pro-Funktionen der jeweiligen Version | **offen – Shopentscheidung** |
| `business` | 5 | `standard` | alle Pro-Funktionen | **offen – Shopentscheidung** |
| `agency` | 25 | `priority` | alle Pro-Funktionen | **offen – Shopentscheidung** |

Regeln:

- Plan-IDs und Site-Limits identisch zu Form Captain, Pass Captain Pro und Voucher Captain Pro.
- Pläne unterscheiden sich nur nach Live-Website-Limit und Support, nicht nach Funktionen. Jeder Plan erhält alle in RC-005 freigegebenen `review.*`-Rechte.
- Staging-/Development-Aktivierungen zählen gemäß WP License Captain nicht als Live-Website.
- Laufzeit: jährlich mit Verlängerung (Suite-Standard). Nach Ablauf gilt RC-061 (keine Datenlöschung, Pro-Aktionen gestoppt, Updates/Support enden).
- Kein Lifetime-Plan und keine Funktionsstaffel ohne gesonderte Produktentscheidung.

## Vorschlag Supportstufen (Abstimmung mit RC-007)

| Stufe | Kanal | Erstreaktion (Werktage, Ziel) | Umfang |
|---|---|---|---|
| `standard` | Ticket/E-Mail | 2 Werktage | Installation, Konfiguration, Fehleranalyse Plugin, Hilfe bei Anbieter-Setup gemäß Doku |
| `priority` | Ticket/E-Mail mit Priorisierung | 1 Werktag | wie standard, plus bevorzugte Bearbeitung und Unterstützung bei Migration aus anderen Review-Systemen |

Nicht enthalten in allen Stufen: Beantragung von Anbieter-API-Zugängen im Namen des Kunden, Rechtsberatung, individuelle Entwicklung von Connectoren.

## Shop-Aufgaben (für RC-058)

- [ ] Preise je Plan festlegen und mit übrigen Captain-Pro-Produkten abstimmen.
- [ ] Verlängerungsrabatt/-modell bestätigen.
- [ ] Upgrade-Pfad single → business → agency (anteilige Verrechnung) bestätigen.
- [ ] Produktbeschreibung darf nur Plattformen mit bestandener Abnahme als „Synchronisierung“ bewerben (siehe RC-002).

## Offen / Freigabe

- Shop/Vertrieb: Preise, Verlängerung, Upgrade.
- Support: Reaktionsziele bestätigen.
