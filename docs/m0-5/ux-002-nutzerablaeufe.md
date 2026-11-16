# UX-002 – Nutzerabläufe

**Status:** Entwurf – Validierung mit Zielnutzern (UX-001/UX-006) offen  
**Stand:** 2026-11-16  
**Grundlage:** [Konzept §8, §17](../../konzept.md), [RC-002 Scope](../m0/rc-002-scope-1-0.md)

Notation: `→` nächster Schritt, `⟂` Abbruch/Fehlerpfad, **[Pro]** nur mit Add-on und Recht.

## F1 – Erstinstallation (Core)

1. Plugin aktivieren → Mindestversionsprüfung (PHP/WP). ⟂ zu alt: Admin-Hinweis, Plugin bleibt inaktiv.
2. Weiterleitung **einmalig** zum Setup-Assistenten (nicht bei Bulk-Aktivierung oder Netzwerkaktivierung).
3. Schritt „Basisdaten“: Marken-/Firmenname, Standardsprache (vorbelegt aus WP), Zeitzone (aus WP).
4. Schritt „Erste Reviews“: Auswahl *eigene Reviews sammeln* (Formular-Block einfügen) / *externe Reviews manuell erfassen* / *Import (CSV)* / *überspringen*.
5. Schritt „Widget“: Grid-Widget anlegen mit Vorschau → Block/Shortcode kopieren.
6. Schritt „Datenschutz“: Datenschutzhinweis-Vorschlag ansehen, Aufbewahrungsfristen bestätigen (Defaults).
7. Abschluss → Dashboard mit Setup-Checkliste (offene Schritte bleiben sichtbar und wiederaufnehmbar).

## F2 – Review erfassen

**F2a lokal durch Kunden:** Besucher füllt Formular-Block → Validierung, Honeypot, Rate-Limit → Bestätigung „Danke, wird nach Prüfung veröffentlicht“ → Review im Status *ausstehend* → Moderator erhält Badge-Zähler im Menü → freigeben/ablehnen.  
⟂ Spam erkannt: stille Ablehnung, Eintrag im Spam-Zähler ohne Personendaten.

**F2b manuell extern durch Admin:** Reviews → „Externe Review hinzufügen“ → Plattform wählen → Originalbewertung inkl. Skala, erlaubter Textauszug, Datum, Profil-/Review-Link, Quellenhinweis (Pflicht) → Speichern als *freigegeben* oder *Entwurf*.

**F2c Import:** Reviews → Import → CSV hochladen → Spaltenzuordnung → Prüflauf (Fehler/Duplikate je Zeile) → Import ausführen → Batch-Protokoll mit „Rückgängig machen“.

## F3 – Plattform verbinden [Pro]

1. Quellen → „Quelle hinzufügen“ → Plattform wählen.
2. Info-Schritt: was benötigt wird (Kontotyp, Rolle, ggf. API-Freigabe), welche Daten gelesen werden, Cachefrist.
3. Verbindung: OAuth-Weiterleitung (Google) **oder** Zugangsdaten-Upload (Apple `.p8`, Play Service-Account-JSON).
4. Verbindungstest → Ergebnis mit Klartext.
5. Profil/App auswählen (nur berechtigte).
6. Sync-Optionen (Rückblick, Intervall innerhalb globaler Grenzen).
7. Vorschau der ersten Reviews → „Initialimport starten“ (Hintergrundjob, Fortschritt sichtbar).  
⟂ fehlende API-Freigabe/Rechte: Zustand „unberechtigter API-Zugang“ (UX-004) mit Fallback-Angebot „Profil-Link/Widget verwenden“.

Ohne Pro: Quellen-Seite zeigt Profil-Link- und Embed-Option je Plattform; native Verbindung als erklärter Pro-Hinweis.

## F4 – Syncfehler beheben [Pro]

Dashboard-Karte „Verbindungsfehler (1)“ → Quellen-Detail → Fehlerursache in Klartext (z. B. „Zugriff abgelaufen“) + empfohlene Aktion („Neu verbinden“, „Später erneut versuchen – Kontingent erschöpft bis 14:00“) → Aktion → erneuter Test → Status grün; Diagnose zeigt Verlauf. Gespeicherte Reviews bleiben bis zum Ablauf der Cachefrist sichtbar.

## F5 – Widget veröffentlichen

Widgets → „Neues Widget“ → Quellen wählen → Auswahlregel (alle freigegebenen / Filter / handverlesen) → Layout (Core: Grid/List; Pro: weitere) → Anzeigeoptionen → Live-Vorschau Desktop/Mobil → Speichern → Block einfügen oder Shortcode kopieren → Verwendungsorte werden in der Widget-Liste angezeigt.  
Alternativ direkt im Editor: Block „Review Captain Widget“ → vorhandenes Widget wählen oder neu anlegen.

## F6 – Anfrage konfigurieren [Pro]

1. Voraussetzung prüfen: Absender/Testmail in Einstellungen → ⟂ fehlt: Hinweis mit Link.
2. Anfragen → „Neue Kampagne“ → Auslöser (z. B. WooCommerce Bestellung *abgeschlossen*).
3. Zielplattform(en) – für alle Empfänger gleich; Hinweis zu Review-Gating-Verbot.
4. Verzögerung, Sprache, Vorlage (Vorschau mit Beispieldaten), Empfängerregel, Ausschlüsse (Refund/Storno).
5. Versandgrundlage: nur `consent` bzw. aktivierte Bestandskundenregel (RC-014).
6. Testmodus: Testmail an Testempfänger → Prüfung.
7. Aktivieren (standardmäßig inaktiv) → Queue zeigt geplante Einladungen mit Status und Skip-Gründen.

## F7 – Pro-Lizenz verwalten [Pro]

Review Captain → Lizenz → Schlüssel eingeben → Aktivieren → Status (Plan, Site-Limit belegt/gesamt, Ablaufdatum, Updates) → Deaktivieren (Site wechseln). ⟂ Site-Limit erreicht: Hinweis mit Link zum Kundenkonto. Ablauf: Banner 30/7 Tage vorher; nach Ablauf Pro-Aktionen deaktiviert, Daten bleiben lesbar/exportierbar.
