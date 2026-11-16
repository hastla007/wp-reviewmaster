# RC-017 – System-of-Record-Regeln

**Status:** Entwurf – Bestätigung durch Maintainer der Quellplugins offen  
**Stand:** 2026-11-16  
**Grundlage:** [Konzept §7.1, §7.7, §7.9](../../konzept.md)

| Datenobjekt | System of Record | Review Captain darf | Review Captain darf nicht |
|---|---|---|---|
| WooCommerce-Produktbewertungen (Kommentare `review`, Meta `rating`, `verified`) | **WooCommerce** | lesend über WP-/Woo-APIs anzeigen | anlegen, ändern, löschen; externe Reviews als Woo-Kommentar speichern; Woo-Durchschnitt/Anzahl beeinflussen |
| Bestellung, Positionen, Erstattung | WooCommerce | Ereignisse empfangen, Bestell-ID als Referenz speichern | Bestelldaten kopieren (außer Referenz, Produkt-IDs, Locale) |
| Gutschein, Einlösung | Voucher Captain | Ereignis + Referenz | Code, Wert, Guthaben speichern |
| Ticket, Check-in, Event | Pass Captain | Ereignis + Referenz | Ticketcode, Check-in-Beleg speichern |
| Partnerzuordnung, Provision | Affiliate Captain | Kampagnen-/Partnerreferenz für Aggregate | Provisionen, Kundenprofile |
| Formular, Einsendung | Form Captain | explizit gemappte Felder als lokalen Review-Entwurf übernehmen | rohe Einsendung, IP, ungemappte Felder |
| Kontakt (E-Mail, Name) | jeweilige Quelle | zur Versandzeit über Resolver abfragen | dauerhaft speichern (Ausnahme: gehashte Suppression) |
| Lokale allgemeine Reviews | **Review Captain** | vollständig verwalten | – |
| Manuell erfasste externe Reviews | **Review Captain** (Snapshot mit Quellenangabe) | verwalten, Text unverändert | Text inhaltlich verändern |
| Synchronisierte externe Reviews | **Plattform** (Original); Review Captain hält Snapshot | importieren, aktualisieren, tombstonen | Original auf der Plattform ändern (außer offizieller Antwort-API mit Audit) |
| Widgets, Testimonials, Trustmarks | Review Captain | vollständig | – |
| Review-Anfragen, Kampagnen, Versandprotokoll, Suppression | Review Captain (Pro) | vollständig | – |
| Lizenzstatus | WP License Captain / Captain-Pro-Client | lesen (Pro) | Review-/Kundendaten senden |

Konfliktregel: Liegt ein Objekt bei einer Quelle, gewinnt deren Zustand. Review Captain speichert nur eine Referenz (`source` + `object_id`) und, falls nötig, einen minimalen Snapshot zur Anzeige.
