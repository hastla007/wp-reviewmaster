# Einstellungs- und Pro-Rechte-Matrix (SET-006)

Status: Entwurf zur Abnahme, Stand 2026-11-23 (Hinweiszeilen nach Entscheidung S1/S5, SET-RC-06). Entitlements laut konzept.md §6 (Katalog noch nicht final, RC-005).

## 1. Grundregeln

1. Der Core enthält **keinen** Lizenzcode, prüft keine Entitlements und enthält keine Namen von Lizenzschlüsseln (Wächtertest `tests/Unit/Settings/NoLicenseKnowledgeTest.php`). Er kennt nur „Feld ist von einem Add-on registriert / nicht registriert“.
2. **Entscheidung S1 der Inhaberin vom 23.11.2026 (SET-RC-06):** Pro-Einstellungen erscheinen im freien Core nur als **graue Hinweiszeile am Ende des jeweiligen Einstellungs-Tabs**: Text mit Kennzeichen „Pro“ und einem Satz, was die Einstellung tut, endend auf „Verfügbar mit Review Captain Pro.“. Kein deaktiviertes Eingabefeld, kein Schloss, keine Warnfarbe, höchstens ein Link (Standard: keiner), kein Funktionscode im Core. Ersetzt die frühere Regel „Pro-Felder sichtbar gesperrt“.
3. Der Betreiber blendet **alle** Hinweise mit einem Schalter aus: `general.show_pro_hints` („Pro-Hinweise zeigen“, Standard an). Das betrifft die Hinweiszeilen, den Kasten „In Review Captain Pro“ der Übersicht und den Vermerk auf der Quellen-Seite.
4. **Entscheidung S5:** Hinweiszeilen gibt es nur für Pro-Funktionen, die heute verkauft werden (Einladungen/Anfragen, Plattform-Abgleich, erweiterte Widgets, Auswertungen; Suite-Anbindungen und eigene Connectoren haben keine Einstellung im Core-Tab und deshalb keine Zeile). Keine Zeilen für geplante Funktionen.
5. Das Add-on registriert das echte Feld über `review_captain_settings_schema` (`owner => 'pro'`, eigene `label`/`description`/`section`) und nennt mit `replaces_hint` die Hinweiszeile(n), die das Feld ersetzt. Funktionen ohne eigenes Feld im Core-Tab entfernen ihre Zeile über den Filter `review_captain_settings_hints`, solange sie aktiv sind.
6. **Kein Core-Feld wird durch Lizenzstatus verändert, geleert oder gesperrt.** Nichts, was heute frei ist, wandert hinter Pro.
7. Add-on-Felder bleiben bei Lizenzablauf, Deaktivierung oder Deinstallation von Pro **gespeichert** und unverändert; sie werden nur nicht angewendet und nicht angezeigt. Reaktivierung stellt den letzten Stand her.

### Datenformat der Hinweiszeilen (gemeinsamer Suite-Vertrag, vorläufig)

`{id, tab, title, text, link}` – `id` aus `[a-z0-9_.-]`, eindeutig, nie ein Lizenzschlüssel; `tab` ein bestehender Tab; `title` und `text` nicht leer; `link` leer oder `http(s)`. Quelle: `Admin\SettingsHints`, Filter `review_captain_settings_hints` (ganze Liste) und `review_captain_settings_hint_link` (Link einer Core-Zeile, Standard leer). Vorläufig, bis die Lizenz-Sitzung den Kernel-Vertrag veröffentlicht.

| Kennung (`SettingsHints::…`) | Tab | Titel | Ersetzt durch |
|---|---|---|---|
| `requests.invitations` (`REQUESTS`) | requests | Bewertungs-Einladungen | Pro-Feld `sender_name` |
| `requests.sender` (`SENDER`) | requests | Absender | Pro-Felder `sender_name`, `sender_email` |
| `requests.send_window` (`SEND_WINDOW`) | requests | Versandzeiten | Pro-Felder `send_window_start`, `send_window_end` |
| `requests.limits` (`LIMITS`) | requests | Grenzen | Pro-Felder `daily_limit`, `cooldown_days` |
| `requests.reminders` (`REMINDERS`) | requests | Erinnerungen | Pro-Feld `reminders_enabled` |
| `sources.platform_import` (`PLATFORM`) | sources | Automatischer Abgleich | Pro entfernt die Zeile, solange der Plattform-Abgleich aktiv ist |
| `display.advanced_widgets` (`WIDGETS`) | display | Weitere Widgets | Pro entfernt die Zeile, solange die erweiterten Widgets aktiv sind |
| `general.reports` (`REPORTS`) | general | Auswertungen | Pro entfernt die Zeile, solange die Auswertungen aktiv sind |

## 2. Matrix je Feld

| Feld | Owner | Ohne Pro | Pro installiert, Funktion nicht lizenziert | Pro + Funktion lizenziert |
|---|---|---|---|---|
| `general.*` | Core | bearbeitbar | bearbeitbar | bearbeitbar |
| `sources.*` | Core | bearbeitbar (wirkt für Core-Connectoren) | bearbeitbar | bearbeitbar, wirkt auch für Pro-Provider |
| `requests.sender_name`, `sender_email`, `send_window_start/end`, `daily_limit`, `cooldown_days`, `reminders_enabled` | Pro (vom Add-on registriert) | nicht vorhanden; graue Hinweiszeilen; gespeicherte Werte unverändert | wie ohne Pro | bearbeitbar (mit `review_captain_manage_requests`; sonst deaktiviert mit neutralem Vermerk) |
| `submissions.*` | Core | bearbeitbar | bearbeitbar | bearbeitbar |
| `display.*` | Core | bearbeitbar | bearbeitbar | bearbeitbar |
| `privacy.*` | Core | bearbeitbar | bearbeitbar | bearbeitbar |
| `access.*` | Core | bearbeitbar | bearbeitbar | bearbeitbar |
| `advanced.*` | Core | bearbeitbar | bearbeitbar | bearbeitbar |

## 3. Erwartete Pro-Ergänzungen (nicht im Core-Schema; Pro definiert)

| Tab | Feld (Arbeitstitel) | Entitlement |
|---|---|---|
| requests | Testempfänger, Bounce-/Beschwerdeverhalten, Versandweg (SMTP/Dienst) | `review.review_requests` |
| privacy | Aufbewahrung Einladungs-/Empfängerdaten, Medien-Testimonials | `review.review_requests` / OFFEN (Testimonials-Entitlement nicht im Katalogvorschlag) |
| sources | Standard-Sync-Intervall neuer Profile | `review.platform_sync` |
| display | Pro-Layoutvoreinstellungen | `review.advanced_widgets` |
| advanced | Connector-Queue-Diagnose | `review.custom_connectors` |

Objekt-Einstellungen: Profil-Verbindungen (`review.platform_sync`), Kampagnen (`review.review_requests`), Pro-Widgets (`review.advanced_widgets`), Integrationen (`review.suite_integrations`, `review.custom_connectors`).

## 4. Prüfpunkte Lizenzablauf

| Situation | Erwartung |
|---|---|
| Lizenz läuft ab | keine neuen Syncs/Versände; Reviews bleiben sichtbar/exportierbar; alle Einstellungen unverändert |
| Pro deaktiviert | `requests`-Werte bleiben in der Option; Core zeigt Hinweiszeilen (oder den neutralen Satz), keine gesperrten Felder; Core-Read entfernt die Werte nicht |
| Pro deinstalliert | Pro-Uninstall entscheidet über Pro-Daten; Core-Felder werden nie angefasst |
| Core-Reset ohne Pro | `requests` bleibt erhalten |

## Abnahme

Entspricht SET-006, wenn:

- [ ] für jedes Feld Owner (Core/Pro) festgelegt ist (Entitlements nur im Add-on);
- [x] ein Test belegt, dass Speichern, Zurücksetzen und Import ohne Pro kein Pro-Feld verändern oder leeren (`SettingsPageTest::test_core_defines_no_addon_fields_and_stored_values_survive`, `ConfigTransferTest::test_export_and_import_keep_addon_request_values`);
- [x] Hinweiszeilen erklären die Funktion in einem Satz, ohne gesperrte Felder (SET-RC-06);
- [ ] die Pro-Ergänzungen mit dem finalen Entitlement-Katalog (RC-005) abgeglichen sind.
