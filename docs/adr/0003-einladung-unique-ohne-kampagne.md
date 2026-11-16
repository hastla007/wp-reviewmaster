# 0003 – Einladungs-Unique-Key ohne Kampagne

- **Status:** vorgeschlagen
- **Datum:** 2026-11-16
- **Betrifft:** Schema, Vertrag
- **Tasks:** RC-025, RC-087, TEC-004

## Kontext
TODO RC-025 nennt den Schlüssel experience_id + recipient_ref + campaign_id, verlangt aber zugleich höchstens eine Einladung je Erlebnis und Empfänger über alle Kampagnen. Mit Kampagne im Schlüssel könnten zwei Kampagnen dasselbe Erlebnis einladen.

## Entscheidung
UNIQUE `invite_key = sha256(experience_id|recipient_ref)`; Kampagne wird gespeichert, ist aber nicht Teil des Schlüssels. Konflikte werden nach Kampagnenpriorität aufgelöst.

## Folgen
TODO RC-025/RC-087 werden bei Freigabe sprachlich angepasst.
