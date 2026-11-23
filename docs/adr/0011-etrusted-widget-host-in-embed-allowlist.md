# ADR-0011: `integrations.etrusted.com` in die Core-Embed-Allowlist aufnehmen

- **Status:** Vorgeschlagen (Entwurf 2026-11-23, wartet auf Zustimmung eines Core-Maintainers)
- **Datum:** 2026-11-23
- **Autor:in / Entscheider:** Claude (Entwurf); Entscheidung: Core-Maintainer
- **Betrifft:** RC-077, TEC-013; `src/Security/EmbedPolicy.php`, `assets/js/frontend-embed.js`, [`embed-security.md`](../architecture/embed-security.md) §3, [`KNOWN-LIMITATIONS.md`](../release/KNOWN-LIMITATIONS.md)
- **Ersetzt:** –

## Kontext

Die Core-Klasse `Security\EmbedPolicy` führt eine feste Host-Allowlist für Drittanbieter-Embeds (`HOSTS`); erweitert wird sie laut [`embed-security.md`](../architecture/embed-security.md) §3 nur per Code und ADR, der Filter `review_captain_embed_hosts` darf sie nur verkleinern. Das Click-to-load-Skript `assets/js/frontend-embed.js` prüft clientseitig dieselbe Liste.

Review Captain Pro (captain-pro, `addons/review-captain-pro/src/Widgets/ProviderEmbed.php`) bietet seit RC-077 neben dem Trusted-Shops-Trustbadge (`widgets.trustedshops.com`) das eTrusted-Bewertungswidget an. Dessen offizielles Skript liegt unter `https://integrations.etrusted.com/applications/widget.js/v2` (eTrusted-Dokumentation „Review Widgets“, Arbeitsstand, siehe `docs/providers/matrix.md`). Pro führt dafür eine eigene Skript-Hostliste (`SCRIPT_HOSTS` mit `integrations.etrusted.com`), die Core-Liste kennt den Host nicht.

Folgen heute:

- Die Core-Allowlist bildet nicht alle Hosts ab, an die die Suite im Frontend Daten übertragen kann; `embed-security.md` §3 und die Core-Liste sind unvollständig (in `KNOWN-LIMITATIONS.md` als „ADR offen“ vermerkt).
- Ein Betreiber, der über `review_captain_embed_hosts` die Core-Liste einschränkt, erreicht das eTrusted-Widget damit nicht; ein über `EmbedPolicy::click_to_load()` gebauter Platzhalter für diesen Host wäre leer.

## Betrachtete Optionen

1. **Host in die Core-Liste aufnehmen** – Core und Pro stimmen überein; Click-to-load, Filter (nur verkleinern), https/Port-443-Prüfung und der Hinweis „Beim Laden werden Daten an … übertragen“ gelten auch für eTrusted. Nachteil: die Core-Liste wächst um einen Host, den nur Pro nutzt (gilt ebenso für `widget.trustpilot.com` und `widgets.trustedshops.com`).
2. **Nur in Pro belassen** – kein Core-Eingriff; aber zwei Allowlists mit unterschiedlichem Inhalt, Doku bleibt unvollständig, Core-Filter wirkt nicht auf eTrusted.
3. **Wildcard `*.etrusted.com`** – verworfen: Allowlist bleibt exakt (keine Wildcards, `embed-security.md` §2/§3).

## Entscheidung

Option 1. `EmbedPolicy::HOSTS` und `ALLOWED` in `assets/js/frontend-embed.js` enthalten zusätzlich genau `integrations.etrusted.com`. Nicht aufgenommen werden `etrusted.com`, `api.etrusted.com`, `login.etrusted.com` (serverseitige API-Hosts der Pro-Adapter, §2) und keine Wildcard. Alle übrigen Regeln bleiben: nur `https`, Port 443, keine Zugangsdaten in der URL, keine IP-Hosts, Filter darf nur entfernen, Laden erst nach Klick.

## Konsequenzen

- Positiv: eine Allowlist für Core und Pro; Betreiber können eTrusted über `review_captain_embed_hosts` abschalten; Doku vollständig.
- Negativ / Risiken: keine neue Datenübertragung ohne Klick; der Host ist eine Arbeitsannahme aus der öffentlichen Anbieterdokumentation und muss mit dem Testzugang (RC-011) bestätigt werden.
- Migration / Kompatibilität: reine Erweiterung, kein Schema, keine Einstellung; Filter-Rückgaben ohne den Host verhalten sich wie bisher.
- Folgearbeiten: Pro kann `ProviderEmbed::SCRIPT_HOSTS` künftig mit `EmbedPolicy::hosts()` schneiden (eigene Aufgabe in captain-pro, nicht Teil dieses ADR).

## Abnahme

- [x] `EmbedPolicy::is_allowed_url( 'https://integrations.etrusted.com/applications/widget.js/v2' )` ist wahr; `etrusted.com`, `api.etrusted.com`, `http://…` und `integrations.etrusted.com.evil.example` bleiben abgelehnt (`tests/Unit/Security/EmbedPolicyTest.php`).
- [x] Der Filter `review_captain_embed_hosts` kann den Host entfernen (`test_etrusted_widget_host_is_allowed_and_can_be_removed`).
- [x] PHP- und JS-Liste sind gleich (`test_click_to_load_script_uses_the_same_host_list`).
- [x] `embed-security.md` §3 und `KNOWN-LIMITATIONS.md` im selben Branch aktualisiert.
- [ ] Host mit eTrusted-Testkanal bestätigt (RC-011, extern).

## Referenzen

- [eTrusted Review Widgets](https://developers.etrusted.com/docs/review-widgets)
- [`docs/providers/widget-fallbacks.md`](../providers/widget-fallbacks.md) §3
- captain-pro `addons/review-captain-pro/src/Widgets/ProviderEmbed.php` (`SCRIPT_HOSTS`)
