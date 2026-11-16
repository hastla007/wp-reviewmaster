# Aufbewahrungs- und Löschmatrix (RC-027)

Status: Spezifikation, Stand 2026-11-16. Grundlage: `konzept.md` 4 (Normalisiertes Review-Modell), 7.9 (Deaktivierung, Ablauf, Export, Wiederherstellung), 11; Tabellen: [database.md](database.md).

> Fristen-Defaults sind **Arbeitsannahmen**, sofern nicht als Anbieterregel gekennzeichnet. Anbieterfristen sind vor Live-Release je Adapter gegen die aktuellen Nutzungsbedingungen zu verifizieren (TODO M0 „Plattform- und Rechtsfreigaben“) und juristisch zu bewerten. Strengere Anbieterregeln überschreiben die Defaults und sind im Adapter fest verdrahtet (nicht vom Betreiber lockerbar, nur verschärfbar).

## 1. Begriffe und Mechanik

| Begriff | Bedeutung | Umsetzung |
|---|---|---|
| **Refresh-Intervall** | Spätestens dann muss ein externer Snapshot erneut erfolgreich abgerufen werden | `reviews.refresh_due_at = fetched_at + Intervall` |
| **Anzeigegrenze** | Snapshot ohne fristgerechten Refresh wird **nicht mehr öffentlich ausgegeben** (Widget, Block, Shortcode, Summen) | Job `retention.refresh_gate`; Abfrage der Ausgabe filtert zusätzlich `refresh_due_at >= jetzt` für Plattformen mit Pflicht-Refresh |
| **Speicherfrist** | Danach wird der Datensatz gelöscht oder zum Tombstone reduziert | Job `retention.purge` (täglich, Batches à 500) |
| **Tombstone** | `status = removed`, `removed_at` gesetzt; Inhalte (`title`, `body`, `author_name`, `author_email`, `source_url`, `meta`) geleert; behalten: `uuid`, `platform`, `profile_id`, `external_id`, `dedupe_key`, Zeitstempel | verhindert Wiederauftauchen veralteter Kopien und erlaubt Hook `review_captain_review_removed_v1` |
| **Disconnect** | „Verbindung trennen“ eines Profils | [provider-auth.md](provider-auth.md) §1.7 |

Öffentliche Ausgabe zeigt externe Reviews nur, wenn `status = approved`, kein Tombstone und Anzeigegrenze eingehalten. Eine Fehlermeldung oder ungültige Verbindung löscht **nie** sofort Daten (konzept 11) – sie führt nur über die definierte Anzeigegrenze/Speicherfrist zum Ausblenden/Löschen.

## 2. Externe Review-Snapshots je Quelle

| Quelle | Refresh-Intervall (Default) | Anzeigegrenze ohne Refresh | Speicherfrist / Löschpfad | Entfernt an der Quelle | Disconnect (Default) |
|---|---|---|---|---|---|
| **Trustpilot** (API) | **≤ 24 h** (Anbieterregel laut Konzept: max. Cache-Aktualisierung 24 h; nicht verlängerbar) | sofort bei Überschreiten von 24 h seit `fetched_at` | ohne erfolgreichen Refresh 7 Tage nach Anzeigegrenze → Tombstone; Tombstone 90 Tage | beim nächsten Sync nicht mehr geliefert / 404 → Tombstone (`rc.remote.removed`) | Snapshots werden spätestens nach 24 h unsichtbar (kein Refresh mehr möglich), nach 7 Tagen Tombstone |
| **Google Business Profile** | 24 h | 7 Tage (zu verifizieren gegen GBP-Richtlinien) | 30 Tage ohne erfolgreichen Refresh → Tombstone; Tombstone 90 Tage | nicht mehr geliefert → Tombstone | bis Speicherfrist sichtbar, dann Tombstone |
| **Google Places** (nur falls RC-069 umgesetzt) | nicht speichern | – | Inhalte nicht persistieren, nur Place-ID (Anbieterregel zu verifizieren) | – | – |
| **Trusted Shops eTrusted** | 24 h | 7 Tage (zu verifizieren) | 30 Tage ohne Refresh → Tombstone | nicht mehr geliefert / gelöscht → Tombstone | wie Google |
| **Apple App Store** | 24 h | 7 Tage (zu verifizieren) | 30 Tage ohne Refresh → Tombstone | nicht mehr geliefert → Tombstone | wie Google |
| **Google Play** (API) | 24 h für Reviews im API-Fenster (ca. 7 Tage) | für Reviews außerhalb des API-Fensters gilt Herkunft „CSV-Import“ (s. u.) | 30 Tage ohne Refresh → Tombstone, sofern im API-Fenster erwartet | geändert/gelöscht innerhalb Fenster → Update/Tombstone | wie Google |
| **Connector** (`origin = connector`) | vom Connector deklariert | vom Connector deklariert | vom Connector deklariert, Default wie Google | Connector meldet Tombstone | Connector-Deaktivierung → `paused`, Daten bleiben bis Frist |
| **Manuell kuratierte externe Review** (`origin = manual`, `platform ≠ local`) | keiner (Betreiber verantwortet Aktualität) | – | Betreiber-Frist (Default unbegrenzt, Hinweis auf Plattformbedingungen) | Betreiber entfernt manuell | – (kein Profil-Sync) |
| **CSV-Import externer Reviews** | keiner | – | wie manuell; Rollback über `import_batches` | manuell | – |

Betreiber können Intervalle **verkürzen** und Fristen **verkürzen**, nicht verlängern über Anbietergrenzen. Option beim Trennen: „Gespeicherte Reviews dieses Profils sofort entfernen“ (→ sofort Tombstone).

## 3. Lokale und sonstige Daten

| Datenart | Speicherort | Frist-Default | Löschpfad | Export | Disconnect / Deaktivierung |
|---|---|---|---|---|---|
| Lokale Review-Einreichung (`local_submission`), inkl. `author_email` | `reviews` | unbegrenzt solange veröffentlicht; `author_email` 24 Monate nach Einreichung anonymisiert (Arbeitsannahme) | Moderator: ablehnen/archivieren; `manage_privacy`: löschen/anonymisieren; WP Privacy Eraser per E-Mail | WP Privacy Exporter (per E-Mail), CSV-Export | Plugin-Deaktivierung: bleibt erhalten |
| Abgelehnte/Spam-Einreichungen (`rejected`) | `reviews` | 30 Tage, dann hart gelöscht | Job `retention.purge` | CSV-Export (nur Admin) | bleibt |
| Text-Testimonial (`kind = testimonial`) | `reviews` | bis Widerruf/Ablauf (`meta.expires_at`) | Widerruf → `archived` + aus Ausgabe; Löschen über `manage_privacy` | CSV-Export | bleibt |
| Moderationshistorie | `review_events` | Lebensdauer der Review; bei Löschung der Review mitgelöscht | mit Review | im Detail-Export | bleibt |
| Tombstones | `reviews` | 90 Tage nach `removed_at`, dann hart gelöscht | `retention.purge` | nein | – |
| Jobs `succeeded`/`failed` | `jobs` | 14 Tage | `maintenance.jobs_cleanup` | Diagnose | Pro-Deaktivierung: Pro-Jobs `paused` |
| Jobs `dead` | `jobs` | 30 Tage | Cleanup oder Admin „Verwerfen“ | Diagnose | – |
| Periodische Jobs | `jobs` | solange Profil/Funktion existiert | mit Profil | – | Disconnect: `failed` |
| Audit-Log | `audit_log` | 12 Monate | Cleanup-Job | Debug-/Audit-Export (ohne PII) | bleibt |
| Import-Batches | `import_batches` | 12 Monate (Rollback nur innerhalb 30 Tagen) | Cleanup; hochgeladene CSV-Datei wird **sofort** nach Verarbeitung gelöscht | Diagnose | – |
| Provider-Credentials | `profiles.credentials_enc` | bis Disconnect | Disconnect löscht; Deinstallation mit Löschoption | **nie** | Disconnect: sofort gelöscht; Pro-Deaktivierung: bleiben (verschlüsselt), keine Nutzung |
| OAuth-States | Option `review_captain_oauth_state_*` | 10 min, einmalig | Verbrauch oder Ablauf (Cleanup) | nie | – |
| Profile (Link/CTA) | `profiles` | bis Löschung durch Betreiber | Quellen-Seite | Einstellungs-Export (ohne Secrets) | – |
| Medien zu Testimonials (Pro) | WP-Mediathek | bis Widerruf/Ablauf | Widerruf → aus Ausgabe; Löschen des Anhangs durch Betreiber | WP-Export | – |
| Einladungs-/Empfängerdaten (Pro) | Pro-Tabellen | Empfängerreferenz 12 Monate nach letztem Ereignis (Arbeitsannahme); Kontaktdaten **nie** gespeichert, nur zur Versandzeit aufgelöst | Privacy Eraser, Retention-Job | Privacy Exporter | Quell-Plugin entfernt: `paused` (`rc.source.unavailable`) |

## 4. Export

- **WordPress Privacy Exporter** (`wp_privacy_personal_data_exporters`): alle Reviews mit `author_email` = angefragte Adresse (lokale Einreichungen), inkl. Status, Datum, Text, Einwilligung. Externe Reviews enthalten keine E-Mail und sind nicht zuordenbar → nicht Teil des personenbezogenen Exports.
- **WordPress Privacy Eraser**: anonymisiert (Name → „Anonym“, E-Mail → NULL, `author_user_id` → NULL) bzw. löscht nach Einstellung; Eintrag in `review_events` (`anonymized`) und `audit_log`; leitet an Jobs (`privacy.erase`) weiter, die Payloads mit Referenzen bereinigen.
- **CSV-Export** (`review_captain_manage_privacy`): keyset-paginiert ([scaling.md](scaling.md)), Spalten inkl. `platform`, `external_id`, `source_url`, `origin` (Abnahme konzept 7.9), CSV-Formel-Injection-Schutz (Werte mit `= + - @ \t \r` am Anfang werden mit `'` präfixiert). Externe Snapshots werden nur exportiert, wenn ihre Anzeigegrenze nicht überschritten ist (Trustpilot) bzw. mit Spalte `stale = 1` gekennzeichnet (andere).
- **Einstellungs-Export:** nie Secrets, nie personenbezogene Daten.

## 5. Backup und Restore

- Backups liegen außerhalb der Kontrolle des Plugins. Dokumentiert wird: Tabellen enthalten nur verschlüsselte Secrets; der Schlüssel (`REVIEW_CAPTAIN_ENCRYPTION_KEY` bzw. Salts in `wp-config.php`) muss separat gesichert werden ([secrets.md](secrets.md)).
- Nach Restore läuft zuerst die [Reconciliation](reconciliation.md): abgelaufene Snapshots (z. B. Trustpilot > 24 h) werden sofort ausgeblendet und gemäß Frist bereinigt, Tokens geprüft, Jobs ohne Außenwirkung zuerst.
- Gelöschte Daten können durch ein älteres Backup wieder auftauchen; Privacy-Löschungen werden deshalb im `audit_log` mit Review-UUID (ohne PII) vermerkt und von der Reconciliation erneut angewendet.

## 6. Pro-Ablauf, Deaktivierung, Deinstallation

| Ereignis | Folge für Daten |
|---|---|
| Pro-Lizenz läuft ab | keine neuen Syncs; vorhandene Snapshots bleiben **innerhalb ihrer Fristen** lesbar/exportierbar/löschbar; Refresh entfällt → Trustpilot-Snapshots nach 24 h unsichtbar; Retention-Jobs (Core) laufen weiter |
| Pro deaktiviert | wie Ablauf; Pro-Jobs `paused` |
| Core deaktiviert | keine Jobs (auch keine Retention!) → Hinweis bei Deaktivierung; bei Reaktivierung sofort Reconciliation + Purge |
| Core deinstalliert | Löschung nur bei Option `uninstall.delete_all_data`; sonst bleibt alles |

## Abnahme

- [ ] Jede Datenart aus §2/§3 hat im Code eine Frist-Konstante bzw. Einstellung mit dokumentiertem Default; Adapter-Mindestregeln können nicht gelockert werden (Test).
- [ ] Trustpilot-Snapshot mit `fetched_at` > 24 h erscheint in keiner öffentlichen Ausgabe (Widget, Shortcode, Block, Summen), auch nicht aus einem Seiten-/Objektcache ([caching.md](caching.md)).
- [ ] An der Quelle gelöschte Review wird beim nächsten Sync zum Tombstone; Inhalte sind geleert; Hook `review_captain_review_removed_v1` feuert einmal.
- [ ] Disconnect ohne „sofort entfernen“ lässt Snapshots bis Fristende bestehen; mit Option sofort Tombstones.
- [ ] Privacy Exporter/Eraser decken lokale Einreichungen ab und hinterlassen keine E-Mail in `reviews`, `jobs.payload`, `audit_log`.
- [ ] CSV-Export enthält Quelle und Plattform-ID und ist gegen Formel-Injection geschützt.
- [ ] Restore eines 3 Tage alten Backups blendet Trustpilot-Snapshots sofort aus und wendet protokollierte Privacy-Löschungen erneut an.
- [ ] Lizenzablauf löscht keine Daten.
