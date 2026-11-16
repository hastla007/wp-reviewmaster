# Authentifizierung und Secrets – RC-026, TEC-008

**Status:** Entwurf – Google-OAuth-App-Eigentum und Verifizierung (RC-009) offen  
**Stand:** 2026-11-16

## RC-026 – Modell je Provider

| Provider | Modell 1.0 | Credential | Scope/Recht (minimal) | Rotation | Widerruf | Gespeichert |
|---|---|---|---|---|---|---|
| Google Business Profile | **Captain-OAuth-App** (zentraler Client, Redirect auf die Betreiber-Site); optional betreibereigener OAuth-Client | Refresh-Token | `business.manage` (⚠ einziger verfügbarer Scope enthält Schreibrechte → Plugin nutzt nur Lesen) | Access-Token on demand; Refresh-Token bis Widerruf | `oauth2/revoke` beim Trennen | Refresh-Token verschlüsselt; Access-Token nur im Speicher/Transient ≤ Ablauf |
| Apple App Store Connect | **Betreibercredential** | Issuer-ID, Key-ID, `.p8` | Team-Key mit minimaler Rolle (RC-012) | Betreiber erzeugt neuen Key, lädt hoch; alter wird gelöscht | Betreiber widerruft Key in App Store Connect; Plugin löscht lokal | `.p8` verschlüsselt; JWT (≤ 20 min) nie gespeichert |
| Google Play | **Betreibercredential** (Service Account) | JSON-Schlüssel | `androidpublisher`, App-Berechtigung nur Lesen | neuer Schlüssel hochladen | Schlüssel in GCP löschen; Plugin löscht lokal | JSON verschlüsselt |
| Trustpilot (1.x) | Betreibercredential (API-Key + OAuth-Client des Kunden) | API-Key, Client-ID/Secret, Tokens | nur Leserechte; Invitation separat | laut Trustpilot | Trennen löscht lokal | verschlüsselt |
| eTrusted (1.x) | Betreibercredential (API-Client) | Client-ID/Secret | Lesen; Invite separat | Secret im Control Centre rotieren | Client löschen | verschlüsselt |

Zentrale OAuth-App: Client-Secret wird **nicht** im Plugin ausgeliefert. Varianten: (a) PKCE-fähiger öffentlicher Client, falls Google das für Web-Apps dieser API erlaubt ⚠; (b) schlanker Captain-Token-Exchange-Endpunkt, der nur Code→Token tauscht und nichts speichert. Entscheidung nach RC-009 im ADR festhalten. Bis dahin: betreibereigener Client als funktionierender Weg.

## TEC-008 – Verschlüsselung und Schlüsselrotation

- **Algorithmus:** libsodium `sodium_crypto_secretbox` (XSalsa20-Poly1305), zufälliger 24-Byte-Nonce je Verschlüsselung, Format `rc1:{key_id}:{base64(nonce|ciphertext)}`.
- **Schlüsselquelle (Priorität):**
  1. Konstante `REVIEW_CAPTAIN_ENCRYPTION_KEY` in `wp-config.php` (32 Byte, base64) – empfohlen.
  2. Fallback: abgeleitet per `hash_hkdf('sha256', AUTH_KEY . SECURE_AUTH_KEY, 32, 'review-captain-v1')`. Diagnose warnt, dass ein Wechsel der WP-Salts gespeicherte Zugangsdaten unlesbar macht.
- **key_id:** erste 8 Hex-Zeichen von `sha256(key)`; erlaubt Erkennung des falschen Schlüssels.
- **Rotation:** neue Konstante setzen + alte als `REVIEW_CAPTAIN_ENCRYPTION_KEY_PREVIOUS`; `wp review-captain crypto rotate` entschlüsselt mit altem und verschlüsselt mit neuem Schlüssel (batchweise, protokolliert ohne Inhalte).
- **Fehlender/ungültiger Schlüssel:** Entschlüsselung schlägt fehl → Verbindung Status `crypto_key_missing`, keine API-Aufrufe, keine Löschung; Admin wird zur Neuverbindung geführt.
- **Backup/Restore:** Ciphertexte sind ohne Schlüssel wertlos; Restore auf anderer Umgebung erfordert denselben Schlüssel, sonst Neuverbindung.
- **Nie in Logs/Exporten:** Secrets haben Datenschutzklasse `S`; Logger und Diagnose-/Einstellungsexport filtern über Allowlist, nicht Blocklist. Admin-Felder zeigen nur „gespeichert ✓ (endet auf …ab12)“ für API-Keys.
- **Speicherort:** `rc_connections.secret` (Pro). Der Crypto-Service liegt im Core (`ReviewCaptain\Security\Crypto`), damit Connectoren denselben Weg nutzen.
