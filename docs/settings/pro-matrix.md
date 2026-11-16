# Einstellungs- und Pro-Rechte-Matrix (SET-006)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Entitlements laut konzept.md §6 (Katalog noch nicht final, RC-005).

## 1. Grundregeln

1. Der Core enthält **keinen** Lizenzcode und prüft keine Entitlements. Er kennt nur „Feld ist von einem Add-on registriert / nicht registriert“.
2. Pro registriert Felder und Gating über den Filter `review_captain_settings_schema`. Jedes Pro-Feld deklariert `owner => 'pro'` und `entitlement => 'review.*'`.
3. Pro-Felder des Tabs `requests` sind im Core **sichtbar gesperrt** (statische Beschreibung aus dem Core-Schema), damit Betreiber den Umfang verstehen.
4. **Kein Core-Feld wird durch Lizenzstatus verändert, geleert oder gesperrt.**
5. Pro-Felder bleiben bei Lizenzablauf, Deaktivierung oder Deinstallation von Pro **gespeichert** und unverändert; sie werden nur nicht angewendet. Reaktivierung stellt den letzten Stand her.
6. Ein Entitlement steuert genau eine Pro-Funktion; keine leeren/geplanten Rechte.

## 2. Matrix je Feld

| Feld | Owner | Entitlement | Ohne Pro | Pro aktiv, Entitlement fehlt/abgelaufen | Pro aktiv + Entitlement |
|---|---|---|---|---|---|
| `general.*` | Core | — | bearbeitbar | bearbeitbar | bearbeitbar |
| `sources.*` | Core | — | bearbeitbar (wirkt für Core-Connectoren) | bearbeitbar | bearbeitbar, wirkt auch für Pro-Provider |
| `requests.sender_name` | Pro | `review.review_requests` | gesperrt sichtbar | gesperrt, Wert sichtbar, unverändert | bearbeitbar |
| `requests.sender_email` | Pro | `review.review_requests` | gesperrt sichtbar | gesperrt, unverändert | bearbeitbar |
| `requests.send_window_start/end` | Pro | `review.review_requests` | gesperrt sichtbar | gesperrt, unverändert | bearbeitbar |
| `requests.daily_limit` | Pro | `review.review_requests` | gesperrt sichtbar | gesperrt, unverändert | bearbeitbar |
| `requests.cooldown_days` | Pro | `review.review_requests` | gesperrt sichtbar | gesperrt, unverändert | bearbeitbar |
| `requests.reminders_enabled` | Pro | `review.review_requests` | gesperrt sichtbar | gesperrt, unverändert; keine Erinnerungen | bearbeitbar |
| `submissions.*` | Core | — | bearbeitbar | bearbeitbar | bearbeitbar |
| `display.*` | Core | — | bearbeitbar | bearbeitbar | bearbeitbar |
| `privacy.*` | Core | — | bearbeitbar | bearbeitbar | bearbeitbar |
| `access.*` | Core | — | bearbeitbar | bearbeitbar | bearbeitbar |
| `advanced.*` | Core | — | bearbeitbar | bearbeitbar | bearbeitbar |

## 3. Erwartete Pro-Ergänzungen (nicht im Core-Schema; Pro definiert)

| Tab | Feld (Arbeitstitel) | Entitlement |
|---|---|---|
| requests | Testempfänger, Bounce-/Beschwerdeverhalten, Versandweg (SMTP/Dienst) | `review.review_requests` |
| privacy | Aufbewahrung Einladungs-/Empfängerdaten, Medien-Testimonials | `review.review_requests` / OFFEN (Testimonials-Entitlement nicht im Katalogvorschlag) |
| sources | Standard-Sync-Intervall neuer Profile | `review.platform_sync` |
| display | Pro-Layoutvoreinstellungen | `review.advanced_widgets` |
| advanced | Connector-Queue-Diagnose | `review.custom_connectors` |

Objekt-Einstellungen: Profil-Verbindungen (`review.platform_sync`), Kampagnen (`review.review_requests`), Pro-Widgets (`review.advanced_widgets`), Integrationen (`review.suite_integrations`, `review.custom_connectors`).

## 4. Prüfpunkte Lizenzablauf

| Situation | Erwartung |
|---|---|
| Lizenz läuft ab | keine neuen Syncs/Versände; Reviews bleiben sichtbar/exportierbar; alle Einstellungen unverändert |
| Pro deaktiviert | `requests`-Werte bleiben in der Option; Core zeigt Tab gesperrt; Core-Read normalisiert, entfernt sie aber nicht |
| Pro deinstalliert | Pro-Uninstall entscheidet über Pro-Daten; Core-Felder werden nie angefasst |
| Core-Reset ohne Pro | `requests` bleibt erhalten |

## Abnahme

Entspricht SET-006, wenn:

- [ ] für jedes Feld Owner (Core/Pro) und Entitlement festgelegt sind;
- [ ] ein Test belegt, dass Lizenzablauf/Deaktivierung von Pro kein Core-Feld und kein Pro-Feld verändert oder leert;
- [ ] gesperrte Pro-Felder Funktion und Lizenzvoraussetzung erklären;
- [ ] die Pro-Ergänzungen mit dem finalen Entitlement-Katalog (RC-005) abgeglichen sind.
