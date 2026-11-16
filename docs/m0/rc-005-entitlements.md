# RC-005 – Lizenz-Entitlements

**Status:** Entwurf – Freigabe offen; abgeglichen mit dem gebauten Add-on (captain-pro, Branch `claude/review-captain-pro`)  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §6 Vorgeschlagene Entitlements, §7.4, §8.8](../../konzept.md), [RC-003](rc-003-free-pro-grenze.md)

## Abgleich mit dem gebauten Add-on (2026-11-16)

Gebaut sind sechs Rechte (Feature-Klassen mit Konstante `KEY`): `review.platform_sync`, `review.review_requests`, `review.advanced_widgets`, `review.analytics`, `review.suite_integrations`, `review.custom_connectors`. Alle stehen per `hold` in `addons/review-captain-pro/wplc-catalog.php` auf „gebaut, Freigabe des Inhabers ausstehend (RC-005)“; die Katalogdatei führt sie deshalb als `planned`, und `--release` lehnt ab.

Abweichung von diesem Entwurf: Testimonial-Wand, Trust-Badge und Auszeichnungen laufen im Add-on unter `review.advanced_widgets`, nicht unter einem eigenen Recht `review.social_proof`. **Entscheidung des Inhabers:** so lassen (einfacher, alles in 1.0) oder abtrennen (siehe ADR-Vorschlag unten). `review.platform_replies` ist nicht gebaut und steht nicht im Katalog – korrekt nach der Regel „nur gebaute Rechte“.

## Regel

Ein Recht kommt erst in den Lizenzkatalog, wenn die zugehörige Funktion **gebaut und in dem Release enthalten** ist. Jedes Recht deckt genau einen Pro-Funktionsbereich ab. Der Core prüft keine Rechte.

## Rechte nach Release

| Entitlement | Funktionsbereich | Ab Release | Katalog zu Pro 1.0? |
|---|---|---|---|
| `review.platform_sync` | API-Verbindungen, mehrere Profile, Delta-Sync, Review-Inbox, Sync-Diagnose | Pro 1.0 | ja |
| `review.review_requests` | Kampagnen, Versandqueue, WooCommerce-Anfrageadapter, Erinnerungen, Versandprotokoll | Pro 1.0 | ja |
| `review.advanced_widgets` | Karussell, Badge/Score, Trustbar/Bewertungsleiste, Produkt-/App-Widget, Design-Controls | Pro 1.0 | ja |
| `review.suite_integrations` | Voucher-, Pass-, Affiliate-, Form-Captain-Adapter; Workflow-Captain-Trigger/Actions | Pro 1.1 | nein |
| `review.custom_connectors` | Pro-Manager, automatisierte Runtime und Queue-Diagnose für kundenspezifische Connectoren | Pro 1.1 | nein |
| `review.social_proof` | **neu:** Testimonial-Studio (Bild/Video/Audio), Testimonial-Wall/Carousels, Trustmarks/Awards, Floating Badge | Pro 1.2 | nein |
| `review.analytics` | erweiterte Reports, geplante Exporte, Analytics-Seite | Pro-Folge | nein |
| `review.platform_replies` | Antworten über offizielle Plattform-APIs | nach MVP | nein |

## Änderungen gegenüber dem Konzept

1. **Neu `review.social_proof`.** Das Konzept beschreibt Social-Proof-Wall, Testimonial-Studio, Trustmarks und Floating Badge als Pro-Funktionen, nennt aber kein Recht dafür. Ohne eigenes Recht müssten sie unter `review.advanced_widgets` fallen, das bereits in 1.0 im Katalog steht – das würde ein noch nicht gebautes Feature mitverkaufen.
2. **Workflow Captain** (RC-WF-04 „Review-Pro-Recht“) wird `review.suite_integrations` zugeordnet, nicht einem eigenen Recht.
3. **Form-Captain-Brücke** nutzt wie im Konzept `review.suite_integrations`; Form Captain Core/API bleibt lizenzfrei.
4. **Diagnose ist nie gesperrt:** Lesender Zugriff auf Status und Fehler bleibt auch ohne Recht, nur Aktionen (Sync starten, Wiederholen) prüfen das Recht.

## Prüfpunkte im Code (Vorgabe für M3)

- Prüfung ausschließlich über den Captain-Pro-Lizenzclient mit signiertem Snapshot; keine Prüfung je Frontend-Request.
- Jede Pro-Aktion prüft genau ein Recht aus obiger Tabelle (zentrale Map `feature => entitlement` im Add-on).
- Fehlendes Recht → Aktion wird nicht ausgeführt, UI zeigt Grund und Lizenzseite; keine Daten werden verändert.

## Offen / Freigabe

- Produkt/Lizenz: neues Recht `review.social_proof` bestätigen.
- WP License Captain: Katalogeintrag zu Pro 1.0 nur mit den drei 1.0-Rechten anlegen (RC-058/RC-059).
