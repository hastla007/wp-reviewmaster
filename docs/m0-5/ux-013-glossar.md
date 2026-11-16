# UX-013 – Begriffe und Hilfetexte

**Status:** Entwurf – Verständnistest mit Betreibern offen  
**Stand:** 2026-11-16

## Regeln

- Sichtbare Texte nutzen Alltagssprache; interne Begriffe (rechte Spalte) erscheinen nur in Code, Doku für Entwickler und Diagnose-Details.
- Deutsch informell („Sie“-frei vermeiden wir nicht: Standard ist **Sie**; `de_DE_formal` identisch, `de_DE` verwendet ebenfalls Sie, da B2B-Zielgruppe).
- Jede Option hat einen Hilfetext nach dem Muster **Wirkung · Beispiel · Risiko/Hinweis**.

## Glossar

| Sichtbarer Begriff (DE) | Visible term (EN) | Interner Begriff | Erklärung für Hilfetexte |
|---|---|---|---|
| Bewertung | Review | review | Eine Kundenmeinung mit Sternen und/oder Text. |
| Eigene Bewertung | Review on this site | local review | Wurde auf Ihrer Website abgegeben. |
| Externe Bewertung | Review from another platform | external snapshot | Stammt von Google, Trustpilot usw. und wird mit Quelle angezeigt. |
| Erfahrungsbericht | Testimonial | testimonial | Von Ihnen kuratierter Kundenbericht, getrennt von Plattformbewertungen. |
| Quelle | Source | provider | Plattform, von der Bewertungen stammen. |
| Profil | Profile | profile / location / business unit / app | Ihr Eintrag auf einer Plattform (Standort, Shop, App). |
| Abruf | Sync | sync run | Übernahme neuer und geänderter Bewertungen. |
| Abrufrhythmus | Sync frequency | interval | Wie oft Review Captain nachsieht. |
| Kontingent | API limit | quota | Wie viele Abrufe die Plattform erlaubt. |
| Später erneut versuchen | Retry later | backoff / retry | Automatischer neuer Versuch nach einer Wartezeit. |
| Aktualität | Freshness | TTL / cache | Wie lange eine externe Bewertung ohne neuen Abruf gezeigt werden darf. |
| Verbindung erneuern | Reconnect | token refresh / re-auth | Zugriff bei der Plattform neu bestätigen. |
| Einladung | Review invitation | request | E-Mail mit der Bitte um eine Bewertung. |
| Kampagne | Campaign | campaign | Regel, wann und wohin eingeladen wird. |
| Erlebnis | Experience | experience_id | Der tatsächliche Kauf/Besuch/die Einlösung, nach dem eingeladen wird. |
| Versandgrundlage | Permission to email | consent / communication_allowed | Darf diese Person angeschrieben werden? |
| Abgemeldet | Unsubscribed | suppression / opt-out | Person möchte keine Einladungen. |
| Übersprungen | Skipped | skipped + reason | Einladung wurde absichtlich nicht gesendet (Grund sichtbar). |
| Funktion in Ihrer Lizenz | Included in your license | entitlement | Pro-Funktion, die Ihre Lizenz freischaltet. |
| Hintergrundaufgaben | Background tasks | jobs / queue | Arbeiten, die zeitversetzt laufen. |
| Kauf verifiziert | Verified purchase | verification_status=purchase | Shop hat einen bezahlten Kauf bestätigt. |
| Teilnahme bestätigt | Attendance confirmed | verification_status=attendance | Ticket/Check-in wurde bestätigt. |

## Hilfetext-Beispiele

- **Abrufrhythmus:** „Legt fest, wie oft neue Bewertungen übernommen werden. Beispiel: alle 12 Stunden. Kürzere Abstände verbrauchen mehr vom Plattform-Kontingent; länger als 24 Stunden ist nicht möglich, weil Plattformen aktuelle Daten verlangen.“
- **Bestandskundenregel:** „Erlaubt Einladungen an Kunden ohne ausdrückliche Einwilligung, wenn die gesetzlichen Voraussetzungen erfüllt sind. Beispiel: Kunde wurde beim Kauf auf das Widerspruchsrecht hingewiesen. Lassen Sie die Zulässigkeit rechtlich prüfen – im Zweifel ausgeschaltet lassen.“
- **Neue Bewertungen veröffentlichen:** „Bestimmt, ob eingereichte Bewertungen sofort sichtbar sind. Empfohlen: erst nach Prüfung. Sofortige Veröffentlichung kann Spam oder unzulässige Inhalte öffentlich machen.“
- **Mindestabstand zwischen Einladungen:** „Verhindert, dass dieselbe Person zu oft angeschrieben wird – auch über verschiedene Kampagnen hinweg. Beispiel: 30 Tage.“

## Testplan (offen)

5 Betreiber erklären nach dem Lesen in eigenen Worten: Abrufrhythmus, Versandgrundlage, Aktualität, Kontingent, Kampagne. Erfolgskriterium: ≥ 4 von 5 korrekt je Begriff; sonst Text überarbeiten.
