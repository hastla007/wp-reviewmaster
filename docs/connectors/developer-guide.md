# CON-018 – Developer Guide: eigenen Connector bauen

Status: v1, an den implementierten Stand angeglichen (`src/Connectors/**`), Stand 2026-11-16. Vertrag: [contract-v1.md](contract-v1.md) (verbindlich). Lauffähige Vorlagen:

- `examples/review-captain-demo-connector/` – Referenz-Connector `demo-booking` (Fähigkeiten `experience_source`, `context_provider`; Jobtyp `demo-booking.reconcile`, eingereiht mit `rc_demo_connector_enqueue_reconcile()`), geprüft durch `tests/Unit/Connectors/DemoConnectorTest.php`
- `examples/connector-sdk/` – kopierbares Skelett `my-connector` (Fähigkeiten `experience_source`, `recipient_resolver`, `context_provider`; `src/Connector.php`, `src/JobHandler.php`, Fixture `tests/fixtures/experience-completed.json`, Tests `tests/ConnectorTest.php`)

Das Beispiel unten (`acme-booking`) folgt demselben Aufbau; Dateinamen weichen bewusst vom SDK ab (`Reconcile_Job.php` statt `JobHandler.php`).

Dieser Guide führt durch ein vollständiges Beispiel: einen Connector für ein fiktives Kursbuchungs-Plugin „Acme Booking“, der abgeschlossene Kurse meldet, Empfänger auflöst, Kursnamen liefert und einen eigenen Abgleichjob ausführt.

> **Sicherheitshinweis:** Connectoren sind normaler PHP-Code ohne Sandbox. Review Captain begrenzt, welche Daten es annimmt und speichert – Ihr Code ist dafür verantwortlich, nur die vertraglich vorgesehenen Daten herauszugeben.

## 1. Voraussetzungen

- Review Captain (Core) aktiv; für die automatische Ausführung zusätzlich Review Captain Pro mit Entitlement `review.custom_connectors` ([licensing.md](licensing.md)).
- PHP und WordPress gemäß Review-Captain-Mindestversionen (Arbeitsannahme PHP 8.1, WP 6.5).
- Der Connector läuft auf **derselben** WordPress-Installation wie das Quellsystem. Remote-Systeme → Webhook/REST (nicht v1).

## 2. Verzeichnisstruktur

```
acme-review-connector/
├── acme-review-connector.php      Plugin-Header, Bootstrap
├── src/
│   ├── Connector.php              ConnectorInterface + Capability-Interfaces
│   └── Reconcile_Job.php          Handler für review_captain_process_job_v1
├── tests/
│   ├── fixtures/experience-completed.json
│   └── ConnectorTest.php
└── readme.txt
```

## 3. Plugin-Header und Bootstrap

Wichtig: Die Klasse, die `ConnectorInterface` implementiert, darf **erst geladen werden, wenn Review Captain aktiv ist** – sonst führt `implements` zu einem Fatal Error. Laden Sie die Datei deshalb innerhalb des Registry-Callbacks.

```php
<?php
/**
 * Plugin Name:       Acme Review Connector
 * Description:       Meldet abgeschlossene Acme-Booking-Kurse an Review Captain.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  review-captain
 * Author:            Acme GmbH
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-review-connector
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const ACME_RC_CONNECTOR_DIR = __DIR__;

add_action(
    'review_captain_connectors_v1',
    static function ( \ReviewCaptain\Connectors\Registry $registry ): void {
        if ( ! function_exists( 'acme_booking_get_enrollment' ) ) {
            return; // Quellsystem nicht aktiv: nicht registrieren (Core zeigt source_unavailable für früher registrierte Connectoren).
        }
        require_once ACME_RC_CONNECTOR_DIR . '/src/Connector.php';
        $registry->register( new \Acme\ReviewConnector\Connector() );
    }
);

add_filter(
    'review_captain_process_job_v1',
    static function ( $result, string $type, array $payload ) {
        if ( null !== $result || ! str_starts_with( $type, 'acme-booking.' ) ) {
            return $result;
        }
        require_once ACME_RC_CONNECTOR_DIR . '/src/Reconcile_Job.php';
        return ( new \Acme\ReviewConnector\Reconcile_Job() )->handle( $type, $payload );
    },
    10,
    3
);
```

`Requires Plugins` (WP ≥ 6.5) verhindert die Aktivierung ohne Review Captain; der Code bleibt trotzdem defensiv.

## 4. Connector-Klasse

```php
<?php
declare(strict_types=1);

namespace Acme\ReviewConnector;

use ReviewCaptain\Connectors\ConnectorException;
use ReviewCaptain\Connectors\Contract\ConnectorInterface;
use ReviewCaptain\Connectors\Contract\ContextProviderInterface;
use ReviewCaptain\Connectors\Contract\ExperienceSourceInterface;
use ReviewCaptain\Connectors\Contract\RecipientResolverInterface;

final class Connector implements
    ConnectorInterface,
    ExperienceSourceInterface,
    RecipientResolverInterface,
    ContextProviderInterface
{
    public function key(): string { return 'acme-booking'; }               // nie ändern
    public function label(): string { return __( 'Acme Booking', 'acme-review-connector' ); }
    public function version(): string { return '1.0.0'; }
    public function contract_version(): string { return '1.0'; }
    public function developer(): string { return 'Acme GmbH'; }

    public function capabilities(): array {
        return [ 'experience_source', 'recipient_resolver', 'context_provider' ];
    }

    /** Cursor = letzte verarbeitete Enrollment-ID (opak für Review Captain). */
    public function poll_experiences( string $cursor, int $limit ): array {
        $after = ctype_digit( $cursor ) ? (int) $cursor : 0;

        try {
            // Eigene API des Quellsystems – keine direkten Zugriffe auf Review-Captain-Tabellen.
            $rows = acme_booking_completed_enrollments( $after, $limit );
        } catch ( \Acme\Booking\TemporaryError $e ) {
            throw new ConnectorException( 'rc.temp.unavailable', 'Acme Booking temporär nicht erreichbar' );
        }

        $events = [];
        foreach ( $rows as $row ) {
            $events[] = [
                'contract'              => '1.0',
                'event_id'              => 'acme-enrollment-' . $row->id . '-' . $row->status,
                'event_type'            => $this->map_status( $row->status ),
                'experience_id'         => 'acme-enrollment-' . $row->id,   // ein Kursbesuch = ein Erlebnis
                'occurred_at'           => gmdate( 'c', (int) $row->status_changed_ts ),
                'object_type'           => 'course',
                'object_id'             => (string) $row->course_id,
                'recipient_ref'         => 'acme-customer:' . $row->customer_id, // keine E-Mail!
                'locale'                => $row->locale ?: null,
                'communication_allowed' => (bool) $row->review_mail_opt_in,
                'consent'               => [
                    'status' => $row->review_mail_opt_in ? 'granted' : 'unknown',
                    'source' => 'acme_booking_checkout',
                    'at'     => $row->opt_in_ts ? gmdate( 'c', (int) $row->opt_in_ts ) : null,
                ],
            ];
            $after = max( $after, (int) $row->id );
        }

        return [
            'events'      => $events,
            'next_cursor' => count( $rows ) < $limit ? null : (string) $after,
        ];
    }

    public function resolve( string $recipient_ref, string $purpose ): ?array {
        if ( ! in_array( $purpose, [ 'review_request', 'review_request_reminder' ], true ) ) {
            return null;
        }
        if ( ! preg_match( '/^acme-customer:(\d+)$/', $recipient_ref, $m ) ) {
            return null;
        }
        $customer = acme_booking_get_customer( (int) $m[1] );
        if ( null === $customer ) {
            return null; // unbekannt → Review Captain bricht ab
        }
        return [
            'email'      => $customer->email,
            'locale'     => $customer->locale ?: null,
            'permission' => $customer->review_mail_opt_in && ! $customer->unsubscribed ? 'allowed' : 'denied',
        ];
    }

    public function describe_context( string $context_type, string $context_id ): ?array {
        if ( 'course' !== $context_type ) {
            return null;
        }
        $course = acme_booking_get_course( (int) $context_id );
        if ( null === $course || ! $course->is_public ) {
            return null;
        }
        return [ 'label' => $course->title, 'url' => get_permalink( $course->page_id ) ?: null ];
    }

    private function map_status( string $status ): string {
        return match ( $status ) {
            'attended'  => 'experience_completed',
            'cancelled' => 'experience_cancelled',
            'refunded'  => 'experience_refunded',
            default     => throw new ConnectorException( 'rc.data.invalid_payload', 'Unbekannter Buchungsstatus' ),
        };
    }
}
```

Hinweise zum Mapping:

- **`event_id`** ist pro Statusänderung stabil (gleiche Abfrage → gleiche ID). So erkennt Review Captain Wiederholungen.
- **`experience_id`** steht für das tatsächliche Erlebnis (ein Kursbesuch). Mehrere Ereignisse zum selben Besuch führen zu höchstens einer Einladung.
- `poll_experiences` sollte **nur** die freigegebenen Status (`attended`, `cancelled`, `refunded`) liefern – keine beliebigen Änderungen.
- Ein einzelnes fehlerhaftes Ereignis sollte lieber ausgelassen (und intern protokolliert) als mit einer Exception die gesamte Seite blockieren. Die Exception im `match` oben ist bewusst konservativ; für Produktion empfiehlt sich `continue` mit eigenem Log.

## 5. Eigener Hintergrundjob

```php
<?php
declare(strict_types=1);

namespace Acme\ReviewConnector;

use ReviewCaptain\Connectors\ConnectorException;

final class Reconcile_Job {
    /** @return array{status: string} */
    public function handle( string $type, array $payload ): array {
        if ( 'acme-booking.reconcile' !== $type ) {
            throw new ConnectorException( 'rc.contract.incompatible', 'Unbekannter Jobtyp' );
        }
        if ( 1 !== ( $payload['v'] ?? null ) || ! isset( $payload['course_id'] ) ) {
            throw new ConnectorException( 'rc.data.invalid_payload', 'Payload-Version oder course_id fehlt' );
        }
        // Idempotent: mehrfaches Ausführen darf nichts doppelt tun.
        acme_booking_reconcile_course( (int) $payload['course_id'] );
        return [ 'status' => 'succeeded' ];
    }
}
```

- Jobtypen: `{connector_key}.{name}` → hier `acme-booking.reconcile`.
- Payload: JSON-serialisierbar, ≤ 64 KB, Feld `v`, keine Secrets/E-Mails/Reviewtexte.
- Jobs werden über die Core-Job-API eingereiht ([contract-v1.md §4](contract-v1.md#4-eigene-jobs-review_captain_process_job_v1)); nie direkt in die Tabelle `jobs` schreiben:

  ```php
  if ( class_exists( '\ReviewCaptain\Plugin' ) && \ReviewCaptain\Plugin::instance()->has( 'jobs' ) ) {
      \ReviewCaptain\Plugin::instance()->get( 'jobs' )->enqueue(
          'acme-booking.reconcile',
          [ 'v' => 1, 'course_id' => $course_id ],
          'acme-booking:reconcile:' . $course_id // Idempotenzschlüssel
      ); // int|false; vorhandener Schlüssel → bestehende Job-ID
  }
  ```

- Statt `['status' => 'succeeded']` darf der Handler auch `['status' => 'continue', 'payload' => [...], 'delay' => 60]` (Checkpoint) oder `['status' => 'retry', 'error_code' => 'rc.temp.unavailable', 'message' => '…']` zurückgeben. Bleibt das Ergebnis `null`, wird der Job mit `rc.contract.incompatible` pausiert und stündlich erneut versucht.

## 6. Fehlerbehandlung

| Situation | Werfen |
|---|---|
| Zeitüberschreitung des eigenen Systems | `rc.net.timeout` |
| Temporärer Fehler, Wartung | `rc.temp.unavailable` |
| Rate Limit (kurzfristig) | `rc.quota.rate_limited` |
| Tages-/Monatskontingent erschöpft | `rc.quota.exceeded` |
| API-Schlüssel/Login ungültig | `rc.auth.invalid` (Job wird pausiert, Retry-Klasse `auth`) |
| Fehlende Rechte im Zielsystem | `rc.auth.permission_denied` (Job pausiert) |
| Endgültiger Fehler, Wiederholung sinnlos | `rc.perm.failed` (Job `dead`) |
| Datensatz unbrauchbar | `rc.data.invalid_payload` |
| Datensatz in Quelle gelöscht | `rc.remote.removed` |
| Quell-Plugin deaktiviert/fehlt | `rc.source.unavailable` (Job pausiert, Retry-Klasse `config`) |
| Unbekannter eigener Jobtyp | `rc.contract.incompatible` (Job pausiert, Retry-Klasse `config`) |

Regeln: Fehlermeldungen kurz, für Admins verständlich, **ohne** personenbezogene Daten und Secrets. Keine `die()`/`exit`/`wp_die()`. Andere Exceptions fängt Review Captain zwar ab, sie werden aber als temporär (`rc.temp.unavailable`) gewertet und erzeugen Retries – werfen Sie deshalb gezielt `ConnectorException`. Unbekannte Codes in einer `ConnectorException` werden ebenfalls als `rc.temp.unavailable` behandelt. Die vollständige Liste mit Retry-Klassen steht in [error-codes.md](../architecture/error-codes.md).

## 7. Datenschutz-Checkliste

- [ ] Event-Payload enthält **keine** E-Mail, keinen Klarnamen, keine Bestell-/Zahlungsdaten; nur `recipient_ref`.
- [ ] `communication_allowed` ist nur `true`, wenn eine nachweisbare Versandberechtigung besteht (Opt-in bzw. dokumentierte Rechtsgrundlage); unbekannt = `false`.
- [ ] `resolve()` gibt nur `email`, `locale`, `permission` zurück und berücksichtigt Abmeldungen im eigenen System.
- [ ] `describe_context()` liefert nur öffentliche Bezeichnungen/URLs.
- [ ] Reviews über `review_source`: `consent_publish` korrekt, keine E-Mail, `platform=local` nur bei Abgabe auf dieser Website.
- [ ] Eigene Logs des Connectors enthalten keine Kontaktdaten.
- [ ] Datenschutzerklärung des Betreibers um den Datenfluss Quellsystem → Review Captain ergänzt (Textvorschlag im readme).
- [ ] Verhalten bei Deaktivierung dokumentiert (Review Captain pausiert; Connector löscht keine Review-Captain-Daten).

## 8. Tests

Mit dem SDK (`examples/connector-sdk/`) laufen Unit-Tests ohne WordPress, ausgeführt aus dem Review-Captain-Repository (die SDK-Tests gehören nicht zur Standard-Testsuite `tests/Unit` in `phpunit.xml.dist`):

```bash
vendor/bin/phpunit --bootstrap tests/bootstrap.php examples/connector-sdk/tests
```

Am robustesten prüfen Sie gegen die Core-Validatoren selbst – so macht es `examples/connector-sdk/tests/ConnectorTest.php`:

```php
$result = ( new \ReviewCaptain\Connectors\ContractValidator() )->validate( $connector );
self::assertSame( \ReviewCaptain\Connectors\ContractValidator::OUTCOME_OK, $result['outcome'] );
self::assertSame( [], \ReviewCaptain\Connectors\ExperienceEvent::validate( $event ) );
```

Minimalbeispiel ohne Core-Validator:

```php
final class ConnectorTest extends \PHPUnit\Framework\TestCase {
    public function test_key_matches_contract(): void {
        self::assertMatchesRegularExpression( '/^[a-z0-9][a-z0-9_-]{2,63}$/', ( new Connector() )->key() );
    }

    public function test_capabilities_have_interfaces(): void {
        $map = [
            'experience_source'  => \ReviewCaptain\Connectors\Contract\ExperienceSourceInterface::class,
            'recipient_resolver' => \ReviewCaptain\Connectors\Contract\RecipientResolverInterface::class,
            'review_source'      => \ReviewCaptain\Connectors\Contract\ReviewSourceInterface::class,
            'request_transport'  => \ReviewCaptain\Connectors\Contract\RequestTransportInterface::class,
            'context_provider'   => \ReviewCaptain\Connectors\Contract\ContextProviderInterface::class,
        ];
        $c = new Connector();
        foreach ( $c->capabilities() as $cap ) {
            self::assertInstanceOf( $map[ $cap ], $c );
        }
    }

    public function test_poll_is_repeatable_and_has_no_email(): void {
        $c     = new Connector();
        $first = $c->poll_experiences( '', 10 );
        self::assertSame( $first, $c->poll_experiences( '', 10 ) );
        foreach ( $first['events'] as $e ) {
            self::assertStringNotContainsString( '@', $e['recipient_ref'] );
        }
    }
}
```

Zusätzlich empfohlen (siehe [contract-tests.md §E](contract-tests.md#e-connector-seite-für-drittentwickler-sdk)): Fixtures gegen den Payload-Validator, Idempotenz von Jobs und `send()`, Laden ohne aktives Review Captain.

Manueller Test (Pro): **Review Captain → Integrationen → Kundenspezifische Connectoren** → Connector aktivieren → „Testlauf“ (Dry-Run zeigt normalisierte Payload und Entscheidungen ohne Versand).

## 9. Release und Kompatibilität

- `key()` niemals ändern – er ist Teil gespeicherter Dedupe-Schlüssel und Jobtypen.
- `contract_version()` auf die niedrigste benötigte Minor-Version setzen (`1.0`), nicht `1.x`, wenn Sie Minor-Features nutzen.
- Neue Payload-Versionen eigener Jobs über das Feld `v` einführen und alte Versionen noch mindestens eine Release-Periode verarbeiten.
- Changelog: Änderungen an Mapping (`event_id`/`experience_id`-Bildung) sind **breaking** für Deduplizierung und erfordern eine Migrationsnotiz.
- Vor Veröffentlichung: [release-gate.md](release-gate.md) Abschnitt „Drittanbieter-Connector“.
