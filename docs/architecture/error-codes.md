# Fehlercodes und Integrationsstatus (RC-030)

| | |
|---|---|
| Status | an den implementierten Stand angeglichen (`src/Support/ErrorCode.php`), Stand 2026-11-16; wo dieses Dokument und der Code abweichen, gilt der Code |
| Format | `rc.<bereich>.<code>` (conventions.md); Bereich und Code `[a-z_]+` |
| Implementierung | `ReviewCaptain\Support\ErrorCode` (Konstanten + `retry_class()`, `is_known()`, `all()`, `admin_action()`) |
| Bezug | TODO RC-030, RC-082, CON-013, TEC-006; konzept.md 7.5, 11 |

## Regeln

- Codes sind **stabile öffentliche Werte** (Hooks, Diagnoseexport, `jobs.last_error_code`, `requests.status_code`). Umbenennen nur per ADR mit Alias-Frist.
- Ein Code hat genau **eine** Retry-Klasse. Unbekannte Codes: `retry_class()` liefert `transient`; `ConnectorException::error_code()` und die Jobauswertung normalisieren auf `rc.temp.unavailable`, `ProviderException`/`ProviderResult::error()` auf `rc.perm.failed`. Der Originalcode bleibt über `raw_error_code()` für die Diagnose erhalten.
- Hinweis zur Implementierung: `ErrorCode::all()` und `is_known()` berücksichtigen nur Werte im Format `rc.<bereich>.<code>`; die Klassennamen (`transient`, `rate_limit`, …) sind keine Fehlercodes.
- `last_error_message` ist sanitisiert, max. 500 Zeichen, ohne Tokens, Header, Reviewtexte, E-Mail-Adressen.
- Admin-Texte sind übersetzbar (`review-captain`); der Code wird zusätzlich angezeigt (Support).

## Retry-Klassen

Exakt nach `ErrorCode::CLASS_*` und `JobQueue::fail()` (Details: [jobs.md](jobs.md) §6):

| Klasse | Verhalten (Jobs, TEC-006) | `attempts` | Endzustand |
|---|---|---|---|
| `transient` | Exponentielles Backoff mit ±20 % Jitter: `min(6 h, 60 s × 3^(n−1))` → 1, 3, 9, 27, 81, 243, 360 min | +1 je Versuch, `max_attempts` Default 8 | `dead` |
| `rate_limit` | `retry_after` beachten (60 s … 24 h); ohne Angabe 15 min | nicht erhöht; Zähler `_rl_deferrals` | `dead` nach mehr als `max(48, 3 × max_attempts)` Verschiebungen |
| `auth` | kein Retry; Job `paused` | – | wartet auf Admin (Reconnect/Schlüssel), Fortsetzen per `retry()`/`resume_paused()` |
| `config` (neu) | kein Retry; Job `paused`; automatische Wiederaufnahme höchstens einmal pro Stunde (Handler/Connector evtl. wieder da) | – | wartet auf Quell-Plugin/kompatiblen Connector |
| `permanent` | kein Retry | – | Job `dead` |
| `data` | kein Retry; Job `dead`. Einzelne ungültige Items überspringt der Aufrufer und zählt sie (z. B. `SyncService` → `invalid`) | – | Job `dead` bzw. Item verworfen |

Ausblick (nicht in v1): einmaliger automatischer Token-Refresh bei `rc.auth.expired`, Verbindungsstatus `rate_limited`/`auth_required`, Profilwarnung ab 20 % ungültigen Items.

## Codes (implementiert in `ReviewCaptain\Support\ErrorCode`)

Exakt die Konstanten aus `src/Support/ErrorCode.php`; Retry-Klasse laut `ErrorCode::retry_class()`, Admin-Aktion laut `ErrorCode::admin_action()` (je Klasse).

| Konstante | Code | Bedeutung | Retry-Klasse |
|---|---|---|---|
| `AUTH_INVALID` | `rc.auth.invalid` | Zugangsdaten ungültig oder abgelehnt (HTTP 401 ohne Refresh-Möglichkeit) | auth |
| `AUTH_EXPIRED` | `rc.auth.expired` | Token abgelaufen, Refresh möglich (HTTP 401 mit `$token_expired`) | auth |
| `AUTH_REVOKED` | `rc.auth.revoked` | Zugriff beim Anbieter widerrufen | auth |
| `PERMISSION_DENIED` | `rc.auth.permission_denied` | fehlende Berechtigung/Tarif/Scope (HTTP 403) | auth |
| `QUOTA_EXCEEDED` | `rc.quota.exceeded` | Tages-/Monatskontingent des Anbieters erschöpft | rate_limit |
| `RATE_LIMITED` | `rc.quota.rate_limited` | Anbieter-Rate-Limit (HTTP 429) | rate_limit |
| `BUDGET_EXHAUSTED` | `rc.quota.budget_exhausted` | lokales API-Budget (`Jobs\Budget`) erreicht (TEC-007) | rate_limit |
| `NET_TIMEOUT` | `rc.net.timeout` | Zeitüberschreitung / Verbindungsfehler (HTTP-Status 0) | transient |
| `NET_UNREACHABLE` | `rc.net.unreachable` | Gegenstelle nicht erreichbar (DNS/Verbindungsaufbau) | transient |
| `TEMP_UNAVAILABLE` | `rc.temp.unavailable` | vorübergehend nicht verfügbar (HTTP ≥ 500, Wartung, Deadlock); Fallback für unbekannte Codes in `ConnectorException` und Jobs | transient |
| `CONTRACT_INCOMPATIBLE` | `rc.contract.incompatible` | Vertragsversion/Fähigkeit inkompatibel, kein Job-Handler | config |
| `CONTRACT_INVALID` | `rc.contract.invalid` | Metadaten/Registrierung ungültig, ungültiges Handler-Ergebnis | permanent |
| `CONTRACT_UNAVAILABLE` | `rc.contract.unavailable` | Vertragsdienst nicht vorhanden, z. B. `WorkflowService::plan_request()` ohne registrierten Pro-Planer (RC-WF-03) | config |
| `DATA_INVALID_PAYLOAD` | `rc.data.invalid_payload` | Payload verletzt Schema/Validierung (auch ungültiger Connector-Schlüssel) | data |
| `DATA_DUPLICATE` | `rc.data.duplicate` | Duplikat (z. B. doppelter Registrierungsschlüssel) | data |
| `REMOTE_REMOVED` | `rc.remote.removed` | Review/Objekt in der Quelle gelöscht (HTTP 404/410) | permanent |
| `SOURCE_UNAVAILABLE` | `rc.source.unavailable` | Quell-/Connector-Plugin deaktiviert, entfernt oder nicht aktiv; Job abgebrochen (`cancel()`) | config |
| `CONSENT_MISSING` | `rc.consent.missing` | Einwilligung/Versandberechtigung fehlt | data |
| `PERMANENT_FAILURE` | `rc.perm.failed` | endgültiger, nicht näher klassifizierter Fehler; Fallback für unbekannte Codes in `ProviderException`/`ProviderResult` | permanent |
| `CRYPTO_KEY_MISSING` | `rc.crypto.key_missing` | kein verwendbarer Verschlüsselungsschlüssel ([secrets.md](secrets.md)) | auth |
| `CRYPTO_DECRYPT_FAILED` | `rc.crypto.decrypt_failed` | gespeichertes Secret nicht entschlüsselbar (Schlüssel gewechselt, Manipulation, Kontext) | auth |
| `INTERNAL` | `rc.internal.error` | interner Fehler, z. B. werfender Registrierungs-Callback | transient (Default-Zweig) |

Admin-Aktionen je Klasse (`admin_action()`): `auth` – „Verbindung neu autorisieren bzw. Schlüssel prüfen“; `rate_limit` – „Keine Aktion nötig – automatischer Neuversuch nach Ablauf des Limits“; `config` – „Quell-Plugin bzw. Connector prüfen (aktiv, kompatible Vertragsversion); der Vorgang wird danach automatisch fortgesetzt“; `data` – „Eingangsdaten bzw. Mapping prüfen; der Vorgang wird nicht wiederholt“; `permanent` – „Vorgang ist endgültig fehlgeschlagen; Konfiguration oder Vertrag prüfen“; `transient` – „Keine Aktion nötig – automatischer Neuversuch mit Wartezeit“.

## Codes (geplant, nicht in `ErrorCode` v1)

Die folgenden Codes stammen aus früheren Fassungen bzw. aus Verträgen für Pro und Suite-Integrationen. Sie sind **nicht** in `ErrorCode` definiert; `ErrorCode::is_known()` liefert für sie `false`, d. h. sie werden je nach Eintrittspunkt auf `rc.temp.unavailable` (Connectoren, Jobs) bzw. `rc.perm.failed` (Provider) normalisiert. Einführung nur per ADR als neue Konstante; die Spalte „vorgesehene Klasse“ gilt dann als Vorgabe.

| Bereich | Code | Bedeutung | vorgesehene Klasse | Admin-Aktion | v1-Ersatz |
|---|---|---|---|---|---|
| Authentifizierung | `rc.auth.insufficient_scope` | fehlende Berechtigung/Tarif/Scope (HTTP 403) | auth | Berechtigung/Tarif beim Anbieter prüfen, neu verbinden | in v1: `rc.auth.permission_denied` |
| Authentifizierung | `rc.auth.refresh_conflict` | paralleler Refresh hat Token bereits erneuert | transient | keine (automatisch neu laden) | – |
| Authentifizierung | `rc.auth.state_invalid` | OAuth-State/Nonce/Redirect ungültig oder abgelaufen | permanent | Verbindungsvorgang neu starten | – |
| Quota / Rate Limit | `rc.quota.local_budget` | lokales API-Budget je Profil erreicht (TEC-007) | rate_limit | Budget in „Quellen & Synchronisierung“ anpassen | in v1: `rc.quota.budget_exhausted` |
| Netzwerk | `rc.net.connection` | Verbindungsaufbau/DNS fehlgeschlagen | transient | Netzwerk/DNS des Servers prüfen | in v1: `rc.net.unreachable` |
| Netzwerk | `rc.net.tls` | TLS-Fehler (Zertifikat) | transient | CA-Zertifikate des Servers aktualisieren | – |
| Netzwerk | `rc.net.host_not_allowed` | Ziel-Host nicht in Allowlist (SSRF-Schutz) | permanent | Konfiguration prüfen; Support kontaktieren | – |
| Gegenstelle | `rc.remote.not_found` | Profil/Objekt nicht gefunden (HTTP 404) | permanent | Profilzuordnung prüfen | in v1: `rc.remote.removed` (HTTP 404/410) |
| Gegenstelle | `rc.remote.server_error` | Fehler beim Anbieter (HTTP 5xx) | transient | keine; bei Dauerfehler Anbieterstatus prüfen | in v1: `rc.temp.unavailable` (HTTP ≥ 500) |
| Generisch | `rc.lock.busy` | Sperre/Lease von anderem Worker gehalten (paralleler Sync/Versand) | transient | keine | – |
| Verträge | `rc.contract.invalid_metadata` | Pflichtmetadaten fehlen/ungültig (Key, Plattform, Version) | permanent | Entwickler des Add-ons informieren | in v1: `rc.contract.invalid` |
| Verträge | `rc.contract.invalid_capabilities` | Capability-Deklaration ungültig (z. B. `write_features` in v1) | permanent | Add-on aktualisieren | in v1: `rc.contract.incompatible` bzw. `rc.contract.invalid` |
| Verträge | `rc.contract.duplicate_key` | Schlüssel bereits registriert | permanent | doppeltes Add-on deaktivieren | in v1: `rc.data.duplicate` |
| Verträge | `rc.contract.missing_capability` | aufgerufene Fähigkeit nicht deklariert | permanent | Add-on/Konfiguration prüfen | – |
| Verträge | `rc.contract.unavailable` | Vertragsdienst nicht vorhanden (z. B. Workflow-Service ohne Pro) | config | Pro installieren/aktivieren | **implementiert** (`ErrorCode::CONTRACT_UNAVAILABLE`, Klasse `config`) |
| Daten | `rc.data.pii_forbidden` | Kontaktdaten/Text in Feld ohne entsprechende Fähigkeit | data | Quell-Add-on korrigieren | – |
| Daten | `rc.data.too_large` | Größenlimit überschritten | data | Quell-Add-on korrigieren | – |
| Quellen / Ereignisse | `rc.source.disabled` | Adapter in Review Captain ausgeschaltet | permanent | Adapter unter „Integrationen“ aktivieren | – |
| Quellen / Ereignisse | `rc.source.not_owner` | `experience_id` gehört nicht zur meldenden Quelle | data | Quell-Add-on korrigieren | – |
| Quellen / Ereignisse | `rc.event.duplicate` | Ereignis bereits verarbeitet (Info, kein Fehler) | permanent | keine | in v1: `rc.data.duplicate` |
| Quellen / Ereignisse | `rc.entitlement.missing` | erforderliches Pro-Recht fehlt/abgelaufen | permanent | Lizenz prüfen | – |
| Erlebnis / Anfrage | `rc.experience.revoked` | Erlebnis widerrufen (Refund, Absage, No-show, Void) | permanent | keine | – |
| Erlebnis / Anfrage | `rc.experience.not_completed` | Planung ohne abgeschlossenes Erlebnis versucht | permanent | Workflow-/Kampagnen-Auslöser prüfen | – |
| Erlebnis / Anfrage | `rc.consent.revoked` | Versandberechtigung widerrufen/Opt-out | permanent | keine | – |
| Erlebnis / Anfrage | `rc.recipient.unknown` | Empfängerreferenz nicht auflösbar | permanent | Quelle prüfen | – |
| Erlebnis / Anfrage | `rc.recipient.invalid_contact` | aufgelöster Kontakt ungültig | permanent | Kontaktdaten in der Quelle korrigieren | – |
| Erlebnis / Anfrage | `rc.recipient.suppressed` | Empfänger auf Suppression-Liste | permanent | keine (nur Empfänger kann reaktivieren, Verfahren RC-090) | – |
| Erlebnis / Anfrage | `rc.resolver.error` | Resolver hat Fehler geworfen | transient | Quell-Add-on prüfen | – |
| Erlebnis / Anfrage | `rc.request.duplicate` | Einladungsschlüssel/Suite-Sperre/Versand-Ledger belegt | permanent | keine | – |
| Erlebnis / Anfrage | `rc.request.frequency_limited` | globale Frequenzgrenze (Cooldown) | permanent | Cooldown-Einstellung prüfen | – |
| Erlebnis / Anfrage | `rc.request.expired` | Gültigkeitsfenster abgelaufen | permanent | Verzögerung/Ablauf der Kampagne prüfen | – |
| Erlebnis / Anfrage | `rc.request.invalid_transition` | unzulässiger Statusübergang | permanent | Fehlerbericht (Programmfehler) | – |
| Erlebnis / Anfrage | `rc.request.not_eligible` | Planungsanfrage verletzt Regel (z. B. Workflow) | permanent | Kampagnenregeln prüfen | – |
| Erlebnis / Anfrage | `rc.request.cancelled_by_admin` | durch Admin abgebrochen | permanent | keine | – |
| Erlebnis / Anfrage | `rc.request.cancelled_by_workflow` | durch Workflow abgebrochen | permanent | keine | – |
| Transport | `rc.transport.not_configured` | kein geprüfter Versandweg (Absender/SMTP) | auth | Versand unter „Anfragen & Zustellung“ einrichten | – |
| Transport | `rc.transport.rejected` | Transport lehnt Nachricht endgültig ab | permanent | Absender/Domain prüfen | – |
| Transport | `rc.transport.bounced` | Hard-Bounce | permanent | keine (Suppression gesetzt) | – |
| Transport | `rc.transport.ambiguous` | Ausgang unklar (Timeout nach Übergabe); kein Zweitversand | permanent | Versandprotokoll des Transports prüfen | – |
| Rechte / Inhalte | `rc.rights.missing` | Testimonial-Rechte fehlen/abgelaufen/widerrufen | permanent | Rechtebeleg erfassen/erneuern | – |
| Rechte / Inhalte | `rc.review.not_pending` | Aktion nur für wartende Reviews erlaubt (z. B. Entwurf löschen nach Freigabe) | permanent | Review manuell prüfen (Privacy-Regeln) | – |
| Cross-site-Signatur (nur mit TEC-011) | `rc.sig.missing` | Signatur-Header fehlen | permanent | Gegenstelle konfigurieren | – |
| Cross-site-Signatur (nur mit TEC-011) | `rc.sig.invalid` | Signatur ungültig | permanent | Schlüssel abgleichen | – |
| Cross-site-Signatur (nur mit TEC-011) | `rc.sig.expired` | Timestamp außerhalb Toleranz | permanent | Serverzeit (NTP) prüfen | – |
| Cross-site-Signatur (nur mit TEC-011) | `rc.sig.replay` | Nonce bereits verwendet | permanent | keine (Angriff/Doppelzustellung) | – |
| Cross-site-Signatur (nur mit TEC-011) | `rc.sig.scope` | Schlüssel hat Scope/Quelle nicht | permanent | Schlüssel-Scope anpassen | – |
| Cross-site-Signatur (nur mit TEC-011) | `rc.sig.key_unknown` | Key-ID unbekannt | permanent | Schlüssel anlegen/abgleichen | – |
| Cross-site-Signatur (nur mit TEC-011) | `rc.sig.key_revoked` | Schlüssel widerrufen/abgelaufen | permanent | rotierten Schlüssel verwenden | – |
| Frontend | `rc.widget.unsupported_version` | Shortcode/Block mit unbekanntem `v` | permanent | Shortcode auf `v="1"` setzen | in v1: nur HTML-Kommentar, kein Code |
| Frontend | `rc.widget.invalid_attribute` | ungültiger Attributwert (ignoriert, Default genutzt) | data | Shortcode korrigieren | in v1: `WidgetDefinition::$notices`, kein Code |
| Frontend | `rc.form.invalid_nonce` | Formular-Nonce ungültig/abgelaufen | permanent | (Besucher) Seite neu laden | in v1: Formular-Fehlerschlüssel `nonce` (`rc_error`) |
| Frontend | `rc.form.rate_limited` | Einreichungs-Rate-Limit | rate_limit | keine | in v1: Formular-Fehlerschlüssel `rate_limited` |
| Frontend | `rc.form.spam` | Honeypot ausgelöst | permanent | keine | in v1: stiller Erfolg ohne Speicherung |
| Frontend | `rc.form.invalid_input` | Pflichtfeld fehlt/ungültig | data | (Besucher) korrigieren | in v1: Formular-Fehlerschlüssel je Feld (`rating`, `body_short`, `email` …) |
| Frontend | `rc.form.context_invalid` | Kontext-Signatur des Formulars ungültig | permanent | Formular neu einbinden | in v1: Formular-Fehlerschlüssel `context` |

## Integrationsstatus

Implementiert in v1 (Connectoren `Connectors\Registry::status()`, Provider `ProviderRegistry::status()`):

| Status | Bedeutung | Code | nächste Admin-Aktion |
|---|---|---|---|
| `active` | registriert, kompatibel, aktiviert (Provider: registriert und kompatibel) | – | – |
| `inactive` | Connector registriert, aber nicht aktiviert, oder nie registriert (nur Connectoren) | – | Aktivierung im Connector-Manager (Pro) |
| `paused` | inkompatibel oder vom Pro-Manager pausiert | `rc.contract.incompatible` bzw. gespeicherter Code | Plugin aktualisieren / fortsetzen |
| `source_unavailable` | Quell-/Connector-Plugin fehlt oder deaktiviert | `rc.source.unavailable` | Quell-Plugin aktivieren |

Geplant (Pro/Profile, nicht in v1):

| Status | Bedeutung | gesetzt durch Codes | nächste Admin-Aktion |
|---|---|---|---|
| `auth_required` | Authentifizierung nötig | `rc.auth.*`, `rc.crypto.*`, `rc.transport.not_configured` | neu verbinden / einrichten |
| `rate_limited` | wartet auf Quota | `rc.quota.*` | keine / Budget anpassen |
| `error` | wiederholte transiente Fehler, Job `dead` | `transient`-Codes nach `max_attempts` | Diagnose ansehen, wiederholen |
| `disconnected` | getrennt, keine Aufrufe | Disconnect | neu verbinden |

Einen Status `incompatible_contract` gibt es nicht; Inkompatibilität ist `paused` mit `rc.contract.incompatible`. Lizenz-/Entitlement-Pausen (`rc.entitlement.missing`, geplant) setzen ebenfalls `paused`.

## `ErrorCode` (implementierte API)

```php
namespace ReviewCaptain\Support;

final class ErrorCode {
    public const AUTH_INVALID = 'rc.auth.invalid';
    // … eine Konstante je implementiertem Code (Tabelle oben)
    public const CLASS_TRANSIENT = 'transient'; // ebenso CLASS_RATE_LIMIT, CLASS_AUTH, CLASS_PERMANENT, CLASS_DATA, CLASS_CONFIG

    /** @return 'transient'|'rate_limit'|'auth'|'permanent'|'data'|'config' */
    public static function retry_class( string $code ): string;
    public static function is_known( string $code ): bool;
    /** @return list<string> */
    public static function all(): array;
    public static function admin_action( string $code ): string; // übersetzt, je Klasse
}
```

Ausblick (nicht in v1): `integration_status( string $code ): ?string`, `label()`. Contract-Test (geplant): Jeder Code in `docs/contracts/*.md` und in `src/` existiert in `ErrorCode` oder ist in der Tabelle „geplant“ geführt (grep-basierter Test).

## Abnahme

- [ ] Inkompatibler Vertrag (`rc.contract.incompatible`), Auth-Fehler (`rc.auth.*`), Quota (`rc.quota.*`), Timeout (`rc.net.timeout`), entfernte Review (`rc.remote.removed`), Retry (Klassen `transient`/`rate_limit`) und endgültiger Fehler (`rc.perm.failed`) haben dokumentierte Codes.
- [ ] Jeder implementierte Code hat Bedeutung, genau eine Retry-Klasse und eine Admin-Aktion; nicht implementierte Codes sind als „geplant“ markiert.
- [ ] `Retry-After` wird bei `rate_limit` beachtet (Unit-Test mit Fixture `error_429.json`).
- [ ] Integrationsstatus-Werte sind festgelegt und Codes zugeordnet (implementiert: `active`, `inactive`, `paused`, `source_unavailable`).
- [ ] Fehlermeldungen enthalten keine Secrets/PII (Log-Scan im Integrationstest).
