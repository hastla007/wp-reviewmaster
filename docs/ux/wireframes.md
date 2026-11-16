# Admin-Wireframes (UX-003)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Textuelle Wireframes; visuelle Referenz: `docs/mockups/*.svg` (Mockup-Daten). Komponenten: [design-system.md](design-system.md). Zustände: [states.md](states.md). Menü/Slugs: [../settings/structure.md](../settings/structure.md).

Gemeinsames Gerüst jeder Seite (WordPress-nativ):

```
┌ .wrap ───────────────────────────────────────────────────────────────┐
│ h1.wp-heading-inline  [Seitentitel]   a.page-title-action [+ Aktion] │
│ hr.wp-header-end                                                     │
│ .notice-Bereich (Erfolg / Warnung / Fehler, dismissible wo sinnvoll) │
│ Seiteninhalt                                                          │
│ Fußzeile: kleine Hilfelinks (Dokumentation, Diagnose)                │
└──────────────────────────────────────────────────────────────────────┘
```

Legende: `[Button]` · `(•)` Radio · `[x]` Checkbox · `[▾ Auswahl]` · `{Pro}` nur mit Pro · `⚠` Warnung.

---

## W1 Dashboard (`page=review-captain`)

```
Review Captain                                            [+ Review hinzufügen]
──────────────────────────────────────────────────────────────────────────────
[Notice: offene Fehler, falls vorhanden – „2 Quellen benötigen Aufmerksamkeit → Ansehen“]

┌ Einrichtung (Checkliste, ausblendbar) ───────────────────────────────────┐
│ [✓] Marke & Sprache   [ ] Erstes Review   [ ] Quelle/Profil-Link          │
│ [ ] Erstes Widget veröffentlicht   [ ] Datenschutzhinweis geprüft         │
│ {Pro} [ ] Anfragepolitik            [Weiter einrichten] [Ausblenden]      │
└──────────────────────────────────────────────────────────────────────────┘
┌ KPI ──────────┐┌ KPI ──────────┐┌ KPI ─────────────┐┌ KPI ──────────────┐
│ Sichtbare     ││ Neu (30 Tage) ││ Letzter Sync {Pro}││ Offene Fehler     │
│ Reviews  128  ││ 12            ││ Heute 03:12       ││ 0 · Diagnose →    │
└───────────────┘└───────────────┘└───────────────────┘└───────────────────┘
┌ Bewertungen nach Quelle (getrennt, kein Gesamtschnitt) ──────────────────┐
│ Website (lokal)  4,6 / 5 · 54 Reviews                                    │
│ Google           4,8 / 5 · 61 (manuelle Auswahl) · Stand 12.11.2026       │
│ Hinweis: Werte beruhen nur auf gespeicherten/ausgewählten Datensätzen.   │
└──────────────────────────────────────────────────────────────────────────┘
┌ Zuletzt eingegangen ───────────────────────── [Zur Review-Liste] ┐ ┌ Schnellaktionen ┐
│ ★★★★☆ „Sehr klare Kommunikation …“ · Website · 14.11. [Prüfen]   │ │ [Quelle hinzuf.]│
│ ★★★★★ „Pünktlich geliefert …“ · Google · 13.11. [Ansehen]        │ │ [Widget erst.]  │
│ (max. 5 Einträge, Text auf 80 Zeichen gekürzt)                   │ │ {Pro}[Anfrage   │
└──────────────────────────────────────────────────────────────────┘ │  testen]        │
┌ {Pro-Info, nur ohne Pro} ───────────────────────────────────────┐ └─────────────────┘
│ Automatische Synchronisierung & Anfragen mit Review Captain Pro │
│ [Mehr erfahren]  (keine Kernfunktion blockiert)                  │
└──────────────────────────────────────────────────────────────────┘
```

Capability: `review_captain_moderate`. KPI-Karten = `.postbox`-Raster (CSS-Grid, mobil einspaltig).

## W2 Reviews – Liste (`page=review-captain-reviews`)

```
Reviews                         [+ Neu hinzufügen] [Importieren] [Exportieren]
Alle (128) | Prüfung ausstehend (3) | Freigegeben (110) | Gemeldet (1) | Abgelehnt (9) | Archiviert (5)
[▾ Mehrfachaktion] [Übernehmen]   [▾ Quelle] [▾ Typ] [▾ Bewertung] [▾ Sprache] [▾ Zeitraum] [Filtern]
                                                               [Suche … ] [Reviews durchsuchen]
┌───┬────────────┬──────────┬───────────┬────────┬──────────────────────┬──────────┬────────┬───────────┐
│[ ]│ Quelle     │ Profil   │ Typ       │ Wert   │ Titel / Auszug       │ Datum    │ Sprache│ Status    │
├───┼────────────┼──────────┼───────────┼────────┼──────────────────────┼──────────┼────────┼───────────┤
│[ ]│ Website    │ –        │ Review    │ 4 / 5  │ „Sehr klare …“       │ 14.11.26 │ de     │ ⏳ Prüfung │
│   │ Zeilenaktionen: Bearbeiten | Freigeben | Ablehnen | Melden | Archivieren                         │
│[ ]│ Google     │ Filiale A│ Review    │ 5 / 5  │ „Pünktlich …“  ↗     │ 13.11.26 │ de     │ ✓ Frei    │
│   │ Zeilenaktionen: Ansehen | Ausblenden | Quelle öffnen ↗ | Zu Widget hinzufügen                   │
└───┴────────────┴──────────┴───────────┴────────┴──────────────────────┴──────────┴────────┴───────────┘
                                                             128 Einträge  « ‹ 1 von 7 › »
```

- `WP_List_Table`; Spalten ein-/ausblendbar über „Ansicht anpassen“. Spalte „Letzter Sync“ {Pro}.
- Mehrfachaktionen: Freigeben, Ablehnen, Archivieren, Exportieren (Auswahl). Löschen nur für lokale/manuelle Einträge.
- Externe Reviews: keine Aktion, die die Quelle verändert; „Ausblenden“ = lokale Sichtbarkeit.
- Wert immer auf Originalskala („4 / 5“), Sterne mit Textalternative.

## W3 Review bearbeiten / ansehen (`page=review-captain-reviews&action=edit&id=…`)

```
Review bearbeiten                                              [← Zur Liste]
┌ Hauptspalte ─────────────────────────────────────┐ ┌ Seitenleiste ───────────────┐
│ Art:  (•) Eigenes Review/Testimonial             │ │ ┌ Status ─────────────────┐ │
│       ( ) Externe Bewertung (Auszug)             │ │ │ [▾ Prüfung ausstehend]  │ │
│ Plattform* [▾ Google]   (nur bei extern)         │ │ │ Änderungsgrund [______] │ │
│ Bewertung* [ 4 ] von [ 5 ]  (Skala)              │ │ │ [Aktualisieren]         │ │
│ Titel      [______________________________]      │ │ └─────────────────────────┘ │
│ Text*      [                                ]    │ │ ┌ Herkunft ───────────────┐ │
│            [                                ]    │ │ │ Ursprung: Formular      │ │
│ Name*      [________________]                    │ │ │ Verifizierung: ungeprüft│ │
│ E-Mail     [________________] (nur lokal, privat)│ │ │ Einwilligung: ja, 14.11.│ │
│ Datum*     [2026-11-14]  Sprache [▾ de]          │ │ │ Import-Batch: –         │ │
│ Profil-/Review-Link* [https://…] (extern)        │ │ └─────────────────────────┘ │
│ Kontext    [▾ Produkt] [Suche: „Kursbuch“ ]      │ │ ┌ Verwenden in ───────────┐ │
│                                                  │ │ │ Widget „Startseite“ [+] │ │
│ ┌ Änderungsverlauf ────────────────────────────┐ │ │ └─────────────────────────┘ │
│ │ 14.11. 10:02  erstellt (Formular)            │ │ │ [Löschen] (nur lokal)       │
│ │ 14.11. 11:30  pending → approved · M. Muster │ │ └─────────────────────────────┘
│ └──────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────┘
```

- Synchronisierte externe Reviews (`origin=provider_sync|connector`): Text, Wert, Name, Datum **read-only** (Hinweis „Inhalt stammt von {Plattform} und kann hier nicht geändert werden“); nur Sichtbarkeit/Archiv/Kontext bearbeitbar. Antwortstatus {Pro} falls verfügbar.
- Verifizierung ist nicht manuell auf „Kauf verifiziert“ setzbar (nur Quellenvertrag).
- Pflichtfelder `*` serverseitig validiert, Fehler oberhalb + am Feld.

## W4 Quellen (`page=review-captain-sources`)

```
Quellen                                                   [+ Quelle hinzufügen]
[Info-Notice] API-Verbindungen setzen ein berechtigtes Eigentümerkonto und die passende Anbieterfreigabe voraus.
┌──────────────────────┬──────────────────────────────┬────────────┬────────────┬──────────────┐
│ Plattform            │ Verbindung / Profil          │ Letzter Sync│ Nächster   │ Status       │
├──────────────────────┼──────────────────────────────┼────────────┼────────────┼──────────────┤
│ Google Business Prof.│ {Pro} OAuth · 2 Standorte    │ Heute 03:12│ Morgen 02:00│ ● Verbunden │
│                      │ Verwalten | Testen | Trennen │            │            │              │
│ Trustpilot           │ Profil-Link · CTA aktiv      │ –          │ –          │ ○ Nur Link   │
│ Trusted Shops        │ Embed-Fallback (nach Consent)│ –          │ –          │ ○ Embed      │
│ Apple App Store      │ –                            │ –          │ –          │ – Nicht eingerichtet [Einrichten] │
│ Google Play          │ {Pro} 1 App · Token läuft ab │ Gestern    │ pausiert   │ ⚠ Warnung    │
└──────────────────────┴──────────────────────────────┴────────────┴────────────┴──────────────┘
┌ Ohne API-Recht ───────────────────────────────────────────────────────────┐
│ Offizielles Embed, Profil-Link oder manuell kuratierte Auswahl. Kein Scraping. │
└───────────────────────────────────────────────────────────────────────────┘
```

**Quelle hinzufügen (Wizard, Schritte als `<ol>` mit aktuellem Schritt `aria-current="step"`):**
1. Plattform wählen (Karten) → 2. Weg wählen: (•) Profil-Link/CTA (Core) ( ) Offizielles Embed (Core, Consent) ( ) API-Verbindung {Pro} → 3. Berechtigungen/Kosten erklären → 4. Verbinden/Testen → 5. Profil wählen → 6. Sync-Optionen {Pro} → 7. Ergebnisvorschau → [Fertig].

**Profil-Detail:** Label, Profil-URL, CTA-Text, Zuordnung (Seite/Produkt), Embed an/aus, {Pro} Sync-Intervall/Fenster-Override, Diagnose-Abschnitt (letzte 10 Läufe, Fehlercode `rc.*` mit Klartext), [Neu verbinden] [Verbindung trennen].

## W5 Widgets (`page=review-captain-widgets`)

```
Widgets                                                        [+ Neues Widget]
┌──────────────────┬──────────┬─────────────┬────────────────┬──────────────┬─────────────────────┐
│ Titel            │ Typ      │ Quellen     │ Auswahl        │ Verwendet auf│ Einbinden           │
├──────────────────┼──────────┼─────────────┼────────────────┼──────────────┼─────────────────────┤
│ Startseite       │ Grid     │ Website,    │ ≥4★, neueste 6 │ Startseite   │ [review_captain v="1" id="12"] [Kopieren] │
│                  │          │ Google      │                │              │                     │
└──────────────────┴──────────┴─────────────┴────────────────┴──────────────┴─────────────────────┘
```

**Widget-Editor (zweispaltig, mobil gestapelt):**

```
┌ Einstellungen ──────────────────────────┐ ┌ Vorschau  [Desktop|Mobil] ───────┐
│ 1 Quellen    [x] Website [x] Google     │ │ ┌card┐ ┌card┐ ┌card┐           │
│ 2 Auswahl    (•) Alle freigegebenen     │ │ │★★★★☆│ │★★★★★│ │★★★★☆│           │
│              ( ) Filter: Bewertung ≥ [▾]│ │ │Text │ │Text │ │Text │           │
│              ( ) Handverlesen [Wählen…] │ │ │Google│ │Web.│ │Google│ Quelle ↗ │
│ 3 Layout     (•) Grid ( ) Liste {Pro…}  │ │ └────┘ └────┘ └────┘           │
│              Anzahl [6]  Spalten [3]    │ │ [Leerzustand ansehen]           │
│ 4 Anzeige    Standard aus Einstellungen │ └──────────────────────────────────┘
│              [ ] abweichend festlegen   │
│ ⓘ Externe Reviews erhalten kein Schema-Markup; Quelle bleibt sichtbar. │
│                      [Speichern] [Shortcode kopieren]                   │
└─────────────────────────────────────────┘
```

## W6 Einstellungen (`page=review-captain-settings&tab=…`)

```
Einstellungen
[Allgemein][Quellen & Synchronisierung][Anfragen & Zustellung 🔒][Einreichungen & Moderation]
[Darstellung][Datenschutz & Aufbewahrung][Zugriff & Rollen][Erweitert]
──────────────────────────────────────────────────────────────────────────────
h2 Identität
  Markenname            [__________________]  Leer = Websitetitel „Meine Website“.
h2 Sprache & Datum
  Standardsprache       [▾ Website-Sprache (Deutsch)]
  Datumsformat          [__________] Leer = WordPress-Format (12.11.2026). Zeitzone: Europe/Berlin (WordPress)
h2 Neue Reviews
  Status neuer Reviews  (•) Prüfung ausstehend (empfohlen)  ( ) Sofort freigeben ⚠
▸ Erweiterte Optionen   (eingeklappt, falls vorhanden)
                                                    [Änderungen speichern]
Änderungsverlauf dieses Tabs →   Tab auf Standard zurücksetzen →
```

- Tab „Anfragen & Zustellung“ ohne Pro: alle Felder `disabled`, oben Info-Box „Mit Review Captain Pro … Ihre Werte bleiben erhalten.“
- Tab „Datenschutz & Aufbewahrung“: unten abgesetzter Bereich „Gefahrenzone“ mit [Alle Plugin-Daten löschen…] (`.button-link-delete`).
- Vollständiger Feldbaum: [../settings/structure.md](../settings/structure.md).

## W7 Diagnose (`page=review-captain-diagnostics`, read-only)

```
Diagnose                                          [Diagnosebericht herunterladen]
┌ Umgebung ──────────────────────────────────────────────────────────────┐
│ Review Captain 1.0.0 · DB-Schema 1 · Einstellungen v1 · PHP 8.2 · WP 6.7 │
│ Multisite: nein · Action Scheduler: ja (WooCommerce) · WP-Cron: aktiv    │
│ Pro: 1.0.0 (Lizenz aktiv) · Contract review_captain_providers_v1 ✓       │
└────────────────────────────────────────────────────────────────────────┘
┌ Hintergrundjobs ────────────────────────────────────────────────────────┐
│ Warteschlange 3 · laufend 0 · fehlgeschlagen 1 · endgültig fehlgeschl. 0│
│ Letzte Jobs: Typ | Status | Versuche | Fehlercode | Zeit                │
│ provider_sync | failed | 2/5 | rc.quota.exceeded | 03:12  [Erneut planen]│
└────────────────────────────────────────────────────────────────────────┘
┌ Quellen ───────────────────────────────────────────────────────────────┐
│ Profil | letzter Erfolg | Token gültig bis (ohne Wert) | Kontingent     │
└────────────────────────────────────────────────────────────────────────┘
┌ Integrationen / Connectoren ───────────────────────────────────────────┐
│ Name | Version | Contract | Status                                       │
└────────────────────────────────────────────────────────────────────────┘
┌ Schema/SEO ────────────────────────────────────────────────────────────┐
│ Yoast erkannt: ja · Review-Markup: nur lokale Reviews · Konflikte: keine │
└────────────────────────────────────────────────────────────────────────┘
```

- Capability `review_captain_view_reports`; „Erneut planen“ zusätzlich `review_captain_manage_sources` + Nonce.
- Diagnosebericht: JSON/Text ohne Secrets, Tokens, E-Mails, Reviewtexte; zeigt vor Download eine Vorschau.
- Zusätzlich Einträge im WordPress-Website-Zustand (Site Health) für Cron und Schema-Version (Vorschlag).

## W8 Pro-Seiten (Kurzskizze, Detail folgt mit Pro-Scope)

| Seite | Kern des Wireframes |
|---|---|
| Anfragen {Pro} | Kampagnenliste (Status, Auslöser, Ziel, gesendet/übersprungen) + 5-Schritt-Editor wie `review-captain-campaign.svg`; Queue-Tabelle mit Grund und Retry |
| Testimonials & Trustmarks {Pro} | Bibliothek mit Medientyp, Rechtenachweis, Ablauf; Trustmark-Liste mit Herausgeber, gültig bis, Prüf-Link |
| Integrationen {Pro} | Suite-Karten (Woo, Voucher, Pass, Affiliate, Form) mit Status/Contract-Version/Test; Bereich „Kundenspezifische Connectoren“ |
| Lizenz {Pro} | Schlüssel, Status, Plan, Sites, Ablauf, Updates (Captain-Pro-Standard) |
| Analytics {Pro} | Zeitreihe je Quelle mit Stichprobengröße, Verteilung, Export |

## Abnahme

Entspricht UX-003 für den Core, wenn:

- [ ] Dashboard, Reviews, Review bearbeiten, Quellen, Widgets, Einstellungen und Diagnose als Wireframe vorliegen und mit Produkt/Entwicklung abgestimmt sind;
- [ ] jede Seite nur native WP-Admin-Muster nutzt (Liste, Tabs, `form-table`, Notices, Postboxen) und mobil funktioniert;
- [ ] Pro-Elemente klar markiert sind und ohne Pro keine Kernfunktion blockieren;
- [ ] die Pro-Seiten (Anfragen, Testimonials/Trustmarks, Integrationen inkl. Connector-Manager, Lizenz) nach Pro-Scope-Freeze eigene Detail-Wireframes erhalten (bis dahin UX-003 nur teilweise erfüllt).
