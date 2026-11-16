# Cross-site-Schnittstellen – Begrenzung und Voraussetzungen (TEC-011)

| | |
|---|---|
| Status | Spezifikation. **In v1 nicht implementiert**; dieses Dokument definiert die Voraussetzungen, bevor ein Cross-site-Weg gebaut werden darf. |
| Bezug | TODO TEC-010, TEC-011, TEC-008; konzept.md 7.4 (letzter Punkt), 7.5, 7.7, 7.10 „Abgrenzung der Integrationsarten“ |

## Abgrenzung

| Weg | Einsatz | Status v1 |
|---|---|---|
| Lokale PHP-Verträge (Hooks, Funktionen, Interfaces) | Suite-Plugins und Connectoren **in derselben WordPress-Installation** (bei Multisite: derselben Site) | **einziger zulässiger Weg** |
| Signierter Webhook-Eingang (REST) | externes System/andere WP-Installation meldet Experience-Ereignisse | nicht vorhanden; erfordert ADR + Umsetzung dieses Dokuments |
| Signierter Webhook-Ausgang | Review Captain meldet Trigger an externes System | nicht vorhanden; erfordert ADR |
| Lokale Hooks über Netzwerk „simulieren“ | – | **verboten** |

conventions.md legt fest: keine öffentlichen Schreib-Endpunkte. Ein signierter Eingang ist eine Ausnahme, die nur per ADR eingeführt wird; bis dahin existiert keine solche Route.

## Voraussetzungen (alle Pflicht)

### 1. Signatur (HMAC-SHA256)

Header jeder Anfrage:

| Header | Inhalt |
|---|---|
| `X-RC-Key-Id` | ID des Schlüssels (`rck_` + 16 Hex) |
| `X-RC-Timestamp` | Unix-Sekunden (UTC) |
| `X-RC-Nonce` | 128 Bit Zufall, Base64url (22 Zeichen) |
| `X-RC-Signature` | `v1=` + Hex(HMAC-SHA256(secret, canonical)) |

Kanonischer String (Zeilen mit `\n` getrennt, ohne abschließendes `\n`):

```
v1
{X-RC-Timestamp}
{X-RC-Nonce}
{HTTP-Methode in Großbuchstaben}
{Pfad ohne Host, inkl. Query in kanonischer Sortierung}
{Hex(SHA-256(roher Request-Body))}
```

- Vergleich ausschließlich mit `hash_equals()`.
- Body wird **roh** gehasht, vor jeder JSON-Dekodierung.
- Mehrere Signaturen (`v1=…,v1=…`) sind während einer Rotation zulässig; eine gültige genügt.

Beispiel (Testvektor, Secret = `rcsec_test_0000000000000000000000000000`, nur für Unit-Tests):

```json
{
  "method": "POST",
  "path": "/wp-json/review-captain/v1/inbound/experience-events",
  "timestamp": "1763452800",
  "nonce": "q3L0b8mY2c9uXw1zA4sD7g",
  "body_sha256": "<SHA-256 des Beispiel-Bodys>",
  "canonical": "v1\n1763452800\nq3L0b8mY2c9uXw1zA4sD7g\nPOST\n/wp-json/review-captain/v1/inbound/experience-events\n<body_sha256>",
  "signature_header": "v1=<HMAC-SHA256 hex>"
}
```

Konkrete Hashwerte werden bei Implementierung als Fixture `tests/fixtures/crosssite/vector-1.json` erzeugt und eingefroren.

### 2. Timestamp

- Toleranz **±300 s** gegenüber Serverzeit; außerhalb → `401`, `rc.sig.expired`.
- Admin-Diagnose warnt bei Uhrabweichung (NTP).

### 3. Replay-Schutz

- Nonce wird je `key_id` gespeichert: Tabelle `{prefix}review_captain_inbound_nonces` (`key_id`, `nonce`, `seen_at`, UNIQUE(`key_id`, `nonce`)), Insert vor Verarbeitung; Duplicate-Key → `409`, `rc.sig.replay`.
- Aufbewahrung: 2 × Toleranz (10 min), Bereinigung per Job.
- Zusätzlich fachliche Idempotenz über `event_id`/`idempotency_key` des Ereignisvertrags.

### 4. Scope

Jeder Schlüssel trägt:

| Attribut | Bedeutung |
|---|---|
| `scopes` | z. B. `experience_events:write`, `summary:read`; Route prüft Scope, sonst `403`, `rc.sig.scope` |
| `source` | gebundener Quellschlüssel; Payload-`source` muss übereinstimmen, `experience_id` mit `{source}:` beginnen |
| `site_id` | Ziel-Site (Multisite) |
| `allowed_ips` | optional, CIDR-Liste |

Ein Schlüssel darf nie Admin-Funktionen, Reviewtexte, Kontakte oder Credentials lesen. Resolver-Aufrufe über Netzwerk sind in v1 ausgeschlossen (Kontakt verlässt die Quelle nicht).

### 5. Schlüsselrotation

- Secret: ≥ 32 Byte aus `random_bytes()`, nur einmal bei Erstellung angezeigt, verschlüsselt gespeichert (TEC-008), nie in Logs/Export.
- Zustände: `active` → `rotating` (neuer Schlüssel aktiv, alter bis zu 7 Tage parallel gültig) → `revoked`.
- Widerruf wirkt sofort (`rc.sig.key_revoked`).
- Empfehlung Rotationsintervall: 90 Tage; Admin-Warnung 14 Tage vorher.
- Audit-Eintrag für Erstellung, Rotation, Widerruf (ohne Secret).

### 6. Transport und Betrieb

- Nur HTTPS. Ausgehende Webhooks: feste Ziel-Host-Allowlist, kein Redirect-Folgen, SSRF-Schutz (keine privaten/Loopback-Adressen), Timeout 5 s.
- Rate-Limit je `key_id` (Vorschlag 60 Anfragen/min) → `429` mit `Retry-After`.
- Body-Limit 64 KiB; Verarbeitung asynchron (Antwort `202` nach Validierung + Persistenz).
- Fehlerantworten enthalten nur Code (`rc.sig.*`, `rc.data.*`), keine Details zur Signaturberechnung.

## Antwortbeispiele

```json
{ "status": "accepted", "code": null, "event_id": "1b2c3d4e-5f60-4718-8a9b-0c1d2e3f4a5b" }
```

```json
{ "status": "rejected", "code": "rc.sig.replay" }
```

## Abnahme

- [ ] Lokale Suite-Adapter verwenden ausschließlich PHP-Verträge; Code-Review bestätigt: keine REST-/HTTP-Aufrufe zwischen Suite-Plugins derselben Installation.
- [ ] Für einen späteren REST-/Webhook-Weg sind Signatur (HMAC-SHA256, kanonischer String), Timestamp (±300 s), Replay-Schutz (Nonce-Tabelle), Scope (Scopes + Quellbindung) und Schlüsselrotation (Parallelphase, Widerruf) als Voraussetzung dokumentiert.
- [ ] Vor Implementierung: ADR, Testvektoren, Negativtests für jeden `rc.sig.*`-Code.
