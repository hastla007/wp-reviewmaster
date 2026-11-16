# Fehlercodes und Integrationsstatus (RC-030)

| | |
|---|---|
| Status | Spezifikation, Entwurf zur Abnahme |
| Format | `rc.<bereich>.<code>` (conventions.md); Bereich und Code `[a-z_]+` |
| Implementierung | `ReviewCaptain\Support\ErrorCode` (Konstanten + `retry_class()`, `admin_action()`, `label()`) |
| Bezug | TODO RC-030, RC-082, CON-013, TEC-006; konzept.md 7.5, 11 |

## Regeln

- Codes sind **stabile öffentliche Werte** (Hooks, Diagnoseexport, `jobs.last_error_code`, `requests.status_code`). Umbenennen nur per ADR mit Alias-Frist.
- Ein Code hat genau **eine** Retry-Klasse. Unbekannte Codes werden als `rc.perm.failed` behandelt und mit dem Originalcode im Debug-Log (ohne PII) vermerkt.
- `last_error_message` ist sanitisiert, max. 500 Zeichen, ohne Tokens, Header, Reviewtexte, E-Mail-Adressen.
- Admin-Texte sind übersetzbar (`review-captain`); der Code wird zusätzlich angezeigt (Support).

## Retry-Klassen

| Klasse | Verhalten (Jobs, TEC-006) | `attempts` | Endzustand |
|---|---|---|---|
| `transient` | Exponentielles Backoff mit Jitter: 1 min, 5 min, 15 min, 1 h, 6 h | +1 je Versuch, `max_attempts` Default 5 | `dead` + Admin-Hinweis |
| `rate_limit` | `Retry-After`/`reset_at` beachten (min. 60 s, max. 24 h); ohne Angabe 15 min; Profil/Verbindung Status `rate_limited` | nicht erhöht (bis 24 h Gesamtverzögerung) | nach 24 h `transient`-Regeln |
| `auth` | kein Retry; einmaliger automatischer Token-Refresh nur bei `rc.auth.expired`; Verbindung/Profil `auth_required`, zugehörige Jobs `paused` | – | wartet auf Admin (Reconnect) |
| `permanent` | kein Retry | – | Job `failed`; Anfrage `skipped`/`failed`/`cancelled` je Kontext |
| `data` | kein Retry für das betroffene Item; Lauf/Batch läuft weiter; Item wird gezählt. Überschreitet ein Lauf 20 % ungültige Items, Profilwarnung | – | Item verworfen, Lauf `succeeded` mit Warnung |

## Codes

| Code | Bedeutung | Retry-Klasse | Admin-Aktion |
|---|---|---|---|
| **Authentifizierung** | | | |
| `rc.auth.invalid` | Zugangsdaten ungültig oder abgelehnt (HTTP 401 ohne Refresh-Möglichkeit) | auth | Quelle neu verbinden |
| `rc.auth.expired` | Token abgelaufen, Refresh möglich | auth | keine (automatischer Refresh); bei Fehlschlag → `rc.auth.invalid` |
| `rc.auth.revoked` | Zugriff beim Anbieter widerrufen | auth | Quelle neu verbinden oder trennen |
| `rc.auth.insufficient_scope` | fehlende Berechtigung/Tarif/Scope (HTTP 403) | auth | Berechtigung/Tarif beim Anbieter prüfen, neu verbinden |
| `rc.auth.refresh_conflict` | paralleler Refresh hat Token bereits erneuert | transient | keine (automatisch neu laden) |
| `rc.auth.state_invalid` | OAuth-State/Nonce/Redirect ungültig oder abgelaufen | permanent | Verbindungsvorgang neu starten |
| **Quota / Rate Limit** | | | |
| `rc.quota.rate_limited` | Anbieter-Rate-Limit (HTTP 429) | rate_limit | keine; bei Häufung Sync-Intervall erhöhen |
| `rc.quota.exceeded` | Tages-/Monatskontingent des Anbieters erschöpft | rate_limit | Kontingent/Tarif prüfen, Sync-Umfang reduzieren |
| `rc.quota.local_budget` | lokales API-Budget je Profil erreicht (TEC-007) | rate_limit | Budget in „Quellen & Synchronisierung“ anpassen |
| **Netzwerk** | | | |
| `rc.net.timeout` | Zeitüberschreitung | transient | bei Dauerfehler Hosting/Firewall prüfen |
| `rc.net.connection` | Verbindungsaufbau/DNS fehlgeschlagen | transient | Netzwerk/DNS des Servers prüfen |
| `rc.net.tls` | TLS-Fehler (Zertifikat) | transient | CA-Zertifikate des Servers aktualisieren |
| `rc.net.host_not_allowed` | Ziel-Host nicht in Allowlist (SSRF-Schutz) | permanent | Konfiguration prüfen; Support kontaktieren |
| **Gegenstelle** | | | |
| `rc.remote.removed` | Review/Objekt auf der Plattform gelöscht | permanent | keine (Tombstone erfolgt automatisch) |
| `rc.remote.not_found` | Profil/Objekt nicht gefunden (HTTP 404) | permanent | Profilzuordnung prüfen |
| `rc.remote.server_error` | Fehler beim Anbieter (HTTP 5xx) | transient | keine; bei Dauerfehler Anbieterstatus prüfen |
| **Generisch** | | | |
| `rc.temp.unavailable` | vorübergehend nicht verfügbar (z. B. DB-Deadlock, Wartung) | transient | keine |
| `rc.perm.failed` | endgültiger, nicht näher klassifizierter Fehler (Fallback) | permanent | Diagnose exportieren, Support |
| `rc.lock.busy` | Sperre/Lease von anderem Worker gehalten (paralleler Sync/Versand) | transient | keine |
| **Verträge** | | | |
| `rc.contract.incompatible` | unbekannte Major-Version eines Providers/Connectors/Ereignisses | config (paused) | Plugin aktualisieren; Status `incompatible_contract` |
| `rc.contract.invalid_metadata` | Pflichtmetadaten fehlen/ungültig (Key, Plattform, Version) | permanent | Entwickler des Add-ons informieren |
| `rc.contract.invalid_capabilities` | Capability-Deklaration ungültig (z. B. `write_features` in v1) | permanent | Add-on aktualisieren |
| `rc.contract.duplicate_key` | Schlüssel bereits registriert | permanent | doppeltes Add-on deaktivieren |
| `rc.contract.missing_capability` | aufgerufene Fähigkeit nicht deklariert | permanent | Add-on/Konfiguration prüfen |
| `rc.contract.unavailable` | Vertragsdienst nicht vorhanden (z. B. Workflow-Service ohne Pro) | permanent | Pro installieren/aktivieren |
| **Daten** | | | |
| `rc.data.invalid_payload` | Payload verletzt Schema/Validierung | data | bei Häufung Add-on/Provider aktualisieren |
| `rc.data.pii_forbidden` | Kontaktdaten/Text in Feld ohne entsprechende Fähigkeit | data | Quell-Add-on korrigieren |
| `rc.data.too_large` | Größenlimit überschritten | data | Quell-Add-on korrigieren |
| **Quellen / Ereignisse** | | | |
| `rc.source.unavailable` | Quell-Plugin deaktiviert/entfernt oder Resolver nicht erreichbar | config (paused) | Quell-Plugin aktivieren; wartende Anfragen bleiben pausiert |
| `rc.source.disabled` | Adapter in Review Captain ausgeschaltet | permanent | Adapter unter „Integrationen“ aktivieren |
| `rc.source.not_owner` | `experience_id` gehört nicht zur meldenden Quelle | data | Quell-Add-on korrigieren |
| `rc.event.duplicate` | Ereignis bereits verarbeitet (Info, kein Fehler) | permanent | keine |
| `rc.entitlement.missing` | erforderliches Pro-Recht fehlt/abgelaufen | permanent | Lizenz prüfen |
| **Erlebnis / Anfrage** | | | |
| `rc.experience.revoked` | Erlebnis widerrufen (Refund, Absage, No-show, Void) | permanent | keine |
| `rc.experience.not_completed` | Planung ohne abgeschlossenes Erlebnis versucht | permanent | Workflow-/Kampagnen-Auslöser prüfen |
| `rc.consent.missing` | Versandberechtigung fehlt/unbekannt | permanent | Consent-Erfassung in der Quelle prüfen |
| `rc.consent.revoked` | Versandberechtigung widerrufen/Opt-out | permanent | keine |
| `rc.recipient.unknown` | Empfängerreferenz nicht auflösbar | permanent | Quelle prüfen |
| `rc.recipient.invalid_contact` | aufgelöster Kontakt ungültig | permanent | Kontaktdaten in der Quelle korrigieren |
| `rc.recipient.suppressed` | Empfänger auf Suppression-Liste | permanent | keine (nur Empfänger kann reaktivieren, Verfahren RC-090) |
| `rc.resolver.error` | Resolver hat Fehler geworfen | transient | Quell-Add-on prüfen |
| `rc.request.duplicate` | Einladungsschlüssel/Suite-Sperre/Versand-Ledger belegt | permanent | keine |
| `rc.request.frequency_limited` | globale Frequenzgrenze (Cooldown) | permanent | Cooldown-Einstellung prüfen |
| `rc.request.expired` | Gültigkeitsfenster abgelaufen | permanent | Verzögerung/Ablauf der Kampagne prüfen |
| `rc.request.invalid_transition` | unzulässiger Statusübergang | permanent | Fehlerbericht (Programmfehler) |
| `rc.request.not_eligible` | Planungsanfrage verletzt Regel (z. B. Workflow) | permanent | Kampagnenregeln prüfen |
| `rc.request.cancelled_by_admin` | durch Admin abgebrochen | permanent | keine |
| `rc.request.cancelled_by_workflow` | durch Workflow abgebrochen | permanent | keine |
| **Transport** | | | |
| `rc.transport.not_configured` | kein geprüfter Versandweg (Absender/SMTP) | auth | Versand unter „Anfragen & Zustellung“ einrichten |
| `rc.transport.rejected` | Transport lehnt Nachricht endgültig ab | permanent | Absender/Domain prüfen |
| `rc.transport.bounced` | Hard-Bounce | permanent | keine (Suppression gesetzt) |
| `rc.transport.ambiguous` | Ausgang unklar (Timeout nach Übergabe); kein Zweitversand | permanent | Versandprotokoll des Transports prüfen |
| **Rechte / Inhalte** | | | |
| `rc.rights.missing` | Testimonial-Rechte fehlen/abgelaufen/widerrufen | permanent | Rechtebeleg erfassen/erneuern |
| `rc.review.not_pending` | Aktion nur für wartende Reviews erlaubt (z. B. Entwurf löschen nach Freigabe) | permanent | Review manuell prüfen (Privacy-Regeln) |
| **Kryptografie** | | | |
| `rc.crypto.key_missing` | Verschlüsselungs-/Hash-Schlüssel fehlt | auth | Schlüssel gemäß Doku (TEC-008) konfigurieren |
| `rc.crypto.decrypt_failed` | gespeichertes Secret nicht entschlüsselbar (Schlüssel gewechselt) | auth | Quelle neu verbinden |
| **Cross-site-Signatur** (nur wenn TEC-011 aktiviert) | | | |
| `rc.sig.missing` | Signatur-Header fehlen | permanent | Gegenstelle konfigurieren |
| `rc.sig.invalid` | Signatur ungültig | permanent | Schlüssel abgleichen |
| `rc.sig.expired` | Timestamp außerhalb Toleranz | permanent | Serverzeit (NTP) prüfen |
| `rc.sig.replay` | Nonce bereits verwendet | permanent | keine (Angriff/Doppelzustellung) |
| `rc.sig.scope` | Schlüssel hat Scope/Quelle nicht | permanent | Schlüssel-Scope anpassen |
| `rc.sig.key_unknown` | Key-ID unbekannt | permanent | Schlüssel anlegen/abgleichen |
| `rc.sig.key_revoked` | Schlüssel widerrufen/abgelaufen | permanent | rotierten Schlüssel verwenden |
| **Frontend** | | | |
| `rc.widget.unsupported_version` | Shortcode/Block mit unbekanntem `v` | permanent | Shortcode auf `v="1"` setzen |
| `rc.widget.invalid_attribute` | ungültiger Attributwert (ignoriert, Default genutzt) | data | Shortcode korrigieren |
| `rc.form.invalid_nonce` | Formular-Nonce ungültig/abgelaufen | permanent | (Besucher) Seite neu laden |
| `rc.form.rate_limited` | Einreichungs-Rate-Limit | rate_limit | keine |
| `rc.form.spam` | Honeypot ausgelöst | permanent | keine |
| `rc.form.invalid_input` | Pflichtfeld fehlt/ungültig | data | (Besucher) korrigieren |
| `rc.form.context_invalid` | Kontext-Signatur des Formulars ungültig | permanent | Formular neu einbinden |

## Integrationsstatus

Status je Verbindung/Profil/Connector/Suite-Adapter (Admin „Quellen“, „Integrationen“, Diagnose). Werte sind stabil.

| Status | Bedeutung | gesetzt durch Codes | nächste Admin-Aktion |
|---|---|---|---|
| `active` | betriebsbereit | erfolgreicher Lauf/Test | – |
| `paused` | vom Admin pausiert oder Lizenz/Entitlement fehlt | `rc.entitlement.missing` | fortsetzen / Lizenz prüfen |
| `auth_required` | Authentifizierung nötig | `rc.auth.invalid`, `rc.auth.revoked`, `rc.auth.insufficient_scope`, `rc.crypto.*`, `rc.transport.not_configured` | neu verbinden / einrichten |
| `rate_limited` | wartet auf Quota | `rc.quota.*` | keine / Budget anpassen |
| `incompatible_contract` | Vertragsversion inkompatibel | `rc.contract.incompatible`, `rc.contract.invalid_capabilities` | Plugin aktualisieren |
| `source_unavailable` | Quell-Plugin fehlt/deaktiviert | `rc.source.unavailable` | Quell-Plugin aktivieren |
| `error` | wiederholte transiente Fehler, Job `dead` | `transient`-Codes nach `max_attempts` | Diagnose ansehen, wiederholen |
| `disconnected` | getrennt, keine Aufrufe | Disconnect | neu verbinden |

## `ErrorCode`-Skizze

```php
namespace ReviewCaptain\Support;

final class ErrorCode {
    public const AUTH_INVALID      = 'rc.auth.invalid';
    public const QUOTA_RATE_LIMITED = 'rc.quota.rate_limited';
    // … eine Konstante je Code dieser Tabelle

    /** @return 'transient'|'rate_limit'|'auth'|'permanent'|'data' */
    public static function retry_class( string $code ): string;
    public static function admin_action( string $code ): string; // übersetzt
    public static function integration_status( string $code ): ?string;
    public static function is_known( string $code ): bool;
}
```

Contract-Test: Jeder Code in `docs/contracts/*.md` und in `src/` existiert in `ErrorCode` und in dieser Tabelle (grep-basierter Test).

## Abnahme

- [ ] Inkompatibler Vertrag (`rc.contract.incompatible`), Auth-Fehler (`rc.auth.*`), Quota (`rc.quota.*`), Timeout (`rc.net.timeout`), entfernte Review (`rc.remote.removed`), Retry (Klassen `transient`/`rate_limit`) und endgültiger Fehler (`rc.perm.failed`) haben dokumentierte Codes.
- [ ] Jeder Code hat Bedeutung, genau eine Retry-Klasse und eine Admin-Aktion.
- [ ] `Retry-After` wird bei `rate_limit` beachtet (Unit-Test mit Fixture `error_429.json`).
- [ ] Integrationsstatus-Werte sind festgelegt und Codes zugeordnet.
- [ ] Fehlermeldungen enthalten keine Secrets/PII (Log-Scan im Integrationstest).
