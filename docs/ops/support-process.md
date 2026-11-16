# Support- und Eskalationsprozess (OPS-003)

Status: **Entwurf**, Stand 2026-11-16. Personen/Kontakte sind **OFFEN** (OPS-009); Reaktionsziele aus [RC-007](../m0/rc-007-support-release.md) (Freigabe Support offen). Incident-Schritte: [runbook.md](runbook.md).

## Kanäle

| Kanal | Wer | Für |
|---|---|---|
| WordPress.org-Forum `review-captain` | alle (Core) | Fragen und Fehler zum kostenlosen Core, ohne Reaktionszusage |
| Ticket/E-Mail `{Support}` | Pro-Kunden mit gültiger Lizenz | Pro und Core im Pro-Kontext, Einrichtung, Anbieter-Setup nach Doku |
| `{Security-Kontakt}` (SECURITY.md, readme.txt) | alle | Sicherheitsmeldungen; Erstreaktion 2 Werktage, unabhängig vom Plan |
| Lizenzserver-Kontakt `{Lizenz}` | intern | Aktivierungs-, Ablauf-, Entitlement-Fehler (WP License Captain) |

## Zuständigkeit nach Fehlerart

| Fehlerart | Erkennen (Diagnosebericht / Fehlercode) | 1st Level | 2nd Level | Eskalation an |
|---|---|---|---|---|
| **Pluginfehler Core** (Fatal, falsche Ausgabe, Datenfehler) | PHP-Fehler, `rc.internal.*`, Schema-Abweichung in `doctor` | Support: Repro-Schritte, Diagnosebericht anfordern | Entwicklung Core | Release-Verantwortliche/r bei S1 (Patch-Release) |
| **Pluginfehler Pro** (Adapter, Einladungen, Widgets Pro) | `rc.contract.*`, `rc.data.invalid_payload`, Job-Dead-Letter | Support | Entwicklung Pro | wie oben |
| **Lizenzfehler** (Aktivierung, Site-Limit, Ablauf, fehlendes Recht) | Pro-Lizenzstatus, `rc.license.*` | Support: Lizenzschlüssel/Plan/Site prüfen | `{Lizenz}` (WP License Captain) | Shop bei Abrechnung/Planwechsel |
| **Anbieter-API-Ausfall / Kontosperre** | `rc.source.unavailable`, `rc.auth.*`, 429/5xx-Häufung | Support: Statusseite des Anbieters prüfen, Kunde informieren | Owner der API-Änderungsroutine ([api-change-routine.md](api-change-routine.md)) | Adapter-Deaktivierung nach Routine; Kundeninfo über Release-Notes |
| **Suite-Schnittstellen** (Woo, Voucher, Pass, Form, Affiliate, Workflow) | `rc.contract.incompatible`, Integrationsstatus | Support: Versionen aller Captain-Plugins erfassen | Entwicklung Pro | Sitzung/Team des jeweiligen Captain-Plugins |
| **Datenschutzanfrage** (Auskunft, Löschung) | – | Support: Betreiber auf WordPress-Werkzeuge hinweisen (RC-047) | Datenschutz-Ansprechperson | Runbook Abschnitt Datenlöschung |
| **Sicherheitslücke** | Meldung an `{Security-Kontakt}` | – (nicht im öffentlichen Forum diskutieren) | Entwicklung + Security | Runbook S1, koordinierte Offenlegung |

## Schweregrade und Reaktionsziele (Werktage Mo–Fr, Europe/Vienna)

| Grad | Beispiel | Pro standard | Pro priority | Eskalation, wenn |
|---|---|---|---|---|
| S1 kritisch | Datenverlust, Sicherheitslücke, Website-Fatal | 1 Werktag | 4 h in der Geschäftszeit | sofort an Entwicklung + Release-Verantwortliche/r |
| S2 hoch | Sync/Anfrageversand fällt für alle Profile aus | 2 Werktage | 1 Werktag | keine Lösung nach 1 Werktag |
| S3 normal | Konfigurationsfrage, Einzelfehler | 2 Werktage | 1 Werktag | keine Lösung nach 5 Werktagen |

## Ablauf je Ticket

1. **Aufnehmen:** Plan/Lizenz, Versionen (WordPress, PHP, DB, Review Captain, Pro, andere Captain-Plugins), Diagnosebericht (enthält keine Secrets). Nie nach Passwörtern, Tokens oder API-Keys fragen; Screenshots mit Zugangsdaten sofort löschen.
2. **Einstufen** (S1–S3) und Fehlerart zuordnen (Tabelle oben).
3. **Reproduzieren** auf einer Testsite; bei Anbieterfehlern mit Fixtures, nie mit Kundendaten.
4. **Eskalieren** nach Tabelle; Übergabe mit Fehlercode, Versionen, Repro-Schritten.
5. **Abschließen:** Lösung/Workaround dokumentieren, wiederkehrende Fälle in die FAQ bzw. [platform-setup.md](../user/platform-setup.md) übernehmen; bei Anbietermustern Eintrag für die monatliche API-Prüfung.

## Nicht im Support enthalten

Beantragung von Anbieter-API-Zugängen im Namen des Kunden, Rechtsberatung (Datenschutz, UWG, Marken), individuelle Entwicklung/Connectoren (separates Angebot).

## Abnahme (OPS-003)

- [x] Zuständigkeit für Pluginfehler, Lizenzfehler und Anbieter-API-Ausfall ist beschrieben.
- [x] Antwort- und Eskalationswege mit Schweregraden sind dokumentiert.
- [ ] Personen/Kontakte `{Support}`, `{Lizenz}`, `{Security-Kontakt}` sind benannt (OPS-009) und Reaktionsziele vom Support bestätigt (RC-007).
