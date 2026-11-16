# Einstellungsvertrag

**Status:** Entwurf  
**Stand:** 2026-11-16  
**Deckt ab:** UX-010, UX-011, UX-012, SET-002, SET-003, SET-004, SET-005, SET-006  
**Grundlage:** [Einstellungsinventar](einstellungsinventar.md), [UX-003 Wireframes](../m0-5/ux-003-admin-wireframes.md)

## 1. Informationsarchitektur (UX-010 / SET-002)

Seite **Review Captain → Einstellungen**, `nav-tab-wrapper`, URL-Parameter `tab`:

| # | Tab (`tab=`) | Option-Gruppe | Abschnitte (Reihenfolge) |
|---|---|---|---|
| 1 | Allgemein (`general`) | `review_captain_general` | Marke & Sprache · Standards für neue Reviews |
| 2 | Quellen & Synchronisierung (`sync`) | `review_captain_sync` | Abrufrhythmus · Mengen · Fehlerverhalten · *Erweitert* |
| 3 | Anfragen & Zustellung (`requests`) | `review_captain_requests` | Absender & Test · Versandzeiten & Grenzen · Versandgrundlage · Zustellprobleme · *Erweitert: Transport* |
| 4 | Einreichungen & Moderation (`submissions`) | `review_captain_submissions` | Moderation · Spam-Schutz · Formularfelder · Uploads |
| 5 | Darstellung (`display`) | `review_captain_display` | Inhalte · Leere Widgets · *Erweitert: Assets* |
| 6 | Datenschutz & Aufbewahrung (`privacy`) | `review_captain_privacy` | Aufbewahrungsfristen · Externe Inhalte · Datenschutzhinweis · Gefahrenzone (Reset/Löschen) |
| 7 | Zugriff & Rollen (`access`) | `review_captain_access` | Rollenmatrix · Multisite |
| 8 | Erweitert (`advanced`) | `review_captain_advanced` | Hintergrundaufgaben · Debug · Konfiguration im-/exportieren |

Abgrenzung: Quellen (Zugangsdaten/Profile), Anfragen (Kampagnen), Widgets (Darstellung je Widget), Integrationen (Adapter/Connectoren) und Diagnose (read-only) sind eigene Seiten. Tabs ohne nutzbare Funktion (z. B. Anfragen ohne Pro) bleiben sichtbar, zeigen aber einen erklärenden Hinweis statt Formularfeldern – gespeicherte Werte bleiben unverändert.

## 2. Einfach/Erweitert und Kontextfelder (UX-011 / SET-009)

- Jeder Abschnitt zeigt standardmäßig nur Felder mit Markierung `level: basic`. Felder `level: advanced` liegen in `<details class="rc-advanced">` mit Überschrift „Erweiterte Optionen“; Zustand pro Nutzer in `user_meta` gemerkt.
- Kontextregeln (deklarativ im Schema, `show_if`):
  - `reply_to`, `transport`-Details nur wenn `sender_email` gesetzt.
  - `existing_customer_basis` erklärt Voraussetzungen und zeigt Pflicht-Hinweis, wenn aktiviert.
  - Upload-Felder erst, wenn ein Recht/Feature für Uploads aktiv ist.
  - Pro-Felder ohne Recht: Feld deaktiviert (`disabled`), Hinweis „Benötigt Review Captain Pro – {Funktion}“, Wert bleibt erhalten.
- Ohne JavaScript sind alle Felder sichtbar (progressive Erweiterung).

## 3. Erstkonfiguration (UX-012 / SET-008)

Setup-Assistent als eigene Admin-Seite ohne Menüeintrag (`review-captain-setup`), Fortschritt in Option `review_captain_setup` (`{step: status}`; Status `todo|done|skipped`).

| Schritt | Inhalt | Pflicht | Speichert in |
|---|---|---|---|
| 1 Basisdaten | Markenname, Sprache | nein (Defaults) | `general` |
| 2 Absender | Absendername/-adresse, Testmail | nein; nur nötig für Anfragen | `requests` |
| 3 Erste Reviews / Quelle | Formular einfügen, manuell erfassen, CSV, Profil-Link; [Pro] Quelle verbinden + Test | nein | Objekte |
| 4 Anfragepolitik [Pro] | Versandgrundlage, Zeitfenster, Cooldown bestätigen | nein | `requests` |
| 5 Erstes Widget | Widget anlegen, Block/Shortcode kopieren | nein | Widget |

Regeln: Jeder Schritt einzeln speicherbar, „Überspringen“ setzt `skipped`, Rückkehr jederzeit über Dashboard-Checkliste. Keine externe Verbindung oder Kampagne wird automatisch aktiviert. Abbrechen verliert keine bereits gespeicherten Schritte.

## 4. Optionsschema und Migration (SET-003)

Jedes Feld ist im Code deklarativ beschrieben (`ReviewCaptain\Settings\Schema`):

```php
'sender_email' => [
    'group'      => 'requests',
    'type'       => 'email',          // string|int|bool|enum|email|url|time|text|map
    'default'    => '',
    'sanitize'   => 'sanitize_email',
    'validate'   => 'is_email_or_empty',
    'scope'      => 'site',           // site|network
    'privacy'    => 1,                // 0|1|S
    'owner'      => 'core',           // core|pro
    'entitlement'=> 'review.review_requests', // nur Anzeige-/Wirkungsbedingung, nie Löschgrund
    'level'      => 'basic',
    'overridable'=> ['campaign'],
    'since'      => '1.0.0',
],
```

- Speicherung je Gruppe als ein Array-Option `review_captain_{group}`; `autoload` nur für `general` und `display` (Frontend-relevant).
- `review_captain_settings_version` (int). Migrationen sind nummerierte, idempotente Callables (`Migrations\Settings\V2::run()`), laufen beim Admin-Upgrade bzw. per WP-CLI, nie im Frontend.
- Fehlende Schlüssel werden beim Lesen mit dem Default ergänzt (keine Schreiboperation beim Lesen).
- Entfernte Schlüssel werden in einer Migration gelöscht, nie stillschweigend weiterverwendet.
- Multisite: `scope: network` liegt in `wp_sitemeta`, alles andere je Site.

## 5. Vererbung und Overrides (SET-004)

Reihenfolge (spätere gewinnt, sofern erlaubt): **Default → Global → Profil → Kampagne/Formular → Widget**.

- Ein Override ist nur für Schlüssel zulässig, die das Schema als `overridable` für diese Ebene markiert.
- Einschränkende Overrides (`nur strenger/enger/länger`) werden validiert; ein lockernder Wert wird abgelehnt und der globale Wert gilt. Beispiele: Kampagne darf `send_window` verengen, `cooldown_days` verlängern, `existing_customer_basis` nur ausschalten.
- UI zeigt neben überschriebenen Feldern „Wirksam: {Wert} (aus Kampagne)“ bzw. „Übernommen aus globaler Einstellung“.
- API: `Settings::effective( string $key, array $context )` liefert `['value'=>…, 'source'=>'global|profile|campaign|form|widget|default']`.

## 6. Sichere Defaults und Reset (SET-005)

Sichere Defaults (nicht verhandelbar ohne bewusste Änderung): neue Reviews `pending`; Versand nur mit `consent` (Bestandskundenregel aus); `sender_email` leer = kein Versand; Erinnerungen aus; externe Inhalte click-to-load; Avatare aus; Quellenattribution fest; Daten bei Deinstallation behalten.

| Aktion | Wirkung | Bestätigung | Capability |
|---|---|---|---|
| Tab zurücksetzen | Gruppenoption auf Defaults | Dialog | `rc_manage_settings` |
| Alle Einstellungen zurücksetzen | alle Gruppen auf Defaults; **keine** Reviews, Quellen, Kampagnen, Widgets betroffen | Dialog + Eingabe „ZURÜCKSETZEN“ | `rc_manage_settings` |
| Verbindung trennen | Quellen-Seite, siehe Konzept §7.9 | Dialog | `rc_connect_sources` |
| Alle Plugin-Daten löschen | separater Bereich „Gefahrenzone“ im Datenschutz-Tab; löscht Tabellen/Optionen der Site sofort | Dialog + Eingabe Website-Domain | `manage_options` + `rc_manage_privacy` |

## 7. Core/Pro-Rechtematrix (SET-006)

| Gruppe | Besitzer | Wirkt nur mit Recht | Verhalten ohne Recht / nach Lizenzablauf |
|---|---|---|---|
| general, submissions, display, privacy, access, advanced | Core | – | voll nutzbar |
| sync | Core (Speicher) | `review.platform_sync` | sichtbar, deaktiviert, Werte bleiben |
| requests | Core (Speicher) | `review.review_requests` | sichtbar, deaktiviert, Werte bleiben; Opt-out-Liste bleibt einsehbar und exportierbar |
| Widget-Layouts Pro | Pro | `review.advanced_widgets` | Widget fällt auf Grid zurück, Einstellung bleibt gespeichert |

Kein Core-Feld wird bei Lizenzänderungen geschrieben oder geleert. Pro speichert keine eigenen Kopien der Core-Optionen.
