# CON-005 – Sicherheits- und Datenmatrix für Connectoren

Status: Spezifikation v1, Stand 2026-11-16. Bezug: [contract-v1.md](contract-v1.md), Konzept §7.10 „Sicherheit und Betriebsgrenzen“, §11.

## Grundsatz: keine Sandbox

Ein Connector ist ausführbarer PHP-Code eines installierten WordPress-Plugins. Er läuft mit denselben Rechten wie WordPress selbst und kann technisch auf alles zugreifen. **Das Connector-Framework ist keine Sandbox** und verspricht keine Isolation gegen bösartige oder fehlerhafte Plugins. Die folgenden Regeln begrenzen, was Review Captain **übergibt, speichert und ausführt** – nicht, was fremder Code kann. Betreiber installieren nur vertrauenswürdige, geprüfte Connectoren; die Admin-Seite zeigt diesen Hinweis bei jeder Aktivierung.

## Querschnittsregeln

| Bereich | Regel |
|---|---|
| Aktivierung | Kein Connector wird nach Installation automatisch aktiv. Aktivieren, Pausieren, Trennen, Testen, Wiederholen: Capability `review_captain_manage_sources` + Nonce `review_captain_{action}` (+ Entitlement in Pro). |
| Ausführung | Ausschließlich im Jobrunner (WP-Cron/Action Scheduler, WP-CLI) bzw. in geprüften Admin-Aktionen; nie im Frontend-Request. Lease, Idempotency-Key, Timeout, Retry/Backoff, Dead-Letter. |
| Datenminimierung | Jede Fähigkeit erhält/liefert nur die in [contract-v1.md](contract-v1.md) definierten Felder. Keine Rohbestellungen, Zahlungsdaten, vollständige Formulareinträge, Ticket-/Gutscheincodes. |
| Logging-Allowlist | Logs/Audit/Diagnose dürfen nur enthalten: Connector-Key, Connector-Version, Capability, Jobtyp, Job-ID, `event_id`, `experience_id`, `external_id`, `request_uuid`, Fehlercode, gekürzte sanitisierte Fehlermeldung, Zeitstempel, Zähler. **Nie:** E-Mail, Name, `recipient_ref`-Auflösung, Reviewtext, Secrets, Tokens, vollständige Payloads. |
| Secrets | Nur über die (Pro-)Konfiguration, maskiert und verschlüsselt gespeichert; nie in Optionen im Klartext, Job-Payloads, Logs oder Diagnoseexporten. |
| Ausgabe | Alle Connector-Strings (Label, Developer, Fehlermeldungen, Context-Labels) werden beim Ausgeben escaped; Review-HTML über `wp_kses`. |
| Netzwerk | Lokale Connectoren dürfen intern HTTP nutzen (z. B. eigenes CRM); Review Captain stellt keine frei konfigurierbaren URL-Abrufe/Webhooks aus Admin-Feldern bereit. Externe Ziele: HTTPS, SSRF-Schutz (keine privaten/Loopback-Adressen, wenn vom Admin eingegeben), Signatur. |
| Inkompatibilität | Bei `incompatible_contract`, `source_unavailable` oder fehlendem Entitlement wird pausiert – nie stillschweigend mit unvollständigen Daten weitergearbeitet. |
| Multisite | Registry, Status und Jobs sind pro Site; keine siteübergreifende Datenweitergabe. |

## Matrix je Fähigkeit

| | Experience Source | Recipient Resolver | Review Source | Request Transport | Context Provider |
|---|---|---|---|---|---|
| **Minimale Datenfelder** | `event_id`, `event_type`, `experience_id`, `occurred_at`, `object_type/id`, `recipient_ref`, `locale`, `communication_allowed`, `consent` | Eingang: `recipient_ref`, `purpose`; Ausgang: `email`, `locale`, `permission` | `external_id`, Rating, Titel, Text, Autor-Anzeigename, Datum, Kontext, `consent_publish`, `verification` | Gerenderte Nachricht, E-Mail, Locale, Links, `idempotency_key` | `label`, `url` |
| **Personenbezug** | pseudonym (`recipient_ref`) | ja (E-Mail) | ja (Name, Inhalt) | ja (E-Mail, Inhalt) | nein |
| **Admin-Capability** | `review_captain_manage_sources` (Aktivierung), `review_captain_manage_requests` (Kampagnenzuordnung) | `review_captain_manage_requests` | `review_captain_manage_sources`; Moderation `review_captain_moderate` | `review_captain_manage_requests` | `review_captain_manage_sources` |
| **Consent-/Berechtigungsprüfung** | `communication_allowed=true`, ggf. `consent.status=granted`; Kampagnenausschlüsse, Suppression | `permission=allowed`; Suppression-Liste und Opt-out im Core vor jedem Versand | `consent_publish=true` Pflicht für Veröffentlichung; Moderation immer | Nur nach bestandener Prüfung durch den Core; `unsubscribe_url` muss mitversendet werden | entfällt |
| **Speicherung im Core** | Event-Metadaten zur Dedupe (ohne Kontakt), Anfrage-Status | **keine** Speicherung; nur im Arbeitsspeicher des Jobs | Tabelle `reviews` (`origin=connector`) | Anfrage-Status, `provider_message_id` | Cache (Transient, 12 h) |
| **Retention** | Dedupe-Schlüssel gemäß Anfrage-Aufbewahrung (Einstellung, Standard 12 Monate) | entfällt | wie lokale Reviews (Aufbewahrungseinstellung) | Anfrage-Protokoll gemäß Aufbewahrung | Cache-Ablauf |
| **Export/Erase (Privacy Tools)** | Anfragen zur E-Mail werden über Resolver nicht rückwärts gesucht; Löschung per `experience_id`/Empfänger über Anfrage-Datensätze | entfällt | Reviews über Autorname/Zuordnung exportier- und löschbar; Tombstone `removed=true` aus der Quelle wird übernommen | Versanddatensätze zur E-Mail (Hash) löschbar | entfällt |
| **Logging-Allowlist** | `event_id`, `experience_id`, `event_type`, Fehlercode | `purpose`, Ergebnis-Kategorie (`allowed/denied/unknown/null`) – **nie** E-Mail oder Referenz-Auflösung | `external_id`, Zähler, Fehlercode – nie Text/Name | `request_uuid`, `status`, `provider_message_id`, Fehlercode | `context_type`, `context_id`, Fehlercode |
| **Retry-Klassifikation** | Retry: `rc.net.timeout`, `rc.temp.unavailable`, `rc.quota.exceeded`; Pause: `rc.auth.invalid`, `rc.source.unavailable`, `rc.contract.incompatible`; verwerfen: `rc.data.invalid_payload` je Event | `null` → kein Retry (`recipient_unknown`); Exceptions wie links | wie Experience Source; `rc.remote.removed` → Tombstone | `rejected` → kein Retry; temporäre Codes → Retry **mit gleichem** `idempotency_key` | Fehler → alter Cache bleibt, Retry im nächsten Intervall |
| **Disconnect/Pause** | Polling stoppt; geplante Einladungen je Einstellung pausiert oder abgebrochen; Cursor bleibt erhalten | Versandjobs, die einen Resolver brauchen, pausieren | Polling stoppt; vorhandene Reviews bleiben unverändert sichtbar | ausstehende Sendungen pausieren; keine Umleitung auf anderen Transport ohne Admin-Entscheidung | Cache bleibt bis Ablauf, danach Fallback auf generisches Label |
| **Resume** | Vertrag, Entitlement, Consent und Kampagnenfristen werden neu geprüft; abgelaufene Einladungen verfallen | Neuprüfung der Berechtigung pro Versand | ab gespeichertem Cursor | Neuprüfung von Suppression/Opt-out vor jedem Versuch | sofort |

## Bedrohungen und Gegenmaßnahmen

| Bedrohung | Gegenmaßnahme |
|---|---|
| Connector liefert E-Mail im Event | Validator lehnt `@` in `recipient_ref` ab; Payload-Felder per Allowlist |
| Event-Flut/Duplikate | `event_id`-Dedupe, `experience_id`-Grenze, `$limit ≤ 100`, Backoff |
| Mehrfachversand bei Retry | `idempotency_key` im Transport-Vertrag Pflicht; Core versendet nach `accepted` nie erneut |
| XSS über Labels/Reviews | Escaping, `wp_kses`, Längenbegrenzung |
| Secret-Leck über Diagnose | Export enthält nur Allowlist-Felder; Secret-Felder maskiert |
| Fatal Error durch fehlerhaften Connector | alle Aufrufe in `try/catch (\Throwable)`; Fehler → Status statt Abbruch |
| Connector umgeht Moderation | Core ignoriert Status-Felder, setzt `pending` |
| SSRF über Admin-Felder | keine frei konfigurierbaren Ziel-URLs im Core; Pro-Konfiguration validiert Host-Allowlist |
