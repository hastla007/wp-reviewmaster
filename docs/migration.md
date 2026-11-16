# Migration und Import (RC-046)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Bezug: RC-042 (Woo getrennt halten), RC-044/RC-045 (CSV-Export/-Import), conventions.md (Tabellen `reviews`, `import_batches`).

## 1. WooCommerce-Produktreviews

### Entscheidung (Vorschlag)

- Woo-Produktreviews bleiben in WooCommerce (System of Record = Woo). Review Captain **liest** sie nur und kann sie in eigenen Widgets **anzeigen** (Option am Widget: Quelle „WooCommerce-Produktreviews“).
- **Kein Duplizieren in Woo:** Review Captain legt nie Kommentare vom Typ `review` an und verändert keine Woo-Sterne (`_wc_average_rating`, `_wc_rating_count`).
- **Kein Kopieren nach Review Captain in 1.0:** keine einmalige Migration in `reviews`, um Doppelhaltung, doppelte Moderation und doppeltes Schema-Markup zu vermeiden. (OFFEN: Bedarf für eine echte Umzugsfunktion, falls Betreiber Woo-Reviews abschalten wollen.)
- **Kein Schema-Markup** von Review Captain für Woo-Reviews; Woo bzw. das SEO-Plugin bleibt dafür zuständig.
- Lesen nur, wenn WooCommerce aktiv ist; sonst ist die Quelle im Widget nicht wählbar. Kein Pflicht-Dependency.

### Feldmapping (Leseadapter → normalisierte Anzeige, nicht gespeichert)

| WooCommerce (Quelle) | Review-Captain-Feld (Anzeige-DTO) | Regel |
|---|---|---|
| `wp_comments.comment_type = 'review'` | `kind = review` | nur dieser Typ; normale Kommentare/Antworten (`comment_parent ≠ 0`) ausgeschlossen |
| `comment_ID` | `external_id` = `woo:{comment_ID}` | nur zur Referenz/Link |
| – | `platform = local`, `origin = connector` (virtuell) | Anzeige-Label „Produktbewertung im Shop“ |
| `comment_post_ID` (Post-Typ `product`) | `context_type = product`, `context_id` | Produkt-Filter im Widget |
| Comment-Meta `rating` (1–5) | `rating_value`, `rating_scale_max = 5` | fehlt/0 → ohne Sterne anzeigen |
| Comment-Meta `verified` (`1`) | `verification = purchase_verified` | von Woo gesetzt; Review Captain setzt es nie selbst |
| `comment_content` | `body` | Ausgabe escaped (`wp_kses_post` → Text), keine Shortcodes |
| `comment_author` | `author_name` | `display.show_author` beachten |
| `comment_author_email` | – | **nie** gelesen/ausgegeben |
| `comment_date_gmt` | `published_at` | UTC |
| `comment_approved = '1'` | `status = approved` | **nur** freigegebene werden angezeigt; `0`, `spam`, `trash` nie |
| Permalink Produkt + `#comment-{ID}` | `source_url` | Link „im Shop ansehen“ |
| Anhänge/Bilder (Plugin-spezifisch) | – | nicht unterstützt |

- Abfrage über `get_comments()` mit `type => 'review'`, `status => 'approve'`, paginiert, per Transient/Objekt-Cache zwischengespeichert, invalidiert bei `comment_post`/`edit_comment`/`wp_set_comment_status`.
- Moderation bleibt in Woo (Produkte › Bewertungen); Review Captain zeigt dafür einen Link statt eigener Aktionen.
- HPOS ist nicht betroffen (Reviews sind Kommentare, keine Bestelldaten).

## 2. Andere lokale Review-Systeme

Kein spezifischer Importer in 1.0. Weg: Export aus dem Fremdsystem → Umwandlung in das CSV-Format aus §3 → Import mit Prüflauf. Für häufige Fremdformate können später Vorlagen/Mapping-Profile folgen (OFFEN, nach Pilotbedarf).

## 3. CSV-Format des Core-Imports

### Datei

- UTF-8 (BOM erlaubt und wird entfernt), Trennzeichen `,` (Semikolon wird automatisch erkannt, wenn die Kopfzeile damit getrennt ist), Feldbegrenzer `"`, Zeilenende LF oder CRLF.
- Erste Zeile = Kopfzeile mit exakt den Spaltennamen unten (Reihenfolge beliebig, unbekannte Spalten → Warnung, ignoriert).
- Grenzen (Vorschlag): max. 5 MB und 10 000 Datenzeilen je Datei; Verarbeitung in Jobs zu je 200 Zeilen innerhalb `advanced.job_time_budget`.
- Upload nur mit `review_captain_moderate` **und** `review_captain_manage_privacy` (Vorschlag, da personenbezogene Massendaten) + Nonce; Datei wird nach Abschluss/Abbruch gelöscht.

### Spalten

| Spalte | Pflicht | Format / erlaubte Werte | Regel |
|---|---|---|---|
| `kind` | ja | `review` \| `testimonial` | |
| `platform` | ja | `local` \| `google` \| `trustpilot` \| `trusted_shops` \| `app_store` \| `google_play` \| `other` | `testimonial` nur mit `local` |
| `external_id` | bei `platform ≠ local` empfohlen | max. 191 Zeichen | Dedupe-Schlüssel (siehe unten) |
| `rating_value` | bei `kind=review` ja | Dezimal mit `.` oder `,` | `rating_scale_min (1) ≤ Wert ≤ rating_scale_max` |
| `rating_scale_max` | nein | Dezimal, Default `5` | > 1 |
| `title` | nein | max. 255 | Text, HTML wird entfernt |
| `body` | ja | Text | HTML wird entfernt (`wp_strip_all_tags`), Zeilenumbrüche bleiben |
| `language` | nein | BCP-47/Locale, max. 20 (`de`, `de_DE`) | |
| `author_name` | ja | max. 191 | |
| `published_at` | ja | ISO 8601 (`2026-11-14` oder `2026-11-14T10:00:00+01:00`) | ohne Zone = Website-Zeitzone; in UTC gespeichert; nicht in der Zukunft |
| `source_url` | bei `platform ≠ local` ja | `https://…` | Pflicht-Quellenlink für externe Reviews |
| `verification` | nein | `unverified` \| `external_platform` | Default: `local` → `unverified`, extern → `external_platform`. `purchase_verified`/`attendance_confirmed` sind **nicht** importierbar (nur Quellenvertrag darf verifizieren) |
| `status` | nein | `pending` \| `approved` \| `rejected` \| `archived` | Default `pending`; `flagged`/`removed` nicht importierbar |
| `context_type` | nein | `post` \| `product` \| `event` \| `voucher` \| `app` \| `form` | |
| `context_id` | mit `context_type` | max. 64 | Existenz wird bei `post`/`product` geprüft (Warnung, kein Fehler) |

Bewusst **keine** Spalte für `author_email`, Avatar, Anhänge oder Antworten: keine Kontaktdaten per Massenimport, keine Medien in 1.0.
Gespeichert wird `origin = csv_import`, `import_batch_id`, `consent_publish = 0` (Hinweis im Prüflauf: „Für importierte Reviews liegt keine im Plugin dokumentierte Einwilligung vor“).

### Prüflauf (Dry-run)

Pflichtschritt vor jedem Import. Ergebnis je Zeile: `ok` · `Warnung` · `Fehler` · `vermutetes Duplikat`.

- **Duplikat sicher:** gleicher `dedupe_key` = `sha1(platform|profile_id|external_id)` (CSV: `profile_id` leer) existiert → Zeile wird übersprungen (keine Aktualisierung in 1.0).
- **Duplikat vermutet** (ohne `external_id`): gleicher `platform` + `author_name` + `published_at` (Tag) + Hash der ersten 200 Zeichen `body` → Standard „überspringen“, pro Zeile umschaltbar.
- Ausgabe: Zusammenfassung + herunterladbare Fehlerliste (CSV mit Zeilennummer und Grund, mit Formel-Injection-Schutz).

### Rollback per `import_batch`

- Jeder Import erzeugt einen `import_batches`-Eintrag (Dateiname gekürzt, Zeilenzahlen, Nutzer, Zeit, Status `dry_run|running|completed|rolled_back|failed`).
- „Import rückgängig machen“ (Reviews › Importe) löscht alle Reviews mit dieser `import_batch_id` samt `review_events`.
- Reviews, die seit dem Import moderiert/bearbeitet wurden, werden vorher aufgelistet und benötigen eine zusätzliche Bestätigung; Widgets mit handverlesener Auswahl verlieren diese Einträge (Hinweis).
- Rollback ist ein Job (zeitbudgetiert), idempotent, und erzeugt Audit-Einträge `import.completed` / `import.rolled_back`.
- Abgebrochener Import (`failed`) kann fortgesetzt oder vollständig zurückgerollt werden.

## 4. Grenzen

- Keine Übernahme von Anhängen, Bildern, Videos, Antworten, Hilfreich-Stimmen.
- Keine Übernahme von Kauf-/Teilnahmeverifizierung aus CSV.
- Keine Aktualisierung bestehender Reviews per CSV (nur Neuanlage) in 1.0.
- Importierte externe Reviews sind **manuelle Auszüge**: sie werden nicht automatisch aktualisiert und unterliegen nicht `retention_external_days` (gilt nur für `provider_sync`/`connector`); Plattformbedingungen zur Weiterverwendung von Texten muss der Betreiber selbst prüfen.
- Kein Schema-Markup für importierte externe Reviews.
- Export (RC-044) nutzt dieselben Spaltennamen plus `uuid`, `origin`, `created_at`; Zellen, die mit `= + - @ Tab CR` beginnen, werden mit `'` präfixiert.

## Abnahme

Entspricht RC-046, wenn:

- [ ] das Feldmapping WooCommerce → Anzeige, die Statusübernahme (nur freigegebene), die Behandlung von Anhängen (nicht unterstützt) und die Grenzen dokumentiert sind;
- [ ] feststeht, dass nichts in Woo dupliziert und keine Woo-Sterne verändert werden (Test zu RC-042);
- [ ] das CSV-Format mit allen Spalten, Pflichtfeldern, Validierung, Dedupe-Regel und Rollback per `import_batch` beschrieben und mit der Implementierung von RC-045 abgeglichen ist;
- [ ] die offene Entscheidung „echter Woo-Umzug ja/nein“ getroffen ist.
