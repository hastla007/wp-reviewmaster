# CON-007 – Contract- und Kompatibilitätstests

Status: Testspezifikation v1, Stand 2026-11-16. Umsetzung: PHPUnit in `tests/unit` (ohne WordPress, Brain Monkey/Stubs). Connector-Entwickler können die Tests „Connector-Seite“ (Abschnitt C) mit dem SDK (`examples/connector-sdk/`) gegen ihren eigenen Connector laufen lassen.

Test-IDs: `CT-<Bereich>-<Nr>`. Jeder Test nennt erwartetes Ergebnis. „Diagnose“ = Registry-Status für die Admin-Anzeige.

## A. Registry und Validator (Core)

| ID | Fall | Erwartung |
|---|---|---|
| CT-R-01 | Gültiger Connector mit einer Capability wird über `review_captain_connectors_v1` registriert | registriert, Status kompatibel, **inaktiv** |
| CT-R-02 | Objekt ohne `ConnectorInterface` wird an `register()` übergeben (über `TypeError`-sicheren Pfad/`mixed`) | verworfen, kein Fatal Error |
| CT-R-03 | `key()` = `ab` (zu kurz), `Ab-c` (Großbuchstabe), `-abc` (Startzeichen), 65 Zeichen, `ab c` | jeweils verworfen, `rc.data.invalid_payload` |
| CT-R-04 | `key()` = `abc`, `a_b-c`, 64 Zeichen gültig | registriert |
| CT-R-05 | Zwei Connectoren mit gleichem Schlüssel | erster bleibt, zweiter verworfen, Diagnose „doppelter Schlüssel“ |
| CT-R-06 | Reservierter Schlüssel (`woocommerce`, `rc-test`) | verworfen |
| CT-R-07 | `contract_version()` = `1.0`, `1.7`, `1.x` | kompatibel |
| CT-R-08 | `contract_version()` = `2.0`, `0.9`, `1`, `v1.0`, `` | `incompatible_contract`, nicht ausführbar, kein Fatal Error |
| CT-R-09 | `contract_version()` = `1.99` (neuer als Core) | kompatibel, Diagnosehinweis |
| CT-R-10 | `capabilities()` leer | verworfen |
| CT-R-11 | `capabilities()` enthält `foo_bar` | `incompatible_contract` |
| CT-R-12 | `capabilities()` enthält `experience_source`, Klasse implementiert `ExperienceSourceInterface` nicht | `incompatible_contract` |
| CT-R-13 | Klasse implementiert `ReviewSourceInterface`, Capability nicht deklariert | registriert, `fetch_reviews` wird nie aufgerufen |
| CT-R-14 | Doppelte Capability-Werte | dedupliziert oder verworfen (deterministisch), kein Fehler zur Laufzeit |
| CT-R-15 | `label()`/`developer()`/`version()` leer oder > Grenzwert | verworfen |
| CT-R-16 | Metadaten-Methode wirft `RuntimeException` | verworfen, Diagnose, Request läuft weiter |
| CT-R-17 | `label()` enthält `<script>` | Ausgabe im Admin escaped |
| CT-R-18 | Hook wird pro Request genau einmal ausgelöst, auch bei mehrfachem Zugriff auf die Registry | einmal |
| CT-R-19 | Review Captain inaktiv (Hook nie ausgelöst) – Demo-Connector geladen | kein Fehler, keine Warnung |

## B. Capability-Payloads (Core-Validator)

### Experience Source

| ID | Fall | Erwartung |
|---|---|---|
| CT-E-01 | Gültiges `experience_completed`-Event | angenommen, genau eine Einladung geplant (sofern Kampagne passt) |
| CT-E-02 | Pflichtfeld fehlt (`event_id`, `experience_id`, `occurred_at`, `communication_allowed`, `consent`) | Event verworfen, `rc.data.invalid_payload`, übrige Events derselben Seite verarbeitet |
| CT-E-03 | `event_type` unbekannt | verworfen |
| CT-E-04 | `occurred_at` ohne Zeitzone / ungültig | verworfen |
| CT-E-05 | `recipient_ref` enthält `@` | verworfen |
| CT-E-06 | Gleiches `event_id` zweimal (gleiche oder spätere Seite) | zweites ignoriert, keine zweite Einladung |
| CT-E-07 | Zwei verschiedene `event_id`, gleiche `experience_id` + Empfänger | höchstens eine Einladung |
| CT-E-08 | `experience_completed`, danach `experience_refunded` vor Versand | geplante Einladung storniert |
| CT-E-09 | `experience_cancelled` nach Versand | keine Erinnerung mehr; nichts zurückgeholt |
| CT-E-10 | `communication_allowed=false` | keine Einladung, Status „blockiert: keine Berechtigung“ |
| CT-E-11 | `consent.status=unknown`, Kampagne verlangt Consent | keine Einladung |
| CT-E-12 | Verspätetes Event (älter als Kampagnen-Höchstalter) | nicht eingeplant |
| CT-E-13 | Mehr Events als `$limit` geliefert | Überschuss verworfen, Fehler protokolliert |
| CT-E-14 | `next_cursor=null` | letzter Cursor bleibt gespeichert, nächstes Polling mit diesem Cursor |
| CT-E-15 | Cursor > 191 Zeichen | `rc.data.invalid_payload`, Cursor nicht übernommen |
| CT-E-16 | Rückgabe ohne `events`-Schlüssel / kein Array | ganze Seite verworfen, Retry-Klasse permanent |

### Recipient Resolver

| ID | Fall | Erwartung |
|---|---|---|
| CT-RR-01 | `permission=allowed`, gültige E-Mail | Versand fortgesetzt |
| CT-RR-02 | `permission=denied` / `unknown` | kein Versand |
| CT-RR-03 | Rückgabe `null` | kein Versand, `recipient_unknown`, kein Retry |
| CT-RR-04 | Ungültige E-Mail | kein Versand, `rc.data.invalid_payload` |
| CT-RR-05 | Empfänger steht in Suppression-Liste, `permission=allowed` | kein Versand (Core-Regel gewinnt) |
| CT-RR-06 | E-Mail erscheint nicht in `jobs.payload`, Log, Audit, Diagnoseexport | geprüft per Snapshot |

### Review Source

| ID | Fall | Erwartung |
|---|---|---|
| CT-RS-01 | Gültige Review | gespeichert mit `origin=connector`, `status=pending` |
| CT-RS-02 | Connector liefert `status=approved` | ignoriert, `pending` |
| CT-RS-03 | Gleiche `external_id` erneut, unverändert | keine Dublette, keine Änderung |
| CT-RS-04 | Gleiche `external_id`, geänderter Text, zuvor `approved` | Inhalt aktualisiert, zurück auf `pending` |
| CT-RS-05 | `removed=true` | `status=removed`, Tombstone |
| CT-RS-06 | `rating_value` außerhalb Skala | verworfen |
| CT-RS-07 | `consent_publish=false` | gespeichert, aber nicht veröffentlichbar |
| CT-RS-08 | `body` mit `<script>`/`onerror` | durch `wp_kses` entfernt |
| CT-RS-09 | `platform=google` | verworfen (nur `local`/`other` erlaubt) |
| CT-RS-10 | `author_email` im Payload | Feld ignoriert, nicht gespeichert |

### Request Transport

| ID | Fall | Erwartung |
|---|---|---|
| CT-T-01 | `status=accepted`, `provider_message_id` | Anfrage „versendet“, ID gespeichert |
| CT-T-02 | `status=rejected` | kein Retry, Status „abgelehnt“ |
| CT-T-03 | `ConnectorException('rc.net.timeout')` | Retry mit **identischem** `idempotency_key` |
| CT-T-04 | Unbekannter `status` | wie `rc.data.invalid_payload`, kein erneuter Versand ohne Admin |
| CT-T-05 | Nach `accepted` wird Job durch Lease-Ablauf erneut geclaimt | `send()` nicht erneut aufgerufen |

### Context Provider

| ID | Fall | Erwartung |
|---|---|---|
| CT-C-01 | Gültige Rückgabe | Label gecacht |
| CT-C-02 | `null` | generisches Label, kein Fehler |
| CT-C-03 | `url` mit `javascript:` | verworfen |
| CT-C-04 | Frontend-Request | Methode wird nicht aufgerufen (nur Cache) |

## C. Fehlercodes und Jobs

| ID | Fall | Erwartung |
|---|---|---|
| CT-J-01 | `ConnectorException` mit jedem Code aus `ErrorCode` | Retry-Klasse gemäß `ErrorCode` |
| CT-J-02 | `ConnectorException('rc.foo.bar')` (unbekannt) | wie `rc.temp.unavailable` |
| CT-J-03 | Andere `Throwable` (`TypeError`, `Error`) | gefangen, `rc.temp.unavailable`, kein Fatal |
| CT-J-04 | Fehlermeldung enthält E-Mail/Token-ähnliche Zeichenfolgen, > 500 Zeichen | sanitisiert und gekürzt in `last_error_message` |
| CT-J-05 | `rc.auth.invalid` | Connector pausiert, Admin-Hinweis |
| CT-J-06 | `rc.source.unavailable` / Quell-Plugin deaktiviert | Jobs `paused`, Status `source_unavailable`; Resume validiert erneut |
| CT-J-07 | `review_captain_process_job_v1` – Handler liefert `['status'=>'succeeded']` | Job `succeeded` |
| CT-J-08 | Kein Handler (Ergebnis `null`) | `failed`, nach `max_attempts` `dead` bzw. pausiert |
| CT-J-09 | Handler für fremdes Präfix | Core ignoriert Ergebnis für fremde Jobtypen (nur Präfix des registrierten Schlüssels zulässig) |
| CT-J-10 | Gleicher Job zweimal geclaimt (parallele Runner) | nur ein Claim erfolgreich (`rows_affected === 1`) |
| CT-J-11 | Gleicher `idempotency_key` zweimal eingereiht | ein Job |
| CT-J-12 | Payload > 64 KB oder mit Feld `email` | abgelehnt |
| CT-J-13 | Frontend-Request | keine Connector-Jobs ausgeführt |
| CT-J-14 | Entitlement fehlt (Pro) | Jobs `paused`, keine Methodenaufrufe |

## D. Versionierung/Deprecation

| ID | Fall | Erwartung |
|---|---|---|
| CT-V-01 | Connector 1.0 auf Core mit Contract 1.n | voll funktionsfähig |
| CT-V-02 | Veraltetes Element benutzt | `_doing_it_wrong()` nur für Admins/Debug, Funktion bleibt |
| CT-V-03 | Snapshot der öffentlichen Signaturen (Reflection) | unverändert gegenüber `contract-v1.md`; Abweichung lässt Test scheitern |

## E. Connector-Seite (für Drittentwickler, SDK)

| ID | Fall |
|---|---|
| CT-S-01 | `key()` erfüllt Regex und ist konstant zwischen Aufrufen |
| CT-S-02 | Jede deklarierte Capability hat ihr Interface |
| CT-S-03 | Metadaten-Methoden ohne DB-/HTTP-Zugriff |
| CT-S-04 | Fixture-Events (`tests/fixtures/*.json` im SDK) bestehen den Payload-Validator |
| CT-S-05 | Wiederholtes `poll_experiences` mit gleichem Cursor liefert dieselben `event_id` |
| CT-S-06 | `send()` mit gleichem `idempotency_key` versendet höchstens einmal |
| CT-S-07 | Fehler werden als `ConnectorException` mit gültigem `rc.*`-Code geworfen |
| CT-S-08 | Plugin lädt ohne aktives Review Captain fehlerfrei |
