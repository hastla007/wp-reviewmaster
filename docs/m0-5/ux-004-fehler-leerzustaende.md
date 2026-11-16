# UX-004 – Fehler- und Leerzustände

**Status:** Entwurf – Texte werden in UX-013 sprachlich getestet  
**Stand:** 2026-11-16

Jeder Zustand hat: **Überschrift** (was ist los), **Erklärung** (warum, ohne Fachjargon), **nächster Schritt** (Primärbutton), optional **Alternative**. Fehlercode (RC-030) erscheint klein und kopierbar für den Support.

| Zustand | Ort | Überschrift | Erklärung | Nächster Schritt | Alternative | Code |
|---|---|---|---|---|---|---|
| Keine Reviews | Reviews, Dashboard | Noch keine Bewertungen | Sammeln Sie eigene Bewertungen über ein Formular oder erfassen Sie vorhandene Bewertungen von anderen Plattformen. | Formular einfügen | Externe Review erfassen · CSV importieren | – |
| Keine Quelle verbunden | Quellen | Noch keine Plattform verbunden | Mit Review Captain Pro werden Bewertungen von Google, App Store und Google Play automatisch übernommen. Ohne Pro können Sie einen Profil-Link oder das offizielle Widget der Plattform einbinden. | Profil-Link hinzufügen | Pro ansehen | – |
| Unberechtigter API-Zugang | Quellen-Detail | Zugriff von der Plattform abgelehnt | Ihr Konto hat für diese Schnittstelle keine Berechtigung. Bei Google muss der Zugriff auf die Business-Profile-Schnittstelle einmalig beantragt werden. | Anleitung öffnen | Stattdessen Profil-Link verwenden | `auth_forbidden` |
| Token abgelaufen/widerrufen | Quellen, Dashboard | Verbindung muss erneuert werden | Der Zugriff ist abgelaufen oder wurde im Plattformkonto entzogen. Gespeicherte Bewertungen bleiben vorerst sichtbar. | Neu verbinden | Verbindung trennen | `auth_expired` |
| Kontingent erschöpft | Quellen-Detail | Plattform-Kontingent vorübergehend erschöpft | Die Plattform erlaubt aktuell keine weiteren Abrufe. Der nächste Versuch erfolgt automatisch um {Zeit}. | – (nur Info) | Abrufintervall anpassen | `quota_exceeded` |
| Plattform nicht erreichbar | Quellen-Detail | Plattform antwortet nicht | Wir versuchen es automatisch erneut. Ihre Website ist davon nicht betroffen. | Jetzt erneut versuchen | – | `provider_unavailable` |
| Keine Reviews im Profil | Quellen-Vorschau | In diesem Profil gibt es noch keine Bewertungen | Die Verbindung funktioniert. Sobald neue Bewertungen eingehen, werden sie übernommen. | Fertig | Anderes Profil wählen | – |
| Lizenz ohne Recht | Pro-Seiten | Diese Funktion ist in Ihrer Lizenz nicht enthalten | Ihre Lizenz ist aktiv, umfasst diese Funktion aber nicht (z. B. Version noch nicht verfügbar). | Lizenz prüfen | – | `entitlement_missing` |
| Lizenz abgelaufen | alle Pro-Seiten (Banner) | Pro-Lizenz abgelaufen | Automatische Abrufe und Anfragen sind pausiert. Ihre Bewertungen bleiben erhalten und exportierbar. | Lizenz verlängern | Daten exportieren | `license_expired` |
| Queue-Fehler (einzelner Job) | Anfragen → Queue | Einladung konnte nicht gesendet werden | Grund: {Klartext}. Es wurde keine doppelte Einladung erzeugt. | Erneut versuchen | Überspringen | z. B. `transport_failed` |
| Queue blockiert (Cron) | Diagnose, Dashboard | Hintergrundaufgaben laufen verspätet | {n} Aufgaben sind überfällig. WordPress führt geplante Aufgaben nur bei Besuchen aus. | Anleitung System-Cron | Jetzt ausführen | `cron_delayed` |
| Absender nicht eingerichtet | Anfragen | Absender fehlt | Einladungen können erst gesendet werden, wenn Absendername und -adresse festgelegt und per Testmail geprüft sind. | Einstellungen öffnen | – | `sender_missing` |
| Keine Versandberechtigung | Queue-Zeile | Übersprungen: keine Erlaubnis | Für diese Person liegt keine Einwilligung vor. | – | – | `consent_missing` |
| Quell-Plugin fehlt | Integrationen | {Plugin} ist nicht aktiv | Wartende Einladungen aus dieser Quelle sind pausiert. | Plugin aktivieren | Einladungen abbrechen | `source_unavailable` |
| Inkompatibler Vertrag | Integrationen | Version nicht kompatibel | {Plugin} {Version} nutzt eine Schnittstelle, die diese Version von Review Captain nicht kennt. | Plugins aktualisieren | – | `incompatible_contract` |
| Widget ohne Treffer | Frontend | (nichts ausgeben; Admin sieht Hinweis im Editor) | Im Editor: „Dieses Widget zeigt aktuell keine Bewertungen – Filter prüfen.“ | Widget bearbeiten | – | – |
| Verschlüsselungsschlüssel fehlt | Quellen, Diagnose | Zugangsdaten können nicht gelesen werden | Der Schlüssel zum Entschlüsseln fehlt oder wurde geändert (z. B. nach Umzug). | Anleitung | Neu verbinden | `crypto_key_missing` |

Regeln: Frontend zeigt Besuchern **nie** Fehlermeldungen; leere Widgets rendern nichts (kein Layout-Shift). Fehlerzustände entfernen keine Daten.
