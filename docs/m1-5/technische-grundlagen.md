# M1.5 – Technische Betriebs- und Sicherheitsgrundlagen

**Status:** Entwurf (Spezifikation). Implementierungs- und Messanteile sind in TODO separat markiert.  
**Stand:** 2026-11-16  
**Grundlage:** [Datenmodell](../m1/datenmodell.md), [Fehlercodes](../m1/rc-029-030-rechte-fehlercodes.md)

## TEC-001 – Job-Runner-Vertrag

- Abstraktion `ReviewCaptain\Jobs\Queue` mit `enqueue( string $type, array $payload, array $opts )` (`unique_key`, `available_at`, `priority`, `max_attempts`) und Handler-Registry `wp_review_captain_job_handlers_v1` (`type => callable`).
- **Speicher immer `rc_jobs`** (eine Wahrheit für Idempotenz, Lease, Diagnose). Der *Trigger* ist austauschbar:
  - `auto` (Default): Action Scheduler vorhanden → wiederkehrende AS-Aktion `review_captain_run_queue` alle 60 s ruft den Worker; sonst WP-Cron-Event `review_captain_run_queue` (Intervall 60 s, eigener Schedule `rc_every_minute`).
  - `system_cron`: Betreiber setzt `DISABLE_WP_CRON` und ruft `wp review-captain queue run --time=50` per Crontab jede Minute.
- Worker: läuft max. `job_time_budget_seconds` (20 s Web, 50 s CLI), claimt Jobs batchweise (TEC-003), stoppt bei Budget/Speicher > 80 % `memory_limit`.
- Kein Job im normalen Frontend-Request (kein Loopback-Run beim Seitenaufruf).

## TEC-002 – Produktions-Cron und Verzögerung

- Diagnose zeigt: Trigger-Modus, letzter Worker-Lauf, Anzahl fälliger Jobs, älteste Fälligkeit, „überfällig“ = fällig seit > 10 min.
- `cron_delayed`-Warnung ab 15 min Überfälligkeit; Hinweistext mit Crontab-Beispiel:
  `* * * * * cd /pfad/zu/wordpress && wp review-captain queue run --time=50 --quiet`
- Anzeige von Zeiten als „frühestens um …“ statt präziser Versprechen, solange WP-Cron genutzt wird.

## TEC-003 – Claiming und Lease

Atomarer Claim per Update mit Token:

```sql
UPDATE rc_jobs SET status='running', claimed_by=%s, lease_until=NOW()+INTERVAL %d SECOND, attempts=attempts+1
WHERE status='queued' AND available_at<=NOW() ORDER BY priority DESC, available_at ASC, id ASC LIMIT %d;
SELECT * FROM rc_jobs WHERE claimed_by=%s AND status='running';
```

- `claimed_by` = zufälliges 32-Hex-Token pro Worker-Lauf; Lease 5 min (verlängerbar via `heartbeat()`).
- Recovery: Worker setzt zu Beginn `running`-Jobs mit `lease_until < NOW()` zurück auf `queued` (oder `dead` bei `attempts >= max_attempts`) und protokolliert `lease_expired`.
- Abschluss nur mit passendem `claimed_by` (`UPDATE … WHERE id=%d AND claimed_by=%s`) – ein verspäteter Worker kann fremde Ergebnisse nicht überschreiben.

## TEC-004 – Idempotenz in der Datenbank

Siehe [Datenmodell](../m1/datenmodell.md): `rc_reviews.external_key`, `rc_reviews.source_entry_key`, `rc_jobs.unique_key`, `rc_experiences.event_key`, `rc_requests.invite_key`, `rc_suppressions.email_hash`, `rc_sync_runs(profile_id, run_key)`. Schreibweg: `INSERT … ON DUPLICATE KEY UPDATE` (Sync: Felder aktualisieren) bzw. `INSERT IGNORE` + Prüfung `rows_affected` (Events/Einladungen: Duplikat → Code `duplicate`).

## TEC-005 – Race-Conditions (Testszenarien)

| # | Szenario | Erwartung | Absicherung |
|---|---|---|---|
| R1 | Zwei Hooks melden dasselbe Erlebnis gleichzeitig | eine Zeile `rc_experiences`, eine Einladung | UNIQUE `event_key`, `invite_key` |
| R2 | Check-in und Voucher-Einlösung für dasselbe reale Erlebnis | eine Einladung | Vorrangregel RC-022 + `invite_key` |
| R3 | Refund trifft ein, während Einladung `sending` ist | kein Versand, wenn Refund vor Versandaufruf committed; sonst `sent` + Protokoll | Re-Check unmittelbar vor Transport in derselben Transaktion: `SELECT … FOR UPDATE` auf Request-Zeile, Widerrufsereignisse setzen `cancelled` nur aus Nicht-Endzuständen |
| R4 | Zwei Worker claimen gleichzeitig | jeder Job genau einmal | TEC-003 |
| R5 | Token-Refresh parallel in zwei Läufen | ein Refresh, zweiter liest neuen Token | Sperre `rc_lock_{connection}` (Option mit `add_option` als Mutex, TTL 60 s) |
| R6 | Zwei Sync-Läufe derselben Quelle (manuell + Cron) | zweiter wird übersprungen | `unique_key = sync:{profile_id}` für aktive Sync-Jobs |
| R7 | Import und Sync derselben externen Review | ein Datensatz | `external_key` |

Diese Szenarien werden als Integrationstests (PHPUnit mit echter DB) in RC-130 umgesetzt; R1/R4/R7 bereits im Core.

## TEC-006 – Retry- und Dead-letter-Politik

| Klasse (RC-030) | Retry | Backoff | Danach |
|---|---|---|---|
| temporary | bis 6 Versuche | exponentiell 1, 2, 4, 8, 16, 32 min ± 20 % Jitter | `dead`, Adminhinweis |
| rate_limit | unbegrenzt innerhalb 24 h | `Retry-After` bzw. Reset-Zeit, mind. 1 min | nach 24 h `dead` |
| auth | 0 | – | Verbindung `paused`, alle Jobs der Verbindung pausiert, Adminaktion |
| permanent | 0 | – | `dead` |
| data | 0 | – | Element überspringen, Lauf fortsetzen, Zähler |
| paused | – | – | `paused`, Wiederaufnahme bei Statuswechsel |

Dead-Letter: Status `dead` in `rc_jobs`; UI „Erneut versuchen“ setzt `queued`, `attempts=0`, behält `unique_key`.

## TEC-007 – API-Budgets und Parallelität

- Je Verbindung: max. 1 gleichzeitiger Sync-Job (R6); max. `sync_max_items_per_run` Elemente und max. 10 Seiten pro Lauf; Fortsetzung über Cursor im Folgelauf.
- Tagesbudget je Verbindung (Default 1.000 Requests, konfigurierbar im Erweitert-Bereich der Quelle); Zähler in `rc_connections.meta`.
- Warnung bei `sync_quota_warn_percent` (Default 80 %); harte Grenze → `quota_exceeded` bis Mitternacht UTC bzw. Reset-Zeit.
- Globale Parallelität: Worker verarbeitet max. 3 Provider-Jobs pro Lauf.

## TEC-008 – Verschlüsselung

Siehe [Auth und Secrets](../m1/auth-und-secrets.md).

## TEC-009 – Multisite- und Site-Isolation

- Tabellen, Optionen, Jobs, Verbindungen, Lizenzstatus (Pro-Client je Site) und Exporte je Site (`$wpdb->prefix`).
- Netzwerkaktivierung: keine Massenschleife über alle Sites bei Aktivierung (Timeout-Gefahr); Schema lazy je Site (RC-028).
- Worker läuft je Site (WP-Cron ist site-spezifisch); CLI: `wp site list --field=url | xargs -I{} wp review-captain queue run --url={}`.
- Netzwerkdeaktivierung: Cron-Events je Site werden beim nächsten Aufruf der Site entfernt (`review_captain_deactivated`-Flag in `wp_sitemeta`); Wiederaktivierung prüft Verträge und setzt pausierte Jobs nicht automatisch fort, sondern zeigt Hinweis.
- `switch_to_blog()` wird nur in CLI/Netzwerkadmin verwendet; jede Abfrage nutzt den aktuellen Prefix.

## TEC-010 – REST-/OAuth-Grenzen

- Jede `register_rest_route` hat eine explizite `permission_callback` mit Capability; `__return_true` nur für die öffentliche Einreichungsroute, die zusätzlich Nonce (`wp_rest`), Honeypot und Rate-Limit prüft.
- OAuth-Callback als Admin-Seite (`admin.php?page=review-captain-sources&rc_oauth=callback`), nicht als offene REST-Route: erfordert eingeloggten Nutzer mit `rc_connect_sources`.
- `state` = zufällige 32 Byte, gebunden an User-ID + Provider + Ablauf (10 min) in Transient; einmalig verwendbar (Replay-Schutz), Vergleich mit `hash_equals`.
- Redirect-URI exakt aus `admin_url()` gebildet, nie aus Request-Parametern.
- Profilwahl nach OAuth: Server prüft, dass die gewählte Profil-ID in der zuvor vom Provider gelieferten Liste (Transient) enthalten ist.

## TEC-011 – Cross-site-Schnittstellen

1.0 enthält **keine** eingehenden Webhooks/REST für Suite-Ereignisse. Spätere Einführung nur mit: HMAC-SHA256-Signatur über `timestamp.body`, Toleranz ±5 min, Nonce-Speicher 10 min (Replay), Scope je Schlüssel, Schlüsselrotation mit zwei aktiven Schlüsseln, Rate-Limit, ADR.

## TEC-012 – Cache und CDN

- Widgets werden serverseitig gerendert; Ergebnis im Objektcache (`wp_cache_*`, Gruppe `review_captain`) mit TTL = min(15 min, verbleibende Aktualitätsfrist der enthaltenen Sync-Reviews).
- Invalidierung: Review-/Widget-Änderung erhöht `review_captain_cache_version` (Option) → alle Widget-Caches ungültig; zusätzlich Aktion `review_captain_content_changed` für Cache-Plugins (z. B. Purge-Hooks).
- Seiten-/CDN-Cache: Widget-HTML enthält keine personenbezogenen oder nutzerabhängigen Daten; Formular-Nonce wird für gecachte Seiten per kleinem REST-Abruf nachgeladen (progressive Enhancement) oder durch zeitlich unkritischen Honeypot+Rate-Limit ergänzt.
- Externe Snapshots mit überschrittener Frist werden beim Rendern ausgefiltert – auch wenn ein Seitencache älter ist, begrenzt die TTL-Empfehlung (Header-Hinweis in Doku) die Auslieferung.
- Lizenz-, Anfrage- und Admin-Daten werden nie in öffentliche Caches geschrieben.

## TEC-013 – Frontend-Embed-Sicherheitsmodell

- **Host-Allowlist** (Filterbar, aber nur erweiterbar per Code): `www.youtube-nocookie.com`, `player.vimeo.com`, `widget.trustpilot.com`, `widgets.trustedshops.com`, `integrations.etrusted.com`. URLs werden geparst (`wp_parse_url`), nur `https`, Host exakt in Liste.
- Kein serverseitiger Abruf von Nutzer-URLs (kein oEmbed-Discovery für beliebige Hosts) → kein SSRF. Provider-API-Hosts sind fest im Adapter kodiert.
- Externe Embeds/Player werden als Platzhalter (Poster, Text, Button „Inhalt von {Host} laden“) gerendert; erst Klick lädt `iframe`/Skript (`external_services_consent_mode = click_to_load`). Hook `review_captain_external_consent_given` erlaubt Consent-Plugins, Laden freizugeben.
- Links: `rel="nofollow noopener noreferrer"` für externe Profil-/Review-Links; keine `target`-Erzwingung.
- Embed-Codes von Anbietern werden nicht als freies HTML gespeichert, sondern als strukturierte Parameter (z. B. Business-Unit-ID, Template-ID) und vom Plugin erzeugt.

## TEC-014 – Skalierung (Messplan)

- WP-CLI `wp review-captain dev seed --reviews=100000 --jobs=10000` (nur wenn `WP_DEBUG` oder `--force`) erzeugt Testdaten.
- Messungen: Adminliste Seite 1 und Seite 500 mit Filtern (Ziel < 300 ms Query), Suche (Ziel < 1 s), CSV-Export 100.000 Zeilen in Batches (Speicher < 64 MB), Worker-Lauf mit 10.000 Jobs (keine Laufzeit > Budget). Ergebnis in `docs/m1-5/messung-tec-014.md` (offen bis Testumgebung).

## TEC-015 – Upgrade-/Restore-Reconciliation

Job `maintenance.reconcile` (nach Schema-Upgrade, nach erkanntem Restore, manuell per CLI):
1. **Restore-Erkennung:** Option `review_captain_instance` speichert Zufalls-ID + `last_seen_at`; weicht Uhrzeit > 24 h rückwärts ab oder fehlt der zuletzt geschriebene Heartbeat-Wert in der DB → Restore vermutet.
2. Alle `running`-Jobs → `queued` bzw. `dead`.
3. Alle Einladungen `planned/eligible` mit Erlebnis älter als Kampagnenfrist → `expired`; vor Versand erneute Prüfung, ob ein Versand bereits nach dem Restore-Zeitpunkt protokolliert wurde (Transport-Message-ID in `rc_requests`).
4. Verbindungen: Test-Aufruf; bei Fehler `auth_expired`, keine Schreibaktionen.
5. Sync-Snapshots mit überschrittener Frist ausblenden; Cursor zurücksetzen auf „Delta seit letztem erfolgreichen `fetched_at`“.
6. Ergebnis in Diagnose und Audit-Log.
