# Normalisiertes Review-Schema v1 (RC-018)

| | |
|---|---|
| Status | Spezifikation, Entwurf zur Abnahme |
| Schema-ID | `review_captain.review_record.v1` (Datensatz) – nicht zu verwechseln mit dem Hook-Payload `review_captain.review.v1` aus conventions.md |
| JSON-Schema | [`schema/review.v1.json`](schema/review.v1.json) |
| Tabelle | `{$wpdb->prefix}review_captain_reviews` |
| Bezug | TODO RC-018, TEC-004; konzept.md 4 „Normalisiertes Review-Modell“, 10 |

Das Schema gilt für (a) Zeilen der Tabelle `reviews`, (b) die Ausgabe von `normalize()` eines Providers (`provider-v1.md`), (c) Import/Export und (d) den Connector-Fähigkeitstyp „Review Import Source“. Feldnamen sind identisch mit den Spaltennamen aus `conventions.md`.

## Feldliste

Reihenfolge und Typen exakt nach conventions.md. „Provider liefert“ = Feld darf in `normalize()` gesetzt werden; alle anderen setzt der Core.

| Feld | Typ (DB) | Pflicht | Werte / Regel | Provider liefert |
|---|---|---|---|---|
| `id` | BIGINT PK | Core | Auto-Increment, nie extern verwendet | nein |
| `uuid` | CHAR(36) UNIQUE | ja | UUID v4, stabil, öffentliche Referenz (Hooks, Export, REST) | nein |
| `kind` | ENUM-String | ja | `review` \| `testimonial` | ja (immer `review`) |
| `origin` | ENUM-String | ja | `local_submission` \| `manual` \| `csv_import` \| `provider_sync` \| `connector` | nein (Core setzt `provider_sync`) |
| `platform` | ENUM-String | ja | `local` \| `google` \| `trustpilot` \| `trusted_shops` \| `app_store` \| `google_play` \| `other` | ja (muss zum Provider passen) |
| `profile_id` | BIGINT NULL | bei `provider_sync` | FK `profiles.id` | nein (Core aus Kontext) |
| `external_id` | VARCHAR(191) NULL | bei `provider_sync` | stabile Plattform-ID, unverändert gespeichert (siehe „Stabile externe ID“) | ja |
| `rating_value` | DECIMAL(6,2) NULL | nein | `rating_scale_min ≤ rating_value ≤ rating_scale_max` | ja |
| `rating_scale_min` | DECIMAL(6,2) | ja | Default `1` | ja |
| `rating_scale_max` | DECIMAL(6,2) | ja | Default `5`, `> rating_scale_min` | ja |
| `title` | VARCHAR(255) | ja (leer erlaubt) | Klartext, kein HTML | ja |
| `body` | LONGTEXT | ja (leer erlaubt) | Klartext; Zeilenumbrüche erlaubt; kein HTML (wird beim Speichern via `sanitize_textarea_field` bereinigt, bei Ausgabe escaped) | ja |
| `language` | VARCHAR(20) | ja | BCP-47-Tag, siehe „Sprache“; `und` wenn unbekannt | ja |
| `author_name` | VARCHAR(191) | ja (leer erlaubt) | öffentlicher Anzeigename, nur wenn Plattform dies erlaubt; sonst leer → Anzeige „Anonym“ | ja |
| `author_email` | VARCHAR(191) NULL | nur `local_submission` | nie öffentlich, nie in Hooks/Logs; für alle anderen `origin` **muss** NULL sein | nein |
| `author_user_id` | BIGINT NULL | nein | WP-User-ID, nur lokale Einreichung eingeloggter Nutzer | nein |
| `verification` | ENUM-String | ja | `unverified` \| `purchase_verified` \| `attendance_confirmed` \| `external_platform` (Setzregeln: `system-of-record.md` Regel 4) | nein (Core setzt `external_platform`) |
| `status` | ENUM-String | ja | `pending` \| `approved` \| `rejected` \| `archived` \| `flagged` \| `removed` | nein |
| `context_type` | VARCHAR(40) NULL | nein | `post` \| `product` \| `event` \| `voucher` \| `app` \| `form` | ja (z. B. `app`, `product`) |
| `context_id` | VARCHAR(64) NULL | wenn `context_type` gesetzt | Objekt-ID im Kontext (WP-Post-ID, Paketname, Event-ID …) | ja |
| `source_url` | TEXT NULL | empfohlen extern | Deep-Link zur Original-Review bzw. zum Profil; nur `https://` | ja |
| `published_at` | DATETIME NULL | empfohlen | Originaldatum der Review (UTC) | ja |
| `fetched_at` | DATETIME NULL | bei `provider_sync` | letzter erfolgreicher Abruf dieses Datensatzes (UTC) | nein |
| `refresh_due_at` | DATETIME NULL | bei `provider_sync` | späteste Anzeigegrenze ohne erneuten Abruf, siehe „Cachezeit“ | nein |
| `consent_publish` | TINYINT | ja | `1` = Veröffentlichung erlaubt; Pflicht `1` für Sichtbarkeit bei `local_submission`, `testimonial` | nein |
| `consent_at` | DATETIME NULL | wenn `consent_publish=1` lokal | Zeitpunkt der Einwilligung | nein |
| `import_batch_id` | BIGINT NULL | bei `csv_import` | FK `import_batches.id` | nein |
| `experience_id` | VARCHAR(191) NULL | nein | Referenz auf das Erlebnis (siehe `experience-event-v1.md`), z. B. bei Review nach Einladung | nein |
| `meta` | LONGTEXT (JSON) | ja (`{}`) | siehe „meta-Schlüssel“; max. 64 KiB; **keine Secrets, keine E-Mail** | teilweise |
| `created_at` / `updated_at` | DATETIME (UTC) | ja | Core | nein |
| `removed_at` | DATETIME NULL | nein | Tombstone-Zeitpunkt | nein |
| `dedupe_key` | VARCHAR(191) UNIQUE NULL | generiert | `sha1(platform|profile_id|external_id)` wenn `external_id` gesetzt (TEC-004) | nein |

### meta-Schlüssel (v1, Allowlist)

Unbekannte Schlüssel werden beim Speichern verworfen. Alle Werte JSON-serialisierbar.

| Schlüssel | Typ | Zweck |
|---|---|---|
| `provider_key` | string | Schlüssel des Providers, der den Datensatz geliefert hat |
| `review_type` | `service` \| `product` \| `app` \| `local` | fachlicher Reviewtyp (konzept 4) |
| `attribution` | object `{label, url, notice}` | Quellenlabel/Attributionshinweis wie vom Provider verlangt |
| `avatar_url` | string (https) | nur wenn Plattform Anzeige erlaubt; Laden nur consent-gesteuert |
| `reply` | object `{body, published_at}` | öffentliche Antwort, falls API liefert |
| `remote_updated_at` | ISO 8601 | Änderungsdatum auf der Plattform |
| `last_verified_at` | ISO 8601 | zuletzt bestätigt vorhanden (Full-Reconcile) |
| `product_ref` | object `{type, id, label}` | Produkt-/Versionsbezug |
| `app_version`, `territory` | string | App-Store-Metadaten |
| `original_language`, `translated` | string, bool | Übersetzungskennzeichen (siehe „Sprache“) |
| `source_plugin`, `source_entry_id` | string | Herkunft bei `origin=connector` (z. B. `form-captain`) |
| `idempotency_key` | string | Idempotenz bei `connector`/`local_submission` |
| `tombstone` | object `{reason, at}` | siehe „Tombstone“ |
| `testimonial` | object | nur `kind=testimonial`, siehe `testimonial-schema-v1.md` |
| `moderation_note` | string | interne Notiz, nie öffentlich |

## Skalen

- Gespeichert wird **immer der Originalwert** mit Originalskala (`rating_value`, `rating_scale_min`, `rating_scale_max`). Es findet keine Umrechnung beim Speichern statt.
- Lokale Reviews und Form-Captain-Entwürfe: ganzzahlig 1–5.
- `rating_value = NULL` ist zulässig (z. B. Text-Testimonial, Plattform ohne Sterne). Solche Datensätze zählen in keiner Rating-Kennzahl.
- Plattformen mit Nicht-Sterne-Skalen (z. B. Empfehlung ja/nein) liefern entweder eine numerische Abbildung, die der Provider in `capabilities().rating_scale` dokumentiert, oder `rating_value = NULL`.
- **Normalisierter Vergleichswert** (nur zur Laufzeit, z. B. für `min_rating` im Widget):
  `norm5 = 1 + 4 × (rating_value − rating_scale_min) / (rating_scale_max − rating_scale_min)`, gerundet auf 2 Nachkommastellen. Wird nie gespeichert und nie als Plattformwert angezeigt.
- Anzeige: Original-Skala sichtbar („4,5 von 5“), Sterne nie nur über Farbe.
- Ein plattformübergreifender Score wird aus diesem Schema nicht abgeleitet (RC-114).

## Sprache

- `language` ist ein BCP-47-Tag in Kleinbuchstaben-Sprache und Großbuchstaben-Region (`de`, `de-DE`, `de-AT`, `en-GB`). Unterstrich-Locales (`de_DE`) werden beim Normalisieren zu `de-DE` umgeschrieben.
- Unbekannt: `und`.
- Lokale Einreichung: Sprache der Seite zum Einreichzeitpunkt (`determine_locale()`), normalisiert.
- Liefert eine Plattform nur eine Übersetzung, gilt: `language` = Sprache des gespeicherten Textes, `meta.translated = true`, `meta.original_language` = Originalsprache (wenn bekannt). Übersetzte Texte werden in der Ausgabe als übersetzt gekennzeichnet.

## Profil, Quelle und stabile externe ID

- `platform` benennt die Plattform; `profile_id` das konkrete verbundene Profil (Standort, Business Unit, Kanal, App). Der Provider-Schlüssel steht in `meta.provider_key`.
- `external_id` ist die vom Provider dokumentierte, **stabile** ID der Review. Ist die API-ID nicht stabil, bildet der Provider eine deterministische ID und dokumentiert die Ableitung in seinem Fixture (`provider-v1.md`). Zeitstempel oder Listenpositionen sind keine zulässigen IDs.
- Eindeutigkeit über `dedupe_key = sha1(platform . '|' . profile_id . '|' . external_id)`. Upsert ausschließlich über `dedupe_key` (`INSERT … ON DUPLICATE KEY UPDATE`), nie „select then insert“.
- Manuell erfasste externe Auszüge (`origin=manual`) dürfen `external_id` setzen; dann greift dieselbe Dublettenregel.

## Kontext

`context_type`/`context_id` ordnen eine Review einem Objekt zu (Produkt, Event, Gutschein, App, Formular, Beitrag). Für `post`/`product` ist `context_id` die WP-Post-ID als String; für `event`/`voucher` die öffentliche stabile ID des Quell-Plugins (`object_ref.id` aus dem Ereignisvertrag); für `app` der Paketname/App-Ressourcen-ID; für `form` die Form-ID. Ein Kontext ist ein Anzeige-/Filterbezug, **kein** Verifizierungsbeleg.

## Cachezeit (`fetched_at`, `refresh_due_at`)

- `fetched_at` = Zeitpunkt der letzten erfolgreichen Bestätigung dieses Datensatzes durch die Plattform.
- `refresh_due_at = fetched_at + max_cache_ttl` des Providers (aus `capabilities().max_cache_ttl_seconds`). Der Refresh-Job plant eine Aktualisierung spätestens bei `refresh_due_at − refresh_margin` (Default 10 % der TTL).
- Ist `refresh_due_at` überschritten und kein Refresh gelungen, wird der Datensatz **nicht mehr angezeigt** (Sichtbarkeitsregel 5), aber nicht gelöscht. Nach erfolgreichem Refresh ist er wieder sichtbar.
- `max_cache_ttl` je Plattform ist **nicht** in diesem Dokument festgelegt, sondern im Auth-/Provider-Record (RC-010 bis RC-013, RC-026, RC-027). Bis zur Bestätigung gilt ein konservativer Default von 24 h.
- Für `origin ∈ {local_submission, manual, csv_import, connector}` ist `refresh_due_at = NULL` (kein Ablauf durch Cache).

## Tombstone und Löschung

| Auslöser | Aktion | `status` | Inhalt |
|---|---|---|---|
| Provider meldet Löschung (`tombstones` in `sync_page`) oder Full-Reconcile findet Datensatz nicht mehr | Tombstone | `removed` | `title`, `body`, `author_name`, `meta.avatar_url`, `meta.reply` werden **geleert**; `rating_value` auf NULL |
| Privacy-Eraser für lokale Einreichung | Tombstone | `removed` | zusätzlich `author_email`, `author_user_id` = NULL |
| Admin „endgültig löschen“ | Hard-Delete inkl. `review_events` (Audit-Eintrag bleibt ohne Inhalt) | – | – |
| Aufbewahrungsfrist für Tombstones abgelaufen (RC-027, Default-Vorschlag 30 Tage) | Hard-Delete | – | – |

- Tombstone setzt `removed_at = now()`, `meta.tombstone = {reason, at}` mit `reason ∈ {remote_removed, remote_not_found, erasure, disconnect_retention, admin}` und feuert `review_captain_review_removed_v1` nach Commit.
- `uuid`, `platform`, `profile_id`, `external_id`, `dedupe_key` bleiben erhalten, damit ein erneuter Sync dieselbe Zeile trifft.
- **Wiederauftauchen:** Liefert die Plattform eine getombstonte Review erneut (z. B. nach Wiederherstellung auf der Plattform), wird sie über `dedupe_key` reaktiviert: Inhalt neu befüllt, `removed_at = NULL`, `status = pending` (erneute Moderation), Event `review_status_changed_v1`.
- Lokale Moderation ist **kein** Tombstone: `rejected`/`archived` behalten den Inhalt.

## Status und Sichtbarkeitslogik

### Statusbedeutung

| Status | Bedeutung | öffentlich sichtbar |
|---|---|---|
| `pending` | wartet auf Moderation | nein |
| `approved` | freigegeben | ja, wenn alle Regeln unten erfüllt |
| `rejected` | abgelehnt | nein |
| `archived` | vom Betreiber ausgeblendet oder automatisch nach Rechteablauf | nein |
| `flagged` | gemeldet, in Prüfung | nein |
| `removed` | Tombstone | nein |

Konzept-Begriffe „sichtbar/ausgeblendet/archiviert“: sichtbar = `approved` + Regeln; ausgeblendet = `rejected`/`flagged`/`pending`; archiviert = `archived`.

Default-Status neuer Datensätze: `local_submission`, `connector`, `csv_import` → `pending`; `manual` → Wahl im Admin (Default `pending`); `provider_sync` → Profileinstellung `import_status` (Default `pending`, **Produktentscheidung offen**).

### Sichtbarkeitsregel (öffentliche Ausgabe)

Ein Datensatz ist öffentlich sichtbar genau dann, wenn **alle** Bedingungen gelten:

1. `status = 'approved'`
2. `removed_at IS NULL`
3. bei `origin = 'local_submission'` oder `kind = 'testimonial'`: `consent_publish = 1`
4. bei `kind = 'testimonial'`: Rechte gültig (nicht widerrufen, nicht abgelaufen; `testimonial-schema-v1.md`)
5. bei `origin = 'provider_sync'`: `refresh_due_at IS NULL OR refresh_due_at > UTC_NOW` (nach Disconnect gilt zusätzlich die Aufbewahrungsfrist; nach deren Ablauf werden die Snapshots mit Reason `disconnect_retention` getombstont)
6. Der Datensatz passt zu den Filtern des Widgets (`widget-v1.md`).

Referenz-SQL (Core, alle Werte über `$wpdb->prepare`):

```sql
SELECT … FROM {prefix}review_captain_reviews r
WHERE r.status = 'approved'
  AND r.removed_at IS NULL
  AND (r.origin <> 'local_submission' OR r.consent_publish = 1)
  AND (r.kind <> 'testimonial' OR r.consent_publish = 1)
  AND (r.origin <> 'provider_sync' OR r.refresh_due_at IS NULL OR r.refresh_due_at > %s)
  -- Regel 4 (Rechteablauf) wird zusätzlich in PHP geprüft; der Ablauf-Job setzt abgelaufene Testimonials auf 'archived'.
ORDER BY COALESCE(r.published_at, r.created_at) DESC, r.id DESC
LIMIT %d
```

Schema-Markup-Berechtigung ist eine zusätzliche, strengere Prüfung (conventions.md, Filter `review_captain_schema_eligible` darf nur verschärfen).

## Beispiele

Externe Review nach `normalize()` (vom Core ergänzt um `uuid`, `origin`, `status` …):

```json
{
  "uuid": "6f1c2a8e-3b7d-4f0a-9a51-0d2e4c9b7a11",
  "kind": "review",
  "origin": "provider_sync",
  "platform": "other",
  "profile_id": 7,
  "external_id": "demo-rev-1001",
  "rating_value": 4.0,
  "rating_scale_min": 1,
  "rating_scale_max": 5,
  "title": "Schnelle Lieferung",
  "body": "Alles wie beschrieben, gerne wieder.",
  "language": "de-DE",
  "author_name": "M. K.",
  "author_email": null,
  "author_user_id": null,
  "verification": "external_platform",
  "status": "pending",
  "context_type": null,
  "context_id": null,
  "source_url": "https://reviews.example.test/r/demo-rev-1001",
  "published_at": "2026-11-10T09:12:00Z",
  "fetched_at": "2026-11-16T06:00:00Z",
  "refresh_due_at": "2026-11-17T06:00:00Z",
  "consent_publish": 0,
  "consent_at": null,
  "import_batch_id": null,
  "experience_id": null,
  "meta": { "provider_key": "demo", "review_type": "service", "attribution": { "label": "Demo Reviews", "url": "https://reviews.example.test/p/acme", "notice": null } },
  "created_at": "2026-11-16T06:00:01Z",
  "updated_at": "2026-11-16T06:00:01Z",
  "removed_at": null
}
```

Lokale Einreichung (vor Moderation):

```json
{
  "uuid": "0b7e9a3c-51a2-4c1e-8f3e-2a9d7c6b5e44",
  "kind": "review",
  "origin": "local_submission",
  "platform": "local",
  "profile_id": null,
  "external_id": null,
  "rating_value": 5,
  "rating_scale_min": 1,
  "rating_scale_max": 5,
  "title": "",
  "body": "Sehr freundliche Beratung im Laden.",
  "language": "de-AT",
  "author_name": "Anna",
  "author_email": "anna@example.test",
  "author_user_id": null,
  "verification": "unverified",
  "status": "pending",
  "context_type": "post",
  "context_id": "42",
  "source_url": null,
  "published_at": "2026-11-16T10:00:00Z",
  "fetched_at": null,
  "refresh_due_at": null,
  "consent_publish": 1,
  "consent_at": "2026-11-16T10:00:00Z",
  "import_batch_id": null,
  "experience_id": null,
  "meta": {},
  "created_at": "2026-11-16T10:00:00Z",
  "updated_at": "2026-11-16T10:00:00Z",
  "removed_at": null
}
```

Tombstone nach Plattformlöschung:

```json
{
  "uuid": "6f1c2a8e-3b7d-4f0a-9a51-0d2e4c9b7a11",
  "platform": "other",
  "profile_id": 7,
  "external_id": "demo-rev-1001",
  "status": "removed",
  "title": "",
  "body": "",
  "author_name": "",
  "rating_value": null,
  "removed_at": "2026-11-18T06:00:00Z",
  "meta": { "provider_key": "demo", "tombstone": { "reason": "remote_removed", "at": "2026-11-18T06:00:00Z" } }
}
```

## Abnahme

- [ ] Feldliste entspricht 1:1 den Spalten aus conventions.md (Contract-Test vergleicht `Schema::columns()` mit `schema/review.v1.json`).
- [ ] Skalen: Originalwert/-skala werden gespeichert; Validator lehnt `rating_value` außerhalb `[min,max]` und `max ≤ min` mit `rc.data.invalid_payload` ab; `norm5` ist nur Laufzeitwert.
- [ ] Sprache: `de_DE` wird zu `de-DE`, fehlend zu `und`; Übersetzungen sind gekennzeichnet.
- [ ] Profil/Quelle/externe ID: Zwei parallele Upserts derselben Review erzeugen genau eine Zeile (`dedupe_key` UNIQUE).
- [ ] Kontext: `context_id` ohne `context_type` wird abgelehnt; unbekannter `context_type` wird abgelehnt.
- [ ] Cachezeit: Datensatz mit `refresh_due_at` in der Vergangenheit wird nicht gerendert, bleibt aber im Admin sichtbar.
- [ ] Tombstone: Inhalt geleert, Identität erhalten, `review_removed_v1` nach Commit; Wiederauftauchen reaktiviert als `pending`.
- [ ] Sichtbarkeit: Testmatrix über alle 6 Status × `origin` × `consent_publish` × `refresh_due_at` bestätigt die Regel.
- [ ] `author_email` ist für `origin ≠ local_submission` immer NULL und erscheint in keinem Hook-Payload, Log oder öffentlichen Output.
