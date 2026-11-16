# Abgleich Suite-Verträge gegen `main` der Nachbar-Plugins (RC-022, RC-100–RC-104)

**Stand:** 2026-11-16 · **Geprüft:** jeweils nur `main` (Pass `b2b88ea`, Voucher `2d84943`, Form `6f4785f`, Affiliate `2a77ed3`, Workflow `081049c`) · **Geändert:** nichts in den Nachbar-Repos.
Bezug: [experience-ownership.md](experience-ownership.md), Pro `docs/SUITE.md` (Branch `claude/review-captain-pro`), Workflow `docs/SPEC-suite-events.md`.

## Ergebnis in einem Satz

Auf `main` passt nur **Form Captain** zu dem, was Review Captain erwartet. **Pass** und **Voucher** haben auf `main` keinen Review-Vertrag; die Pro-Adapter dafür gelten nur für noch nicht gemergte Branches (`claude/sharp-mendel-xiiwf3`). **Affiliate** ist korrekt nie Auslöser.

## Pass Captain (1.4.0, kein Vertragskonstante)

| Erwartet (Core/Pro) | `main` | Lücke |
|---|---|---|
| `WPCAP_VERSION ≥ 1.5.0`, `WPCAP_CONTRACT_VERSION ≥ 1.1.0` | 1.4.0, keine Konstante | Pro-Pass-Adapter bleibt inaktiv – korrekt |
| Check-in mit Ticket- **und** Termin-ID | `wpcap_ticket_checked_in( $ticket_id, $context )` (`src/Checkin.php:210`), keine `occurrence_id` | Core-ID `pass-captain:{site}:attendance:{ticket_id}:{occurrence_id}` nicht bildbar |
| Teilnahme bestätigt / Eventende / No-show | keine Hooks | **RC-100 offen** |
| Absage | nur `wpcap_occurrence_cancelled`; `wpcap_event_cancelled` wird nicht ausgelöst; `wpcap_order_cancelled` hat 1 statt 3 Argumente | Spec ≠ Code |
| Refund | `wpcap_ticket_revoked`, `wpcap_order_refunded`, `wpcap_refund_completed` vorhanden | nutzbar |
| Teilnehmer-/Käufer-Resolver | keiner; `wp_pass_captain_ticket_service_v1` ist schon für den Gutscheintausch vergeben | **RC-101 offen**; neuer Servicename nötig (Pro SUITE.md „Offen“ veraltet) |
| Transport | `wp_pass_captain_connectors_v1` existiert, `dispatch()` wird aber nie aufgerufen | kein Transport |

## Voucher Captain (1.1.0, keine Vertragskonstante)

| Erwartet | `main` | Lücke |
|---|---|---|
| `VCAP_VERSION ≥ 1.2.0` und `vcap_review_*`-Hooks (eligible, revoked, source_active, first_redemption_only, opted_out, recipient_*) | 1.1.0, keiner der Hooks vorhanden (Voucher `TODO.md:296-304` offen) | Pro-Voucher-Adapter inaktiv – korrekt; Pro SUITE.md „geliefert“ gilt nur für Branch/PR #16 |
| numerische `redemption_id` | nur `redemption_key` (String) im Payload | Core-ID-Format auf `redemption_key` umstellen **oder** Voucher liefert ID – Entscheidung nötig |
| Klassifizierung Leistung vs. Wert | fehlt | RC-098 offen |
| Checkout-Einlösung typisiert | nur generischer Ledger-Hook `vcap_voucher_event(…, 'redeemed', …)` | — |
| Payload ohne Geheimnisse | Payload enthält den Gutscheincode (`RedemptionService.php:713`) | Adapter darf `code` nie übernehmen/loggen |

## Form Captain (1.0.0-dev, `FORM_CAPTAIN_CONTRACT_VERSION = 1.0.0`)

Alles vorhanden, was Core/Pro voraussetzen: `form_captain_loaded_v1`, `form_captain_register_action_v1` (nur in/nach `loaded_v1`, Definition mit `label`, `contract`, optional `requires.entitlement`), `form_captain_connectors_v1`, `entry_created_v1`, `entry_status_changed_v1`, `entry_deleted_v1`, `consent_withdrawn_v1`. Abweichungen:

1. Entitlement-Filter heißt `form_captain_entitlement_granted` (ohne `_v1`).
2. Kein „Einsendung geändert“-Hook, nur Statuswechsel.
3. **Multisite:** Core-Idempotenz `form-captain:{site_id}:{entry_id}`, Pro nutzt `external_id = form-captain:{entry_id}` ohne Site → Pro an Core-Format angleichen (RC-104).

## Affiliate Captain (0.15.0, Vertrag 1.4.0)

`wpac_affiliate_attributed`, `wpac_commission_*`, Lese-API `wpac_get_commissions()` (ab 1.3.0) vorhanden; `wpac_get_order_attribution()` nur Vorschlag. Pro-Prüfung „≥ 1.3.0“ ist kompatibel; Pro SUITE.md nennt noch 0.14.0/1.3.0. Kein Auslöser (`CONTRACT.md:383`) – passt. **RC-102**: Grundlage vorhanden, keine Blocker.

## Abweichung Core ↔ Pro bei Quellschlüsseln

| Quelle | Core (experience-ownership.md) | Pro (SUITE.md) |
|---|---|---|
| Pass | `pass-captain:{site}:attendance:{ticket_id}:{occurrence_id}` | `pass:ticket:{ticket_id}` |
| Voucher | `voucher-captain:{site}:redemption:{redemption_id}` | `voucher:{reference}` |
| Form | `form-captain:{site_id}:{entry_id}` | `form-captain:{entry_id}` |

Folge: Ohne Angleichung greift die Dedupe-Ebene „Erlebnis“ (`experience_id` UNIQUE) nicht quellenübergreifend und Multisite kann kollidieren. **Empfehlung:** Pro übernimmt das Core-Format (Core ist laut ADR-0002 Vertragsbesitzer). Änderung im Pro-Branch nur nach Absprache mit der Pro-Sitzung.

## Nächste Schritte (Anfragen an die Nachbar-Sitzungen, nicht hier umsetzbar)

- Pass: Check-in-Payload um `occurrence_id` ergänzen; Hooks für „Teilnahme bestätigt“, Eventende, No-show; Resolver unter neuem Namen (z. B. `wp_pass_captain_attendee_service_v1`); `WPCAP_CONTRACT_VERSION`; Branch `claude/sharp-mendel-xiiwf3` mergen.
- Voucher: PR #16 mergen (1.2.0, `vcap_review_*`); stabile Einlöse-ID; Vertragskonstante.
- Pro: Quellschlüssel auf Core-Format; Form-Entitlement-Filtername; Affiliate-Versionsangabe aktualisieren.

## Entscheidung RC-022 (Inhaberin, 16.11.2026)

**Es gilt das Core-Format** aus [experience-ownership.md](experience-ownership.md) (mit Site-ID). Review Captain Pro übernimmt es für Pass, Voucher und Form (Umsetzung im Pro-Repository offen).
