# Entscheidungsvorlage: Autorenkorrektur freigegebener Reviews (RC-125)

| | |
|---|---|
| Status | **Entschieden** (Inhaberin, 16.11.2026) |
| Art | Spezifikation, **nicht implementiert** |
| Bezug | TODO RC-125; konzept.md §3 (Wettbewerbslücken), §7.3; [review-schema-v1.md](../contracts/review-schema-v1.md); [race-conditions.md](../contracts/race-conditions.md) R-12; [database.md](../architecture/database.md) §2.8 (`reviews.revision`) |
| Gilt für | nur **lokale** Reviews (`platform = local`, `origin` ∈ `local_submission`, `connector`). Externe Reviews werden ausschließlich bei der Plattform geändert und per Sync übernommen. |

## Frage

Sollen Autorinnen und Autoren ihre eigene, bereits **freigegebene** Bewertung nachträglich korrigieren können (Tippfehler, geänderte Erfahrung, Rücknahme), und wie verhindern wir dabei, dass ein freigegebener Text unbemerkt durch einen nicht geprüften ersetzt wird?

## Anforderungen (aus RC-125)

1. **Identitätsprüfung:** Nur die Person, die die Bewertung abgegeben hat, darf eine Korrektur einreichen.
2. **Versionierung:** Die freigegebene Fassung bleibt bis zur erneuten Freigabe öffentlich sichtbar; jede Fassung ist nachvollziehbar.
3. **Audit:** Wer hat wann was beantragt und freigegeben – ohne Texte im Audit-Log.
4. Keine unbemerkte Änderung: eine Korrektur wird **nie** automatisch veröffentlicht.

## Vorschlag

### 1. Identitätsprüfung

| Weg | Ablauf | Bewertung |
|---|---|---|
| A. Angemeldete Nutzer | `author_user_id` = aktuelle Nutzer-ID; Nonce + Capability `read` | einfach, aber nur für Websites mit Kundenkonten |
| B. Magic Link per E-Mail (**empfohlen** als Standard) | Formular „Bewertung korrigieren“ fragt nur die E-Mail-Adresse; existiert eine lokale Review mit dieser `author_email`, wird ein Einmal-Link versendet: Token 32 Byte zufällig, gespeichert nur als `hash_hmac('sha256', token, wp_salt('auth'))`, gültig 30 min, einmalig, gebunden an `review_uuid`. Antwort im Formular immer gleich („Falls eine Bewertung existiert, haben wir einen Link gesendet“) – keine Auskunft, ob eine Adresse bekannt ist. Rate-Limit je IP-Hash und je Adresse. | funktioniert ohne Konto; E-Mail-Versand nötig (Absender aus Einstellungen) |
| C. Bestellnummer/Ticket | Verifikation über Connector (Woo/Pass Captain) | nur Pro, später |

Nicht zulässig: Identifikation nur über Name, Cookie oder IP.

### 2. Versionierung

- Neue Tabelle `review_revisions` (Schema v3, Vorschlag): `id`, `review_id`, `revision` (= `reviews.revision` zum Zeitpunkt), `status` (`proposed` \| `approved` \| `rejected` \| `superseded`), `rating_value`, `title`, `body`, `created_at`, `decided_at`, `decided_by`, `reason`.
- Ablauf: Korrektur → Zeile `proposed`; das öffentliche Review bleibt unverändert. Moderation sieht einen Diff (alt/neu). Freigabe = in **einer** Transaktion: alte Fassung als `superseded` sichern, Inhalt per `ReviewRepository::update( $r, false, true )` (Optimistic Lock auf `revision`) übernehmen. Scheitert der CAS (zwischenzeitliche Änderung), bleibt der Vorschlag offen und wird neu angezeigt.
- Rating-Änderung gilt als inhaltliche Änderung: Karte zeigt „bearbeitet am …“ (Transparenz für Leser, UWG/Omnibus-Richtlinie: keine Irreführung über Bewertungen).
- Rücknahme durch die Autorin = Statuswechsel `archived` mit Grund `author_withdrawn` (sofort wirksam, kein Moderationsschritt nötig, Widerruf der Einwilligung).
- Aufbewahrung alter Fassungen: Vorschlag 90 Tage nach Ersetzung (Retention), Löschung mit Review/Eraser; Export enthält alle Fassungen.

### 3. Audit

`audit_log`-Ereignisse (Allowlist, nur IDs/Codes): `review.correction_requested` (`review_id`, Weg A/B/C), `review.correction_approved` / `review.correction_rejected` (`from`/`to`-Revision, `reason`), `review.withdrawn_by_author`. Keine Texte, keine E-Mail, keine Tokens. `review_events` erhält `edited` mit Feldnamen (bestehendes Muster `record_edit()`).

### 4. Events/Workflow

Freigegebene Korrektur löst `review_captain_review_status_changed_v1` **nicht** aus (Status unverändert); vorgeschlagen ist ein neues Ereignis `review_captain_review_corrected_v1` (Payload wie `review_captain.review.v1` + `revision`). Schema-Markup nutzt `dateModified` nicht (nicht Teil der Richtlinie); keine Änderung.

## Offene Entscheidungen des Inhabers

1. Standardweg B (Magic Link) aktivieren oder nur A (Konto)?
2. Sollen Korrekturen der Sterne erlaubt sein oder nur Text? (Vorschlag: erlaubt, sichtbar gekennzeichnet.)
3. Hinweis „bearbeitet“ öffentlich anzeigen? (Vorschlag: ja.)
4. Aufbewahrungsfrist alter Fassungen (Vorschlag 90 Tage).
5. Priorität: Core 1.x oder Pro?

## Aufwand (Schätzung)

Schema v3 + Migrator, Korrekturformular + Token-Versand, Moderationsansicht mit Diff, Retention/Export/Eraser-Erweiterung, Tests (inkl. Race „Korrekturfreigabe vs. Sync/Moderation“): ca. 6–8 Personentage.

## Entscheidung der Inhaberin (16.11.2026)

**Umsetzung in 1.0** (neue Aufgabe **RC-125A**) nach diesem Vorschlag: Identitätsprüfung per Einmal-Link (Weg B) und für angemeldete Nutzer (Weg A), Versionierung in `review_revisions` (Schema v4), Korrektur nie automatisch veröffentlicht, „bearbeitet am …“ sichtbar, Rücknahme sofort wirksam, Audit ohne Texte.

**Umsetzung (16.11.2026):** `src/Moderation/CorrectionService.php` (Links, Vorschlag, Freigabe mit Revisions-CAS, Ablehnung, Rücknahme, Aufbewahrung 90 Tage, Fassungen für Export), `CorrectionHandler.php` (admin-post `review_captain_correction`, Shortcode `[review_captain_correction]`, neutrale Antwort ohne Konten-Ermittlung, Rate-Limit), Admin-Seite „Korrekturen“ (`src/Admin/CorrectionsPage.php`), Schema v4 `review_revisions`, Karte „bearbeitet am …“, Einstellung `submissions.corrections_enabled` (Standard aus). Tests: `tests/Unit/Moderation/CorrectionTest.php` (10). Offen: Browser-Test des Ablaufs und E-Mail-Versand auf echter Seite; Ereignis `review_captain_review_corrected_v1` ist noch nicht eingeführt.
