# Cache- und CDN-Verhalten (TEC-012)

Status: Spezifikation, Stand 2026-11-16. Grundlage: [retention-matrix.md](retention-matrix.md) (Pflicht-Refresh, Tombstones), `konzept.md` 9 („zuletzt zulässiger Cache bleibt bis zur erlaubten Aktualisierung sichtbar“), 11.

## 1. Cache-Ebenen

| Ebene | Wer steuert | Inhalt | Regel |
|---|---|---|---|
| Provider-Snapshot | Review Captain (`reviews`-Tabelle) | externe Reviews | Refresh/Anzeigegrenze je Quelle (Trustpilot ≤ 24 h) |
| Fragment-Cache (Widget-HTML) | Review Captain, Objektcache/Transients | gerendertes HTML eines Widgets | TTL = min(nächste Anzeigegrenze aller enthaltenen Reviews, 1 h); Generationszähler (§2) |
| Abfrage-Cache (Summen/Zähler) | Review Captain, Objektcache | Anzahl/Durchschnitt je Quelle | wie Fragment-Cache |
| Seiten-Cache / CDN | Hoster, Cache-Plugin | ganze HTML-Seite | nicht kontrollierbar; Empfehlungen + Purge-Signale (§3) |
| Browser | Header | Admin, REST, Formular | `no-store` für alles Personenbezogene (§4) |

## 2. Fragment- und Abfrage-Cache

- Schlüssel: `rc:w:{gen}:{sha1(normalisierte Attribute + Locale + v)}`, Gruppe `review_captain`. `gen` = Generationszähler (`wp_cache_get( 'gen', 'review_captain' )`, persistiert als Option `review_captain_cache_gen` ohne Autoload, falls kein persistenter Objektcache).
- Invalidierung: Generationszähler wird erhöht bei `review_captain_review_created_v1`, `review_captain_review_status_changed_v1`, `review_captain_review_removed_v1`, bei Refresh-Gate-Läufen, die Reviews ausblenden, sowie bei Änderung von Darstellungs-Einstellungen. Kein Durchiterieren einzelner Keys.
- TTL wird beim Rendern berechnet: `min( 3600, min(refresh_due_at bzw. Anzeigegrenze der enthaltenen Reviews) − jetzt )`. Enthält ein Widget Trustpilot-Reviews, kann ein Fragment nie länger leben als die 24-h-Grenze der ältesten enthaltenen Review.
- Ohne persistenten Objektcache werden Fragmente nur für Widgets mit > 20 Reviews als Transient gespeichert (sonst direkt rendern; die Abfragen sind indiziert).
- Fragmente enthalten **keine** Nonces und keine nutzerspezifischen Daten. Formulare (`[review_captain_form]`) werden nie fragment-gecacht.

## 3. Seiten-Cache und CDN

Review Captain kann fremde Seiten-Caches nicht zuverlässig steuern. Regeln:

- **Empfehlung (Doku + Diagnose-Hinweis):** maximale Seiten-Cache-TTL für Seiten mit externen Reviews ≤ 12 h; bei Trustpilot-Inhalten zwingend < 24 h abzüglich Sync-Intervall.
- **Purge-Signal:** Bei Generationswechsel, der eine öffentliche Änderung bedeutet (Freigabe, Entfernung, Tombstone, Ausblenden durch Refresh-Gate), ruft Review Captain `clean_post_cache()` für Beiträge auf, die bekanntermaßen ein Review-Captain-Block/-Shortcode enthalten. Die Zuordnung pflegt `save_post` (Erkennung von `review-captain/reviews` bzw. `[review_captain`), gespeichert als Post-Meta `_review_captain_embeds` (Version + Hash). Viele Cache-Plugins reagieren auf `clean_post_cache`. Weitere plugin-spezifische Purges sind optionale Integrationen (nicht v1).
- **Ohne Purge-Möglichkeit** (CDN ohne Integration) gilt die TTL-Empfehlung; die Diagnose warnt, wenn Seiten mit Trustpilot-Inhalten existieren und kein System-Cron aktiv ist.
- Keine Ausgabe von `Cache-Control`-Headern auf Frontend-Seiten durch Review Captain (Seiten gehören dem Theme/Cache-Plugin), außer bei Formular-Ergebnisseiten (§4).

## 4. Nie öffentlich cachen

| Kontext | Maßnahme |
|---|---|
| Admin-Seiten, REST-Antworten, OAuth-Start/-Callback | `nocache_headers()` |
| Formular-Absendung und Ergebnis („Danke“, Fehlermeldungen) | Redirect (303) auf Seite mit Query-Flag, `nocache_headers()`, Konstante `DONOTCACHEPAGE` |
| Einladungs-/Abmelde-Links (Pro) | `nocache_headers()`, `DONOTCACHEPAGE`, `noindex`; Token nur als POST-Bestätigung wirksam |
| Lizenzdaten (Pro) | nie im Frontend; Admin `no-store` |
| Moderationsansichten mit `author_email` | nur Admin, `no-store` |

## 5. Nonces in gecachten Seiten

Das Einreichungsformular enthält einen Nonce (`review_captain_submit_review`). Für abgemeldete Nutzer ist er für alle Besucher gleich und 12–24 h gültig; eine länger gecachte Seite liefert abgelaufene Nonces. Verhalten: Bei ungültigem Nonce geht **keine** Eingabe verloren – das Formular wird mit den übermittelten Werten (außer Honeypot) und frischem Nonce erneut angezeigt („Bitte erneut absenden“). Empfehlung Seiten-Cache-TTL für Formularseiten ≤ 10 h.

## 6. Tombstones und Löschung

- Ein Tombstone oder ausgeblendeter Snapshot wird in der nächsten Render-Abfrage nicht mehr geliefert (Abfragefilter), Fragmente sind durch Generationswechsel ungültig, Seiten erhalten ein Purge-Signal.
- Privacy-Löschung zusätzlich: sofortiger Generationswechsel + Purge, unabhängig vom Job-Takt.

## Abnahme

- [ ] Trustpilot-Review mit `fetched_at` = jetzt − 24 h − 1 min wird weder aus Fragment- noch aus Abfrage-Cache ausgegeben (Test mit persistentem Objektcache und mit Transients).
- [ ] Freigabe/Entfernung einer Review ist beim nächsten Seitenaufruf (ohne Seiten-Cache) sichtbar; `clean_post_cache()` wird für einbettende Beiträge aufgerufen.
- [ ] Admin-, REST-, OAuth- und Formular-Ergebnisantworten tragen `no-store`/`no-cache`-Header.
- [ ] Formular mit abgelaufenem Nonce verliert keine Eingaben.
- [ ] Keine Fragment-Cache-Einträge enthalten Nonces, E-Mail-Adressen oder nutzerbezogene Inhalte (Inspektion im Test).
