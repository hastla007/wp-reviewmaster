# Connectoren – Dokumentationsindex

Status: Spezifikation für Connector-Vertrag **v1** (Stand 2026-11-16). Grundlage: [konzept.md §7.10](../../konzept.md#710-kundenspezifische-projekt-connectoren), [TODO.md „Kundenspezifische Projekt-Connectoren“](../../TODO.md#kundenspezifische-projekt-connectoren) und die verbindlichen [Konventionen](../architecture/conventions.md).

Ein Connector ist ein **separates WordPress-Plugin**, das sich über einen versionierten öffentlichen PHP-Vertrag bei Review Captain registriert und kundenspezifische Systeme derselben WordPress-Installation anbindet (Buchung, CRM, ERP, Kurse, Mitglieder …). Der Core wird dafür nicht verändert.

| Dokument | Task | Inhalt |
|---|---|---|
| [use-cases.md](use-cases.md) | CON-001 | Fähigkeiten, Anwendungsfälle, Abgrenzung Suite-Adapter / lokale Connectoren / Webhooks |
| [contract-v1.md](contract-v1.md) | CON-002, CON-003 (inkl. Payloads CON-004) | Registry-Hook, `ConnectorInterface`, Capability-Interfaces, Payloads, Versionierung, Deprecation |
| [security-data-matrix.md](security-data-matrix.md) | CON-005 | Datenfelder, Rechte, Consent, Retention/Erase, Logging, Retry, Disconnect je Fähigkeit |
| [licensing.md](licensing.md) | CON-006 | Free/Pro-Grenze (Konzeptvorgabe, finale Bestätigung RC-005 offen) |
| [contract-tests.md](contract-tests.md) | CON-007 | Testfälle für Registry, Validator, Payloads, Jobs, Idempotenz |
| [developer-guide.md](developer-guide.md) | CON-018 | vollständiges Beispiel: Plugin-Header, Registrierung, Jobs, Fehler, Datenschutz, Tests |
| [release-gate.md](release-gate.md) | CON-019 | Abnahmebedingungen vor Pro 1.0 |

Code-Beispiele im Repository:

- `examples/review-captain-demo-connector/` – lauffähiger Referenz-Connector (CON-015)
- `examples/connector-sdk/` – kopierbares SDK-Skelett (CON-017)

## Kurzfassung des Vertrags

```php
add_action( 'review_captain_connectors_v1', function ( \ReviewCaptain\Connectors\Registry $registry ): void {
    $registry->register( new My_Connector() ); // implements ConnectorInterface + Capability-Interfaces
} );
```

- Namespace der Interfaces: `ReviewCaptain\Connectors\Contract\`
- Fehler: `throw new \ReviewCaptain\Connectors\ConnectorException( 'rc.temp.unavailable', 'CRM antwortet nicht' );`
- Eigene Hintergrundjobs: Filter `review_captain_process_job_v1`
- Sicherheitsmodell: PHP-Connectoren sind **keine Sandbox** – nur vertrauenswürdige, geprüfte Plugins installieren.
