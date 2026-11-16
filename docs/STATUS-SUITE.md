# Suite-Status aus Sicht von Review Captain

**Stand:** 2026-11-16 · **Basis:** Branch `claude/plugin-todo-abarbeiten-xzv9c8` (= Core `claude/sleepy-gates-6mm217` + Ergänzungen dieser Sitzung). Andere Repos wurden nur gelesen, nicht geändert.

## Überblick

| Repo / Produkt | Was gebaut ist | Tests | Auf `main`? | Was fehlt |
|---|---|---|---|---|
| **wp-reviewmaster** – Review Captain Core | Plugin vollständig bis M1.5/M7: Schema v3, Job-Runner, Moderation, Import/Export, Block/Shortcode, SEO/Yoast, Einstellungen, Privacy, Connector-Registry, Workflow-Provider | 397 Unit-Tests (SQLite) grün; Race- und Upgrade-Test auf MariaDB 10.11 grün | **nein** – liegt auf dem Arbeitsbranch | Release-Gates RC-131…RC-143, Sicherheitsfunde SR-04…SR-13, Übersetzungen, erster CI-Lauf mit PHP 8.5 / MariaDB 12.3 / MySQL 9.7 |
| **captain-pro** – Review Captain Pro (`addons/review-captain-pro`) | Add-on mit Lizenzclient, Entitlements `review.*`, Plattformadaptern, Suite-Adaptern (Woo, Voucher, Pass, Form, Affiliate) | 801 Tests grün (mit Core-Checkout) | **nein** – Branch `claude/review-captain-pro` | Preise (RC-004), Entitlements stehen auf `hold` (RC-005) → Release gesperrt; kein Security-Review; Plattformdaten UNVERIFIED (keine Accounts); Quellschlüssel weichen vom Core ab; `Requires PHP` muss auf 8.5 (MINVER-1) |
| **wp-license** – WP License Captain | Lizenzserver, noch als Vorschau | – | Vorschau | produktiver Betrieb, echter Lizenzlauf mit Pro |
| **wp-passmaster** – Pass Captain 1.4.0 | Tickets, Check-in, Refunds | – | ja | Review-Vertrag (Termin-ID im Check-in, Teilnahme/No-show/Eventende, Resolver, Vertragskonstante) nur auf Branch `claude/sharp-mendel-xiiwf3` bzw. gar nicht |
| **wp-couponmaster** – Voucher Captain 1.1.0 | Gutscheine, Einlösung, Refund | – | ja | `vcap_review_*`-Hooks (PR #16, 1.2.0) nicht gemergt; stabile Einlöse-ID; Leistung-vs.-Wert-Klassifizierung |
| **wp-formmaster** – Form Captain 1.0.0-dev | Formulare inkl. `form_captain_register_action_v1` und Entry-Ereignissen v1 | – | ja | nichts Blockierendes für Review Captain |
| **wpaffiliatemaster** – Affiliate Captain 0.15.0 | Vertrag 1.4.0, Provisionsereignisse, Lese-API | – | ja | `wpac_get_order_attribution()` nur Vorschlag (nicht blockierend) |
| **wp-workflowmaster** – Workflow Captain | Suite-Event-Spezifikation (Entwurf) | – | ja (Spec) | Spec auf `main`-Stand der Nachbarn ziehen |
| **wp-captainplugins** | Sammel-/Vertriebsrepo | – | – | Review Pro ohne Preis im Katalog |

Details zu den Verträgen: [contracts/suite-abgleich-2026-11-16.md](contracts/suite-abgleich-2026-11-16.md).

## Fortschritt Core (TODO.md)

- 216 von 251 Punkten erledigt, 35 offen (≈ 86 %, Stand 16.11.2026 abends; 3 neue Punkte aus Entscheidungen: RC-026A, RC-119A, RC-125A). Offen sind überwiegend Freigaben/Entscheidungen der Inhaberin bzw. von Shop/Support (M0-Entwürfe RC-001…RC-016, ADR 0008–0010 „Vorgeschlagen“), Suite-Abhängigkeiten (RC-095…RC-104) und die Release-Phase (RC-131…RC-143, SEO-016/017).

## Diese Sitzung (16.11.2026)

- Kollegen-Core `sleepy-gates` als Basis übernommen, eigener Doppel-Core verworfen; M0-Entwürfe, Glossar, ADR-Vorschläge 0008–0010 portiert.
- TEC-014: Messung auf MariaDB, Schema v3 mit `sorted_at` + Index und Keyset-Export.
- TEC-005: Race-Tests mit parallelen Prozessen; Fix für verlorene Insert-Rennen.
- MINVER-1: PHP 8.5 / MySQL 9.7 / MariaDB 12.3 im Code und in der CI (lokal nicht testbar).
- Suite-Vertragsabgleich gegen `main` der Nachbar-Plugins.

## Entscheidungen / Freigaben, die blockieren

1. Merge von `claude/plugin-todo-abarbeiten-xzv9c8` nach `main` (Core) und `claude/review-captain-pro` (Pro) – kein PR ohne Freigabe.
2. Preise und Entitlement-Freigabe Review Captain Pro (RC-004/RC-005).
3. ADR 0008 (Auth-Modell), 0009 (Connector-Lizenzgrenze), 0010 (`sorted_at`).
4. Quellschlüssel Core ↔ Pro vereinheitlichen (Empfehlung: Core-Format).
5. Merge der Review-Verträge in Pass (`claude/sharp-mendel-xiiwf3`) und Voucher (PR #16).
