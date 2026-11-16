# Einladungsregel – technische Spezifikation (RC-025)

| | |
|---|---|
| Status | Spezifikation, Entwurf zur Abnahme |
| Gilt für | Review Captain Pro (`review.review_requests`), alle Auslöser (native Suite-Adapter, Custom Connector, Workflow Captain) |
| Bezug | TODO RC-025, RC-087, RC-091, RC-092, RC-WF-04, TEC-004; konzept.md 7.9 „Verbindliche Einladungsregel“, „Deduplizierung“ |

## Regel (fachlich)

1. Pro tatsächlichem Erlebnis und Empfänger wird **höchstens eine Einladung** versendet – über alle Kampagnen und alle Suite-Auslöser hinweg.
2. Nur `experience_completed` (tatsächliche Nutzung/Teilnahme) darf planen; Kauf, Ausstellung, Versand nicht.
3. Zielplattform und Formulierung legt die Kampagne vor der ersten Einladung fest; für alle berechtigten Empfänger gleich. Keine Auswahl nach Sternen, Zufriedenheit oder Beschwerde (RC-091).
4. Kein automatischer zweiter Versand. Eine Erinnerung (Default aus, max. 1) bleibt in derselben Erlebnis-/Empfängergrenze und ist keine zweite Einladung.
5. Opt-out oder fehlende/unbekannte Versandberechtigung blockiert alle Kampagnen für diesen Empfänger, bis ein zulässiger neuer Status vorliegt.

## Technische Sperren

Die Regel wird auf **vier** Ebenen durchgesetzt; jede Ebene ist ein Datenbank-Constraint oder ein atomarer CAS – keine „erst lesen, dann schreiben“-Prüfung.

| # | Sperre | Schlüssel | Wo | Wann | verhindert |
|---|---|---|---|---|---|
| S1 | **Einladungsschlüssel** (TODO-Vorgabe) | `invitation_key = sha1(experience_id \| recipient_ref \| campaign_id)` UNIQUE | `requests` | beim Anlegen (T1/T2) | Doppelanlage durch wiederholte/parallele Ereignisse innerhalb einer Kampagne |
| S2 | **Suite-Sperre** Erlebnis/Empfänger | `suite_lock_key = sha1(experience_id \| recipient_ref)` UNIQUE NULL | `requests` | beim Anlegen; bleibt gesetzt solange Status ∈ {`planned`, `eligible`, `sent`, `delivered`, `clicked`, `unsubscribed`} oder `sent_at IS NOT NULL`; wird auf NULL gesetzt bei `skipped`, `cancelled`, `expired`, `failed` ohne `sent_at` | zweite Kampagne (native + Workflow, Woo-Kampagne + Pass-Kampagne) für dasselbe Erlebnis und dieselbe Referenz |
| S3 | **Versand-Ledger** Kontakt/Erlebnis | `send_key = sha1(contact_hash \| experience_id)` UNIQUE | `request_sends` | unmittelbar vor Transport (T8) | gleicher Mensch unter **verschiedenen** `recipient_ref` (Käufer- und Teilnehmerreferenz derselben Person) für dasselbe Erlebnis |
| S4 | **Empfänger-Guard** | `contact_hash` PK, CAS auf `last_sent_at` / `suppressed_at` | `recipient_guards` | unmittelbar vor Transport (T8) | Opt-out/Suppression; globale Frequenzgrenze (Cooldown) über verschiedene Erlebnisse und Quellen, z. B. Käufer mit mehreren Tickets, Woo-Bestellung + Gutscheineinlösung derselben Person |

`contact_hash = HMAC-SHA256(lowercase(trim(email)), hash_key)` (recipient-resolver-v1.md). Ohne verfügbaren `hash_key` (TEC-008) wird **nicht** versendet (`rc.crypto.key_missing`).

Globale Frequenzgrenze (Cooldown): Einstellung `review_captain_requests_cooldown_days`, Vorschlag Default **30 Tage** (Produktentscheidung offen). `0` deaktiviert S4-Cooldown, nicht aber die Suppression.

### Versandreihenfolge (T8)

1. CAS-Claim der Anfrage (`send_claim`, request-lifecycle.md).
2. Eligibility erneut prüfen (Erlebnis nicht `revoked`, Kampagne aktiv, `cancel_requested = 0`).
3. Resolver → `contact` (nur im Speicher), `contact_hash` berechnen.
4. S4: `INSERT IGNORE` Guard-Zeile; dann CAS
   `UPDATE recipient_guards SET last_sent_at=%s, revision=revision+1 WHERE contact_hash=%s AND suppressed_at IS NULL AND (last_sent_at IS NULL OR last_sent_at < %s) AND revision=%d` → `rows_affected === 1`, sonst `skipped` (`rc.recipient.suppressed` bzw. `rc.request.frequency_limited`).
5. S3: `INSERT INTO request_sends (…)` → Duplicate-Key ⇒ S4 zurücksetzen (CAS auf alten Wert), `skipped` mit `rc.request.duplicate`.
6. `cancel_requested` ein letztes Mal prüfen; gesetzt ⇒ S3-Zeile löschen, S4 zurücksetzen, `cancelled`.
7. Transport.
   - angenommen ⇒ CAS `eligible → sent`.
   - abgelehnt vor Annahme (eindeutig) ⇒ S3-Zeile löschen, S4 zurücksetzen, Retry nach Retry-Klasse oder `failed`.
   - **mehrdeutig** (Timeout nach Übergabe) ⇒ `sent` mit `delivery_state = unknown`, `status_code = rc.transport.ambiguous`, **kein** automatischer Retry (lieber höchstens einmal als doppelt).

## SQL-Vorschlag (Pro-Tabellen, dbDelta-kompatibel)

```sql
CREATE TABLE {prefix}review_captain_requests (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  campaign_id bigint(20) unsigned NOT NULL,
  campaign_version int(10) unsigned NOT NULL DEFAULT 1,
  experience_id varchar(191) NOT NULL,
  recipient_ref varchar(191) NOT NULL,
  source varchar(64) NOT NULL,
  invitation_key char(40) NOT NULL,
  suite_lock_key char(40) DEFAULT NULL,
  status varchar(20) NOT NULL,
  status_code varchar(64) DEFAULT NULL,
  hold_code varchar(64) DEFAULT NULL,
  delivery_state varchar(20) NOT NULL DEFAULT 'unknown',
  target_platform varchar(40) NOT NULL,
  target_profile_id bigint(20) unsigned DEFAULT NULL,
  transport varchar(64) NOT NULL,
  locale varchar(20) NOT NULL DEFAULT 'und',
  contact_hash char(64) DEFAULT NULL,
  due_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  eligible_at datetime DEFAULT NULL,
  sent_at datetime DEFAULT NULL,
  delivered_at datetime DEFAULT NULL,
  clicked_at datetime DEFAULT NULL,
  responded_at datetime DEFAULT NULL,
  closed_at datetime DEFAULT NULL,
  reminder_count tinyint(3) unsigned NOT NULL DEFAULT 0,
  last_reminder_at datetime DEFAULT NULL,
  send_claim varchar(64) DEFAULT NULL,
  send_claim_expires_at datetime DEFAULT NULL,
  cancel_requested tinyint(1) NOT NULL DEFAULT 0,
  attempts int(10) unsigned NOT NULL DEFAULT 0,
  revision int(10) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY invitation_key (invitation_key),
  UNIQUE KEY suite_lock_key (suite_lock_key),
  KEY status_due (status,due_at),
  KEY campaign_status (campaign_id,status),
  KEY experience_id (experience_id),
  KEY recipient_ref (recipient_ref),
  KEY contact_hash (contact_hash)
) {$charset_collate};

CREATE TABLE {prefix}review_captain_request_sends (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  request_id bigint(20) unsigned NOT NULL,
  send_key char(40) NOT NULL,
  contact_hash char(64) NOT NULL,
  experience_id varchar(191) NOT NULL,
  sent_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY send_key (send_key),
  KEY request_id (request_id),
  KEY contact_sent (contact_hash,sent_at)
) {$charset_collate};

CREATE TABLE {prefix}review_captain_recipient_guards (
  contact_hash char(64) NOT NULL,
  suppressed_at datetime DEFAULT NULL,
  suppression_reason varchar(40) DEFAULT NULL,
  last_sent_at datetime DEFAULT NULL,
  revision int(10) unsigned NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (contact_hash)
) {$charset_collate};
```

Hinweise:

- UNIQUE auf Spalte mit NULL erlaubt in MySQL/MariaDB mehrere NULL-Werte – genau das nutzt `suite_lock_key` zum Freigeben.
- Hash-Spalten statt Composite-Unique, weil `varchar(191)`×2 + bigint in utf8mb4 Index-Längengrenzen älterer Row-Formate überschreiten kann und feste Längen den Vergleich vereinfachen. Das Trennzeichen `|` ist in `experience_id`, `recipient_ref` und `idempotency_key` verboten (Pattern in `schema/experience-event.v1.json`), daher sind die Hash-Eingaben eindeutig.
- `suppression_reason`: `unsubscribed`, `complaint`, `bounce_hard`, `admin`, `erasure`, `source_revoked`.
- Privacy-Eraser: `recipient_ref`, `contact_hash` der Anfrage werden anonymisiert (`recipient_ref = 'erased:{uuid}'`, `contact_hash = NULL`); `recipient_guards`-Zeile mit `suppression_reason = erasure` bleibt bestehen, damit kein erneuter Versand erfolgt (Rechtsprüfung RC-014 offen), `request_sends` wird gelöscht.

## Anlage (T1) – Pseudocode

```php
$key  = sha1( $experience_id . '|' . $recipient_ref . '|' . $campaign_id );
$lock = sha1( $experience_id . '|' . $recipient_ref );
$ok   = $wpdb->query( $wpdb->prepare(
    "INSERT IGNORE INTO {$t} (uuid, campaign_id, …, invitation_key, suite_lock_key, status, …) VALUES (%s, %d, …, %s, %s, 'planned', …)",
    …
) );
if ( 1 !== $wpdb->rows_affected ) {
    return Result::duplicate( 'rc.request.duplicate' ); // S1 oder S2 belegt – keine zweite Zeile
}
```

`INSERT IGNORE` fängt nur Duplicate-Key-Konflikte; andere Fehler werden über `$wpdb->last_error` geprüft und als `rc.temp.unavailable` wiederholt.

## Abnahme

- [ ] Unique-Key `experience_id + recipient_ref + campaign_id` ist als `invitation_key` UNIQUE umgesetzt; paralleles Anlegen derselben Kombination ergibt genau eine Zeile.
- [ ] Suite-Sperre: zwei Kampagnen (z. B. native Pass-Kampagne und Workflow-Plan) für dasselbe Erlebnis + Empfänger → genau eine aktive Anfrage, die zweite `rc.request.duplicate`.
- [ ] Käufer- und Teilnehmerreferenz derselben Person für dasselbe Erlebnis → genau ein Versand (S3).
- [ ] Opt-out/Suppression und Cooldown werden vor jedem Versand atomar geprüft (S4); parallele Versandjobs an denselben Kontakt → höchstens einer sendet.
- [ ] Mehrdeutiger Transportausgang führt nie zu automatischem Zweitversand.
- [ ] Wiederholte, verspätete und konkurrierende Suite-Ereignisse erzeugen höchstens eine Einladung je Erlebnis/Empfänger (E2E RC-106).
