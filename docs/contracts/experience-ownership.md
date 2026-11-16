# Experience-ID-Ownership je Quelle (RC-022)

| | |
|---|---|
| Status | **Spezifikation. Bestätigung durch die Quell-Plugins (WooCommerce-Adapter-Team, Voucher Captain, Pass Captain, Form Captain) offen.** |
| Bezug | TODO RC-022, RC-095–RC-104, RC-WF-04, RC-WF-06; konzept.md 7.3, 7.9 „Erlebnis-ID und verantwortliches Plugin“, Integrationsstatus-Abgleich |
| Verwandt | [`experience-event-v1.md`](experience-event-v1.md), [`invitation-rule.md`](invitation-rule.md) |

> Die hier beschriebenen IDs, Ereignisse und Felder der Quell-Plugins sind **Anforderungen von Review Captain**, keine Beschreibung bereits vorhandener Schnittstellen. Laut Integrationsstatus-Abgleich (konzept.md) sind ein öffentlicher Completion-Vertrag (Pass), die Klassifizierung „Einlösung = Leistung“ (Voucher) und die Form→Review-Aktion (Form) noch offen. Bestehende interne Actions der Quell-Plugins werden **nicht** als API vorausgesetzt.

## Allgemeine Regeln

1. **Genau ein ID-Verantwortlicher je Erlebnis:** das Plugin, das die Leistung kontrolliert. Es erzeugt die `experience_id` beim ersten gültigen Erlebnisereignis, speichert sie dauerhaft am Quellobjekt und verwendet sie für alle Folgeereignisse.
2. **Format:** `{source}:{site_id}:{experience_kind}:{stable_object_key}`
   - `source` = Quellschlüssel aus dem Ereignisvertrag,
   - `site_id` = `get_current_blog_id()` (Multisite-Isolation; Single-Site `1`),
   - `experience_kind` = `[a-z_]+`,
   - `stable_object_key` = unveränderliche ID(s) des Quellobjekts, mit `:` verbunden, ohne PII/Codes.
   - Gesamtlänge ≤ 191 Zeichen.
3. **Kein Parallel-Schlüssel:** Review Captain und andere Plugins erfinden keine eigene ID für ein fremdes Erlebnis. Ausnahme WooCommerce (kein eigener Experience-Vertrag): der Review-Captain-Woo-Adapter **leitet** die ID deterministisch aus der Bestell-ID ab; das ist eine Ableitung, keine zweite Kaufentität.
4. **Getrennte Erlebnisse bleiben getrennt:** verschiedene Eventteilnahmen, separate Gutscheinleistungen, separate Bestellungen.
5. **Dedupe** erfolgt auf drei Ebenen: Ereignis (`event_id`, `idempotency_key`), Erlebnis (`experience_id` UNIQUE in `experiences`), Einladung (`invitation-rule.md`).
6. **Ownership-Übergabe:** Enthält eine Woo-Bestellung Positionen, deren Leistung ein Suite-Plugin kontrolliert (Gutschein, Ticket), gehören diese Positionen **nicht** zum Woo-Erlebnis. Der Woo-Adapter schließt sie aus, sobald das zuständige Suite-Plugin aktiv und sein Adapter in Review Captain eingeschaltet ist. Besteht die Bestellung nur aus solchen Positionen, entsteht kein Woo-Erlebnis.

## Matrix

| Quelle | ID-Verantwortlicher | Erlebnis (Auslöser `experience_completed`) | `experience_id` | Empfänger (`recipient_ref`) | widerrufende Ereignisse | Dedupe-Regeln |
|---|---|---|---|---|---|---|
| **WooCommerce-Kauf** | WooCommerce (Bestell-ID); Ableitung durch Review-Captain-Woo-Adapter | konfigurierbarer Bestellstatus (Default `completed`) – nur für Positionen ohne Ownership-Übergabe | `woocommerce:{site}:order:{order_id}` | `woocommerce:customer:{user_id}` bzw. Gastbestellung `woocommerce:order_billing:{order_id}` | Voll-Erstattung, Storno, Chargeback → `experience_refunded`/`experience_cancelled`; Teil-Erstattung mit `context.items` | eine Bestellung = ein Erlebnis; mehrere Positionen in `context.items` (Mehrprodukt-Anfrage RC-096); Statuswechsel hin und her erzeugt keine zweite Anfrage (`idempotency_key = woocommerce:{site}:completed:{order_id}`) |
| **Voucher-Einlösung** | Voucher Captain | tatsächliche Einlösung **einer als „Leistung/Erfahrung“ klassifizierten Gutscheinart** bzw. abgeschlossener Leistungspass. Ausgabe, Kauf, Zustellung, Rabattcode, Guthabenaufladung = nie `experience_completed` | `voucher-captain:{site}:redemption:{redemption_id}` | bevorzugt tatsächlicher Nutzer: `voucher-captain:beneficiary:{id}`; Käufer `voucher-captain:purchaser:{id}` nur per Kampagnenregel | Einlösung storniert/aufgehoben → `experience_voided`; Gutschein erstattet → `experience_refunded` | je Einlösung ein Erlebnis; erneute Meldung derselben Einlösung → `idempotency_key = voucher-captain:{site}:redeemed:{redemption_id}`. **Entschieden (16.11.2026, laut Voucher-Sitzung):** nur die erste bestätigte Einlösung je Wertgutschein zählt; wird sie widerrufen, darf die nächste zählen. Umsetzung in Voucher Captain (`vcap_review_first_redemption_only`). |
| **Pass-Teilnahme** | Pass Captain | gültiger Check-in (Default) oder versioniertes „Teilnahme bestätigt“ für Events ohne Check-in; Service-Slot abgeschlossen | `pass-captain:{site}:attendance:{ticket_id}:{occurrence_id}` | Ticketinhaber/Teilnehmer `pass-captain:attendee:{id}`; Käufer `pass-captain:buyer:{id}` nur per Kampagnenregel | Absage → `experience_cancelled`, Refund → `experience_refunded`, No-show → `experience_no_show`, Check-in aufgehoben → `experience_voided` | Check-in + Eventende = **ein** Erlebnis (`experience_updated`); je Ticket und Termin ein Erlebnis; Mehrfach-Check-in desselben Tickets → gleicher `idempotency_key` `pass-captain:{site}:checkin:{ticket_id}:{occurrence_id}`; Käufer mit mehreren Tickets → Empfängersperre (invitation-rule.md, Regel S3) |
| **Form-Captain-Einsendung** | Form Captain | **kein Einladungsauslöser.** Eine explizit gemappte Einsendung wird über `review_captain_create_local_review_v1()` als lokaler Review-Entwurf (`pending`, `unverified`) übergeben | `form-captain:{site}:entry:{entry_id}` (dient nur Idempotenz/Verknüpfung, wird in `reviews.experience_id` **nicht** als Einladungsbezug genutzt) | – (keine Einladung) | Löschung der Einsendung → wartender Review wird gelöscht; freigegebener folgt Privacy-Regeln | `idempotency_key = form-captain:{site_id}:{entry_id}` (konzept 7.3 E) → höchstens ein Review je Einsendung: der Core setzt `external_id = idempotency_key`, damit greift `dedupe_key` UNIQUE (siehe workflow-provider-v1.md, Service `create_draft_review`) |
| Custom Connector | Connector-Plugin | ausdrücklich freigegebenes Abschlussereignis | `{connector_key}:{site}:{kind}:{id}` | `{connector_key}:{art}:{id}` | wie Ereignisvertrag | wie Ereignisvertrag |

## Konkurrierende Quellen – Entscheidungstabelle

| Situation | Erwartetes Verhalten |
|---|---|
| Woo-Bestellung enthält nur ein Gutscheinprodukt; Voucher-Adapter aktiv | kein Woo-Erlebnis; Einladung erst nach Voucher-Einlösung |
| Woo-Bestellung enthält Gutschein + physisches Produkt | Woo-Erlebnis nur mit dem physischen Produkt (`context.items`); Gutschein separat über Voucher |
| Woo-Bestellung enthält Tickets; Pass-Adapter aktiv | Ticketpositionen gehören Pass; Woo-Erlebnis nur für übrige Positionen |
| Voucher-Adapter **inaktiv**, Gutscheinprodukt über Woo gekauft | keine Ownership-Übergabe möglich → Woo-Kampagne darf die Gutscheinposition **nicht** einladen, wenn das Produkt als „Gutschein/Leistung später“ markiert ist (Produkt-Mapping RC-095); Default: ausschließen |
| Check-in und Eventende kommen gleichzeitig | dasselbe `experience_id` → eine `experiences`-Zeile, eine Anfrage (race-conditions.md R-01) |
| Workflow Captain plant für dasselbe Erlebnis zusätzlich | Workflow-Plan läuft über dieselbe Einladungsregel → `skipped` mit `rc.request.duplicate` (workflow-provider-v1.md) |

## Bestätigungsstatus

| Quelle | Benötigte Bestätigung | Status |
|---|---|---|
| WooCommerce-Adapter (Review Captain Pro) | Bestellstatus-Auswahl, HPOS/Classic, Positionsausschluss bei Übergabe | offen (RC-095) |
| Voucher Captain | Einlöse-Ereignis als öffentlicher, versionierter Vertrag; Klassifizierung Leistung vs. Wert; Käufer/Beschenkter-Referenzen; Resolver | offen (RC-098, RC-099) – auf `main` fehlen alle `vcap_review_*`-Hooks ([Abgleich](suite-abgleich-2026-11-16.md)) |
| Pass Captain | Completion-Vertrag (Check-in, Teilnahme bestätigt, No-show, Absage, Refund) mit stabiler Ticket-/Termin-ID; Teilnehmerreferenz; Resolver | offen (RC-100, RC-101) – auf `main` kein Vertrag, Check-in ohne Termin-ID ([Abgleich](suite-abgleich-2026-11-16.md)) |
| Form Captain | `form_captain_register_action_v1`, Feldmapping, Entry-ID-Stabilität, Löschereignis | auf `main` vorhanden; offen nur Multisite-Schlüssel im Pro-Adapter ([Abgleich](suite-abgleich-2026-11-16.md)) |

## Abnahme

- [ ] WooCommerce-Kauf, Voucher-Einlösung, Pass-Teilnahme und Form-Captain-Einsendung haben jeweils genau einen ID-Verantwortlichen und ein dokumentiertes ID-Format.
- [ ] Dedupe-Regeln je Quelle sind dokumentiert und durch Tests (Doppel-Event, Statuswechsel, Check-in + Eventende, Woo + Voucher in einer Bestellung) abgedeckt.
- [ ] Ownership-Übergabe Woo → Voucher/Pass ist in der Entscheidungstabelle beschrieben und getestet.
- [ ] Form-Captain-Einsendungen lösen nachweislich nie eine Einladung aus.
- [ ] Jede Quelle hat den Bestätigungsstatus „bestätigt“ mit Datum und Vertragsversion des Quell-Plugins, bevor der Adapter als fertig gilt.
