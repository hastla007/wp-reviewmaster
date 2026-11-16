# Review Captain Demo Connector (CON-015)

Referenz-Connector für den [Connector-Vertrag v1](../../docs/connectors/contract-v1.md). Er ist ein **eigenständiges WordPress-Plugin**, das ohne Änderung am Core auskommt und keine fremden Datenbanktabellen liest oder schreibt.

## Was er zeigt

| Vertragselement | Umsetzung |
|---|---|
| Registrierung | `add_action( 'review_captain_connectors_v1', … )` – Klassen werden erst im Callback geladen (kein Fatal Error ohne Review Captain) |
| `experience_source` | `poll_experiences()` meldet **ein** abgeschlossenes Kurs-Erlebnis aus `fixtures/enrollments.json` |
| `context_provider` | `describe_context( 'course', 'C-101' )` liefert Kursname und öffentliche URL |
| Eigener Job | `demo-booking.reconcile` über den Filter `review_captain_process_job_v1`; Einreihen mit `rc_demo_connector_enqueue_reconcile()` über die Core-Job-API |
| Fehler | `ConnectorException` mit `rc.*`-Codes (`rc.source.unavailable`, `rc.data.invalid_payload`) |

Das Ereignis enthält nur Referenzen (`recipient_ref = demo-booking:participant:p-7f3a`), keine E-Mail-Adresse und keinen Namen.

## Installation

1. Ordner `review-captain-demo-connector` nach `wp-content/plugins/` kopieren.
2. Review Captain aktivieren, danach den Demo Connector.
3. **Review Captain → Diagnose → Connectoren** zeigt `demo-booking` als registriert und **inaktiv**. Die Aktivierung und automatische Ausführung erfolgt über Review Captain Pro (`review.custom_connectors`).

## Test

Der Core-Test `tests/Unit/Connectors/DemoConnectorTest.php` prüft, dass der Connector den Contract-Validator besteht und seine Ereignisse den Payload-Validator (`ExperienceEvent::validate`) bestehen.

## Grenzen

- Nur Beispieldaten; kein echtes Buchungssystem.
- Kein `recipient_resolver`: ohne Resolver kann Review Captain keine Einladung versenden. Wie ein Resolver aussieht, zeigt das [Connector-SDK](../connector-sdk/).
