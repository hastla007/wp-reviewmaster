# ADR-0005: Tokenverschlüsselung mit libsodium secretbox

- **Status:** Angenommen
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Core-Maintainer Review Captain
- **Betrifft:** RC-026, TEC-008, TEC-015, RC-078; `docs/architecture/secrets.md`, `docs/architecture/provider-auth.md`
- **Ersetzt:** –

## Kontext

Pro speichert Provider-Zugangsdaten (OAuth-Refresh-Tokens, Client-Secrets, Trustpilot-API-Secrets, App-Store-Connect-`.p8`-Schlüssel, Google-Service-Account-Keys) auf der Betreiber-Site. Konzept 11: „Secrets verschlüsselt ablegen; WordPress-Keys sind nicht als dauerhaftes Secret-Management zu behandeln, wenn der Betreiber explizite Schlüssel konfigurieren muss.“ Verfügbarkeit: PHP ≥ 7.2 enthält `ext-sodium` meist; WordPress liefert seit 5.2 `sodium_compat` als Polyfill mit, d. h. die Funktionen sind auf jeder unterstützten Installation vorhanden.

## Betrachtete Optionen

1. **OpenSSL AES-256-GCM** – verfügbar, aber fehleranfällige Parameter (IV-Länge, Tag-Handling), `ext-openssl` nicht garantiert.
2. **libsodium `crypto_secretbox` (XSalsa20-Poly1305)** – authentifizierte Verschlüsselung, 24-Byte-Zufalls-Nonce ohne Kollisionsrisiko in der Praxis, Polyfill durch WordPress garantiert.
3. **Externes Secret-Management (Vault, KMS)** – für typische WordPress-Hosts nicht verfügbar; ggf. später als optionaler Adapter.

## Entscheidung

- Verfahren: `sodium_crypto_secretbox()` mit 32-Byte-Schlüssel und je Verschlüsselung neuer Nonce aus `random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES )`.
- **Primärer Schlüssel:** Konstante `REVIEW_CAPTAIN_ENCRYPTION_KEY` in `wp-config.php`, Base64-kodierte 32 Byte (z. B. erzeugt mit `wp review-captain secrets generate-key`).
- **Fallback:** Fehlt die Konstante, wird ein Schlüssel aus `wp_salt( 'auth' )` abgeleitet (`sodium_crypto_generichash( 'review-captain|secretbox|v1|' . wp_salt( 'auth' ), '', 32 )`). Im Fallback zeigen Quellen-Seite und Diagnose eine dauerhafte Warnung für Nutzer mit `review_captain_manage_sources` bzw. `review_captain_manage_settings`: Salt-Rotation macht alle Zugangsdaten unlesbar, Backups sind nur zusammen mit `wp-config.php` wiederherstellbar.
- Envelope-Format, Kontextbindung, Rotation und Fehlerverhalten: `docs/architecture/secrets.md`.
- Bei ungültigem/fehlendem Schlüssel wird nie entschlüsselt geraten, nie ein neuer Schlüssel automatisch erzeugt und nie gelöscht: betroffene Profile gehen auf `auth_required`, Jobs auf `paused` (`rc.auth.invalid`).

## Konsequenzen

- Positiv: einheitlicher, auditierbarer Weg; funktioniert ohne Zusatz-Extensions.
- Negativ: Ohne Konstante ist die Sicherheit an die WordPress-Salts gebunden (Angreifer mit DB- **und** `wp-config.php`-Zugriff kann entschlüsseln – unvermeidbar bei lokaler Speicherung). Schlüsselverlust erfordert Neuverbindung aller Quellen.
- Folgearbeiten: CLI `secrets generate-key|rotate|status`, Diagnose-Anzeige, Tests mit `sodium_compat`.

## Abnahme

- [ ] Roundtrip-Tests mit nativer Extension und mit `sodium_compat`.
- [ ] Manipulierter Ciphertext, falscher Schlüssel oder vertauschte Profilzuordnung → Entschlüsselung schlägt fehl, Profil `auth_required`, keine PHP-Warnung/Fatal, kein Secret im Log.
- [ ] Fallback-Modus zeigt Warnung in Diagnose und Quellen-Seite.
- [ ] Rotation per CLI verschlüsselt alle Profile neu, ohne Verbindungsabbruch.

## Referenzen

- `konzept.md` 7.9 (Authentifizierung), 11
- libsodium-Dokumentation „Secret-key authenticated encryption“
