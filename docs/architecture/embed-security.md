# Frontend-Embed-Sicherheitsmodell (TEC-013)

Status: Spezifikation, Stand 2026-11-16. Grundlage: `konzept.md` 8.6, 9 (Qualitätsanforderungen), 11; RC-073, RC-077; [`conventions.md`](conventions.md) („Kein Frontend-JavaScript nötig; CSS-Klassenpräfix `rc-`“).

## 1. Grundsätze

1. **Keine beliebigen serverseitigen Abrufe.** Review Captain lädt nie eine URL, die ein Nutzer oder Admin frei eingegeben hat (kein oEmbed-Discovery, kein Avatar-/Bild-Proxy, kein „Vorschau laden“).
2. **Keine Drittanbieter-Requests ohne Zustimmung.** Externe Player/Widgets werden erst nach ausdrücklicher Nutzerinteraktion (click-to-load) **und**, wo vorhanden, Consent geladen.
3. **Kein gespeichertes Fremd-HTML.** Eingefügter Embed-Code (TrustBox, Trustbadge) wird geparst und als strukturierte Konfiguration gespeichert; das HTML wird von Review Captain aus einem festen Template erzeugt.
4. **Progressive Enhancement:** Ohne JavaScript erscheint statt des Embeds ein normaler Link zur Plattform.

## 2. Serverseitige Host-Allowlist (SSRF-Schutz)

Ausgehende HTTP-Requests nur über `ReviewCaptain\Http\Client`, der `wp_safe_remote_request()` nutzt und zusätzlich:

- Host muss exakt in der Allowlist des jeweiligen Adapters stehen (keine Wildcards, keine IP-Literale, nur `https`, Port 443).
- `redirection => 0`; Weiterleitungen werden nur befolgt, wenn das Ziel wieder in derselben Allowlist liegt (max. 2).
- Auflösung auf private/reservierte Adressen wird abgelehnt (`wp_safe_remote_*` + `http_request_host_is_external`).
- Timeout 10 s (Verbindung 5 s), Antwortgröße max. 5 MB.

| Adapter | erlaubte Hosts (Arbeitsstand, je Adapter zu verifizieren) |
|---|---|
| Google Business Profile | `oauth2.googleapis.com`, `accounts.google.com` (nur Browser-Redirect), `mybusinessaccountmanagement.googleapis.com`, `mybusinessbusinessinformation.googleapis.com`, `mybusiness.googleapis.com` |
| Google Play | `oauth2.googleapis.com`, `androidpublisher.googleapis.com` |
| Trustpilot | `api.trustpilot.com` (+ OAuth-Host laut Doku) |
| eTrusted | `login.etrusted.com`, `api.etrusted.com` |
| App Store Connect | `api.appstoreconnect.apple.com` |
| Connectoren | eigene deklarierte Liste, vom Betreiber bestätigt; keine Hosts aus freien Admin-Feldern ([konzept 7.10]) |

Profil-URLs (`profiles.profile_url`) und `reviews.source_url` werden **nie** serverseitig abgerufen; sie werden nur als Links ausgegeben (Validierung: `https`, `wp_http_validate_url()`, optional Host-Prüfung je Plattform, z. B. `*.trustpilot.com` → exakte Liste im Adapter).

## 3. Frontend-Embed-Allowlist

| Embed | erlaubter Host für `iframe src` / Script | Quelle der Parameter |
|---|---|---|
| YouTube-Video (Testimonial) | `www.youtube-nocookie.com` (`/embed/{id}`) | Video-ID per Regex `^[A-Za-z0-9_-]{11}$` aus YouTube-URL extrahiert |
| Vimeo-Video | `player.vimeo.com` (`/video/{id}`, `dnt=1`) | numerische ID |
| Trustpilot TrustBox | `widget.trustpilot.com` (Bootstrap-Script + iframe) | Template-ID, Business-Unit-ID, Locale, Theme – je per Whitelist-Regex |
| Trusted Shops Trustbadge/Widgets | `widgets.trustedshops.com` | TS-ID/Widget-ID per Regex |

Andere Hosts werden beim Speichern abgelehnt („Diese Quelle wird nicht unterstützt“). Erweiterung der Liste nur per Code (Adapter) und ADR, nicht per Admin-Feld. Ein Filter darf die Liste nur **verkleinern**.

## 4. Click-to-load

Markup (serverseitig gerendert):

```html
<div class="rc-embed" data-rc-embed="youtube" data-rc-src="https://www.youtube-nocookie.com/embed/ID">
  <img class="rc-embed__poster" src="(lokales Vorschaubild aus Mediathek)" alt="…" loading="lazy">
  <p class="rc-embed__notice">Beim Abspielen werden Daten an YouTube (Google) übertragen. <a href="…Datenschutzhinweis">Mehr</a></p>
  <button type="button" class="rc-embed__load">Video laden und abspielen</button>
  <a class="rc-embed__fallback" href="https://www.youtube.com/watch?v=ID" rel="noopener noreferrer nofollow ugc" target="_blank">Auf YouTube ansehen</a>
</div>
```

- Ein kleines, optionales Script (`assets/js/embed.js`, nur geladen, wenn die Seite Embeds enthält, ohne Abhängigkeiten, kein Inline-Script) ersetzt nach Klick den Platzhalter durch das `iframe`. Kein Autoplay-Parameter ohne Klick; Audio/Video starten nie automatisch.
- `iframe`-Attribute: `sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"`, `referrerpolicy="strict-origin-when-cross-origin"`, `loading="lazy"`, `allow="fullscreen; picture-in-picture"`, `title` gesetzt.
- Vorschaubilder sind **lokale** Mediathek-Anhänge (kein Hotlink auf `i.ytimg.com`).
- Consent: Ist die WP Consent API vorhanden (`wp_has_consent()`), wird für TrustBox/Trustbadge Kategorie `marketing` bzw. `statistics` (je Anbieter dokumentiert) vorausgesetzt; sonst immer click-to-load. Eine Einstellung „Embeds ohne Klick laden“ existiert nicht.
- TrustBox/Trustbadge-Scripts werden nie im `<head>` oder global eingebunden, sondern erst nach Klick/Consent dynamisch eingefügt.

## 5. Links und Ausgabe

| Link-Art | Attribute |
|---|---|
| Externe Links mit Bezug zu Review-Inhalten (Original-Review `source_url`, Plattformprofil in Review-Karte, Autor-/Fallback-Links) | `rel="noopener noreferrer nofollow ugc"`, `target="_blank"` nur mit sichtbarem Hinweis/Screenreader-Text „öffnet in neuem Tab“ |
| CTA zu eigenen Plattformprofilen des Betreibers (Review-CTA-Block) | `rel="noopener noreferrer nofollow"` (kein `ugc`, da Betreiberinhalt) |

- Reviewtexte: Ausgabe mit `esc_html()` bzw. `wp_kses()` mit minimaler Allowlist (`br`, `p`); keine Links im Reviewtext (URLs als Text).
- Avatare externer Plattformen: standardmäßig **nicht** geladen (Initialen-Avatar); Opt-in je Quelle mit Datenschutzhinweis, nur von der Plattform-Domain, `loading="lazy"`, `referrerpolicy="no-referrer"`.
- Bewertungslogos werden lokal ausgeliefert (`assets/`), nicht von Anbieter-CDNs.

## Abnahme

- [ ] Versuch, eine Profil-/Source-URL auf `http://127.0.0.1`, `http://169.254.169.254` oder einen nicht gelisteten Host zu setzen, führt zu keinem ausgehenden Request (Test mit HTTP-Mock, der jede Anfrage protokolliert).
- [ ] Adapter-Clients verweigern Hosts außerhalb ihrer Allowlist und Redirects auf fremde Hosts.
- [ ] Seite mit Video-Testimonial und TrustBox erzeugt beim Laden **null** Requests an Drittanbieter (Browser-Netzwerkprotokoll); erst nach Klick werden nur die gelisteten Hosts kontaktiert.
- [ ] Ohne JavaScript ist statt des Embeds ein funktionierender Link sichtbar.
- [ ] Eingefügter Embed-Code mit zusätzlichem `<script>`/`onload` wird nicht übernommen; gespeichert werden nur validierte Parameter.
- [ ] Alle externen Links tragen die in §5 definierten `rel`-Attribute (Snapshot-Test der Templates).
