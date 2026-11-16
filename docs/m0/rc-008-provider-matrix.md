# RC-008 – Provider-Matrix

**Status:** Entwurf – Anbieterangaben müssen vor Feature-Freeze an den Primärdokumenten verifiziert werden (Markierung ⚠)  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §4, §16](../../konzept.md); Anbieterdokumentation laut Quellenliste im Konzept (Recherche 15.11.2026). Die Primärdokumente konnten bei Erstellung dieses Entwurfs nicht erneut abgerufen werden.

Legende: ✅ im Konzept belegt · ⚠ aus Fachkenntnis, vor Freeze verifizieren · ❓ unbekannt, muss beim Anbieter geklärt werden

## Übersicht

| Kriterium | Google Business Profile | Trustpilot | Trusted Shops / eTrusted | Apple App Store Connect | Google Play |
|---|---|---|---|---|---|
| **Kontotyp / Tarif** | Google-Konto mit Eigentümer-/Verwalterrolle für verifizierte Standorte ✅ | Trustpilot-Business-Konto; API-Recht abhängig von Tarif/Data-Solutions-Paket ✅ | Trusted-Shops-Händlerkonto mit eTrusted-Zugang; API-Client im Control Centre ⚠ | Apple Developer Program, App-Store-Connect-Teamzugriff ✅ | Google-Play-Developer-Konto, Play-Console-Zugriff auf die App ✅ |
| **API-Freigabe** | Business-Profile-APIs müssen bei Google beantragt werden; ohne Freigabe Quota 0 ⚠ | Partner-/API-Freigabe durch Trustpilot für private Endpunkte ✅/❓ | API-Client durch Händler anlegbar; Restricted API ggf. vertraglich ⚠/❓ | keine gesonderte Freigabe, API-Key durch Account Holder/Admin ⚠ | Google-Cloud-Projekt mit aktivierter Android-Publisher-API; Verknüpfung in der Play Console ⚠ |
| **Authentifizierung** | OAuth 2.0, Scope `https://www.googleapis.com/auth/business.manage` ⚠ | Public: API-Key; Private: OAuth 2.0 ✅ | OAuth 2.0 Client Credentials (Client-ID/Secret) ⚠ | JWT (ES256) aus Issuer-ID, Key-ID und privatem `.p8`-Schlüssel; Token-Lebensdauer max. 20 min ⚠ | Service Account (JSON-Schlüssel) oder OAuth 2.0, Scope `https://www.googleapis.com/auth/androidpublisher` ⚠ |
| **Lesbare Felder** | Review-ID, Reviewer-Anzeigename/-Foto (sofern freigegeben), Sternebewertung (Enum ONE–FIVE), Kommentar, Erstell-/Änderungszeit, Antwort ⚠ | Review-ID, Sterne 1–5, Titel, Text, Sprache, Verbraucher-Anzeigename, Datum, Antwort, Business-Unit-Summary (TrustScore, Anzahl) ✅/⚠ | Review-ID, Rating, Titel, Kommentar, Typ (Service/Produkt), Kanal, Datum, Antwort ⚠ | Review-ID, Rating 1–5, Titel, Text, Reviewer-Nickname, Territory, Datum, Antwortbeziehung ✅/⚠ | Review-ID, Autorname, Sternebewertung, Text, Sprache, App-Version, Gerät, Zeitpunkt, Entwicklerantwort ⚠ |
| **Pagination / Delta** | Seitenweise mit `pageToken`; Sortierung nach Aktualisierung möglich ⚠ | seitenweise (Page/PerPage) ⚠ | Cursor/Seiten ⚠ | Cursor-basierte Links (`links.next`), Filter Territory/Rating ✅/⚠ | `token`-basierte Pagination; **liefert nur Reviews mit Text, die in der letzten Woche erstellt oder geändert wurden** ⚠ – Initialimport älterer Reviews nur über Play-Console-Berichte (manuell) |
| **Einladungsfunktion** | keine offizielle Einladungs-API; Review-Link (`writereview`-URL zur Place-ID) als Ziel ⚠ | Invitation API (Service-/Produkteinladungen), nur mit Freigabe ✅ | Event-/Invite-API für Einladungen bei berechtigtem Vertrag ✅/⚠ | keine; In-App-Bewertungsdialog ist App-Sache | keine; In-App-Review-API ist App-Sache |
| **Antwortfunktion** | Antworten per API möglich (Schreibrecht im selben Scope) ⚠ | private API mit Berechtigung ⚠ | ⚠/❓ | `customerReviewResponses`, erfordert passende Rolle ✅/⚠ | `reviews.reply` ⚠ |
| **Limits / Quota** | projektbezogene Quota nach Freigabe (QPM) ⚠ | tarif-/schlüsselabhängig ❓ | ❓ | Rate-Limit pro Key/Stunde, Header `X-Rate-Limit` ⚠ | tägliche/stündliche Quota für Reviews-Endpunkte ⚠ |
| **Attribution** | Quelle „Google“ mit Link zum Profil; Google-Markenrichtlinien ✅ | Trustpilot-Name/-Logo und Link je Review/Summary nach Markenrichtlinie ✅ | Trusted-Shops-Kennzeichnung; Trustbadge nur als offizielles Asset ⚠ | Quelle „App Store“ mit Link zur App-Seite; Apple-Badge-Richtlinien ⚠ | Quelle „Google Play“ mit Link; Google-Play-Badge-Richtlinien ⚠ |
| **Cache / Refresh** | gemäß GBP-Richtlinien; regelmäßige Aktualisierung, gelöschte Reviews entfernen ⚠ | **max. 24 h** Refresh angezeigter API-Inhalte ✅ | ❓ vertraglich klären | ❓ (keine bekannte Frist; Default 24 h ansetzen) | ❓ (Default 24 h ansetzen) |
| **Löschpflicht** | gelöschte/entfernte Reviews beim nächsten Sync tombstonen; bei Disconnect Snapshots nach Frist entfernen ✅ | Löschungen übernehmen ✅ | ⚠ | ⚠ | ⚠ |
| **Kosten** | API kostenlos (nach Freigabe) ⚠ | API-Zugang Teil kostenpflichtiger Pakete ✅/❓ | Teil des Händlervertrags ❓ | kostenlos (Developer-Programm vorausgesetzt) ⚠ | kostenlos ⚠ |
| **Fallback** | Profil-Link + manuelle Kuratierung (Core) ✅ | TrustBox-Widget (Consent) + Profil-Link + manuell ✅ | Trustbadge/Review-Widget (Consent) + Profil-Link + manuell ✅ | App-Store-Link + manuell ✅ | Play-Store-Link + manuell ✅ |
| **1.0-Entscheidung** ([RC-002](rc-002-scope-1-0.md)) | nativer Sync | Widget/Link | Widget/Link | nativer Sync | nativer Sync |

## Interne Default-Regeln, solange eine Anbieterfrist unbekannt ist

- Cache-/Refreshintervall angezeigter externer Inhalte: **höchstens 24 Stunden**.
- Gelöschte oder nicht mehr gelieferte Reviews werden beim nächsten vollständigen Abgleich getombstoned und nicht mehr ausgegeben.
- Nach Disconnect: keine neuen Abrufe; Snapshots werden spätestens nach Ablauf des Refreshintervalls ausgeblendet und nach der Aufbewahrungsfrist (RC-027) gelöscht.

## Konsequenzen für die Architektur

1. **Google Play:** Da die API nur ein kurzes Zeitfenster liefert (⚠), muss der Delta-Sync mindestens täglich laufen; Lücken bei Ausfällen > 7 Tage werden in der Diagnose als „mögliche Lücke“ angezeigt. Ein CSV-Import aus Play-Console-Berichten (RC-045) dient als Initialimport.
2. **Google Places Fallback (RC-069):** Empfehlung **streichen** für 1.0 – maximal fünf Reviews, kostenpflichtige SKU, eigene Attributionspflichten und Verwechslungsgefahr mit GBP-OAuth-Daten. Neubewertung nur bei belegter Nachfrage.
3. **Apple:** JWT wird pro Lauf erzeugt, nie gespeichert; nur der `.p8`-Schlüssel wird verschlüsselt abgelegt (TEC-008).
4. **Trustpilot/eTrusted:** Adapter-Gerüst so bauen, dass Sync später ohne Schemaänderung ergänzt werden kann (capability discovery, RC-020).

## Offen / Verifikation

- [ ] Alle ⚠-Angaben gegen die Primärdokumente prüfen und Datum der Prüfung eintragen (Netzwerkzugang zu developers.google.com, developers.trustpilot.com, developers.etrusted.com, developer.apple.com erforderlich).
- [ ] ❓-Punkte schriftlich bei Trustpilot und Trusted Shops anfragen (Tarif, Cachefrist, Plugin-Use-Case).
- [ ] Matrix in die Plattform-Setup-Dokumentation (OPS-002) übernehmen.
