# Einstellungsinventar (SET-001, UX-009)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Quelle: konzept.md §5, §8, §9; TODO.md „Plugin-Einstellungen“; [option-schema.md](option-schema.md).

Ziel: jede geplante Option hat **genau einen fachlichen Besitzer** (Admin-Seite) und **einen Geltungsbereich**. Was an einem Objekt (Quelle/Profil, Kampagne, Formular, Widget) gepflegt wird, erscheint nicht zusätzlich in den globalen Einstellungen – global gibt es höchstens den **Standardwert**, den das Objekt überschreiben darf ([inheritance.md](inheritance.md)).

Legende
- **Seite:** Admin-Seite, auf der das Feld bearbeitet wird. `Einstellungen › <Tab>` = Tab der Einstellungsseite.
- **Scope:** `global` (Site-Option) · `profil` · `kampagne` · `formular` · `widget`. Multisite: alle Werte site-lokal.
- **Owner:** `Core` oder `Pro` (Pro = Feld wird vom Add-on angemeldet, nur mit Pro sichtbar und bearbeitbar; ohne Pro zeigt der Core an seiner Stelle höchstens eine graue Hinweiszeile, SET-RC-06).
- **Speicherort:** `S` = `review_captain_settings[tab][key]`; `P` = Tabelle `profiles`; `W` = Widget-Definition (Speicherort OFFEN, siehe unten); `B` = Block-/Shortcode-Attribut; `K` = Kampagne (Pro-Speicher).
- **Cap:** Capability zum Ändern (immer zusätzlich Nonce).

## 1. Globale Einstellungen (`review_captain_settings`)

| Schlüssel | Nutzerfrage | Seite | Scope | Owner | Sicherer Default | Cap | Speicher |
|---|---|---|---|---|---|---|---|
| `general.brand_name` | Welcher Name steht in Vorlagen und Hinweisen? | Einstellungen › Allgemein | global | Core | `''` → Websitetitel | manage_settings | S |
| `general.date_format` | Wie werden Daten angezeigt? | Einstellungen › Allgemein | global (Widget darf überschreiben) | Core | `''` → WP-Format | manage_settings | S |
| `general.new_review_status` | Sollen neue eingereichte Reviews sofort sichtbar sein? | Einstellungen › Allgemein | global | Core | `pending` | manage_settings | S |
| `general.show_pro_hints` | Sollen graue Hinweise auf Review Captain Pro erscheinen? (SET-RC-06) | Einstellungen › Allgemein | global | Core | `true` | manage_settings | S |
| `sources.sync_window_start` | Wann darf synchronisiert werden (Beginn)? | Einstellungen › Quellen & Synchronisierung | global (Profil darf einschränken) | Core | `02:00` | manage_settings | S |
| `sources.sync_window_end` | … (Ende)? | Einstellungen › Quellen & Synchronisierung | global | Core | `05:00` | manage_settings | S |
| `sources.max_pages_per_run` | Wie viel wird pro Lauf höchstens abgerufen? | Einstellungen › Quellen & Synchronisierung | global (Profil darf senken) | Core | `5` | manage_settings | S |
| `sources.pause_after_auth_errors` | Wann wird eine Quelle nach Anmeldefehlern pausiert? | Einstellungen › Quellen & Synchronisierung | global | Core | `3` | manage_settings | S |
| `sources.removed_review_behavior` | Was passiert lokal, wenn ein Review bei der Quelle verschwindet? | Einstellungen › Quellen & Synchronisierung | global (Adapter darf verschärfen) | Core | `tombstone` | manage_settings | S |
| `requests.sender_name` | Welcher Absendername steht in Einladungen? | Einstellungen › Anfragen & Zustellung | global | Pro | `''` → Markenname | manage_settings + manage_requests | S |
| `requests.sender_email` | Von welcher Adresse wird versendet? | Einstellungen › Anfragen & Zustellung | global | Pro | `''` → kein Versand | manage_settings + manage_requests | S |
| `requests.send_window_start` | Ab wann darf versendet werden? | Einstellungen › Anfragen & Zustellung | global (Kampagne darf einschränken) | Pro | `09:00` | manage_settings + manage_requests | S |
| `requests.send_window_end` | Bis wann? | Einstellungen › Anfragen & Zustellung | global | Pro | `18:00` | manage_settings + manage_requests | S |
| `requests.daily_limit` | Wie viele Einladungen höchstens pro Tag? | Einstellungen › Anfragen & Zustellung | global (Kampagne darf senken) | Pro | `200` | manage_settings + manage_requests | S |
| `requests.cooldown_days` | Wie lange keine weitere Einladung an dieselbe Person? | Einstellungen › Anfragen & Zustellung | global (Kampagne darf verlängern) | Pro | `90` | manage_settings + manage_requests | S |
| `requests.reminders_enabled` | Dürfen Kampagnen Erinnerungen senden? | Einstellungen › Anfragen & Zustellung | global (Kampagne nur, wenn global an) | Pro | `false` | manage_settings + manage_requests | S |
| `submissions.enabled` | Dürfen Besucher selbst Reviews einreichen? | Einstellungen › Einreichungen & Moderation | global | Core | `true` | manage_settings | S |
| `submissions.require_consent` | Muss eine Veröffentlichungs-Einwilligung abgefragt werden? | Einstellungen › Einreichungen & Moderation | global (Formular darf nicht lockern) | Core | `true` | manage_settings | S |
| `submissions.honeypot` | Spam-Falle aktiv? | Einstellungen › Einreichungen & Moderation | global | Core | `true` | manage_settings | S |
| `submissions.rate_limit_per_hour` | Wie viele Einreichungen pro Stunde und Absender? | Einstellungen › Einreichungen & Moderation | global | Core | `5` | manage_settings | S |
| `submissions.min_text_length` | Wie kurz darf ein Text sein? | Einstellungen › Einreichungen & Moderation | global (Formular darf überschreiben) | Core | `20` | manage_settings | S |
| `submissions.max_text_length` | Wie lang darf ein Text sein? | Einstellungen › Einreichungen & Moderation | global (Formular darf senken) | Core | `5000` | manage_settings | S |
| `submissions.allow_title` | Titel-Feld anbieten? | Einstellungen › Einreichungen & Moderation | global (Formular darf überschreiben) | Core | `true` | manage_settings | S |
| `submissions.notify_admin` | Admin über neue Einreichungen informieren? | Einstellungen › Einreichungen & Moderation | global | Core | `true` | manage_settings | S |
| `submissions.min_seconds` | Wie schnell darf ein Formular frühestens abgeschickt werden? | Einstellungen › Einreichungen & Moderation | global (Filter gewinnt) | Core | `3` | manage_settings | S |
| `submissions.hold_words` | Welche Wörter halten eine Bewertung zur Prüfung zurück? | Einstellungen › Einreichungen & Moderation | global (Filter gewinnt) | Core | `''` | manage_settings | S |
| `submissions.hold_links` | Bewertungen mit Links zur Prüfung zurückhalten? | Einstellungen › Einreichungen & Moderation | global (Filter gewinnt) | Core | `false` | manage_settings | S |
| `submissions.block_email_domains` | Welche E-Mail-Domains dürfen nicht bewerten? | Einstellungen › Einreichungen & Moderation | global (Filter gewinnt) | Core | `''` | manage_settings | S |
| `submissions.captcha_provider` | Zusätzlicher Spam-Schutz (eingebaute Rechenprüfung ohne Dritte)? | Einstellungen › Einreichungen & Moderation | global | Core | `none` | manage_settings | S |
| `submissions.require_purchase`, `submissions.purchase_max_age_days` | Dürfen nur bestätigte Käufer bewerten, und wie lange nach dem Kauf? | Einstellungen › Einreichungen & Moderation | global (Quelle per Filter) | Core | aus, 0 = keine Frist | manage_settings | S |
| `submissions.trusted_proxy_header` | Welche Kopfzeile nennt die echte Absenderadresse hinter einem Proxy? | Einstellungen › Einreichungen & Moderation | global (Filter gewinnt) | Core | `none` | manage_settings | S |
| `submissions.correction_link_hours` | Wie lange gilt ein Korrektur-Link? | Einstellungen › Einreichungen & Moderation | global | Core | `1` | manage_settings | S |
| `submissions.photo_max_edge` | Auf welche längste Kante werden Fotos verkleinert? | Einstellungen › Einreichungen & Moderation | global (Filter gewinnt) | Core | `1600` | manage_settings | S |
| `display.show_stars` | Sterne anzeigen? | Einstellungen › Darstellung | global → widget | Core | `true` | manage_settings | S |
| `display.show_date` | Datum anzeigen? | Einstellungen › Darstellung | global → widget | Core | `true` | manage_settings | S |
| `display.show_author` | Namen der Verfasser anzeigen? | Einstellungen › Darstellung | global → widget | Core | `true` | manage_settings | S |
| `display.show_avatar` | Profilbilder anzeigen? | Einstellungen › Darstellung | global → widget | Core | `false` | manage_settings | S |
| `display.date_style` | „vor 3 Tagen“ oder „12.11.2026“? | Einstellungen › Darstellung | global → widget | Core | `absolute` | manage_settings | S |
| `display.empty_text` | Was steht da, wenn es (noch) keine Reviews gibt? | Einstellungen › Darstellung | global → widget | Core | `''` → Standardtext | manage_settings | S |
| `display.reduced_motion` | Bewegung/Auto-Rotation vermeiden? | Einstellungen › Darstellung | global → widget (nur verschärfen) | Core | `true` | manage_settings | S |
| `display.load_css` | Plugin-CSS laden? | Einstellungen › Darstellung | global | Core | `true` | manage_settings | S |
| `display.rating_display` | Sterne, Zahl oder beides? | Einstellungen › Darstellung | global | Core | `both` | manage_settings | S |
| `display.decimal_places` | Wie viele Nachkommastellen bei Durchschnitten? | Einstellungen › Darstellung | global | Core | `1` | manage_settings | S |
| `display.star_color`, `display.accent_color` | Welche Stern- und Akzentfarbe? | Einstellungen › Darstellung | global | Core | `''` → Theme/CSS | manage_settings | S |
| `display.widget_limit`, `widget_layout`, `widget_columns`, `widget_heading_level`, `widget_new_tab` | Was gilt für Widgets ohne eigenen Wert? | Einstellungen › Darstellung | global → widget (Widget gewinnt, Filter vor Einstellung) | Core | 6, grid, 3, h2, aus | manage_settings | S |
| `display.allow_min_rating`, `display.filter_notice` | Dürfen Widgets nach Sternen filtern, und welcher Hinweis steht dann dabei? | Einstellungen › Darstellung | global (Filter gewinnt) | Core | an, `''` → Standardtext | manage_settings | S |
| `display.schema_contexts`, `display.schema_min_reviews` | Für welche Inhalte und ab wie vielen Bewertungen strukturierte Daten? | Einstellungen › Darstellung | global (nur einschränken) | Core | alle drei, 1 | manage_settings | S |
| `privacy.retention_external_days` | Wie lange bleiben externe Reviews ohne Bestätigung gespeichert? | Einstellungen › Datenschutz & Aufbewahrung | global (Adapter darf verkürzen) | Core | `30` | manage_settings + manage_privacy | S |
| `privacy.retention_rejected_days` | Wann werden abgelehnte und zurückgezogene Reviews gelöscht? | Einstellungen › Datenschutz & Aufbewahrung | global | Core | `90` | manage_settings + manage_privacy | S |
| `privacy.retention_logs_days` | Wie lange bleiben Protokolle? | Einstellungen › Datenschutz & Aufbewahrung | global | Core | `90` | manage_settings + manage_privacy | S |
| `privacy.retention_audit_days` | Wie lange bleibt das Änderungsprotokoll der Einstellungen? (SET-RC-17) | Einstellungen › Datenschutz & Aufbewahrung | global | Core | `365` | manage_settings + manage_privacy | S |
| `privacy.retention_jobs_days` | Wie lange bleiben erledigte Hintergrundjobs? | Einstellungen › Datenschutz & Aufbewahrung | global | Core | `30` | manage_settings + manage_privacy | S |
| `privacy.retention_contact_days` | Wann wird die E-Mail-Adresse zu einer Bewertung oder Produktfrage gelöscht? | Einstellungen › Datenschutz & Aufbewahrung | global | Core | `365` für neue Installationen, bestehende `0` = unbegrenzt (E5, Rechtsprüfung offen) | manage_settings + manage_privacy | S |
| `privacy.retention_approved_days` | Werden freigegebene Bewertungen nach einer Frist gelöscht? | Einstellungen › Datenschutz & Aufbewahrung | global | Core | `0` = nie | manage_settings + manage_privacy | S |
| `privacy.retention_corrections_days` | Wie lange bleiben entschiedene Korrekturfassungen? | Einstellungen › Datenschutz & Aufbewahrung | global | Core | `90` | manage_settings + manage_privacy | S |
| `privacy.erase_mode` | Was passiert bei einer Löschanfrage mit eigenen Reviews? | Einstellungen › Datenschutz & Aufbewahrung | global | Core | `anonymize` | manage_settings + manage_privacy | S |
| `privacy.delete_data_on_uninstall` | Beim Löschen des Plugins alle Daten entfernen? (Einschalten nur mit Bestätigungswort, SET-RC-20) | Einstellungen › Datenschutz & Aufbewahrung | global (site) | Core | `false` | manage_settings + manage_privacy | S |
| `privacy.delete_data_confirmed_at` | intern: Zeitpunkt der Bestätigung (UTC) | – (nur Anzeige „Bestätigt am …“) | global (site) | Core | `''` | – | S |
| `privacy.privacy_notice` | Welcher Hinweis steht unter dem Formular? | Einstellungen › Datenschutz & Aufbewahrung | global (Formular darf überschreiben) | Core | `''` → Standardtext | manage_settings + manage_privacy | S |
| `access.moderate_roles` | Wer darf Reviews moderieren? | Einstellungen › Zugriff & Rollen | global | Core | administrator, editor | manage_settings + promote_users | S (+ Rollen-Caps) |
| `access.settings_roles` | Wer darf diese Einstellungen ändern? | Einstellungen › Zugriff & Rollen | global | Core | administrator | manage_settings + promote_users | S (+ Rollen-Caps) |
| `access.sources_roles`, `requests_roles`, `reports_roles`, `trust_roles`, `privacy_roles` | Wer hat die übrigen fünf Rechte? (Matrix, SET-RC-16) | Einstellungen › Zugriff & Rollen | global | Core | administrator | manage_settings + promote_users + das jeweilige Recht | S (+ Rollen-Caps) |
| `advanced.job_time_budget` | Wie lange darf ein Hintergrundlauf dauern? | Einstellungen › Erweitert | global | Core | `20` | manage_settings | S |
| `advanced.lease_seconds` | Wann gilt ein hängender Job als verwaist? | Einstellungen › Erweitert | global | Core | `300` | manage_settings | S |
| `advanced.debug_logging` | Ausführliches Protokoll zur Fehlersuche? | Einstellungen › Erweitert | global | Core | `false` | manage_settings | S |
| `advanced.use_action_scheduler` | Action Scheduler statt WP-Cron nutzen, falls vorhanden? | Einstellungen › Erweitert | global | Core | `true` | manage_settings | S |

## 2. Objekt-Einstellungen (nicht in `review_captain_settings`)

| Einstellung | Nutzerfrage | Seite | Scope | Owner | Default | Cap | Speicher |
|---|---|---|---|---|---|---|---|
| Plattform, Label, Profil-URL | Welches Profil ist das? | Quellen | profil | Core | – (Pflicht) | manage_sources | P |
| CTA-Text „Auf … bewerten“ | Welcher Text steht auf dem Bewertungsknopf? | Quellen (Profil) | profil | Core | „Bewerten Sie uns auf {Plattform}“ | manage_sources | P (`meta`) |
| Credentials/OAuth, API-Scope | Mit welchem Konto wird verbunden? | Quellen › Verbinden | profil | Pro | – | manage_sources | verschlüsselt, Pro-Speicher (nicht in Option) |
| Sync aktiv / Intervall / Fenster-Override | Wann wird diese Quelle abgeglichen? | Quellen (Profil) | profil | Pro | erbt global | manage_sources | P (`meta`) |
| Offizielles Widget-Embed (TrustBox/Trustbadge) aktiv | Soll das Anbieter-Widget eingebettet werden? | Quellen (Profil) | profil | Core (Embed-Fallback) | aus | manage_sources | P (`meta`), siehe [widget-fallbacks](../providers/widget-fallbacks.md) |
| Widget: Typ, Quellen, Auswahlregel, Layout, Anzahl | Was zeigt dieses Widget? | Widgets | widget | Core (Grid/Liste), Pro (weitere Layouts) | Grid, 6 Einträge | moderate | W |
| Widget: Anzeige-Overrides (`show_*`, `date_style`, `empty_text`) | Abweichend vom Standard anzeigen? | Widgets | widget | Core | erbt `display.*` | moderate | W / B |
| Formular: Kontext (`context_type`/`context_id`), Felder, Datenschutztext | Wofür sammelt dieses Formular? | Block-Editor / Shortcode | formular | Core | erbt `submissions.*` | edit_posts (Block) | B |
| Kampagne: Auslöser, Verzögerung, Ziel, Vorlage, Limits | Wann und wohin wird eingeladen? | Anfragen | kampagne | Pro | erbt `requests.*`, Versand aus | manage_requests | K |
| Connector-Konfiguration | Wie arbeitet dieser Connector? | Integrationen | connector | Pro (Manager) | nach Connector-Schema | manage_settings | Pro-Speicher |
| Lizenzschlüssel | Welche Lizenz ist aktiv? | Lizenz | global | Pro | – | manage_options | Pro-Lizenzclient |

**Speicherort Widget-Definitionen (W): OFFEN.** conventions.md listet keine Widget-Tabelle (konzept.md §10 skizzierte `wp_rc_widgets`). Vorschlag: nicht-öffentlicher CPT `rc_widget` mit Post-Meta oder eigene Tabelle per ADR.

## 3. Entfernte bzw. bewusst nicht angelegte Optionen (Duplikate)

| Kandidat (Quelle) | Entscheidung | Begründung |
|---|---|---|
| Zeitzone (TODO Allgemein) | keine Option | WordPress-Zeitzone ist maßgeblich, wird nur angezeigt |
| „Standardquelle“, „Standarddarstellung“ (konzept §8.9) | keine Option | Quelle wählt das Widget; Darstellung = Tab Darstellung |
| Sync-Intervall global (konzept §8.9) | nicht global | gehört zum Profil (Pro); global nur Fenster/Limits |
| „Quellenlabel anzeigen“ | keine Option | Attribution externer Reviews ist Pflicht |
| CSS-Variablen im Admin (konzept §8.9 „Darstellung“) | keine Option in 1.0 | Theme setzt `--rc-*` per CSS/theme.json ([design-system](../ux/design-system.md)); OFFEN für Pro |
| Diagnose als Tab (konzept §8.9 Nr. 8) | eigene Seite | read-only, eigene Capability `view_reports` |
| Protokollaufbewahrung im Tab Anfragen (konzept §8.9) | verschoben | zentral `privacy.retention_logs_days`; Einladungsdaten-Frist liefert Pro im Tab Datenschutz |
| Erlaubte Uploadtypen/-größen (TODO Einreichungen) | nicht in Core 1.0 | Core-Formular nimmt keine Uploads an; Pro-Testimonials |
| Testempfänger, Bounce-Verhalten, Opt-out-Sperre (TODO Anfragen) | Pro, nicht im Core-Schema | Pro ergänzt per `review_captain_settings_schema`; Opt-out-Sperre ist feste Regel, kein Schalter |
| Aufbewahrung Einladungsdaten/Medien (TODO Datenschutz) | Pro | Pro ergänzt Felder im Tab Datenschutz |
| Konfiguration Im-/Export, Datenlöschung (TODO Erweitert) | Aktion, keine Option | Buttons im Tab Erweitert bzw. Datenschutz, siehe [defaults-reset.md](defaults-reset.md) |

## Abnahme

Entspricht SET-001 und UX-009, wenn:

- [ ] jede Option aus §1 und §2 Nutzerfrage, Seite, Geltungsbereich, Owner (Core/Pro), sicheren Default, Capability und Speicherort hat;
- [ ] jedes Objekt (Quelle, Kampagne, Formular, Widget) genau einen fachlichen Besitzer hat und kein Feld an zwei Stellen bearbeitbar ist;
- [ ] die Liste der entfernten Duplikate (§3) mit Produkt abgestimmt ist;
- [ ] der offene Speicherort für Widget-Definitionen per ADR entschieden ist.
