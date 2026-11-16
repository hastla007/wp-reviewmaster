# Secrets: Tokenverschlüsselung und Schlüsselrotation (TEC-008)

Status: Spezifikation, Stand 2026-11-16. Entscheidung: [ADR-0005](../adr/0005-tokenverschluesselung-libsodium.md). Betroffene Daten: [provider-auth.md](provider-auth.md); Speicherort `profiles.credentials_enc` ([database.md](database.md) §2.3).

## 1. Schlüsselquelle

| Priorität | Quelle | Format | Diagnose-Status |
|---|---|---|---|
| 1 | Konstante `REVIEW_CAPTAIN_ENCRYPTION_KEY` in `wp-config.php` | Base64 von genau 32 zufälligen Bytes | `explicit` (grün) |
| 2 | Fallback aus WordPress-Salt: `sodium_crypto_generichash( 'review-captain|secretbox|v1|' . wp_salt( 'auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES )` | abgeleitet | `fallback` (gelb, dauerhafte Warnung) |

- Ist die Konstante gesetzt, aber ungültig (kein Base64, ≠ 32 Byte): **kein** Fallback, Status `invalid` (rot); keine Ver-/Entschlüsselung, Verbinden neuer Quellen gesperrt, bestehende Profile `auth_required`.
- Schlüssel erzeugen: `wp review-captain secrets generate-key` gibt eine fertige `define(…)`-Zeile aus (nur auf STDOUT, nie gespeichert/geloggt).
- Fallback-Warnung (Quellen-Seite, Diagnose, Site Health-Test): „Zugangsdaten sind mit einem aus den WordPress-Salts abgeleiteten Schlüssel verschlüsselt. Ändern sich die Salts (z. B. durch ein Sicherheits-Plugin), müssen alle Quellen neu verbunden werden. Empfehlung: `REVIEW_CAPTAIN_ENCRYPTION_KEY` setzen.“
- Multisite: Die Konstante gilt netzwerkweit; Kontextbindung (§2) verhindert, dass Chiffrate zwischen Sites kopiert verwendet werden.

## 2. Verfahren und Envelope

- Algorithmus: `sodium_crypto_secretbox` (XSalsa20-Poly1305), Nonce 24 Byte aus `random_bytes()`, **pro Verschlüsselung neu**. Verfügbar nativ (`ext-sodium`) oder über das in WordPress gebündelte `sodium_compat`.
- Klartext ist JSON mit Kontext:

```json
{ "v": 1, "ctx": { "blog_id": 3, "profile_uuid": "…", "platform": "google" }, "secrets": { "refresh_token": "…" }, "access": { "token": "…", "expires_at": "2026-11-16T12:00:00Z" } }
```

- Gespeichert: `rc1:{key_id}:{base64( nonce ‖ ciphertext )}`.
  - `key_id` = erste 16 Hex-Zeichen von `sodium_crypto_generichash( 'review-captain|key-id|' . $key )` (kein Rückschluss auf den Schlüssel); zusätzlich in `profiles.key_id`.
- Beim Entschlüsseln wird `ctx` gegen die aktuelle Zeile geprüft (gleiche `blog_id`, `profile_uuid`, `platform`); Abweichung = Fehler (Schutz gegen das Umkopieren von Chiffraten zwischen Profilen/Sites).
- Klartext-Strings werden nach Gebrauch mit `sodium_memzero()` überschrieben, soweit PHP das zulässt; Secrets werden in einem Value Object `Secret` gehalten, dessen `__toString()`, `__debugInfo()`, `jsonSerialize()` und `__serialize()` nur `"[redacted]"` liefern.
- `review_captain_key_check`: Option mit `key_id` und einem Testchiffrat eines festen Klartexts – erkennt Schlüsselwechsel, ohne Profile anfassen zu müssen.

## 3. Rotation

1. Neuen Schlüssel erzeugen (`secrets generate-key`).
2. In `wp-config.php`: alten Wert nach `REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS` verschieben, neuen in `REVIEW_CAPTAIN_ENCRYPTION_KEY` setzen. (`…_PREVIOUS` wird **nur** zum Entschlüsseln verwendet.)
3. `wp review-captain secrets rotate [--network]`: entschlüsselt jedes Profil mit dem zum `key_id` passenden Schlüssel, verschlüsselt mit dem neuen, schreibt per Compare-and-swap auf `credentials_rev`. Fortschritt resumable (nur Profile mit altem `key_id`). Admin-Alternative: Job `secrets.rotate` über Diagnose („Jetzt neu verschlüsseln“, `review_captain_manage_settings`).
4. `wp review-captain secrets status` zeigt Anzahl Profile je `key_id`. Sind keine alten mehr vorhanden: `…_PREVIOUS` entfernen.
5. Audit: `secrets.rotated` mit Anzahl, ohne Schlüsselmaterial.

Wechsel von Fallback → explizitem Schlüssel: identischer Ablauf; der Fallback-Schlüssel gilt dabei automatisch als „previous“, solange die Salts unverändert sind.

Rotation der **Provider-Credentials** selbst (neuer API-Key, neuer `.p8`): [provider-auth.md](provider-auth.md) je Provider.

## 4. Fehlendes oder falsches Schlüsselmaterial

| Situation | Verhalten |
|---|---|
| Entschlüsselung schlägt fehl (falscher Schlüssel, manipuliertes Chiffrat, Kontextabweichung) | kein Fatal, keine PHP-Warnung; Profil `auth_required`, Jobs `paused` mit `rc.auth.invalid`; Admin-Hinweis „Zugangsdaten nicht lesbar – Schlüssel geändert? Quelle neu verbinden oder vorherigen Schlüssel als `REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS` hinterlegen.“ |
| Salts geändert im Fallback-Modus | wie oben, für alle Profile; Diagnose erklärt Ursache (`key_check` passt nicht) |
| Konstante ungültig | Status `invalid`, Verbinden gesperrt |
| Sodium nicht verfügbar (theoretisch) | API-Verbindungen deaktiviert, Diagnose-Fehler |

Es wird **nie** automatisch ein neuer Schlüssel erzeugt, nie unverschlüsselt gespeichert und nie gelöscht, weil eine Entschlüsselung fehlschlägt.

## 5. Backup und Wiederherstellung

- Datenbank-Backups enthalten nur Chiffrate. Wiederherstellung funktioniert nur mit dem **zugehörigen** Schlüssel → Betreiber-Dokumentation: `wp-config.php` (Konstante bzw. Salts) separat und sicher sichern.
- Restore auf andere Umgebung (Staging-Klon): Kontext `blog_id` gleich, Schlüssel ggf. gleich → technisch entschlüsselbar. Deshalb pausiert die [Reconciliation](reconciliation.md) bei geänderter `home_url` alle Jobs mit Außenwirkung bis zur Bestätigung.
- Nach Restore prüft die Reconciliation jedes Profil (Testaufruf/Refresh); widerrufene Tokens werden als `auth_required` markiert, nie als gültig angezeigt.

## 6. Nie in Logs/Exporten

- Redaction zentral in `ReviewCaptain\Support\Redactor` für: Job-Fehlermeldungen, Audit-Kontext, Debug-Export, HTTP-Fehlerausgaben, `WP_Error`-Daten.
- Ausgehende HTTP-Requests: Header `Authorization`, `apikey`, `x-api-key` und Body-Felder `client_secret`, `refresh_token`, `assertion`, `code`, `code_verifier` werden vor jeder Protokollierung entfernt. `WP_DEBUG_LOG`-Ausgaben des Plugins laufen ebenfalls über den Redactor.
- CSV-/Einstellungs-/Debug-Export und WordPress Privacy Exporter enthalten `credentials_enc` nie, auch nicht verschlüsselt.
- Admin-Formulare: Secret-Felder `type="password"`, `autocomplete="off"`, Wert nie zurück ins HTML gerendert; leeres Feld beim Speichern = unverändert.

## Abnahme

- [ ] Verschlüsseln/Entschlüsseln mit nativer Extension und `sodium_compat` (Test, Nonce je Aufruf verschieden).
- [ ] Manipuliertes Chiffrat, falscher Schlüssel und kopiertes Chiffrat eines anderen Profils/einer anderen Site werden abgelehnt → `auth_required`.
- [ ] Fallback-Modus zeigt Warnung (Diagnose, Quellen, Site Health); ungültige Konstante sperrt Verbinden.
- [ ] Rotation mit `…_PREVIOUS` verschlüsselt alle Profile neu, ist abbrech- und fortsetzbar, ohne dass eine Verbindung ausfällt.
- [ ] Suchtest: bekannter Klartext-Token taucht nach Sync, Fehler, Export, Debug-Export, Audit und `WP_DEBUG_LOG` nirgends auf (DB-Dump + Logdateien + HTML).
- [ ] Restore ohne passenden Schlüssel → alle Profile `auth_required`, keine Daten gelöscht.
