# Secrets: Tokenverschlüsselung und Schlüsselrotation (TEC-008)

Status: Spezifikation, Verfahren und Format an den implementierten Stand angeglichen (`src/Security/Crypto.php`), Stand 2026-11-16. Wo dieses Dokument und der Code abweichen, gilt der Code; nicht umgesetzte Teile sind als „Ausblick (nicht in v1)“ markiert. Entscheidung: [ADR-0005](../adr/0005-tokenverschluesselung-libsodium.md). Betroffene Daten: [provider-auth.md](provider-auth.md); Speicherort `profiles.credentials_enc` ([database.md](database.md) §2.3).

## 1. Schlüsselquelle

| Priorität | Quelle | Format | Diagnose-Status |
|---|---|---|---|
| 1 | Konstante `REVIEW_CAPTAIN_ENCRYPTION_KEY` in `wp-config.php` | Base64 (strikt) von genau 32 Bytes (`SODIUM_CRYPTO_SECRETBOX_KEYBYTES`) | `explicit` (grün) |
| 2 | Fallback aus WordPress-Salt: `sodium_crypto_generichash( 'review-captain|secretbox|v1|' . wp_salt( 'auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES )` | abgeleitet | `fallback` (gelb, dauerhafte Warnung) |

- Ist die Konstante gesetzt, aber ungültig (kein Base64, ≠ 32 Byte): **kein** Fallback, Status `invalid` (rot); keine Ver-/Entschlüsselung (`encrypt()` wirft `rc.crypto.key_missing`). Verbinden neuer Quellen gesperrt, bestehende Profile `auth_required` (Pro).
- Ohne `sodium_crypto_secretbox` (weder `ext-sodium` noch `sodium_compat`): Status `unavailable`.
- Status abfragbar über `Crypto::status()` (`explicit` \| `fallback` \| `invalid` \| `unavailable`), `is_fallback_key()`, `is_available()`, `key_id()`; `wp review-captain doctor` meldet ihn (`explicit` = ok, `fallback` = Warnung, sonst Fehler).
- Schlüssel erzeugen: `Crypto::generate_key()` liefert Base64 von 32 Zufallsbytes. Ausblick (nicht in v1): CLI `wp review-captain secrets generate-key`, die eine fertige `define(…)`-Zeile ausgibt (nur auf STDOUT, nie gespeichert/geloggt).
- Fallback-Warnung (Quellen-Seite, Diagnose, Site Health-Test): „Zugangsdaten sind mit einem aus den WordPress-Salts abgeleiteten Schlüssel verschlüsselt. Ändern sich die Salts (z. B. durch ein Sicherheits-Plugin), müssen alle Quellen neu verbunden werden. Empfehlung: `REVIEW_CAPTAIN_ENCRYPTION_KEY` setzen.“
- Multisite: Die Konstante gilt netzwerkweit; Kontextbindung (§2) verhindert, dass Chiffrate zwischen Sites kopiert verwendet werden.

## 2. Verfahren und Envelope

- Algorithmus: `sodium_crypto_secretbox` (XSalsa20-Poly1305), Nonce 24 Byte aus `random_bytes()`, **pro Verschlüsselung neu**. Verfügbar nativ (`ext-sodium`) oder über das in WordPress gebündelte `sodium_compat`.
- Verschlüsselt wird ein JSON-Envelope mit Kontext; der eigentliche Klartext (bei Credentials typischerweise selbst ein JSON-String des Aufrufers) steht in `d`:

```json
{ "v": 1, "ctx": { "blog_id": "3", "platform": "google", "profile_uuid": "…" }, "d": "{\"refresh_token\":\"…\"}" }
```

- Gespeichert: `rc1:{key_id}:{base64( nonce ‖ ciphertext )}` (Präfix `Crypto::PREFIX = 'rc1'`; Prüfmuster `^rc1:[0-9a-f]{16}:[A-Za-z0-9+/]+={0,2}$`).
  - `key_id` = erste 16 Hex-Zeichen von `sodium_crypto_generichash( 'review-captain|key-id|' . $key )` (kein Rückschluss auf den Schlüssel); zusätzlich in `profiles.key_id`.
- Kontext: `encrypt( string $plaintext, array $context = [] )` speichert den Kontext kanonisiert (nur skalare Werte, als Strings, nach Schlüssel sortiert). `decrypt( string $cipher, ?array $context = null )` prüft ihn **nur, wenn ein Kontext übergeben wird**; Abweichung = Fehler (Schutz gegen das Umkopieren von Chiffraten zwischen Profilen/Sites). Aufrufer müssen daher beim Entschlüsseln den erwarteten Kontext (z. B. `blog_id`, `profile_uuid`, `platform`) mitgeben.
- Fehler: `encrypt()` wirft `\RuntimeException( 'rc.crypto.key_missing' )`, `decrypt()` bei jedem Fehler (falscher/unbekannter Schlüssel, Manipulation, Formatfehler, `v` ≠ 1, Kontextabweichung) `\RuntimeException( 'rc.crypto.decrypt_failed' )` – nie mit Klartext oder Schlüsselmaterial in der Meldung. Beide Codes gehören zur Retry-Klasse `auth` ([error-codes.md](error-codes.md)).
- Zwischenpuffer (Envelope, entschlüsselter Klartext) werden mit `sodium_memzero()` überschrieben, soweit PHP das zulässt; `Crypto::__debugInfo()` zeigt nur `status` und `key_id`. Ausblick (nicht in v1): Value Object `Secret`, dessen `__toString()`, `__debugInfo()`, `jsonSerialize()` und `__serialize()` nur `"[redacted]"` liefern.
- `review_captain_key_check`: Option `{key_id, probe}` mit einem Testchiffrat des festen Klartexts `review-captain-key-check` (`store_key_check()`, `key_check_matches(): ?bool`) – erkennt Schlüsselwechsel, ohne Profile anfassen zu müssen.

## 3. Rotation

Implementiert (`Crypto`): `REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS` (gleiches Format) wird **nur** zum Entschlüsseln verwendet; `needs_rotation( $cipher ): bool` erkennt Chiffrate mit fremdem `key_id`, `rotate( $cipher, ?array $context = null ): string` entschlüsselt mit dem passenden Schlüssel und verschlüsselt mit dem aktuellen (Kontext bleibt erhalten), `Crypto::key_id_of( $cipher )` liefert den `key_id` eines Chiffrats.

Ablauf:

1. Neuen Schlüssel erzeugen (`Crypto::generate_key()`; Ausblick: `secrets generate-key`).
2. In `wp-config.php`: alten Wert nach `REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS` verschieben, neuen in `REVIEW_CAPTAIN_ENCRYPTION_KEY` setzen.
3. Ausblick (nicht in v1): `wp review-captain secrets rotate [--network]` bzw. Job `secrets.rotate` ruft `rotate()` für jedes Profil auf und schreibt per Compare-and-swap auf `credentials_rev`; resumable (nur Profile mit altem `key_id`).
4. Ausblick (nicht in v1): `wp review-captain secrets status` zeigt Anzahl Profile je `key_id`. Sind keine alten mehr vorhanden: `…_PREVIOUS` entfernen.
5. Ausblick (nicht in v1): Audit `secrets.rotated` mit Anzahl, ohne Schlüsselmaterial.

Wechsel von Fallback → explizitem Schlüssel: identischer Ablauf; der aus den Salts abgeleitete Schlüssel gilt bei gesetzter Konstante automatisch als „previous“ (implementiert), solange die Salts unverändert sind.

Rotation der **Provider-Credentials** selbst (neuer API-Key, neuer `.p8`): [provider-auth.md](provider-auth.md) je Provider.

## 4. Fehlendes oder falsches Schlüsselmaterial

| Situation | Verhalten |
|---|---|
| Entschlüsselung schlägt fehl (falscher Schlüssel, manipuliertes Chiffrat, Kontextabweichung) | kein Fatal, keine PHP-Warnung; `rc.crypto.decrypt_failed` → Jobs `paused` (Klasse `auth`); Profil `auth_required` (Pro); Admin-Hinweis „Zugangsdaten nicht lesbar – Schlüssel geändert? Quelle neu verbinden oder vorherigen Schlüssel als `REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS` hinterlegen.“ |
| Salts geändert im Fallback-Modus | wie oben, für alle Profile; Diagnose erklärt Ursache (`key_check` passt nicht) |
| Konstante ungültig | Status `invalid`, `rc.crypto.key_missing`, Verbinden gesperrt |
| Sodium nicht verfügbar (theoretisch) | Status `unavailable`, API-Verbindungen deaktiviert, Diagnose-Fehler |

Es wird **nie** automatisch ein neuer Schlüssel erzeugt, nie unverschlüsselt gespeichert und nie gelöscht, weil eine Entschlüsselung fehlschlägt.

## 5. Backup und Wiederherstellung

- Datenbank-Backups enthalten nur Chiffrate. Wiederherstellung funktioniert nur mit dem **zugehörigen** Schlüssel → Betreiber-Dokumentation: `wp-config.php` (Konstante bzw. Salts) separat und sicher sichern.
- Restore auf andere Umgebung (Staging-Klon): Kontext `blog_id` gleich, Schlüssel ggf. gleich → technisch entschlüsselbar. Deshalb pausiert die [Reconciliation](reconciliation.md) bei geänderter `home_url` alle Jobs mit Außenwirkung bis zur Bestätigung.
- Nach Restore prüft die Reconciliation jedes Profil (Testaufruf/Refresh); widerrufene Tokens werden als `auth_required` markiert, nie als gültig angezeigt.

## 6. Nie in Logs/Exporten

- Redaction in v1 dezentral: `JobQueue::sanitize_message()` (Job-Fehlermeldungen), `ConnectorException::sanitize_message()` (Connector-/Provider-Meldungen: E-Mail → `[email]`, tokenartige Zeichenketten → `[redacted]`, max. 500 Zeichen) und die Allowlist von `AuditLog` (Audit-Kontext). Ausblick (nicht in v1): zentraler `ReviewCaptain\Support\Redactor` auch für Debug-Export, HTTP-Fehlerausgaben, `WP_Error`-Daten.
- Ausblick (Pro/Provider-Runtime): Ausgehende HTTP-Requests: Header `Authorization`, `apikey`, `x-api-key` und Body-Felder `client_secret`, `refresh_token`, `assertion`, `code`, `code_verifier` werden vor jeder Protokollierung entfernt. `WP_DEBUG_LOG`-Ausgaben des Plugins laufen ebenfalls über den Redactor.
- CSV-/Einstellungs-/Debug-Export und WordPress Privacy Exporter enthalten `credentials_enc` nie, auch nicht verschlüsselt.
- Admin-Formulare: Secret-Felder `type="password"`, `autocomplete="off"`, Wert nie zurück ins HTML gerendert; leeres Feld beim Speichern = unverändert.

## Abnahme

- [ ] Verschlüsseln/Entschlüsseln mit nativer Extension und `sodium_compat` (Test, Nonce je Aufruf verschieden).
- [ ] Manipuliertes Chiffrat, falscher Schlüssel und kopiertes Chiffrat eines anderen Profils/einer anderen Site werden abgelehnt → `auth_required`.
- [ ] Fallback-Modus zeigt Warnung (Diagnose, Quellen, Site Health); ungültige Konstante sperrt Verbinden.
- [ ] `Crypto::rotate()` mit `…_PREVIOUS` verschlüsselt neu (Unit-Test); Ausblick: Massenrotation verschlüsselt alle Profile neu, ist abbrech- und fortsetzbar, ohne dass eine Verbindung ausfällt.
- [ ] Suchtest: bekannter Klartext-Token taucht nach Sync, Fehler, Export, Debug-Export, Audit und `WP_DEBUG_LOG` nirgends auf (DB-Dump + Logdateien + HTML).
- [ ] Restore ohne passenden Schlüssel → alle Profile `auth_required`, keine Daten gelöscht.
