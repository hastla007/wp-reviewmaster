# CON-006 – Lizenzgrenze für Connectoren

Status: **Konzeptvorgabe** (konzept.md §6 und §7.10). Die finale kommerzielle Bestätigung erfolgt mit **RC-005** und ist offen. Bis dahin ist diese Aufteilung Arbeitsgrundlage für Core und Pro; der Core enthält in keinem Fall Lizenzcode.

## Aufteilung

| Bestandteil | Produkt | Entitlement |
|---|---|---|
| Öffentlicher Vertrag (`ConnectorInterface`, Capability-Interfaces, `ConnectorException`) | Core (frei) | – |
| Registry `review_captain_connectors_v1` inkl. Validator (Schlüssel, Contract-Version, Fähigkeiten) | Core (frei) | – |
| Read-only-Diagnose im Core: registrierte Connectoren, Version, Contract-Kompatibilität, Fähigkeiten | Core (frei) | – |
| Dokumentation, Demo-Connector, Connector-SDK | Core-Repository (frei) | – |
| Generischer Connector-Manager (Statuskarten, Aktivieren/Pausieren/Trennen, Konfiguration, Testlauf/Dry-Run, Queue- und Fehlerdiagnose, Wiederholen) | Pro | `review.custom_connectors` |
| Automatisierte Runtime: Polling von Experience/Review Sources, Recipient Resolver im Versandfluss, Request Transport, Context-Abruf, Connector-Jobs über `review_captain_process_job_v1` | Pro | `review.custom_connectors` |
| Offizielle Captain-Suite-Adapter (WooCommerce, Voucher/Pass/Affiliate/Form Captain) | Pro | `review.suite_integrations` |
| Cross-Site Webhooks/REST | Pro (später) | eigenes Integrations-Entitlement, offen |

## Verhalten ohne bzw. mit abgelaufenem Entitlement

- Core-Funktionen, vorhandene Reviews (auch `origin=connector`), Anzeige und Export bleiben vollständig nutzbar.
- Connectoren werden weiterhin registriert und in der Core-Diagnose angezeigt; es werden **keine** Connector-Methoden ausgeführt (außer Metadaten bei der Registrierung).
- Wartende Connector-Jobs gehen in `paused` (nicht `dead`), damit sie nach Reaktivierung der Lizenz nach erneuter Prüfung (Vertrag, Consent, Fristen) fortgesetzt werden können.
- Keine Lizenzabfrage pro Frontend-Request; Pro prüft gegen den signierten Lizenz-Snapshot.
- Ein Connector kann eigene Oberflächen mitbringen; er darf die Pro-Runtime nicht nachbauen, um das Entitlement zu umgehen (Lizenzbedingung, technisch nicht erzwingbar – siehe „keine Sandbox“).

## Technische Kopplung

- Pro registriert sich über `review_captain_loaded` und schaltet die Runtime frei; der Core kennt Pro nicht.
- Der Core fragt keine Entitlements ab. Die Ausführungsentscheidung („darf laufen“) trifft Pro über einen Core-Hook bzw. dadurch, dass nur Pro die Connector-Jobs einreiht.

## Offen (RC-005)

- Bestätigung, dass `review.custom_connectors` in allen bezahlten Plänen enthalten ist (Konzept: alle Pro-Funktionen in jedem Plan).
- Katalogeintrag der Feature-Schlüssel im Lizenzserver (`wp-license`).
- Entitlement für Cross-Site-Verbindungen.
- Ob einfache Context-Provider-Labels ohne Pro im Core genutzt werden dürfen (Arbeitsannahme: nein, Runtime = Pro).
