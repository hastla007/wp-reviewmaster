# REST- und OAuth-Grenzen (TEC-010)

Status: Spezifikation, Stand 2026-11-16. Grundlage: [`conventions.md`](conventions.md) (Öffentliche Ausgabe, Rechte), [capabilities.md](capabilities.md), [provider-auth.md](provider-auth.md), `konzept.md` 11.

## 1. REST-Grundregeln

- Namespace ausschließlich `review-captain/v1`; nur für Admin-Zwecke und Editor-Vorschau.
- **Jede** Route registriert eine explizite `permission_callback` (nie `__return_true`). Cookie-authentifizierte Aufrufe benötigen den REST-Nonce (`X-WP-Nonce`, Action `wp_rest`); WordPress prüft ihn, wir verlassen uns nicht auf Application Passwords für Admin-Schreibrouten (sind erlaubt, unterliegen aber denselben Capabilities).
- **Keine öffentlichen Schreib-Endpunkte.** Frontend-Einreichungen laufen über `admin-post.php` (`action=review_captain_submit_review`) mit Nonce, Honeypot und Rate-Limit.
- `args` mit `validate_callback`/`sanitize_callback` und Schema für jede Route; unbekannte Parameter werden ignoriert, IDs sind Ganzzahlen oder UUIDs.
- Antworten: `Cache-Control: no-store` (`nocache_headers()`), keine Secrets, keine E-Mail-Adressen außer in Moderationsansichten für `review_captain_moderate`.
- Fehler als `WP_Error` mit Code `rc.*` und HTTP-Status; keine Stacktraces.

### 1.1 Routen v1 (Vorschlag)

| Methode / Route | permission_callback | Zweck |
|---|---|---|
| `GET /review-captain/v1/preview` | `current_user_can( 'edit_posts' )` (**Arbeitsannahme**) | Block-Editor-Vorschau; liefert nur Inhalte, die öffentlich gerendert würden |
| `GET /review-captain/v1/reviews` | `review_captain_moderate` | Adminliste/Auswahl im Widget-Builder |
| `POST /review-captain/v1/reviews/{uuid}/status` | `review_captain_moderate` | Moderation (Block-/Drawer-UI) |
| `GET /review-captain/v1/profiles` | `review_captain_manage_sources` | Quellenliste |
| `GET /review-captain/v1/diagnostics` | `review_captain_view_reports` | Diagnose-Daten (ohne Secrets) |

Pro-Routen registriert Pro im selben Namespace unter `pro/…` mit denselben Regeln (Capability **und** Entitlement in der `permission_callback`).

## 2. OAuth-Ablauf

OAuth-Start und -Callback laufen **nicht** über REST, sondern über `admin-post.php` (Cookie-Login, Capability-Prüfung, keine Exposition im REST-Index).

```
[Admin klickt "Mit Google verbinden"]
 POST admin-post.php?action=review_captain_oauth_start   (Nonce review_captain_connect_source, Cap manage_sources)
   → state = 32 Byte random (base64url); code_verifier = 32–64 Byte random; challenge = S256
   → speichert Option review_captain_oauth_state_{sha256(state)} = {
        blog_id, user_id, provider, profile_uuid|null, redirect_uri, code_verifier,
        created_at, expires_at = +10 min }
   → 302 zum Provider (state, code_challenge, redirect_uri, scope)
[Provider] → GET admin-post.php?action=review_captain_oauth_callback&code=…&state=…
   1. Nutzer eingeloggt (sonst Login-Redirect mit Rückkehr, State bleibt bis Ablauf gültig)
   2. state-Datensatz per sha256(state) laden; ATOMAR verbrauchen:
      DELETE FROM options WHERE option_name = %s  → rows_affected === 1, sonst Abbruch (Replay)
   3. expires_at nicht überschritten; blog_id == get_current_blog_id(); user_id == get_current_user_id()
   4. current_user_can( 'review_captain_manage_sources' ) erneut prüfen
   5. provider aus Datensatz (nicht aus URL); redirect_uri exakt (String-Vergleich) wie gespeichert
   6. Fehlerparameter des Providers (error=access_denied …) → verständliche Meldung, kein Token
   7. Code-Tausch serverseitig mit code_verifier (+ Client-Secret bei vertraulichem Client)
   8. Tokens verschlüsselt in "pending connection" (Profil status=paused, ohne external_ref)
   9. 303 → Profilwahl-Seite (?pending=<profile_uuid>), ohne Token/Code in der URL
```

- **State-Vergleich** mit `hash_equals()`; gespeichert wird nur der Hash, nicht der State selbst.
- **PKCE (S256)** immer, wenn der Provider es unterstützt – auch bei vertraulichen Clients.
- **Redirect-URI**: fest `admin_url( 'admin-post.php?action=review_captain_oauth_callback' )` der jeweiligen Site; keine vom Request beeinflussbaren Teile (`return_to`, `redirect`-Parameter werden ignoriert). Nach erfolgreichem Callback wird nur auf feste Admin-Seiten des Plugins weitergeleitet (`wp_safe_redirect`).
- **Abgelaufene States** werden vom Cleanup-Job gelöscht.
- Callback-Antworten: `nocache_headers()`, `Referrer-Policy: no-referrer` (Code nicht per Referer weitergeben), keine Ausgabe von `code`/`state` in HTML oder Logs.

## 3. Profilwahl (Schutz gegen unautorisierte Auswahl)

1. Profilwahl-Seite lädt die verfügbaren Profile **serverseitig** mit den Tokens der Pending-Connection und speichert die Liste der erlaubten `external_ref`-Werte (z. B. Location-Ressourcennamen) im Pending-Profil (`state.allowed_refs`, max. 15 min gültig).
2. Formular sendet nur einen Index bzw. Wert aus dieser Liste, plus Nonce `review_captain_select_profile_{profile_uuid}`.
3. Server prüft: Capability, Nonce, Pending-Profil gehört zur aktuellen Site und wurde von **demselben Nutzer** gestartet, gewählter Wert ∈ `allowed_refs`. Andernfalls Abbruch (`rc.auth.invalid`), Audit-Eintrag.
4. Unique `(platform, external_ref)` verhindert, dass dasselbe Provider-Profil doppelt verbunden wird; Reconnect aktualisiert das bestehende Profil (gleiche `external_ref`) statt ein zweites anzulegen.
5. Erst danach `status = active` und Planung des Initialimports.

Nicht-OAuth-Credentials (API-Key, Client-Credentials, `.p8`, Service-Account-JSON) werden per `admin-post.php`-Formular (Capability + Nonce) übertragen, sofort verschlüsselt und mit einem Testaufruf geprüft; die Profilwahl folgt demselben Schema 1–5.

## 4. Replay und Wiederholung

| Angriff / Fehlerfall | Abwehr |
|---|---|
| Callback-URL erneut aufgerufen | State einmalig (atomares DELETE), Code beim Provider einmalig |
| State eines anderen Nutzers/einer anderen Site | `user_id`/`blog_id`-Bindung |
| Login-CSRF (Angreifer schiebt eigenen Code unter) | State gebunden an Nutzer + Start per POST mit Nonce |
| Manipulierte Profilwahl | `allowed_refs` serverseitig, Nutzer-/Site-Bindung, Nonce |
| Doppelklick „Verbinden“ | zwei States, nur ein Callback erfolgreich; Pending-Profil wird wiederverwendet |
| Offener Redirect | feste Redirect-Ziele, `wp_safe_redirect` |

## 5. Cross-Site-Schnittstellen (Abgrenzung zu TEC-011)

Webhooks oder REST-Zugriffe von anderen Installationen sind in Core v1 **nicht** vorgesehen. Falls später nötig: eigener Endpunkt mit HMAC-Signatur, Timestamp-Fenster (± 5 min), Nonce-/Event-ID-Replay-Tabelle, Scope je Schlüssel und Schlüsselrotation – Voraussetzung ist ein eigenes ADR.

## Abnahme

- [ ] Statischer Test: jede Route in `review-captain/v1` hat eine `permission_callback`, die für einen anonymen Nutzer `false`/`WP_Error` liefert.
- [ ] Jede Route mit Nutzer ohne Capability → 401/403, ohne Datenleck in der Fehlermeldung.
- [ ] Callback mit falschem, abgelaufenem, bereits verbrauchtem oder fremdem State (anderer Nutzer, andere Site) wird abgelehnt und erzeugt keinen Token-Tausch.
- [ ] Redirect-URI-Manipulation (zusätzliche Parameter, andere Domain) führt nicht zu einer Weiterleitung außerhalb des Admins.
- [ ] Profilwahl mit einer nicht gelisteten `external_ref` wird abgelehnt und auditiert.
- [ ] `code`, `state`, Tokens erscheinen nicht in HTML, Logs, Audit oder Referer.
- [ ] Doppelter Verbindungsversuch desselben Provider-Profils erzeugt kein zweites Profil.
