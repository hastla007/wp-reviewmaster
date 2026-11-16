# Multisite und Site-Isolation (TEC-009)

Status: Spezifikation, Stand 2026-11-16. Grundlage: [`conventions.md`](conventions.md) („jede Site hat eigene Tabellen über `$wpdb->prefix`“), [database.md](database.md) §5, [capabilities.md](capabilities.md) §5.

## 1. Isolationsprinzip

Review Captain ist in v1 **ausschließlich site-lokal**. Es gibt keine netzwerkweiten Tabellen, Optionen (`get_site_option`), Queues oder Verbindungen. Netzwerkweit wirken nur Konstanten aus `wp-config.php` (`REVIEW_CAPTAIN_ENCRYPTION_KEY`, `…_PREVIOUS`, `DISABLE_WP_CRON`).

| Bereich | Isolation |
|---|---|
| Tabellen | `{$wpdb->prefix}review_captain_*` je Site (`wp_2_review_captain_reviews` …) |
| Optionen | `review_captain_*` in der Options-Tabelle der Site |
| Rollen/Capabilities | pro Site (`{prefix}user_roles`) |
| OAuth-Verbindungen | pro Site: eigenes Profil, eigene Credentials, Redirect-URI = `admin_url()` **der Site**; State enthält `blog_id` und wird nur auf derselben Site eingelöst ([rest-oauth.md](rest-oauth.md)) |
| Secrets | Envelope-Kontext enthält `blog_id` ([secrets.md](secrets.md)) |
| Warteschlangen | `jobs`-Tabelle je Site; WP-Cron-Event je Site; `jobs run` arbeitet im Kontext genau einer Site (`--url=` bzw. `--network` iteriert mit `switch_to_blog()`) |
| GET_LOCK-Namen | enthalten `blog_id` |
| Lizenzstatus (Pro) | je Site (Aktivierung zählt pro Live-Site; Pro-Repo verantwortet Details) |
| Datenexport/Privacy | WordPress-Privacy-Werkzeuge arbeiten je Site; Exporter/Eraser sehen nur die Tabellen der aktuellen Site |
| Caches | Objektcache-Gruppen sind in WordPress automatisch blog-spezifisch; Transients je Site ([caching.md](caching.md)) |
| Uploads (CSV, `.p8`) | nur temporär im Upload-Verzeichnis der Site, sofort gelöscht |

Code-Regel: Keine Verwendung von `$wpdb->base_prefix`, `get_site_option()`/`update_site_option()` oder fest kodierter Blog-IDs im Core. Nach jedem `switch_to_blog()` folgt `restore_current_blog()` in `finally`.

## 2. Aktivierung

| Fall | Verhalten |
|---|---|
| Einzelsite-Aktivierung in Multisite | Installation nur für diese Site |
| Netzwerkaktivierung | keine synchrone Schleife über alle Sites (Timeout-Gefahr). Jede Site installiert beim ersten `admin_init` (Versionsvergleich) bzw. über `wp review-captain db migrate --network`. Bis zur Installation ist die Site für Review Captain inaktiv (Shortcodes rendern leer, kein Fehler). |
| Neue Site bei netzwerkaktivem Plugin | Hook `wp_initialize_site` (Priorität > 10): Tabellen, Optionen, Capabilities, Cron-Event anlegen |
| Site gelöscht | Filter `wpmu_drop_tables` ergänzt die sechs Tabellen; Cron-Events verschwinden mit der Options-Tabelle |
| Site archiviert/deaktiviert/als Spam markiert | WordPress führt dort keinen Cron aus; `jobs run --network` überspringt Sites mit `archived`, `deleted`, `spam` |

## 3. Deaktivierung und Reaktivierung

- **Netzwerkdeaktivierung:** Plugin-Code wird nirgends mehr geladen; verbleibende WP-Cron-Events `review_captain_run_jobs` haben keinen Callback und sind wirkungslos. Der Deaktivierungs-Hook entfernt Events für bis zu 100 Sites synchron; weitere Sites werden bei Reaktivierung bzw. durch `wp review-captain cleanup-cron --network` bereinigt. Daten bleiben erhalten.
- **Reaktivierung:** je Site Versionsvergleich, ggf. Migration, danach **Reconciliation** (Leases aus der Zeit vor der Deaktivierung, überfällige Jobs, abgelaufene Snapshots, Token-Prüfung; siehe [reconciliation.md](reconciliation.md)). Jobs mit Außenwirkung, die während der Deaktivierung fällig wurden, werden vor Ausführung erneut auf Gültigkeit geprüft.
- **Deinstallation:** `uninstall.php` iteriert Sites in Batches; Daten werden **nur** auf Sites gelöscht, deren Einstellung `uninstall.delete_all_data = true` ist.

## 4. Rechte im Netzwerk

- Super Admins haben auf jeder Site alle Review-Captain-Rechte (WordPress-Standard für `current_user_can`).
- Site-Admins verwalten nur ihre Site. Eine Netzwerkadmin-Oberfläche gibt es in v1 nicht (**Arbeitsannahme**; spätere Netzwerkübersicht nur lesend und per ADR).
- Ein Nutzer, der auf mehreren Sites Rollen hat, sieht je Site nur deren Daten.

## 5. Domain-Mapping, Klonen

- Redirect-URIs werden aus `admin_url( 'admin-post.php' )` der Site erzeugt; Domain-Mapping ändert die URI → bestehende OAuth-Clients beim Provider müssen angepasst werden (Diagnose zeigt die aktuelle Redirect-URI zum Kopieren).
- Wird eine Site in ein anderes Netzwerk/eine andere Domain kopiert, greift die Klon-Erkennung der [Reconciliation](reconciliation.md) (`home_url`-Hash).

## Abnahme

- [ ] Testnetz mit 3 Sites: Reviews, Profile, Jobs, Optionen, Capabilities, OAuth-Verbindungen und Privacy-Export sind je Site getrennt (Daten aus Site 2 in Site 3 nicht sichtbar, nicht exportierbar).
- [ ] Credentials von Site 2 lassen sich, in Site 3 kopiert, nicht entschlüsseln.
- [ ] OAuth-State von Site 2 wird auf Site 3 abgelehnt.
- [ ] Netzwerkaktivierung mit 500 Sites erzeugt keinen Timeout; jede Site installiert lazy.
- [ ] Neue Site erhält Tabellen sofort; gelöschte Site verliert ihre Tabellen.
- [ ] Netzwerkdeaktivierung und Reaktivierung verlieren keine Daten und lösen Reconciliation aus.
- [ ] Code-Scan: keine `base_prefix`-/`*_site_option`-Verwendung im Core.
