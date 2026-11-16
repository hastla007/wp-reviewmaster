# Migration zu Review Captain (RC-046)

**Stand:** 2026-11-16 · gilt für Review Captain Core 0.1+  
**Werkzeug:** Review Captain → Import & Export (CSV mit Prüflauf, Duplikaterkennung und „Import rückgängig machen“)

## Grundregeln

1. **Erst prüfen, dann importieren.** Jede Datei durchläuft einen Prüflauf; gespeichert wird erst nach Bestätigung.
2. **Herkunft bleibt erhalten.** Bewertungen von anderen Plattformen werden mit `provider`, Originalskala, Datum und Link importiert und sind immer als externe Bewertung gekennzeichnet.
3. **Nichts wird verifiziert.** Importierte Bewertungen erhalten nie „Kauf verifiziert“ oder „Teilnahme bestätigt“ – auch wenn das Quellsystem das kannte. Verifizierung setzt nur ein offizieller Quellvertrag (RC-041).
4. **WooCommerce bleibt unberührt.** Native WooCommerce-Produktbewertungen werden nicht verschoben, kopiert oder in Woo-Kommentare zurückgeschrieben (RC-042). Ein Import ist nur nötig, wenn Sie Woo-Bewertungen zusätzlich in Review-Captain-Widgets zeigen wollen.
5. **Rückgängig machen** ist bis zu 90 Tage nach dem Import möglich (Import-Batch).

## CSV-Format

UTF-8, Trennzeichen Komma oder Semikolon (wird erkannt), erste Zeile = Spaltennamen. Maximal 5.000 Zeilen und 10 MB pro Datei.

| Spalte | Pflicht | Inhalt |
|---|---|---|
| `provider` | ja | `site` (auf Ihrer Website abgegeben) oder `google`, `trustpilot`, `etrusted`, `apple_app_store`, `google_play`, `other` |
| `external_id` | empfohlen bei Plattformen | ID der Bewertung beim Anbieter – verhindert doppelte Importe |
| `rating_value` | ja, wenn kein Text | Originalbewertung |
| `rating_min` / `rating_max` | nein | Skala, Standard 1/5 |
| `title`, `content` | einer davon oder Rating | Text ohne HTML (HTML wird entfernt) |
| `author_name` | nein | öffentlicher Anzeigename |
| `language` | nein | z. B. `de-AT` |
| `type` | nein | `service`, `product`, `app`, `event`, `voucher`, `general` |
| `context_type`, `context_id` | nein | z. B. `product` / `123` (Woo-Produkt-ID) |
| `source_url` | **ja bei Plattformen** | https-Link zur Bewertung oder zum Profil |
| `attribution` | empfohlen | Quellenhinweis, z. B. „Bewertung auf Google“ |
| `status` | nein | `approved` veröffentlicht sofort, sonst „ausstehend“ |
| `published_at` | empfohlen | Datum der Bewertung (ISO 8601) |

Duplikate werden erkannt über `provider` + `external_id` bzw. – ohne ID – über Name + Datum + Text innerhalb der Datei.

## Aus WooCommerce-Produktbewertungen

Nur sinnvoll, wenn Woo-Bewertungen zusätzlich in Review-Captain-Widgets erscheinen sollen. Export z. B. per WP-CLI:

```bash
wp comment list --type=review --status=approve --fields=comment_ID,comment_post_ID,comment_author,comment_date_gmt,comment_content --format=csv > woo-reviews.csv
```

Mapping: `comment_ID` → `external_id` (Präfix `woo-` empfehlen), `comment_post_ID` → `context_id` mit `context_type=product`, `comment_author` → `author_name`, `comment_date_gmt` → `published_at`, `comment_content` → `content`, Bewertung aus Kommentar-Meta `rating` → `rating_value`. `provider` = `site`, `type` = `product`.

**Grenzen:** Der Status „verifizierter Käufer“ wird nicht übernommen. Bilder in Bewertungen werden nicht importiert. Die Original-Woo-Bewertungen bleiben bestehen; um doppelte Anzeige zu vermeiden, Woo-Bewertungs-Tab oder Review-Captain-Widget auf der Produktseite wählen.

## Aus anderen lokalen Review-Plugins

Die meisten lokalen Review-Plugins speichern Bewertungen als eigenen Beitragstyp oder als Kommentare und bieten einen CSV-Export. Typisches Mapping:

| Quellfeld (typisch) | Review-Captain-Spalte | Hinweis |
|---|---|---|
| Bewertung/Rating (1–5) | `rating_value` | andere Skalen über `rating_max` angeben |
| Titel | `title` | |
| Inhalt/Text | `content` | HTML wird entfernt |
| Name des Autors | `author_name` | E-Mail-Adressen **nicht** importieren |
| Datum | `published_at` | |
| zugeordnete Seite/Produkt | `context_type` + `context_id` | |
| Status freigegeben | `status=approved` | sonst Moderationsqueue |
| Plattform (bei aggregierten Reviews) | `provider`, `source_url`, `attribution` | ohne Link kein Import externer Reviews |

**Grenzen:** Antworten des Betreibers, Upvotes, benutzerdefinierte Felder, Anhänge/Bilder und Verifizierungs-Flags werden nicht übernommen. E-Mail-Adressen der Autoren werden bewusst nicht importiert (Datenminimierung); Datenschutzauskünfte für importierte Bewertungen sind daher nur über den Namen manuell möglich.

## Nach dem Import

1. Reviews → Filter „Import“ bzw. Batch-Link prüfen.
2. Widgets anlegen und Darstellung prüfen.
3. Altes Plugin deaktivieren, sobald die Anzeige stimmt; Daten des alten Plugins erst nach Ablauf der Rückgängig-Frist löschen.
