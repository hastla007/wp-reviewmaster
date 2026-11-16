# RC-027 – Aufbewahrungs- und Löschmatrix

**Status:** Entwurf – Fristen abhängig von RC-014 (Recht) und RC-008 (Anbieter)  
**Stand:** 2026-11-16

| Datenart | Tabelle | Standardfrist | Refresh | Löschpfad / Widerruf | Privacy Export | Privacy Eraser | Backup-Hinweis | Bei Disconnect | Bei Deinstallation (Option an) |
|---|---|---|---|---|---|---|---|---|---|
| Lokale Reviews (freigegeben) | `rc_reviews` origin=local/connector | unbefristet bis Löschung | – | Admin löschen; Eraser | ja (per `author_email_hash`/`author_user_id`) | anonymisiert Autor (Name → „Anonym“, Hash/User-ID entfernt); Text bleibt nur, wenn Betreiber Option „Text bei Löschanfrage entfernen“ nicht aktiv hat (Default: Text entfernen) | in WP-Backup enthalten | – | gelöscht |
| Abgelehnte/Spam-Reviews | `rc_reviews` | 30 Tage | – | Retention-Job | ja | gelöscht | – | – | gelöscht |
| Manuelle externe Reviews | `rc_reviews` origin=manual | bis Löschung durch Admin | Admin prüft Aktualität (Hinweis nach 12 Monaten) | Admin | nein (keine Betroffenenzuordnung) | – | – | – | gelöscht |
| Synchronisierte externe Reviews | `rc_reviews` origin=sync | solange Quelle verbunden und Snapshot ≤ Aktualitätsfrist | ≤ 24 h (bzw. Anbieterfrist) | Tombstone bei Löschung beim Anbieter; physisch löschen nach 7 Tagen Tombstone | nein | – | Restore → Reconciliation verwirft veraltete Snapshots (TEC-015) | ausblenden sofort nach Ablauf der Frist, löschen nach 7 Tagen | gelöscht |
| Moderationsverlauf | `rc_review_log` | wie Review | – | mit Review | – | mit Review | – | – | gelöscht |
| Text-/Medien-Testimonials | `rc_testimonials` + Mediathek | bis Ablauf/Widerruf | – | Widerruf → ausblenden, Medien nach 30 Tagen löschen | ja (manuelle Zuordnung) | ja | – | – | Tabelle gelöscht; Medien bleiben in der Mediathek (Hinweis) |
| Erlebnisereignisse | `rc_experiences` (Pro) | 90 Tage nach Verarbeitung | – | Retention-Job | über `recipient_ref` der Quelle | gelöscht | – | – | gelöscht |
| Einladungen (Versandnachweis) | `rc_requests` (Pro) | 365 Tage, dann anonymisiert (`recipient_ref` → NULL) | – | Retention-Job | ja | anonymisiert | – | – | gelöscht |
| Geplante, nicht gesendete Einladungen | `rc_requests` | bis `expired` (60 Tage) | – | Widerrufsereignis | ja | gelöscht | Reconciliation verhindert Altversand | – | gelöscht |
| Suppression/Opt-out | `rc_suppressions` (Pro) | unbefristet (Hash) | – | Admin kann entfernen (Audit) | Treffer ja/nein | **nicht** gelöscht (Löschung würde erneuten Versand ermöglichen) – Hinweis im Export | – | – | gelöscht |
| Zugangsdaten | `rc_connections` (Pro) | bis Trennen | Token-Refresh | Trennen = sofort löschen + Widerruf | nein | – | ohne Schlüssel wertlos | sofort gelöscht | gelöscht |
| Jobs | `rc_jobs` | erledigt: 7 Tage; dead: 30 Tage | – | Retention-Job | nein (keine PII im Payload, nur Referenzen) | Jobs mit betroffener Referenz gelöscht | – | Provider-Jobs `cancelled` | gelöscht |
| Audit-Log | `rc_audit_log` | 90 Tage | – | Retention-Job | User-bezogene Einträge ja | `user_id` anonymisiert | – | – | gelöscht |
| Import-Batches | `rc_import_batches` | 90 Tage (danach kein Rollback) | – | Retention-Job | nein | – | – | – | gelöscht |
| Einstellungen | `wp_options` | bis Deinstallation | – | Reset | nein | – | – | – | gelöscht |

Ausführung: täglicher Job `maintenance.retention` (zeitbudgetiert, batchweise 500 Zeilen), auch per `wp review-captain maintenance retention`. Jede Frist ist in den Datenschutzeinstellungen änderbar, aber nie „unbegrenzt“ für Einladungen/Logs.
