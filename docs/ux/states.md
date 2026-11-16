# Leer-, Fehler- und Erststart-Zustände (UX-004, UX-012)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Fehlercodes: Format `rc.<bereich>.<code>` (conventions.md). Texte sind Entwürfe für UX-013.

Grundsätze
- Jeder Zustand nennt **was passiert ist**, **was es für die Website bedeutet** und **den nächsten Schritt** (genau ein primärer Button).
- Fehler lassen **keine** Reviews verschwinden und löschen nichts.
- Keine internen Begriffe (TTL, Backoff, Lease, Entitlement, Payload) im Haupttext; Fehlercode klein darunter für den Support.
- Admin: WP-Notices (`notice-info|warning|error|success`), persistente Probleme zusätzlich als Status in der jeweiligen Liste. Frontend: niemals technische Fehler ausgeben.

## 1. Erststart (UX-012)

| Schritt | Inhalt | Erledigt wenn | Überspringen |
|---|---|---|---|
| 0 Willkommen | einmalige Weiterleitung nach Aktivierung zum Dashboard (nicht bei Bulk-Aktivierung/Netzwerk-Aktivierung/WP-CLI) | angezeigt | – |
| 1 Basisdaten | Markenname, Sprache bestätigen (vorbelegt) | gespeichert oder bestätigt | ja, Defaults bleiben |
| 2 Erste Reviews | Wahl: manuell anlegen · Formular einbinden · CSV importieren | ≥1 Review vorhanden | ja |
| 3 Quelle | Profil-Link/CTA hinzufügen; {Pro} Verbindung + **Verbindungstest** | Profil gespeichert, {Pro} Test ok | ja |
| 4 Erstes Widget | Widget-Wizard, Shortcode/Block | Widget auf veröffentlichter Seite erkannt | ja |
| 5 Datenschutz | Datenschutzhinweis, Fristen ansehen, Link zur WP-Datenschutzerklärung-Textvorlage | Tab einmal gespeichert/bestätigt | ja |
| 6 {Pro} Absender & Anfragepolitik | Absender + Testmail, Limits; erste Kampagne als **Entwurf** | Testmail ok | ja |

- Fortschritt je Site in `review_captain_onboarding` (Vorschlag: `{ step_states: {…}, dismissed: bool, started_at, completed_at }`), nie in `review_captain_settings`.
- Wiederaufnahme: Checkliste zeigt Status je Schritt; „Weiter einrichten“ springt zum ersten offenen Schritt.
- Überspringen speichert nichts, verändert keine Defaults und aktiviert keine Verbindung.
- Der Assistent ist ausblendbar und über Dashboard › „Einrichtung erneut anzeigen“ zurückholbar.

## 2. Leerzustände

| ID | Ort | Bedingung | Text (Entwurf) | Primäraktion |
|---|---|---|---|---|
| E1 | Dashboard | keine Reviews | „Noch keine Reviews. Legen Sie Ihr erstes Review an oder sammeln Sie Bewertungen mit einem Formular.“ | [Review hinzufügen] (sekundär: Formular einbinden, CSV importieren) |
| E2 | Reviews-Liste | keine Reviews | wie E1 | [Neu hinzufügen] |
| E3 | Reviews-Liste | Filter ohne Treffer | „Keine Reviews für diese Filter.“ | [Filter zurücksetzen] |
| E4 | Moderation | keine ausstehenden | „Alles erledigt – keine Reviews warten auf Prüfung.“ | – |
| E5 | Quellen | keine Quelle | „Noch keine Quelle. Fügen Sie einen Profil-Link hinzu, damit Besucher Sie auf Google & Co. bewerten können.“ {Pro}: „… oder verbinden Sie ein Konto.“ | [Quelle hinzufügen] |
| E6 | Widgets | kein Widget | „Noch kein Widget. Ein Widget zeigt ausgewählte Reviews auf Ihrer Website.“ | [Widget erstellen] |
| E7 | Widget-Vorschau | Auswahl leer | Vorschau des Frontend-Leerzustands (F1) + „Keine freigegebenen Reviews passen zu dieser Auswahl.“ | [Auswahl ändern] |
| E8 | Diagnose | keine Jobs | „Noch keine Hintergrundaufgaben gelaufen.“ | – |
| E9 | {Pro} Anfragen | keine Kampagne | „Noch keine Kampagne. Einladungen werden erst versendet, wenn Sie eine Kampagne testen und aktivieren.“ | [Kampagne erstellen] |

## 3. Fehler- und Warnzustände (Admin)

| ID | Situation | Code | Anzeige | Text (Entwurf) | Nächster Schritt |
|---|---|---|---|---|---|
| X1 | **Keine Quelle verbunden** (Pro aktiv) | – | Info auf Dashboard/Quellen | „Review Captain Pro ist aktiv, aber noch keine Quelle verbunden.“ | [Quelle verbinden] |
| X2 | **Unberechtigter API-Zugang** (Konto hat kein API-Recht/Tarif) | `rc.perm.failed` | Fehler am Profil | „{Plattform} erlaubt diesem Konto keinen Datenzugriff. Häufig fehlt eine API-Freigabe oder ein passender Tarif. Bis dahin können Sie Profil-Link oder offizielles Widget nutzen.“ | [Anleitung öffnen] · sekundär [Fallback einrichten] |
| X3 | **Abgelaufener/widerrufener Token** | `rc.auth.invalid` | Warnung Dashboard + Fehler am Profil; Sync pausiert nach `pause_after_auth_errors` | „Die Verbindung zu {Profil} ist abgelaufen. Ihre gespeicherten Reviews bleiben sichtbar, werden aber nicht mehr aktualisiert.“ | [Neu verbinden] |
| X4 | Token läuft bald ab (≤ 7 Tage, falls Anbieter Ablauf liefert) | – | Warnung am Profil | „Die Verbindung zu {Profil} läuft am {Datum} ab.“ | [Jetzt erneuern] |
| X5 | Kontingent erschöpft | `rc.quota.exceeded` | Warnung am Profil | „{Plattform} hat vorübergehend weitere Abrufe gesperrt. Nächster Versuch: {Zeit}.“ | keine Aktion nötig · [Seiten je Abruf senken] |
| X6 | Anbieter nicht erreichbar | `rc.net.timeout`, `rc.temp.unavailable` | Info; erst nach 3 Fehlversuchen Warnung | „{Plattform} ist gerade nicht erreichbar. Wir versuchen es automatisch erneut.“ | – |
| X7 | Profil beim Anbieter entfernt | `rc.source.unavailable` | Fehler am Profil | „Das Profil wurde bei {Plattform} nicht gefunden oder Ihr Zugriff wurde entfernt.“ | [Profil prüfen] · [Verbindung trennen] |
| X8 | Inkompatibler Adapter/Connector | `rc.contract.incompatible` | Fehler Diagnose/Integrationen | „{Name} {Version} passt nicht zu dieser Review-Captain-Version.“ | [Updates prüfen] |
| X9 | Ungültige Antwortdaten | `rc.data.invalid_payload` | Warnung Diagnose | „Einige Daten von {Plattform} konnten nicht gelesen werden und wurden übersprungen.“ | [Diagnosebericht herunterladen] |
| X10 | **Fehlende Reviews nach Sync** (0 importiert) | – | Info nach Initialimport | „Für {Profil} wurden keine Reviews gefunden. Prüfen Sie, ob das richtige Profil gewählt ist und der Zeitraum passt.“ | [Profil ändern] |
| X11 | **Queuefehler**: Jobs hängen / Cron läuft nicht | – | Warnung Dashboard + Diagnose | „Hintergrundaufgaben laufen nicht. Letzte Ausführung: {Zeit}. Ohne sie werden Quellen nicht aktualisiert und Fristen nicht angewendet.“ | [Diagnose öffnen] (zeigt Server-Cron-/WP-CLI-Beispiel) |
| X12 | **Queuefehler**: Job endgültig fehlgeschlagen (`dead`) | Job-`last_error_code` | Fehler Diagnose | „Eine Aufgabe ({Typ}) ist nach {n} Versuchen fehlgeschlagen.“ | [Erneut planen] |
| X13 | **Lizenz ohne Zugriff** (Pro aktiv, Lizenz fehlt/abgelaufen/Entitlement fehlt) | – | Warnung Dashboard, gesperrte Pro-Bereiche | „Ihre Review-Captain-Pro-Lizenz ist {nicht aktiviert/abgelaufen}. Synchronisierung und Anfragen sind pausiert. Alle Reviews und Einstellungen bleiben erhalten.“ | [Lizenz verwalten] |
| X14 | Lizenz: Site-Limit erreicht | – | Fehler Lizenzseite | „Diese Lizenz ist bereits auf {n} Websites aktiv.“ | [Kundenkonto öffnen] |
| X15 | Absender fehlt / nicht geprüft {Pro} | – | Warnung Anfragen | „Ohne geprüfte Absenderadresse wird nichts versendet.“ | [Testmail senden] |
| X16 | Mindestversion nicht erfüllt | – | Fehler-Notice global | „Review Captain benötigt PHP {x}/WordPress {y}. Aktuell: {a}/{b}.“ | [Mehr erfahren] |
| X17 | CSV-Prüflauf mit Fehlern | – | Tabelle im Importschritt | „{n} von {m} Zeilen enthalten Fehler und werden nicht importiert.“ | [Fehlerliste herunterladen] · [Nur gültige importieren] |
| X18 | Speichern ohne Berechtigung / Nonce abgelaufen | – | WP-Standardfehler | „Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und speichern Sie erneut.“ | [Neu laden] |

## 4. Frontend-Zustände

| ID | Situation | Ausgabe |
|---|---|---|
| F1 | Widget ohne passende Reviews | `display.empty_text` bzw. Standardtext in `<p class="rc-empty">`; kein Layoutsprung (keine leeren Karten) |
| F2 | Widget-ID existiert nicht / gelöscht | für Besucher: nichts; für Nutzer mit `review_captain_moderate`: Hinweisbox „Widget {id} nicht gefunden“ |
| F3 | Quelle gestört | letzter zulässiger Cache wird normal angezeigt, kein Fehlertext |
| F4 | Formular deaktiviert | „Bewertungen können derzeit nicht abgegeben werden.“ |
| F5 | Formular: Validierungsfehler | Fehlerliste oben (`role="alert"`, Fokus), Fehler am Feld (`aria-describedby`), Eingaben bleiben erhalten |
| F6 | Formular: Rate-Limit | „Sie haben bereits mehrere Bewertungen gesendet. Bitte versuchen Sie es später erneut.“ |
| F7 | Formular: Erfolg | „Danke! Ihre Bewertung wird nach Prüfung veröffentlicht.“ (bei `approved`: „… ist jetzt sichtbar.“) |
| F8 | Embed ohne Consent | Platzhalter mit Anbietername, Datenschutzhinweis und [Inhalt laden]; daneben Profil-Link |

## Abnahme

Entspricht UX-004 und UX-012, wenn:

- [ ] keine Quelle verbunden (X1/E5), unberechtigter API-Zugang (X2), abgelaufener Token (X3), fehlende Reviews (E1–E3, X10), Lizenz ohne Zugriff (X13) und Queuefehler (X11/X12) Text, Ort und genau einen nächsten Schritt haben;
- [ ] die Erstkonfiguration von Basisdaten über Quelle/Test bis zur ersten Kampagne führt, wiederaufnehmbar ist und Überspringen keine Daten verändert;
- [ ] Frontend-Zustände keine technischen Fehler zeigen und barrierefrei sind;
- [ ] die Texte in UX-013 mit Betreibern getestet wurden.
