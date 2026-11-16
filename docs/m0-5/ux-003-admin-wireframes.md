# UX-003 – Admin-Wireframes

**Status:** Entwurf – Abstimmung offen. Visuelle Mockups für Dashboard, Quellen, Kampagne und Proof-Wall liegen unter [docs/mockups](../mockups/); dieses Dokument legt Struktur und Inhalt aller Seiten fest (WordPress-native Komponenten: `wrap`, `nav-tab-wrapper`, `WP_List_Table`, `notice`, `postbox`).  
**Stand:** 2026-11-16

## Menü

```
Review Captain
├─ Dashboard
├─ Reviews            (Badge: ausstehende Moderation)
├─ Anfragen           [Pro]
├─ Widgets
├─ Testimonials       (Core: Text; Pro-Folge: Medien, Trustmarks)
├─ Quellen
├─ Integrationen      (Core: Vertragsinfo; Pro: Suite-Adapter, Connector-Manager)
├─ Einstellungen
├─ Diagnose
└─ Lizenz             [Pro, vom Add-on ergänzt]
```

## Dashboard

```
[Notice: Setup-Checkliste 3/5 erledigt — Weiter einrichten]
┌ KPI ───────────┬────────────────┬───────────────┬────────────────┐
│ Sichtbare      │ Ø je Quelle    │ Neu (30 Tage) │ Verbindungs-   │
│ Reviews  128   │ G 4,7 · eig 4,5│ 12            │ fehler 0       │
└────────────────┴────────────────┴───────────────┴────────────────┘
┌ Zuletzt eingegangen (5) ───────────────┐ ┌ Schnellaktionen ─────┐
│ Quelle | ★ | Auszug | Datum | Aktion   │ │ Review erfassen      │
└────────────────────────────────────────┘ │ Widget erstellen     │
┌ Pro-Funktionen (nur ohne Pro, einklappbar)┐│ Quelle verbinden [P] │
└──────────────────────────────────────────┘└─────────────────────┘
```

## Reviews

```
Reviews  [Externe Review hinzufügen] [Import] [Export]
Alle (128) | Ausstehend (3) | Freigegeben | Abgelehnt | Archiviert | Gemeldet
[Quelle ▾][Typ ▾][Bewertung ▾][Zeitraum ▾][Sprache ▾][Filtern]   [Suche____]
☐ | Quelle | Profil | Typ | ★ | Titel/Auszug | Datum | Status | Verifiziert
Bulk: Freigeben · Ablehnen · Archivieren
```

Detail (eigene Seite, `postbox`-Layout): Originaltext, Quelle/Link, Verifizierungsstatus, Kontext (Produkt/Event), Moderationsverlauf mit Grund, Aktionen. Externe Reviews: Text nicht editierbar.

## Anfragen [Pro]

Tabs: **Kampagnen** (Liste: Name, Auslöser, Ziel, Status aktiv/inaktiv, gesendet 30 T) · **Queue** (geplant/gesendet/übersprungen/fehlgeschlagen/abgemeldet, Grund, Wiederholen) · **Abmeldungen** (Anzahl, Suche nach E-Mail → nur Treffer ja/nein). Kampagnen-Editor: Abschnitte Auslöser, Ziel, Timing, Vorlage mit Vorschau, Empfängerregel/Ausschlüsse, Testmodus.

## Widgets

Liste: Titel, Typ, Quellen, Regel, Verwendungsorte, Shortcode (kopierbar). Editor: zweispaltig – links Einstellungen (Quellen → Auswahl → Layout → Anzeige), rechts Live-Vorschau mit Umschalter Desktop/Mobil.

## Testimonials

Core: Liste/Editor für Text-Testimonials (Zitat, Name-Modus, Kontext, Status). Pro-Folge: Tabs Medien-Testimonials, Trustmarks & Awards, Bewertungsleisten (Felder laut RC-015/RC-016).

## Quellen

Je Plattform eine Karte: Status-Pill (nicht verbunden / verbunden / Warnung / Fehler), Profil/App, letzter/nächster Sync, Aktionen. Core zeigt je Karte „Profil-Link“ und „Offizielles Widget einbetten“. Wizard laut UX-002 F3.

## Integrationen

Abschnitte: **Captain Suite** (Karten WooCommerce, Voucher, Pass, Affiliate, Form, Workflow: installiert/Version/Vertrag kompatibel/aktiv) · **Kundenspezifische Connectoren** (Tabelle: Name, Entwickler, Version, Fähigkeiten, Vertrag, Status, letzte Ausführung, wartende Jobs, Fehler; Aktionen Test/Pause/Trennen/Wiederholen) · **Entwickler** (Link zum öffentlichen Vertrag). Ohne Pro: Status lesbar, Aktionen mit Pro-Hinweis.

## Einstellungen

Tabs gemäß TODO „Empfohlene WP-Admin-Struktur“: Allgemein · Quellen & Synchronisierung · Anfragen & Zustellung · Einreichungen & Moderation · Darstellung · Datenschutz & Aufbewahrung · Zugriff & Rollen · Erweitert. Pro Tab: Settings-API-Tabelle, Hilfetext unter jedem Feld, „Erweitert“-Bereiche als `<details>`.

## Diagnose (read-only)

Abschnitte: Umgebung (WP/PHP/Woo/Yoast-Versionen, Multisite), Jobs (fällig/überfällig/laufend/fehlgeschlagen, Runner, Cron-Modus), Verbindungen (Tokenablauf ohne Secret, letzter Fehlercode), Verträge (Suite/Connector-Kompatibilität), SEO-Status, Button „Diagnosebericht herunterladen“ (ohne Secrets/PII).

## Lizenz [Pro]

Lizenzschlüssel-Feld (maskiert), Aktivieren/Deaktivieren, Status-Tabelle (Plan, Sites belegt/gesamt, Ablauf, Updates, Support-Level), Link Kundenkonto.
