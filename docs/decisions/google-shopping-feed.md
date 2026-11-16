# Entscheidungsvorlage: Google-Shopping-Produktbewertungs-Feed (RC-127)

| | |
|---|---|
| Status | **Vorlage – Entscheidung des Inhabers offen** (Stand 2026-11-16) |
| Bezug | TODO RC-127; konzept.md §3 (Wettbewerbslücken: Shopping-Sterne), [seo/schema-eligibility.md](../seo/schema-eligibility.md) |
| Quellen | öffentliche Google-Hilfeseiten zum „Product Ratings“-Programm und zur „Product Reviews Feed“-Spezifikation (XML-Schema). **Alle Angaben ungeprüft gegen den aktuellen Stand** – Programmbedingungen ändern sich; vor einer Umsetzung muss die verantwortliche Person die aktuellen Seiten im Merchant Center prüfen und mit Datum dokumentieren. |

## Frage

Soll Review Captain einen Feed lokaler Produktbewertungen für das Google Merchant Center (Produktbewertungen/„Product Ratings“, Sterne in Shopping-Anzeigen und kostenlosen Einträgen) erzeugen und bewerben?

## Anforderungen von Google (nach öffentlicher Doku, ungeprüft)

| Bereich | Anforderung (Stand der Recherche, ungeprüft) | Folge für Review Captain |
|---|---|---|
| Teilnahme | Merchant-Center-Konto; Teilnahme am Produktbewertungsprogramm muss beantragt/aktiviert werden; Mindestmenge an Bewertungen (früher genannt: etwa 50 über alle Produkte) | Website-Betreiber muss selbst teilnehmen; wir können das nicht automatisieren |
| Feedformat | XML-Feed nach Googles „Product Reviews Feed“-Schema (Elemente u. a. `review_id`, `reviewer`/`name`, `review_timestamp`, `content`, `review_url`, `ratings/overall` mit `min`/`max`, `products/product` mit GTIN/MPN+Marke/SKU, optional `collection_method`, `is_spam`) | neuer Exporter, Produkt-IDs aus WooCommerce (GTIN/MPN/SKU) nötig |
| Produktzuordnung | Bewertung muss eindeutig einem Produkt (GTIN bevorzugt) zugeordnet sein | nur `context_type = product` mit Woo-Produkt; RC-123 (Mehrprodukt-Formular) hilft |
| Herkunft | Bewertungen müssen echte Kundenbewertungen sein; Herkunft/Erhebungsmethode (`collection_method`: z. B. unaufgefordert, nach Bestellung) muss korrekt angegeben werden; keine Bewertungen von Händlern selbst, keine gekauften/incentivierten ohne Kennzeichnung | nur `origin = local_submission`/`connector` mit `consent_publish`; nie `manual`/`csv_import` ohne Herkunftsnachweis; Pro-Einladungen = „post fulfillment“ |
| Inhalt | Richtlinien gegen Spam, Werbung, personenbezogene Daten, Off-Topic; negative Bewertungen dürfen nicht gefiltert werden (kein Review-Gating) | Feed enthält **alle** freigegebenen, nicht nur positive; abgelehnte werden als entfernt markiert bzw. weggelassen |
| Aktualität | regelmäßige Lieferung (vollständig oder inkrementell), Löschungen müssen übermittelt werden | Job `feed.product_reviews` (Retention/Eraser berücksichtigen) |
| Validierung | Google validiert Feeds beim Upload; Fehlerberichte im Merchant Center | Selbsttest gegen das XSD vor Auslieferung |
| Dritte | Alternativ über zertifizierte Bewertungs-Aggregatoren | Trustpilot/Trusted Shops liefern bereits selbst – keine Doppel-Lieferung |

## Bewertung

| Kriterium | Einschätzung |
|---|---|
| Nutzen | hoch für Woo-Shops mit eigenem Bewertungsbestand (Sterne in Shopping) |
| Rechtliches/Policy-Risiko | mittel: falsche `collection_method`, fehlende Einwilligung zur Weitergabe an Google, Gating → Kontosperre beim Händler |
| Datenschutz | Weitergabe von Text + Anzeigename an Google = Übermittlung an Dritte; Einwilligungstext im Formular müsste das abdecken (Änderung am Consent-Text, Bestandsbewertungen ohne diese Einwilligung **nicht** einbeziehen) |
| Technik | XML-Exporter + Woo-Produkt-IDs + Job + Validierung: ca. 5–7 Personentage; danach Pflege bei Schemaänderungen |
| Abhängigkeiten | WooCommerce (GTIN-Feld ist seit Woo 9.x Kernfeld), RC-123, Pro-Einladungen für Menge |
| Vermarktung | darf erst beworben werden, wenn ein Pilot-Händler die Freigabe im Merchant Center erhalten hat (TODO RC-127: „sonst wird das Feature nicht beworben“) |

## Empfehlung

1. **Nicht in Core 1.x.** Als Pro-Funktion „Google Shopping Feed“ einplanen, frühestens nach RC-123 und nach Pilotbedarf.
2. Vor der Umsetzung: aktuelle Google-Richtlinien und XSD prüfen und datiert in diesem Dokument festhalten; Consent-Text um „Weitergabe an Google Shopping“ ergänzen (nur neue Bewertungen).
3. Feed enthält ausschließlich lokale, freigegebene Produktbewertungen mit Einwilligung zur Weitergabe, korrekter `collection_method` und Woo-Produktkennung; keine Gating-Filter.
4. Abnahme: ein Pilot-Merchant-Center mit freigegebenem Feed, Validierungsbericht ohne Fehler, Löschung/Eraser-Weitergabe getestet.

## Offene Entscheidungen des Inhabers

- Pro oder eigenes Add-on? Preisstufe?
- Pilot-Händler und verantwortliche Person für die Richtlinienprüfung.
- Akzeptieren wir die Consent-Text-Änderung im Core-Formular (Opt-in-Checkbox „auch an Google übermitteln“)?
