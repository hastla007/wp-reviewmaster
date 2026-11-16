# Provider-Matrix (RC-008)

Status: **Entwurf, nicht verifiziert**, Stand 2026-11-16. Quellen: konzept.md §4 und die öffentlichen Doku-Links aus konzept.md §16 (recherchiert 15.11.2026). In diesem Arbeitsschritt wurden die Anbieterdokumente **nicht erneut abgerufen**.

Spalte **bestätigt?**:
- `Doku, Prüfung offen` = laut öffentlicher Anbieterdoku (konzept.md §16), noch nicht gegen die aktuelle Primärquelle und nicht mit echtem Konto geprüft.
- `Annahme, ungeprüft` = Vorwissen/Ableitung ohne Beleg in konzept.md; vor Verwendung zwingend prüfen.
- `bestätigt (Datum, Person)` = mit Testkonto verifiziert (RC-009 … RC-013). Derzeit: **keine Zeile bestätigt**.

Grundsatz für alle: kein Scraping; nur eigene/verwaltete Profile/Apps; Attribution sichtbar; Fallback ohne API = Profil-Link, offizielles Embed (nach Consent) oder manuelle Auswahl.

## Übersicht

| Plattform | Weg 1.0 (Vorschlag, RC-002 offen) | Entitlement | Core-Fallback |
|---|---|---|---|
| Google Business Profile | API-Sync eigener verifizierter Standorte (Pro 1.0) | `review.platform_sync` | Profil-Link + CTA, manuelle Auszüge |
| Trustpilot | Pro 1.x nach API-/Tarifprüfung; sonst TrustBox/Link | `review.platform_sync` (+ später `review.review_requests`) | Profil-Link, TrustBox nach Consent ([widget-fallbacks.md](widget-fallbacks.md)) |
| Trusted Shops / eTrusted | Pro 1.x nach Vertrags-/API-Prüfung; sonst Trustbadge/Widget | `review.platform_sync` | Profil-Link, Trustbadge nach Consent |
| Apple App Store | API-Sync read-only eigener Apps (Pro 1.0) | `review.platform_sync` | App-Store-Link, manuelle Auszüge |
| Google Play | API-Sync read-only eigener Apps (Pro 1.0) | `review.platform_sync` | Play-Store-Link, manuelle Auszüge |

## Google Business Profile (+ Places als getrennter Fallback)

Doku: [Review Data](https://developers.google.com/my-business/content/review-data), [Policies](https://developers.google.com/my-business/content/policies), [Place Details](https://developers.google.com/maps/documentation/places/web-service/place-details)

| Aspekt | Inhalt | bestätigt? |
|---|---|---|
| Kontotyp/Tarif | Google-Konto mit Verwaltungsrecht für **verifizierte** Standorte; Google-Cloud-Projekt des Betreibers bzw. des Plugin-Anbieters (OFFEN: wessen OAuth-Client, RC-009) | Doku, Prüfung offen |
| API-/Partnerfreigabe | Business Profile APIs erfordern eine Zugangsfreigabe durch Google (Antragsverfahren) | Annahme, ungeprüft |
| Authentifizierung | OAuth 2.0 mit Nutzerzustimmung; Scope für Business-Verwaltung | Doku, Prüfung offen |
| Lesen | Reviews je Location paginiert: Sterne, Text, Reviewer-Anzeigename, Zeitstempel, ggf. Antwort | Doku, Prüfung offen |
| Schreiben/Antwort | Antwort-Endpunkt vorhanden; nicht im MVP (`review.platform_replies` später) | Doku, Prüfung offen |
| Einladungen | keine Invite-API; nur Review-Link des Profils | Annahme, ungeprüft |
| Rate Limits | Kontingent je Projekt in der Cloud Console; konkrete Werte nicht dokumentiert in konzept.md | offen |
| Kosten | GBP-API laut Konzept ohne eigene Gebühr; Places-Fallback kostenpflichtig je SKU | Annahme, ungeprüft |
| Speicher/Löschung | Richtlinien zu Attribution/Darstellung in den API Policies; Lösch-/Aktualisierungspflichten zu prüfen | Doku, Prüfung offen |
| Places-Fallback | Place Details liefert höchstens 5 Reviews; Review-Felder in teurerer SKU; API-Key, nicht mit GBP-OAuth vermischen (RC-069: implementieren oder streichen) | Doku, Prüfung offen |
| Fallback | Profil-Link „Auf Google bewerten“, manuell kuratierte Auszüge | – |

## Trustpilot

Doku: [API Overview](https://developers.trustpilot.com/), [Authentication](https://developers.trustpilot.com/authentication), [Content Refresh Guidelines](https://developers.trustpilot.com/ds-caching-best-practices/), [WooCommerce-Integration](https://woocommerce.com/products/trustpilot-reviews/)

| Aspekt | Inhalt | bestätigt? |
|---|---|---|
| Kontotyp/Tarif | Trustpilot-Business-Konto; API-Verfügbarkeit abhängig von Tarif/Modul, nicht in jedem Tarif garantiert | Doku, Prüfung offen |
| API-/Partnerfreigabe | API-Key je Anwendung; private Endpunkte nur mit Berechtigung; Freigabe des konkreten Plugin-Use-Cases nötig (RC-010) | Doku, Prüfung offen |
| Authentifizierung | öffentliche Endpunkte per API-Key; private Endpunkte OAuth 2.0 | Doku, Prüfung offen |
| Lesen | Business-Unit-Reviews, Summary (Score, Anzahl) | Doku, Prüfung offen |
| Schreiben/Antwort | private Endpunkte für Antworten existieren laut Übersicht; nicht im MVP | Annahme, ungeprüft |
| Einladungen | Invitation-API nur für freigeschaltete Konten; sonst Review-Link (RC-072) | Doku, Prüfung offen |
| Rate Limits | in API-Doku je Endpunkt zu prüfen | offen |
| Kosten | API/Module tarifabhängig (Trustpilot-Abo) | Doku, Prüfung offen |
| Speicher/Löschung | **maximale Cache-Aktualisierung 24 h** für angezeigte API-Inhalte; Löschungen übernehmen; Attribution | Doku, Prüfung offen |
| Fallback | TrustBox-Widget (nach Consent), Profil-Link | – |

## Trusted Shops / eTrusted

Doku: [Common API](https://api.trustedshops.com/documentation/public/), [Restricted API](https://api.trustedshops.com/documentation/restricted/), [eTrusted Getting Started](https://developers.etrusted.com/docs/getting-started), [Review Widgets](https://developers.etrusted.com/docs/review-widgets)

| Aspekt | Inhalt | bestätigt? |
|---|---|---|
| Kontotyp/Tarif | Trusted-Shops-/eTrusted-Kundenkonto mit passendem Produkt; Shop-ID/Channel | Doku, Prüfung offen |
| API-/Partnerfreigabe | API-Client (Client-ID/Secret) im eTrusted-Kontrollzentrum; Common API ≠ Vollzugriff auf private Reviews; Vertrag/Freigabe vor Launch (RC-011) | Doku, Prüfung offen |
| Authentifizierung | OAuth 2.0 Client Credentials (Token mit Ablauf, Refresh) | Annahme, ungeprüft |
| Lesen | Service- und Produktreviews je Channel, paginiert | Doku, Prüfung offen |
| Schreiben/Antwort | Antworten über API möglich? | offen |
| Einladungen | Event-/Invite-API nur mit gültigem Vertrag und zugelassenen Feldern (RC-076) | Doku, Prüfung offen |
| Rate Limits | zu prüfen | offen |
| Kosten | vertragsabhängig (Trusted-Shops-Produkt) | Doku, Prüfung offen |
| Speicher/Löschung | zu prüfen; bis dahin `retention_external_days` als Obergrenze | offen |
| Fallback | Trustbadge/Review-Widget (nach Consent), Profil-Link | – |

## Apple App Store

Doku: [App Store Connect API: Customer Reviews](https://developer.apple.com/documentation/appstoreconnectapi/customer-reviews)

| Aspekt | Inhalt | bestätigt? |
|---|---|---|
| Kontotyp/Tarif | Apple-Developer-Programm, App-Store-Connect-Team mit der App | Doku, Prüfung offen |
| API-/Partnerfreigabe | API-Key im Team erzeugen; minimale Rolle zu bestimmen (RC-012) | Doku, Prüfung offen |
| Authentifizierung | JWT (ES256) aus Issuer-ID, Key-ID, privatem Schlüssel (.p8); kurzlebige Tokens | Doku, Prüfung offen |
| Lesen | `customerReviews` je App: Rating, Titel, Text, Reviewer-Alias, Territory, Datum; filterbar | Doku, Prüfung offen |
| Schreiben/Antwort | `customerReviewResponses` separat, braucht Rechte; nach MVP | Doku, Prüfung offen |
| Einladungen | keine (In-App-Rating-Prompt ist App-seitig, nicht Plugin) | Annahme, ungeprüft |
| Rate Limits | stündliches Limit je Key laut Apple-Doku (Wert zu prüfen) | Annahme, ungeprüft |
| Kosten | Apple-Developer-Programm (Jahresgebühr); API selbst ohne Zusatzkosten | Annahme, ungeprüft |
| Speicher/Löschung | zu prüfen | offen |
| Fallback | App-Store-Link, manuelle Auszüge | – |

## Google Play

Doku: [Reviews.list](https://developers.google.com/android-publisher/api-ref/rest/v3/reviews/list)

| Aspekt | Inhalt | bestätigt? |
|---|---|---|
| Kontotyp/Tarif | Play-Console-Entwicklerkonto, App/Paketname | Doku, Prüfung offen |
| API-/Partnerfreigabe | Google-Cloud-Projekt verknüpft, Service-Account mit Play-Console-Berechtigung (RC-013) | Doku, Prüfung offen |
| Authentifizierung | Service-Account (oder OAuth) mit Scope `androidpublisher` | Doku, Prüfung offen |
| Lesen | `reviews.list`/`get`: Sterne, Text, Sprache, App-Version, Gerät, ggf. Entwicklerantwort; Übersetzungssprache wählbar | Doku, Prüfung offen |
| Zeitfenster | API liefert nur Reviews der **letzten Tage** (bekannt: ca. eine Woche) → Initialimport älterer Reviews nicht möglich, Sync muss regelmäßig laufen | Annahme, ungeprüft |
| Schreiben/Antwort | `reviews.reply`; nach MVP | Doku, Prüfung offen |
| Einladungen | keine (In-App-Review-API ist App-seitig) | Annahme, ungeprüft |
| Rate Limits | Kontingent je Projekt, zu prüfen | offen |
| Kosten | Play-Console-Registrierung (einmalig); API ohne Zusatzkosten | Annahme, ungeprüft |
| Speicher/Löschung | zu prüfen | offen |
| Fallback | Play-Store-Link, manuelle Auszüge | – |

## Prüfplan

1. Je Zeile Primärquelle öffnen, Abrufdatum + Zitat/Abschnitt notieren, Spalte aktualisieren.
2. Testkonto (RC-009 … RC-013): Verbindung, Lesen, Paginierung, Löschung, Kontingent, Token-Widerruf protokollieren → `bestätigt (Datum, Person)`.
3. Abweichungen gegenüber konzept.md §4 in konzept.md und TODO zurückspielen.
4. Regelmäßige Wiederholung über [../ops/api-change-routine.md](../ops/api-change-routine.md).

## Abnahme

Entspricht RC-008, wenn:

- [ ] für alle fünf Plattformen Kontotyp/Tarif, API-Freigabe, Authentifizierung, lesbare Felder, Antwort-/Einladungsfunktionen, Limits, Attribution, Cache-/Löschpflicht, Kosten und Fallback eingetragen sind;
- [ ] jede Zeile einen Prüfstatus hat und keine Funktion als zugesagt gilt, deren Zeile nicht `bestätigt` ist;
- [ ] die Zeilen „offen“ (Rate Limits, Löschfristen, Kosten) aus Primärquellen gefüllt sind;
- [ ] RC-002 (welche Plattform in 1.0 nativ synchronisiert) auf Basis dieser Matrix entschieden ist.
