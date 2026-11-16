# Anfragen – RC-022, RC-024, RC-025

**Status:** Entwurf – RC-022 benötigt Bestätigung der Quellplugin-Maintainer  
**Stand:** 2026-11-16  
**Grundlage:** [Konzept §7.3, §7.9](../../konzept.md), [Verträge](vertraege.md)

## RC-022 – Experience-ID-Ownership

| Quelle | Besitzer der `experience_id` | Bildung | Auslösendes Ereignis | Widerrufende Ereignisse | Dedupe-Hinweise |
|---|---|---|---|---|---|
| WooCommerce (Kauf) | **Review-Captain-WooCommerce-Adapter** (WooCommerce selbst kennt keine Experience-ID) | `woocommerce:{blog_id}:order:{order_id}`; bei Mehrprodukt-Anfrage bleibt es **ein** Erlebnis je Bestellung | Bestellstatus laut Kampagne (Default `completed`) | `refunded`, `cancelled`, vollständige Erstattung; Teilerstattung entfernt nur betroffene Positionen | Wird die Bestellung über Voucher/Pass erfüllt, gewinnt das Erlebnis der Quelle mit tatsächlicher Leistung (siehe unten) |
| Voucher Captain (Einlösung) | **Voucher Captain** | `voucher-captain:{blog_id}:redemption:{redemption_id}` | Einlösung bzw. erbrachte Leistung (klassifizierte Produktart) | Einlösung storniert, Erstattung | Ausstellung/Kauf/Guthaben erzeugen **kein** Erlebnis |
| Pass Captain (Teilnahme) | **Pass Captain** | `pass-captain:{blog_id}:ticket:{ticket_id}` (je Teilnehmer) | gültiger Check-in; optional „Teilnahme bestätigt“ | Absage, Refund, No-show | Check-in + Eventende = dasselbe Erlebnis |
| Form Captain (Einsendung) | **Form Captain** | kein Erlebnis; Idempotency-Key `form-captain:{site_id}:{entry_id}` für Review-Entwurf | – | Löschen der Einsendung löscht wartenden Entwurf | erzeugt nie Einladungen |
| Kundenspezifischer Connector | Connector | `connector:{key}:{blog_id}:{object}` | laut Connector | laut Connector | wie Suite |

**Vorrangregel bei verketteten Quellen:** Ein WooCommerce-Kauf von Gutschein-/Ticketprodukten erzeugt keine eigene Einladung, wenn die Produktart als „Leistung über Voucher/Pass“ klassifiziert ist; die Einladung entsteht erst mit dem Erlebnis der Leistungsquelle. Der WooCommerce-Adapter überspringt diese Positionen (`reason=fulfilled_by_source`).

## RC-024 – Anfrage-Lifecycle

```
            ┌──────────── cancelled (Widerruf/Refund/Kampagne deaktiviert)
            │
planned ──▶ eligible ──▶ sending ──▶ sent ──▶ delivered | delivery_unknown
   │           │            │          │
   │           │            │          └──▶ clicked (nur falls datensparsam messbar & aktiviert)
   │           │            └──▶ failed (permanent) ─┐
   │           │            └──▶ eligible (Retry, temporär)
   │           └──▶ skipped (consent_missing, suppressed, recipient_unresolved, cooldown, daily_limit→eligible am Folgetag)
   ├──▶ paused (source_unavailable, license_inactive) ──▶ planned
   └──▶ expired (Kampagnenfrist überschritten)
sent/delivered ──▶ unsubscribed (Empfänger meldet sich ab; Status für Statistik, Suppression gesetzt)
```

| Zustand | Bedeutung | Endzustand |
|---|---|---|
| `planned` | Erlebnis angenommen, Versandzeitpunkt in der Zukunft | nein |
| `eligible` | Versandzeitpunkt erreicht, Prüfungen bestanden, wartet auf Zeitfenster/Limit | nein |
| `sending` | Job hat Lease; Versand läuft | nein |
| `sent` | Transport hat angenommen | nein (kann `delivered`, `unsubscribed` werden) |
| `delivered` / `delivery_unknown` | Zustellstatus, falls Transport ihn liefert | ja |
| `clicked` | optional | ja |
| `skipped` | absichtlich nicht gesendet, Grund Pflicht | ja |
| `cancelled` | durch Widerruf/Admin | ja |
| `failed` | endgültiger Fehler nach Retry-Politik (TEC-006) | ja (manuell „erneut versuchen“ → `eligible`, gleiche Zeile) |
| `paused` | Quelle/Lizenz fehlt | nein |
| `expired` | Frist (Default 60 Tage nach Erlebnis) überschritten | ja |
| `unsubscribed` | Abmeldung nach Versand | ja |

Prüfungen beim Übergang `planned → eligible` und **erneut** unmittelbar vor `sending`: Kampagne aktiv, Lizenzrecht, Erlebnis nicht widerrufen, Resolver ok, Versandgrundlage, Suppression, Cooldown.

## RC-025 – Einladungsregel technisch

1. **Harte Regel:** höchstens eine Einladung je `experience_id` + `recipient_ref` – erzwungen durch UNIQUE `invite_key = sha256(experience_id|recipient_ref)` in `rc_requests`. Die Kampagne wird als Spalte gespeichert, ist aber nicht Teil des Schlüssels.
2. Mehrere Kampagnen passen auf dasselbe Erlebnis → nur die Kampagne mit höchster Priorität (dann ältester ID) legt die Einladung an; die anderen erzeugen keinen Datensatz, sondern einen Zähler `campaign_conflict` in der Diagnose.
3. Konkurrierende Suite-Ereignisse (z. B. Woo `completed` und Voucher-Einlösung) mit derselben `experience_id` laufen über `INSERT … ON DUPLICATE KEY` ins Leere; unterschiedliche `experience_id` für denselben realen Vorgang werden durch die Vorrangregel (RC-022) verhindert.
4. **Cooldown** (`cooldown_days`) kampagnenübergreifend je Empfänger-E-Mail-Hash, geprüft zur Versandzeit.
5. **Erinnerung** (optional): gleiche Zeile, Feld `reminder_sent_at`; nie eine zweite Zeile.
6. Ereignis-Idempotenz: `rc_experiences.event_key = sha256(source|event_id)` UNIQUE.
7. Die Unique-Constraints sind die Wahrheit; Anwendungsprüfungen davor sind nur Optimierung (TEC-004/TEC-005).
