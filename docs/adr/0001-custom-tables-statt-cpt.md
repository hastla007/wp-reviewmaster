# ADR-0001: Eigene Tabellen statt Custom Post Types für Reviews

- **Status:** Angenommen
- **Datum:** 2026-11-16
- **Autor:in / Entscheider:** Core-Maintainer Review Captain
- **Betrifft:** RC-018, RC-028, TEC-004, TEC-014, SEO-004; `docs/architecture/database.md`, `conventions.md`
- **Ersetzt:** –

## Kontext

Review Captain speichert lokale Reviews, Text-Testimonials und quellgebundene Snapshots externer Plattformen (Google, Trustpilot, eTrusted, App Store, Google Play). Anforderungen:

- Deduplizierung externer Reviews per Unique-Constraint auf Datenbankebene (TEC-004), auch bei parallelen Sync-Läufen.
- Mengen ≥ 100.000 Reviews pro Site mit gefilterter Adminsuche (TEC-014).
- Pflicht-Refresh/Tombstones je Quelle (Trustpilot ≤ 24 h), zeitgesteuerte Löschung.
- Externe Reviews dürfen keine eigenen öffentlichen URLs, Archive, Sitemaps oder Suchtreffer erzeugen (SEO, Plattformbedingungen).
- Hintergrundjobs mit atomarem Claim und Lease (TEC-003).

## Betrachtete Optionen

1. **Custom Post Type + Postmeta** – nutzt WP-Infrastruktur (Revisionen, REST, Rechte). Nachteile: keine Unique-Constraints über Meta, EAV-Joins für Filter (`rating`, `platform`, `status`) skalieren schlecht, CPT erzeugt standardmäßig Permalinks/Suchtreffer/Sitemap-Einträge, die aktiv unterdrückt werden müssten; Postmeta-Lösch-/Refreshjobs sind teuer; Jobs als Posts sind für Lease-Claims ungeeignet.
2. **Kommentare (`comment_type`)** – kollidiert mit WooCommerce-Produktreviews (System of Record bleibt WooCommerce, RC-017) und verfälscht Kommentarzähler.
3. **Eigene Tabellen mit `$wpdb->prefix`** – volle Kontrolle über Spalten, Indizes und Constraints; keine öffentlichen URLs; atomare UPDATE-Claims. Nachteile: eigene Migrationen, eigene Exporter/Eraser, eigene Listenansicht.

## Entscheidung

Option 3. Core nutzt die Tabellen `reviews`, `review_events`, `profiles`, `jobs`, `import_batches`, `audit_log` mit Präfix `{$wpdb->prefix}review_captain_` exakt wie in `conventions.md` beschrieben. Schemaänderungen ausschließlich über `ReviewCaptain\Install\Schema` (dbDelta) und `Migrator`. Es wird **kein** CPT für Reviews/Testimonials registriert. Medien (Bilder/Videos von Testimonials) bleiben WordPress-Anhänge in der Mediathek und werden per ID referenziert.

## Konsequenzen

- Positiv: DB-erzwungene Idempotenz (`dedupe_key`, `idempotency_key`), planbare Indizes, keine ungewollte Indexierung, Lease-Claims per Einzel-UPDATE.
- Negativ: eigene Adminliste (`WP_List_Table`), eigene Privacy-Exporter/-Eraser, eigene Migrationen und Multisite-Installation je Site; Backup-Plugins müssen die Tabellen einschließen (die meisten sichern alle Tabellen mit Präfix).
- Migration: Schema-Version in `review_captain_db_version`; siehe `docs/architecture/database.md`.
- Folgearbeiten: RC-028 Migrationsplan, TEC-014 Messung, WordPress Privacy Exporter/Eraser.

## Abnahme

- [ ] Aktivierung legt alle sechs Tabellen an; erneute Aktivierung ist idempotent (dbDelta ohne Diff).
- [ ] Zweifacher paralleler Insert derselben externen Review (gleiche `platform`, `profile_id`, `external_id`) ergibt genau eine Zeile.
- [ ] Es existiert kein öffentlicher Permalink, kein Sitemap-Eintrag und kein WP-Suchtreffer für Reviews.
- [ ] Privacy Exporter/Eraser decken `reviews.author_email` ab.

## Referenzen

- `konzept.md` Abschnitt 10 (Schema-Skizze), 7.9 (System of Record)
- `docs/architecture/database.md`
