# Testimonial-Schema v1 (RC-019)

| | |
|---|---|
| Status | Spezifikation, Entwurf zur Abnahme; Rechtetexte abhängig von RC-015 (Medienrechte-Prüfung) |
| Bezug | TODO RC-019, RC-053, RC-107–RC-110, RC-115; konzept.md 5 (Social-Proof-Wall), 9, 10 |
| Basis | [`review-schema-v1.md`](review-schema-v1.md) |

## Abgrenzung Core / Pro

| Ebene | Umfang | Speicherort |
|---|---|---|
| **Core** | Text-Testimonials (Name/Bezeichnung, Text, optional Rating, Kontext, Rechtebeleg, Ablauf, Widerruf, Moderation) | Tabelle `reviews` mit `kind = 'testimonial'`, Rechte in `meta.testimonial` |
| **Pro** | Bild-, Video-, Audio-Testimonials, Transkript/Untertitel, Poster, externe Einbettung (click-to-load), detaillierter Rechtebeleg je Rechteart | Pro-Tabellen `review_captain_testimonial_media` und `review_captain_testimonial_rights`, Verweis auf `reviews.id` |

Ein Testimonial ist **immer** ein Website-eigener Erfahrungsbericht: `platform = 'local'`, nie als Plattform-Review ausgegeben, nie mit Review-Schema-Markup ausgezeichnet. Der Datensatz bleibt in `reviews`, damit Moderation, Privacy-Export/-Eraser, Hooks und Widgets eine einzige Datenbasis haben. Pro-Tabellen erweitern nur.

## Core: Feldnutzung in `reviews`

| Feld | Regel für `kind=testimonial` |
|---|---|
| `kind` | `testimonial` |
| `origin` | `manual` (Admin-Erfassung), `local_submission` (Pro-Sammelformular, später), `connector` |
| `platform` | `local` (Pflicht) |
| `rating_value` | optional; wenn gesetzt, Skala 1–5 |
| `title` | optional, z. B. Überschrift |
| `body` | Testimonial-Text (Pflicht für `format=text`; bei Medien Kurzbeschreibung/Zitat) |
| `author_name` | Name oder Pseudonym, wie im Rechtebeleg freigegeben |
| `author_email` | nur `local_submission`; sonst NULL |
| `verification` | i. d. R. `unverified`; `purchase_verified`/`attendance_confirmed` nur über Quellvertrag |
| `status` | Moderationsstatus (`pending` \| `approved` \| `rejected` \| `archived` \| `flagged` \| `removed`) |
| `context_type` / `context_id` | Zuordnung zu Produkt, Kurs/Event, Gutschein, App, Seite |
| `language` | BCP-47 |
| `consent_publish` / `consent_at` | **Pflicht `1`** für Sichtbarkeit; Zeitpunkt der Freigabe |
| `meta.testimonial` | siehe unten |

### `meta.testimonial` (Core)

| Schlüssel | Typ | Pflicht | Bedeutung |
|---|---|---|---|
| `format` | `text` \| `image` \| `video` \| `audio` | ja | Core legt nur `text` an; andere Werte setzt Pro |
| `display_label` | string ≤ 191 | nein | Bezeichnung neben dem Namen („Teilnehmerin Kurs Mai 2026“) |
| `rights.name_use` | bool | ja | Name darf veröffentlicht werden; `false` → Ausgabe „Anonym“ bzw. Initialen gemäß Beleg |
| `rights.evidence_type` | `signed_form` \| `email_confirmation` \| `checkbox_submission` \| `contract` \| `other` | ja | Art des Rechtebelegs |
| `rights.evidence_ref` | string ≤ 191 | ja | interne Referenz auf den Beleg (z. B. Dokumentnummer, Attachment-ID eines **nicht öffentlichen** Belegs, Submission-UUID); kein Belegtext |
| `rights.granted_at` | ISO 8601 UTC | ja | Zeitpunkt der Einwilligung/Rechteeinräumung |
| `rights.expires_at` | ISO 8601 UTC \| null | ja | Ablauf; `null` = unbefristet (Produktentscheidung, ob unbefristet zulässig ist: RC-015) |
| `rights.revoked_at` | ISO 8601 UTC \| null | ja | Widerrufszeitpunkt |
| `rights.revocation_reason` | `subject_request` \| `admin` \| `expired_evidence` \| `other` \| null | nein | |
| `rights.recorded_by_user_id` | int | ja | WP-User, der den Beleg erfasst hat |
| `derived_from_review_uuid` | UUID \| null | nein | wenn aus einer lokalen Review umgewandelt; nur mit **gesonderter** Zustimmung des Urhebers (konzept 9) |

## Pro: Medien und Rechte

### Tabelle `{prefix}review_captain_testimonial_media` (Pro, Vorschlag)

| Spalte | Typ | Regel |
|---|---|---|
| `id` | BIGINT PK | |
| `uuid` | CHAR(36) UNIQUE | |
| `review_id` | BIGINT, Index | FK `reviews.id`, Datensatz mit `kind=testimonial` |
| `media_type` | `image` \| `video` \| `audio` \| `embed` | |
| `attachment_id` | BIGINT NULL | WordPress-Mediathek-Anhang (Upload) |
| `embed_provider` | VARCHAR(40) NULL | nur Werte der festen Host-Allowlist (TEC-013) |
| `embed_ref` | VARCHAR(191) NULL | Video-ID beim Embed-Provider, **keine** freie URL |
| `poster_attachment_id` | BIGINT NULL | Pflicht für `video` und `embed` |
| `captions_attachment_id` | BIGINT NULL | WebVTT; Pflicht für `video`, wenn gesprochener Inhalt |
| `transcript` | LONGTEXT NULL | Pflicht für `audio`; Pflicht für `video`, wenn keine Untertitel |
| `mime_type` | VARCHAR(100) | geprüft gegen Allowlist (RC-109) |
| `file_size` | BIGINT | Byte |
| `duration_seconds` | INT NULL | |
| `language` | VARCHAR(20) | BCP-47 der Tonspur/Untertitel |
| `sort_order` | INT | |
| `created_at`, `updated_at` | DATETIME UTC | |

Index: `(review_id, sort_order)`.

### Tabelle `{prefix}review_captain_testimonial_rights` (Pro, Vorschlag)

Je Testimonial und **Rechteart** eine Zeile; für Medien ersetzt sie `meta.testimonial.rights` als maßgebliche Quelle (Core-Rechte bleiben für `name` gespiegelt, damit Core-Sichtbarkeit auch ohne Pro funktioniert).

| Spalte | Typ | Regel |
|---|---|---|
| `id` | BIGINT PK | |
| `review_id` | BIGINT | FK `reviews.id` |
| `right_type` | `name` \| `image` \| `voice` \| `video` \| `text` | |
| `evidence_type` | wie Core | |
| `evidence_attachment_id` | BIGINT NULL | Beleg-Datei, **nicht** in öffentlich erreichbarem Upload-Pfad (Speicherort offen, RC-109) |
| `evidence_ref` | VARCHAR(191) | |
| `scope` | VARCHAR(191) | Verwendungszweck, v1 fest `website` |
| `granted_at` | DATETIME | |
| `expires_at` | DATETIME NULL | |
| `revoked_at` | DATETIME NULL | |
| `revocation_reason` | VARCHAR(40) NULL | |
| `recorded_by_user_id` | BIGINT | |
| `created_at`, `updated_at` | DATETIME UTC | |

Unique: `(review_id, right_type)`. Index: `(expires_at)`, `(revoked_at)`.

### Erforderliche Rechte je Format

| `format` | erforderliche `right_type` (alle gültig) |
|---|---|
| `text` | `text`, zusätzlich `name` wenn Name angezeigt wird |
| `image` | `text` (falls Text), `image`, `name` wenn angezeigt |
| `audio` | `voice`, `name` wenn angezeigt |
| `video` | `video`, `voice` (wenn Ton), `image`, `name` wenn angezeigt |

Core prüft für `text` vereinfacht über `meta.testimonial.rights` (eine Einwilligung deckt Text + Name gemäß `name_use`).

## Rechtebeleg, Ablauf, Widerruf

**Gültig** ist ein Recht, wenn `revoked_at IS NULL` und (`expires_at IS NULL` oder `expires_at > now`).

| Ereignis | Wirkung |
|---|---|
| Veröffentlichung (`pending → approved`) | nur möglich, wenn alle erforderlichen Rechte gültig und `consent_publish = 1`; sonst Fehler `rc.rights.missing` |
| Ablauf erreicht | täglicher Job `testimonial.rights_expiry` setzt `status = archived`, `review_events`-Eintrag `rights_expired`, Hook `review_captain_review_status_changed_v1`. Render-Zeit prüft zusätzlich (Defense in Depth). |
| Widerruf erfasst (Admin, Privacy-Anfrage) | sofort `status = archived`, `revoked_at` gesetzt, Caches der betroffenen Seiten invalidiert (TEC-012). Medien werden nicht mehr ausgeliefert (Pro: Anhang wird auf privat gestellt bzw. entfernt – Verfahren RC-109). |
| Widerruf + Löschwunsch | Privacy-Eraser: Tombstone (`status = removed`, Inhalt geleert, Medienanhänge gelöscht, Rechtezeilen bis auf Nachweis-Minimum `right_type`, `granted_at`, `revoked_at` anonymisiert). Aufbewahrung des Widerrufsnachweises: RC-014/RC-027. |
| Erneuerung | neue Rechtezeile/neues `granted_at`; Status bleibt `archived`, bis ein Moderator erneut freigibt |

Ablaufwarnung im Admin 30 Tage vor `expires_at` (Default, einstellbar).

## Moderation

Zustände identisch zu Reviews (`review-schema-v1.md`). Zusätzliche Regeln:

- Kein automatisches Veröffentlichen; Testimonials starten als `pending` (auch bei Admin-Erfassung Default `pending`, Admin kann direkt freigeben, wenn Rechte vollständig).
- Änderungen an Text oder Medien nach Freigabe setzen `status = pending`, außer reine Tippfehlerkorrektur mit protokolliertem Grund (`review_events`).
- Ausgabe kennzeichnet sichtbar „Erfahrungsbericht“ (Klasse `rc-testimonial`), getrennt von Plattform-Reviews.

## Beispiele

Core-Text-Testimonial:

```json
{
  "uuid": "a3d7b8c1-9e2f-4a6b-8c0d-1e2f3a4b5c6d",
  "kind": "testimonial",
  "origin": "manual",
  "platform": "local",
  "rating_value": null,
  "rating_scale_min": 1,
  "rating_scale_max": 5,
  "title": "",
  "body": "Der Workshop hat unser Team spürbar weitergebracht.",
  "language": "de-DE",
  "author_name": "Jana Beispiel",
  "author_email": null,
  "verification": "unverified",
  "status": "approved",
  "context_type": "event",
  "context_id": "pass-evt-2026-05",
  "consent_publish": 1,
  "consent_at": "2026-05-20T14:00:00Z",
  "meta": {
    "testimonial": {
      "format": "text",
      "display_label": "Teamleiterin, Workshop Mai 2026",
      "rights": {
        "name_use": true,
        "evidence_type": "signed_form",
        "evidence_ref": "RF-2026-0042",
        "granted_at": "2026-05-20T14:00:00Z",
        "expires_at": "2028-05-20T00:00:00Z",
        "revoked_at": null,
        "revocation_reason": null,
        "recorded_by_user_id": 3
      },
      "derived_from_review_uuid": null
    }
  }
}
```

Pro-Video-Testimonial (Medien- und Rechtezeilen):

```json
{
  "review_uuid": "c9e1f2a3-4b5c-4d6e-8f70-819203a4b5c6",
  "meta_testimonial_format": "video",
  "media": [
    {
      "uuid": "d1e2f3a4-b5c6-4d7e-9f80-91a2b3c4d5e6",
      "media_type": "video",
      "attachment_id": 812,
      "poster_attachment_id": 813,
      "captions_attachment_id": 814,
      "transcript": null,
      "mime_type": "video/mp4",
      "file_size": 18234112,
      "duration_seconds": 74,
      "language": "de-DE",
      "sort_order": 0
    }
  ],
  "rights": [
    { "right_type": "video", "evidence_type": "signed_form", "evidence_ref": "RF-2026-0051", "scope": "website", "granted_at": "2026-06-01T09:00:00Z", "expires_at": "2027-06-01T00:00:00Z", "revoked_at": null },
    { "right_type": "voice", "evidence_type": "signed_form", "evidence_ref": "RF-2026-0051", "scope": "website", "granted_at": "2026-06-01T09:00:00Z", "expires_at": "2027-06-01T00:00:00Z", "revoked_at": null },
    { "right_type": "image", "evidence_type": "signed_form", "evidence_ref": "RF-2026-0051", "scope": "website", "granted_at": "2026-06-01T09:00:00Z", "expires_at": "2027-06-01T00:00:00Z", "revoked_at": null },
    { "right_type": "name",  "evidence_type": "signed_form", "evidence_ref": "RF-2026-0051", "scope": "website", "granted_at": "2026-06-01T09:00:00Z", "expires_at": "2027-06-01T00:00:00Z", "revoked_at": null }
  ]
}
```

## Abnahme

- [ ] Typen Text/Bild/Video/Audio sind beschrieben; Core legt nur `format=text` in `reviews.kind=testimonial` an, Pro erweitert über Medientabelle.
- [ ] WordPress-Media-Anhang (`attachment_id`, Poster, Untertitel) und Transkript-/Untertitelpflicht je Format sind festgelegt.
- [ ] Kontext (`context_type`/`context_id`) funktioniert identisch zu Reviews.
- [ ] Rechtebeleg: Veröffentlichung ohne gültige Rechte schlägt mit `rc.rights.missing` fehl (Test).
- [ ] Ablauf: Job archiviert abgelaufene Testimonials; Render-Prüfung blendet auch ohne Job-Lauf aus (Test mit `expires_at` in der Vergangenheit).
- [ ] Widerruf: sofortige Ausblendung, Cache-Invalidierung, Medien nicht mehr ausgeliefert.
- [ ] Moderationsstatus: Änderung nach Freigabe führt zurück auf `pending`.
- [ ] Testimonials erzeugen nie Review-Schema-Markup und werden sichtbar als Erfahrungsbericht gekennzeichnet.
