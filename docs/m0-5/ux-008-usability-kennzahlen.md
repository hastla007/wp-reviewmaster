# UX-008 – Usability-Kennzahlen

**Status:** Entwurf  
**Stand:** 2026-11-16

## Grundsatz

**Keine Telemetrie an den Hersteller.** Kennzahlen werden ausschließlich lokal in der WordPress-Installation als Zeitstempel ohne Personenbezug gespeichert und nur angezeigt bzw. auf ausdrücklichen Wunsch im Diagnosebericht mitgegeben. In Nutzertests (UX-006, OPS-006) werden sie durch Beobachtung erhoben.

## Kennzahlen

| Kennzahl | Definition | Erhebung im Plugin | Ziel (Pilot) |
|---|---|---|---|
| Zeit bis zur ersten Verbindung | Aktivierung → erster erfolgreicher Verbindungstest einer Quelle (Pro) bzw. erster Profil-Link (Core) | Option `review_captain_milestones` mit `activated_at`, `first_source_at` (UTC, nur Zeitstempel) | Median < 10 min |
| Zeit bis zum ersten Widget | Aktivierung → erstes veröffentlichtes Widget (Block/Shortcode auf veröffentlichter Seite gefunden) | `first_widget_published_at` | Median < 15 min |
| Zeit bis zur ersten Review | Aktivierung → erste freigegebene Review | `first_review_at` | – (informativ) |
| Einrichtungsabbrüche | Setup-Schritt begonnen, nicht abgeschlossen, nach 7 Tagen nicht wieder aufgenommen | `setup_steps` mit Status je Schritt | < 30 % in Pilotgruppe |
| Fehlerzustände je Woche | Anzahl der UX-004-Zustände mit Code | aggregiert aus Diagnose (bereits vorhanden) | sinkend |

## Nicht erfasst

- Keine Nutzer-IDs, IPs, Klickpfade, Seitenaufrufe, Inhalte.
- Keine Übertragung an den Lizenzserver (auch nicht in Pro).

## Verwendung

- Setup-Checkliste im Dashboard nutzt die Meilensteine.
- Diagnosebericht enthält die Zeitstempel nur, wenn der Betreiber die Option „Einrichtungsdaten anhängen“ wählt.
- Pilot (OPS-006): Betreiber werden gebeten, den Diagnosebericht freiwillig zu teilen; Auswertung manuell.
