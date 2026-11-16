# Review Captain – Verträge und Spezifikationen (Index)

Stand: 2026-11-16. Alle Dokumente sind **Spezifikationen im Entwurf zur Abnahme**, sofern nicht anders markiert. Verbindliche Namen, Tabellen, Hooks, Statuswerte und das Fehlercode-Format stammen aus [`../architecture/conventions.md`](../architecture/conventions.md); bei Widerspruch gilt conventions.md. Anbieterspezifische Werte (Auth, Scopes, Limits, Cache-Fristen) sind hier **nicht bestätigt** und werden in den Auth-/Provider-Records (RC-010–RC-013, RC-026) festgelegt.

## Dokumente

| Dokument | TODO | Inhalt | Status |
|---|---|---|---|
| [system-of-record.md](system-of-record.md) | RC-017 | Eigentumsmatrix je Datenart, Regeln gegen Doppelführung | Entwurf |
| [review-schema-v1.md](review-schema-v1.md) + [schema/review.v1.json](schema/review.v1.json) | RC-018 | Feldliste (= Spalten `reviews`), Skalen, Sprache, externe ID/Dedupe, Kontext, Cachezeit, Tombstone, Sichtbarkeitslogik | Entwurf |
| [testimonial-schema-v1.md](testimonial-schema-v1.md) | RC-019 | Core-Text-Testimonials (`kind=testimonial`), Pro-Medien-/Rechtetabellen, Ablauf, Widerruf | Entwurf; Rechtetexte hängen an RC-015 |
| [provider-v1.md](provider-v1.md) | RC-020 | Provider-Interface, Registry `review_captain_providers_v1`, `SyncService`, Fixtures (`demo`) | an Implementierung angeglichen |
| [experience-event-v1.md](experience-event-v1.md) + [schema/experience-event.v1.json](schema/experience-event.v1.json) | RC-021, CON-004 | Ereignisvertrag `review_captain.experience_event.v1` | Entwurf |
| [experience-ownership.md](experience-ownership.md) | RC-022 | ID-Verantwortliche und Dedupe je Quelle (WooCommerce, Voucher, Pass, Form) | **Spezifikation; Bestätigung durch Quell-Plugins offen** |
| [recipient-resolver-v1.md](recipient-resolver-v1.md) | RC-023 | Kontaktauflösung nur zur Versandzeit | Entwurf |
| [request-lifecycle.md](request-lifecycle.md) | RC-024 | Zustände, Mermaid-Diagramm, Übergangstabelle, Pro-Tabelle `requests` | Entwurf |
| [invitation-rule.md](invitation-rule.md) | RC-025 | Einladungsschlüssel, Suite-Sperre, Versand-Ledger, Empfänger-Guard, SQL | Entwurf |
| [../architecture/error-codes.md](../architecture/error-codes.md) | RC-030 | Fehlercodes, Retry-Klassen, Integrationsstatus | an Implementierung angeglichen; nicht implementierte Codes „geplant“ |
| [workflow-provider-v1.md](workflow-provider-v1.md) | RC-WF-01, -02, -03, -06 | Trigger, Payload-Allowlists, Services, zulässige Auslöser | Entwurf; Workflow-Captain-Registry unbestätigt |
| [race-conditions.md](race-conditions.md) | TEC-005 | 12 Szenarien mit erwartetem Verhalten und Testfällen | Entwurf |
| [cross-site-interfaces.md](cross-site-interfaces.md) | TEC-011 | Voraussetzungen für spätere signierte REST/Webhooks | Entwurf (v1: nicht implementiert) |
| [widget-v1.md](widget-v1.md) | RC-049–RC-055, RC-039, RC-118 | Shortcode-/Block-/Formularvertrag | **verbindlich**, an Implementierung angeglichen |
| [../hooks.md](../hooks.md) | – | generierte Referenz aller `review_captain_*`-Hooks (`bin/list-hooks.php`) | generiert |

## Lesereihenfolge für Implementierung

1. Core sofort: `widget-v1.md`, `review-schema-v1.md`, `testimonial-schema-v1.md` (nur Core-Teil), `error-codes.md`, `system-of-record.md`.
2. Core-Verträge (lizenzfrei, Implementierung der Laufzeit in Pro): `provider-v1.md`, `experience-event-v1.md`, `recipient-resolver-v1.md`, `workflow-provider-v1.md` (Service-Funktionen).
3. Pro: `request-lifecycle.md`, `invitation-rule.md`, `race-conditions.md`, `experience-ownership.md`.

## Ergänzungen gegenüber conventions.md (per ADR nachgetragen)

**Erledigt mit [ADR-0007](../adr/0007-oeffentliche-hooks-v1.md):** Die folgenden Namen stehen jetzt in [`conventions.md`](../architecture/conventions.md) – implementierte als Vertrag (Hook-Tabelle), nicht implementierte als „Reservierte Namen“. Abweichend umgesetzt: Provider-Namespace `ReviewCaptain\Providers\Contract\` (statt `ReviewCaptain\Contracts\Provider\*`), admin-post-Action `review_captain_submit` (statt `review_captain_submit_review`). Die Tabelle bleibt als Herkunftsnachweis erhalten.

Diese Namen wurden konsistent zum Präfix `review_captain_` ergänzt. Hook-Namen aus konzept.md mit `wp_`-Präfix wurden gemäß ADR-0002 ohne `wp_` übernommen.

| Art | Name | Definiert in |
|---|---|---|
| action | `review_captain_experience_event_v1` | experience-event-v1.md |
| action | `review_captain_request_status_changed_v1`, `review_captain_request_reminder_sent_v1`, `review_captain_request_responded_v1`, `review_captain_recipient_opted_out_v1` | request-lifecycle.md, workflow-provider-v1.md |
| Funktion | `review_captain_submit_experience_event_v1()` | experience-event-v1.md |
| Funktion | `review_captain_plan_request_v1()`, `review_captain_cancel_request_v1()`, `review_captain_create_local_review_v1()`, `review_captain_delete_local_review_draft_v1()`, `review_captain_get_review_summary_v1()` | workflow-provider-v1.md |
| Klasse/API | `ReviewCaptain\Contracts\Provider\*`, `ReviewCaptain\Contracts\Connector\RecipientResolverInterface`, `ReviewCaptain\Api::register_request_service()`, `::workflow_descriptor_v1()` | provider-v1.md, recipient-resolver-v1.md, workflow-provider-v1.md |
| Payload-Schema | `review_captain.experience_event.v1`, `review_captain.request.v1`, `review_captain.recipient.v1`, `review_captain.workflow_provider.v1`, `review_captain.review_record.v1` | jeweilige Dokumente |
| Option | `review_captain_requests_cooldown_days`, `review_captain_data_version`, `review_captain_instance_marker` | invitation-rule.md, widget-v1.md, race-conditions.md |
| Pro-Tabellen (Vorschlag) | `review_captain_requests`, `review_captain_request_log`, `review_captain_request_sends`, `review_captain_recipient_guards`, `review_captain_inbound_events`, `review_captain_experiences`, `review_captain_sync_runs`, `review_captain_testimonial_media`, `review_captain_testimonial_rights`, `review_captain_inbound_nonces` (nur TEC-011) | jeweilige Dokumente |
| admin-post-Action / Nonce | `review_captain_submit_review` | widget-v1.md |
| `meta`-Allowlist für `reviews.meta` | Schlüsselliste | review-schema-v1.md |

Offen für das ADR: Pro-Tabellen nutzen dasselbe Präfix `{$wpdb->prefix}review_captain_` mit eigener Versionsoption (Vorschlag `review_captain_pro_db_version`).

## Offene Produktentscheidungen

| # | Frage | Vorschlag in den Specs | Dokument |
|---|---|---|---|
| P1 | Initialstatus synchronisierter externer Reviews (`pending` vs. `approved`) | Implementierung: `pending`, umstellbar über Filter `review_captain_sync_initial_status` (je Profil: offen) | review-schema-v1.md |
| P2 | Globale Frequenzgrenze (Cooldown) je Kontakt | 30 Tage, `0` = aus | invitation-rule.md |
| P3 | Klickerfassung (`clicked`) überhaupt anbieten? | Default aus, je Kampagne aktivierbar nach Datenschutzprüfung | request-lifecycle.md |
| P4 | Zulässige Versandgrundlagen (`send_permission.basis`) je Markt | Kampagne akzeptiert nur konfigurierte Grundlagen; Entscheidung RC-014 | experience-event-v1.md |
| P5 | Teil-Einlösungen von Wertgutscheinen: jedes Mal ein Erlebnis, nur erste, oder gar nicht? | offen | experience-ownership.md |
| P6 | Pass: Empfängerstrategie (nur Teilnehmer / Teilnehmer, sonst Käufer / nur Käufer) als Default | Teilnehmer bevorzugt (konzept 7.9) | experience-ownership.md |
| P7 | Ablauf von Testimonial-Rechten: unbefristet zulässig? Default-Laufzeit? | `expires_at` nullable; Entscheidung RC-015 | testimonial-schema-v1.md |
| P8 | Speicherort für Rechtebelege/Medien unveröffentlichter Testimonials (nicht öffentlich) | geschützter Pfad, RC-109 | testimonial-schema-v1.md |
| P9 | Aufbewahrung von Tombstones und Suppression nach Privacy-Löschung | Tombstone 30 Tage; Suppression bleibt (Rechtsprüfung) | review-schema-v1.md, invitation-rule.md |
| P10 | Deprecation-Frist öffentlicher Verträge | 12 Monate **und** 2 Minor-Releases | provider-v1.md, workflow-provider-v1.md |
| P11 | Mindeststichprobe für Aggregate an Suite-Plugins | 5 (min. 3) | workflow-provider-v1.md |
| P12 | Restore-Erkennung: wartende Anfragen nach Restore automatisch freigeben oder immer manuell? | manuell | race-conditions.md |
| P13 | E-Mail im lokalen Formular Pflicht oder optional | Implementierung: immer Pflicht (keine Einstellung in v1) | widget-v1.md |
| P14 | Default `kind` im Shortcode (`review` vs. `all`) | entschieden durch Implementierung: `all` | widget-v1.md |
| P15 | Kontext „aktueller Beitrag“ im Shortcode (`context_id="current"`) | entschieden durch Implementierung: in v1 enthalten | widget-v1.md |
| P16 | Anzeige nativer WooCommerce-Produktreviews im Review-Captain-Widget (lesend) – in Widget v1 nicht enthalten; Verhältnis zur Woo-Migration RC-046 | offen | system-of-record.md |
| P17 | Konservativer Default-Cache-TTL für externe Snapshots bis Bestätigung je Anbieter | 24 h | review-schema-v1.md |
