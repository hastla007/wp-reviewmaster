# System-of-Record-Regeln (RC-017)

| | |
|---|---|
| Status | Spezifikation, Entwurf zur Abnahme |
| Bezug | TODO RC-017; konzept.md 7.1, 7.7, 7.9 „System of Record für Bewertungsdaten“ |
| Verbindliche Namen | `docs/architecture/conventions.md` |
| Stand | 2026-11-16 |

## Zweck

Jede Datenart hat genau **einen** Eigentümer (System of Record, SoR). Nur der SoR legt an, ändert und löscht. Alle anderen Plugins arbeiten mit stabilen Referenzen, lesen über öffentliche APIs oder halten einen klar gekennzeichneten, aktualisierbaren Snapshot. Kein Plugin liest oder schreibt Tabellen, interne Optionen oder private Klassen eines anderen Plugins.

## Begriffe

| Begriff | Bedeutung |
|---|---|
| **Eigentümer (SoR)** | Einzige Stelle, die den Datensatz anlegt, ändert und löscht und dessen ID vergibt. |
| **Snapshot** | Lokale, quellgebundene Kopie fremder Daten (z. B. externe Plattform-Review) mit `fetched_at`, `refresh_due_at` und Tombstone-Pfad. Snapshot ≠ Eigentum am Inhalt. |
| **Referenz** | Stabile ID auf ein fremdes Objekt (`experience_id`, `recipient_ref`, `object_ref`), ohne Inhalt. |
| **Lesende Einbindung** | Anzeige zur Laufzeit über die öffentliche API des Eigentümers, ohne Kopie in Review-Captain-Tabellen. |

## Eigentumsmatrix

| Datenart | System of Record | Rolle von Review Captain | Schreibweg | Lösch-/Widerrufsweg |
|---|---|---|---|---|
| WooCommerce-Produktreviews (Kommentare `comment_type=review`) | **WooCommerce** (WordPress-Kommentare) | nur lesende Einbindung über WP-/Woo-API; **keine Kopie** in `reviews`, keine Änderung der nativen Sternesumme/Anzahl | ausschließlich WooCommerce | ausschließlich WooCommerce |
| Bestellung, Position, Erstattung, Kunde | **WooCommerce** | Referenzen (`experience_id`, `recipient_ref`, `object_ref`) | WooCommerce | WooCommerce; Review Captain reagiert auf Refund/Storno-Ereignisse |
| Gutschein, Einlösung, Leistung, Käufer/Beschenkter | **Voucher Captain** | Referenzen; Experience-Ereignis nach Einlösung | Voucher Captain | Voucher Captain |
| Event, Termin, Ticket, Check-in, Teilnehmer | **Pass Captain** | Referenzen; Experience-Ereignis nach Teilnahme | Pass Captain | Pass Captain |
| Formular, Einsendung | **Form Captain** | übernimmt nur explizit gemappte Felder als **neuen** lokalen Review-Entwurf | Form Captain | Form Captain; Löschung der Einsendung löscht nur den noch **wartenden** verknüpften Review (konzept 7.3 E) |
| Partnerzuordnung, Provision | **Affiliate Captain** | optionale nicht personenbezogene Kampagnen-/Partnerreferenz in `context` | Affiliate Captain | Affiliate Captain |
| Lokale allgemeine Reviews (`kind=review`, `platform=local`) | **Review Captain** | Eigentümer | `reviews` | Moderation, Privacy-Eraser, Tombstone |
| Externe Plattform-Reviews (`origin=provider_sync`) | **Plattform** (Inhalt) / **Review Captain** (Snapshot-Datensatz) | Eigentümer des **Snapshots**, nicht des Inhalts | nur Sync (`provider-v1.md`) | Plattform löscht → Tombstone; Ablauf `refresh_due_at` → ausgeblendet; Disconnect → Aufbewahrungsregel |
| Manuell kuratierte externe Auszüge (`origin=manual`, `platform≠local`) | **Review Captain** (Datensatz), Plattform (Inhalt) | Eigentümer des Datensatzes; Betreiber verantwortet Nutzungsrecht | Admin | Admin, Privacy-Eraser |
| Testimonials (Text Core, Medien Pro) | **Review Captain** | Eigentümer inkl. Rechtebeleg | `reviews` (`kind=testimonial`), Pro-Medientabellen | Widerruf, Ablauf, Eraser (`testimonial-schema-v1.md`) |
| Widgets/Widget-Definitionen | **Review Captain** | Eigentümer | Shortcode/Block, Pro-Registry | Admin |
| Quellprofile, API-Credentials, Sync-Cursor | **Review Captain** (auf der Betreiber-Site) | Eigentümer | `profiles`, Pro-Sync-Status | Disconnect (Token-Widerruf soweit unterstützt) |
| Review-Anfragen, Kampagnen, Versandnachweise, Suppression | **Review Captain Pro** | Eigentümer | Pro-Tabellen (`request-lifecycle.md`) | Eraser, Aufbewahrungsjob |
| Kontaktangaben (E-Mail, Name) des Empfängers | **Quell-Plugin** | wird nur zur Versandzeit über den Resolver geholt, **nicht gespeichert** (nur `contact_hash`, siehe `recipient-resolver-v1.md`) | Quelle | Quelle; Review Captain löscht Referenzen per Eraser |
| Experience-ID | **Quell-Plugin, das die Leistung kontrolliert** | speichert die Referenz | Quelle (siehe `experience-ownership.md`) | — |
| Lizenzstatus | **WP License Captain / Pro-Lizenzclient** | Core kennt keinen Lizenzstatus | Pro | Pro |

## Regeln

1. **Keine Doppelführung.** Importierte Reviews werden nie als WooCommerce-Kommentar angelegt und fließen nie in `_wc_average_rating`/`_wc_review_count` oder vergleichbare native Kennzahlen ein (RC-042).
2. **Synchronisieren heißt nur importieren/aktualisieren/tombstonen.** Review Captain ändert nie den Ursprungseintrag auf der Plattform. Antwort- oder Löschaktionen auf der Plattform benötigen einen eigenen offiziellen API-Adapter, eine eigene Capability (`replies_write`, siehe `provider-v1.md`) und einen Audit-Eintrag; nicht Teil von v1.
3. **Lokale Moderation verfälscht keine Quelle.** Ausblenden (`archived`, `rejected`, `flagged`) ist erlaubt; Text, Rating, Skala, Datum, Autor und Quellenangabe einer externen Review werden nie editiert. Korrekturen an lokalen Reviews werden in `review_events` mit Grund protokolliert.
4. **Verifizierung nur durch den Eigentümer des Belegs.** `verification=purchase_verified` darf nur ein Quellvertrag setzen, der den Kauf besitzt (WooCommerce-Adapter); `attendance_confirmed` nur der Pass-Captain-Vertrag; `external_platform` nur `origin=provider_sync`. Formularwerte, CSV-Spalten oder Entry-Metadaten setzen nie eine Verifizierung (RC-041).
5. **Widgets kombinieren, kennzeichnen aber je Review die Quelle.** Ein plattformübergreifender Gesamtwert ist standardmäßig aus (RC-114).
6. **Schema-Markup** nur für `platform=local`, `kind=review`, `status=approved` (conventions.md). Externe Reviews und Testimonials erzeugen kein `Review`/`AggregateRating`-Markup.
7. **Lizenzablauf, Disconnect, Plugin-Deaktivierung** löschen nie stillschweigend Daten; Folgen siehe konzept.md 7.9 und `request-lifecycle.md`.
8. **Ownership-Übergabe innerhalb der Suite** (z. B. Woo-Bestellung mit Gutscheinprodukt): Die Leistung gehört dem Plugin, das sie kontrolliert. Details in `experience-ownership.md`.

## Beispiel: Kennzeichnung im Export

```json
{
  "uuid": "6f1c2a8e-3b7d-4f0a-9a51-0d2e4c9b7a11",
  "kind": "review",
  "origin": "provider_sync",
  "platform": "trustpilot",
  "profile_id": 3,
  "external_id": "ext-000123",
  "system_of_record": "platform",
  "snapshot": { "fetched_at": "2026-11-15T08:00:00Z", "refresh_due_at": "2026-11-16T08:00:00Z" }
}
```

`system_of_record` ist ein abgeleitetes Exportfeld (`platform` für `provider_sync`, sonst `review_captain`) und wird nicht gespeichert.

## Abnahme

- [ ] Für jede Datenart der Matrix ist genau ein SoR benannt; Review Captain besitzt lokale allgemeine Reviews, externe Review-Snapshots, Widgets, Testimonials und Anfragen; WooCommerce besitzt Woo-Produktreviews.
- [ ] Test: Import/Sync externer Reviews erzeugt **keinen** WordPress-Kommentar und verändert keine Woo-Rating-Metadaten.
- [ ] Test: Kein Review-Captain-Code liest fremde Plugin-Tabellen (statische Prüfung: keine SQL-Zugriffe außer auf `{$wpdb->prefix}review_captain_*` und WP-Core-APIs).
- [ ] Test: Moderationsaktionen an externen Reviews ändern weder `body`, `title`, `rating_value`, Skala noch `published_at`.
- [ ] Export kennzeichnet jede Review mit `platform`, `origin`, `external_id` und abgeleitetem `system_of_record`.
- [ ] Verifizierungsstatus kann nur über die in Regel 4 genannten Wege gesetzt werden (Unit-Test des Validators).
