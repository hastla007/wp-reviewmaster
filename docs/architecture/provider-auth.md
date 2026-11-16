# Authentifizierungsmodell je Provider (RC-026)

Status: Spezifikation/Entscheidungsvorlage, Stand 2026-11-16. Gilt für Pro-Adapter (Core enthält keine API-Syncs). Grundlage: `konzept.md` 4, 7.9 („Authentifizierung und Eigentümerschaft der Verbindungen“), 11; [secrets.md](secrets.md), [rest-oauth.md](rest-oauth.md), [ADR-0005](../adr/0005-tokenverschluesselung-libsodium.md).

> **Anbieterangaben** (Scopes, Token-Laufzeiten, Rollen, Limits) entsprechen dem Kenntnisstand bei Erstellung und sind vor Implementierung jedes Adapters gegen die aktuelle Anbieterdokumentation zu verifizieren (M0 Plattformfreigaben, M4). Abweichungen werden hier und ggf. per ADR nachgetragen.

## 1. Gemeinsame Regeln (alle Provider)

1. **Kein Review-Proxy im MVP:** Tokens, Secrets und Review-Daten bleiben auf der Site des Betreibers ([konzept 7.9]). Der Lizenzserver sieht keine Provider-Credentials.
2. **Least Privilege:** nur Lese-Scopes/-Rollen. Schreibrechte (Antworten, Einladungen) erst mit eigenem Feature, eigenem Consent-Schritt und Audit-Eintrag.
3. **Transparenz beim Verbinden:** Vor dem Start werden Konto/Tarifvoraussetzung, angeforderte Rechte (Klartext + technischer Scope), Datenarten und Aufbewahrung ([retention-matrix.md](retention-matrix.md)) angezeigt. Nach Verbindung: verbundenes Konto (nicht-geheimer Bezeichner), auswählbare Profile.
4. **Speicherung:** Alle Secrets (Refresh-/Access-Tokens, Client-Secrets, API-Secrets, private Schlüssel) verschlüsselt in `profiles.credentials_enc` (libsodium secretbox, kontextgebunden an Site + Profil-UUID). Nicht-geheime Bezeichner (Client-ID, Issuer-ID, Key-ID, Business-Unit-ID, Channel-ID, Paketname) in `profiles.settings`/`external_ref`.
5. **Nie** in Logs, `jobs.payload`, `audit_log`, Exporten, Debug-Export, HTML, URLs oder REST-Antworten. Formularfelder für Secrets sind write-only (nach Speichern nur „•••• gesetzt am …, Fingerprint abcd“).
6. **Access-Tokens** werden nur so lange zwischengespeichert, wie sie gültig sind (verschlüsselt im selben Envelope mit `expires_at`); Refresh unter Profil-Sperre mit Compare-and-swap auf `profiles.credentials_rev` (TEC-005 „Tokenrefresh“).
7. **Trennen** („Verbindung trennen“): (a) sofort Profil `disconnected`, keine neuen API-Aufrufe; (b) offene Provider-Jobs → `failed` („abgebrochen durch Trennen“, siehe [jobs.md](jobs.md) §6.1); (c) Token-Widerruf beim Provider, wo unterstützt (best effort, Fehler blockieren das Trennen nicht und werden angezeigt); (d) `credentials_enc` löschen; (e) Snapshots gemäß [retention-matrix.md](retention-matrix.md) (Default: bleiben bis Fristende, Option „sofort entfernen“); (f) Audit-Eintrag `profile.disconnected`.
8. **Fehler 401/403** → Fehlerklasse `auth` (`rc.auth.invalid`) → Profil `auth_required`, Jobs `paused` ([jobs.md](jobs.md) §6.2). Kein Löschen von Daten.
9. **Hosts:** ausgehende Aufrufe nur an die je Adapter fest codierten Hosts ([embed-security.md](embed-security.md) §2).

## 2. Übersicht

| Provider | Credential-Typ | Lese-Scope / Rolle | Refresh | Widerruf durch Plugin | Rotation | Empfehlung App-Eigentum |
|---|---|---|---|---|---|---|
| Google Business Profile | OAuth 2.0 Authorization Code + PKCE, `access_type=offline` | `https://www.googleapis.com/auth/business.manage` (kein reiner Lese-Scope verfügbar) | Refresh-Token → Access-Token (~1 h) | ja (`oauth2.googleapis.com/revoke`) | Reconnect; Client-Secret im Google-Cloud-Projekt | **offen** – siehe §8 |
| Trustpilot | API-Key (öffentliche Endpunkte) und/oder OAuth Authorization Code (private Business-Endpunkte) | API-Key-Leserechte; OAuth nur, wenn private Endpunkte nötig | OAuth: Refresh-Token | OAuth: soweit Endpunkt vorhanden, sonst nur lokal löschen + Hinweis | API-Key/Secret im Trustpilot-Developer-Portal neu erzeugen | Betreiber-Credential (Tarif-/Kundenrecht) |
| Trusted Shops eTrusted | OAuth 2.0 Client Credentials (Client-ID/-Secret aus eTrusted Control Center) | Leserechte des API-Clients auf Kanal(e) | kein Refresh-Token; Access-Token bei Ablauf neu holen | nein (lokal löschen; Betreiber deaktiviert Client im Control Center) | Secret im Control Center neu erzeugen → im Plugin ersetzen | Betreiber-Credential |
| Apple App Store Connect | JWT ES256 aus API-Key (`.p8`), Issuer-ID, Key-ID | Key-Rolle mit Lesezugriff auf Kundenrezensionen (voraussichtlich „Customer Support“ – zu verifizieren) | JWT je Aufrufserie neu signieren (Laufzeit ≤ 20 min) | nein (Key in App Store Connect widerrufen) | neuen Key erzeugen, `.p8` ersetzen, alten Key widerrufen | Betreiber-Credential |
| Google Play | Service-Account (JSON-Key, JWT-Bearer RS256) **oder** OAuth Authorization Code | `https://www.googleapis.com/auth/androidpublisher`; Play-Console-Berechtigung nur für die betreffende App (Leserecht auf Rezensionen – genaue Berechtigung zu verifizieren) | Service-Account: Token per signiertem JWT; OAuth: Refresh-Token | OAuth: `revoke`; Service-Account: nein (Key im GCP löschen / Nutzer in Play Console entfernen) | neuen SA-Key erzeugen, ersetzen, alten löschen | Betreiber-Credential (Service-Account empfohlen) |

## 3. Google Business Profile (RC-065 bis RC-068)

- **Voraussetzung:** Google-Konto mit Verwaltungszugriff auf verifizierte Standorte; Google-Cloud-Projekt mit freigeschaltetem Business-Profile-API-Zugang (Zugangsantrag bei Google erforderlich).
- **Flow:** Authorization Code mit PKCE (S256), `access_type=offline`, `prompt=consent` (sichert Refresh-Token beim Reconnect), `include_granted_scopes=false`. State/Redirect-Prüfung: [rest-oauth.md](rest-oauth.md).
- **Profilwahl:** Konten (Account Management API) → Standorte (Business Information API) → Auswahl; nur serverseitig gelistete Standorte sind wählbar. `external_ref` = Ressourcenname `accounts/{a}/locations/{l}`.
- **Reviews:** paginiert über die Reviews-Ressource des Standorts (`pageToken`); `updateTime` als Delta-Cursor.
- **Scope-Hinweis:** `business.manage` erlaubt technisch auch Schreibzugriff. Das Plugin ruft im MVP ausschließlich Lese-Endpunkte auf; die Verbindungsseite sagt das ausdrücklich.
- **Gespeicherte Secrets:** Refresh-Token, Access-Token-Cache; bei Betreiber-Credential zusätzlich Client-Secret.
- **Ablaufrisiken:** Refresh-Token kann verfallen (Widerruf durch Nutzer, Passwort-/Sicherheitsereignisse, lange Nichtnutzung, Apps im Status „Testing“ nach 7 Tagen) → `invalid_grant` → `auth`.
- **Widerruf:** `POST https://oauth2.googleapis.com/revoke` mit Refresh-Token beim Trennen.
- **Places-API-Fallback (RC-069):** getrennter API-Key, nie mit GBP-OAuth vermischt; separates ADR, falls umgesetzt.

## 4. Trustpilot (RC-070 bis RC-073)

- **Voraussetzung:** Trustpilot-Business-Konto mit API-Zugang im Tarif (nicht garantiert); Business-Unit-ID.
- **Modus A – API-Key:** für öffentliche Business-Unit-/Review-Endpunkte. Key als Header (nicht als Query-Parameter, wo der Endpunkt das erlaubt), behandelt als Secret.
- **Modus B – OAuth:** nur wenn private Endpunkte benötigt werden (z. B. private Review-Daten, Invitations – letzteres nicht MVP). **Nur Authorization-Code-Flow**; ein Passwort-Grant, der Benutzerpasswörter des Betreibers speichern würde, wird **nicht** unterstützt.
- **Prüfung vor Sync:** API-Recht, Business-Unit gehört zum Konto, Endpunkte erreichbar (`test`-Schritt des Provider-Vertrags).
- **Refresh-Pflicht:** angezeigte API-Inhalte max. **24 h** alt ([retention-matrix.md](retention-matrix.md)); Budget so, dass jede angezeigte Review binnen 24 h erneuert wird.
- **Rotation:** neuer API-Key/Secret im Developer-Portal → im Plugin ersetzen → Verbindungstest → alter Key im Portal deaktivieren.
- **Fallback:** offizielle TrustBox (consent-gesteuert, [embed-security.md](embed-security.md)) oder Profil-Link.

## 5. Trusted Shops eTrusted (RC-074 bis RC-077)

- **Voraussetzung:** eTrusted-Konto mit API-Freigabe; API-Client (Client-ID/-Secret) im eTrusted Control Center; Kanal-ID(s).
- **Flow:** OAuth 2.0 Client Credentials gegen den eTrusted-Login-Endpunkt; kurzlebiges Access-Token, kein Refresh-Token – bei Ablauf/401 einmalig neu anfordern, dann `auth`.
- **Profilwahl:** Kanäle über die API listen; nur gelistete Kanäle wählbar; `external_ref` = Kanal-ID. Service- und Produktreviews werden getrennt typisiert (`meta.review_type`).
- **Gespeicherte Secrets:** Client-Secret, Access-Token-Cache.
- **Widerruf/Rotation:** im Control Center (Client deaktivieren bzw. Secret neu erzeugen); Plugin löscht lokal. Invite-API (RC-076) erst mit Vertrag und eigenem Scope-/Consent-Schritt.

## 6. Apple App Store Connect (RC-078, RC-079)

- **Voraussetzung:** App-Store-Connect-Team-API-Key mit passender Rolle; Issuer-ID; Key-ID; `.p8`-Datei (nur einmal herunterladbar); App-ID(s).
- **Token:** JWT, Header `{"alg":"ES256","kid":"<Key-ID>","typ":"JWT"}`, Claims `iss` = Issuer-ID, `iat`, `exp` ≤ `iat + 20 min`, `aud` = `appstoreconnect-v1`. Signatur mit `openssl_sign( …, OPENSSL_ALGO_SHA256 )`; die DER-Signatur **muss** in das JWS-Format R‖S (64 Byte) konvertiert werden. JWT wird nur im Speicher des laufenden Jobs gehalten, nie persistiert.
- **Upload:** `.p8` per Datei-Upload oder Einfügen; Validierung (PEM, EC P-256), Speicherung nur verschlüsselt, keine Kopie im Upload-Verzeichnis (Temp-Datei sofort löschen). Anzeige danach nur Key-ID + Fingerprint.
- **Profilwahl:** Apps des Teams listen; `external_ref` = App-ID. Reviews über `customerReviews` der App (Territory, Rating, Titel, Text, Reviewer-Alias, Datum; Antwortstatus separat).
- **Voraussetzung `ext-openssl`:** Diagnose prüft Verfügbarkeit; ohne OpenSSL kein App-Store-Adapter.
- **Rotation/Widerruf:** neuen Key anlegen → `.p8`/Key-ID ersetzen → Test → alten Key in App Store Connect widerrufen. Ein widerrufener Key führt zu 401 → `auth`.

## 7. Google Play (RC-080, RC-081)

- **Voraussetzung:** Play-Console-Zugriff auf die eigene App; Paketname.
- **Variante A (empfohlen) – Service-Account:** Betreiber erstellt Service-Account im eigenen GCP-Projekt, aktiviert die Android Publisher API, lädt JSON-Key hoch und lädt die Service-Account-E-Mail in der Play Console **nur für diese App** mit minimaler Berechtigung ein. Token per JWT-Bearer (RS256) mit Scope `androidpublisher`. Gespeichert: JSON-Key (verschlüsselt; nur `client_email`, `private_key`, `private_key_id`, `token_uri` werden übernommen).
- **Variante B – OAuth:** wenn Organisationsrichtlinien Service-Account-Keys verbieten; Ablauf wie Google (§3) mit Scope `androidpublisher`.
- **Daten-Hinweis:** `reviews.list` liefert nur Reviews der letzten ca. 7 Tage (neu/geändert). Ältere Reviews nur über CSV-Import aus den Play-Console-Berichten (`import_batches.source_format = play_console_csv`).
- **Rotation/Widerruf:** neuen SA-Key → ersetzen → Test → alten Key im GCP löschen; Entfernen des Service-Accounts in der Play Console entzieht sofort den Zugriff.

## 8. Offene Entscheidung: Captain-OAuth-App vs. Betreiber-Credential

Betrifft vor allem Google Business Profile (und Google-Play-OAuth, Trustpilot-OAuth, falls angeboten).

| Kriterium | Captain-OAuth-App (zentral registriert) | Betreiber-Credential (eigenes Cloud-Projekt/Client) |
|---|---|---|
| Einrichtung für Betreiber | ein Klick | hoch: GCP-Projekt, API-Freigabe/Zugangsantrag, Consent-Screen, Redirect-URI |
| Redirect-URI | muss bei Google fest registriert sein → zentrale Redirect-Station unter Captain-Domain nötig, die Code/State an die Site weiterleitet | exakt die Site-URL, keine Zwischenstation |
| Client-Secret | darf nicht an alle Sites verteilt werden → Code-Tausch muss zentral erfolgen (Broker sieht Tokens transient) **oder** öffentlicher Client mit PKCE, falls vom Provider für Web-Apps zugelassen | bleibt beim Betreiber |
| Verhältnis zu „kein Review-Proxy im MVP“ | Broker ist kein Daten-Proxy, aber ein zentraler Auth-Dienst mit Betriebs-, Datenschutz- (AVV) und Verfügbarkeitspflichten | vollständig konform |
| Google-Verifizierung/Kontingent | Captain trägt App-Verifizierung, API-Zugangsantrag und Kontingent für alle Kunden (Quota-Teilung!) | je Betreiber eigenes Kontingent |
| Widerruf/Incident | Captain kann App-weit sperren; ein Leak trifft alle Kunden | isoliert je Betreiber |

**Empfehlung:** MVP mit **Betreiber-Credential** für alle Provider (Google mit geführter Schritt-für-Schritt-Anleitung und Prüfassistent). Captain-OAuth-App für Google Business Profile als **späteres Komfort-Feature**, nur nach eigenem ADR mit Broker-Architektur (Code-Tausch-Dienst ohne Token-Speicherung, Signatur Site↔Broker, AVV, Quota-Konzept, Verifizierung bei Google).

**Status: offen.** Entscheidung durch Produkt + Core-Maintainer vor Start von RC-065; Ergebnis als ADR (Kandidat in [../adr/README.md](../adr/README.md)).

Weitere offene Punkte:

- Exakte minimale Rolle/Berechtigung bei App Store Connect und Play Console für reinen Lesezugriff (Verifikation im echten Konto, M0).
- Trustpilot: welche Endpunkte im Zieltarif per API-Key ausreichen; Token-Laufzeiten und Widerrufsendpunkt.
- eTrusted: Token-Laufzeit, verfügbare Scopes je API-Client.

## Abnahme

- [ ] Für jeden der fünf Provider existiert ein Auth-Record (dieses Dokument) mit Kontotyp/Tarif, Credential-Typ, Scope/Rolle, Refresh, Widerruf, Rotation, gespeicherten Secrets – gegen aktuelle Anbieterdoku verifiziert und mit Datum versehen.
- [ ] Die Entscheidung Captain-OAuth-App vs. Betreiber-Credential ist per ADR getroffen.
- [ ] Je Provider Test: Secret erscheint nicht in Logs, Job-Payload, Audit, CSV-/Debug-Export, HTML-Quelltext oder REST-Antwort (Suchtest nach dem Klartextwert).
- [ ] Trennen stoppt API-Aufrufe sofort, setzt offene Provider-Jobs auf `failed`, versucht Widerruf (wo unterstützt) und löscht `credentials_enc`.
- [ ] 401/403 und abgelaufener/widerrufener Token führen zu `auth_required` + `paused`, nicht zu Datenverlust.
- [ ] App-Store-JWT validiert gegen die Apple-API in einem echten Test (ES256-Signaturformat korrekt).
- [ ] Rotation je Provider ist ohne Neuanlage des Profils möglich (Secret ersetzen → Test → aktiv).
