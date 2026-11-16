# CON-019 – Connector-Release-Gate

Status: v1, Stand 2026-11-16. Ergänzt die Release-Gates in [TODO.md](../../TODO.md#release-gates).

Kein Connector-Feature gilt als **Pro-1.0-fertig**, bevor alle Punkte unten erfüllt und mit Nachweis (Testlauf, PR, Protokoll) abgehakt sind.

## A. Core (Vertrag und Registry)

- [ ] `contract-v1.md` entspricht 1:1 dem Code (Reflection-Snapshot-Test CT-V-03 grün).
- [ ] Registry und Validator umgesetzt (CON-008); alle Tests CT-R-* grün.
- [ ] Inkompatible/ungültige Connectoren erzeugen keinen Fatal Error und werden in der Diagnose angezeigt.
- [ ] Connectoren sind nach Registrierung inaktiv; keine Ausführung ohne Admin-Aktivierung.
- [ ] Jobrunner-Erweiterung (CON-009): atomarer Claim/Lease, Idempotenz, Retry/Backoff, Dead-Letter, `review_captain_process_job_v1`; Tests CT-J-* grün; keine Connector-Jobs im Frontend-Request.
- [ ] `ConnectorException` und alle `rc.*`-Codes in `ErrorCode` mit Retry-Klasse; `docs/architecture/error-codes.md` aktuell.
- [ ] Core enthält keinen Lizenzcode.

## B. Pro (Manager und Runtime)

- [ ] Manager-Seite (CON-010) zeigt Entwickler, Version, Fähigkeiten, Contract-Kompatibilität, Aktivstatus, letzte Ausführung, wartende Jobs, letzten sanitisierten Fehler.
- [ ] Test, Pause, Disconnect, Wiederholen: Capability + Nonce geprüft (Negativtests).
- [ ] Konfiguration typisiert; Secrets maskiert/verschlüsselt, nicht in Logs/Export (CON-011).
- [ ] Dry-Run ohne realen Versand oder externe Schreibaktion (CON-012).
- [ ] Health-Status mit nächster sinnvoller Admin-Aktion je Fehlercode (CON-013).
- [ ] Plugin-Lifecycle: Deaktivierung des Quell-Plugins → `source_unavailable`, Jobs pausiert; Reaktivierung prüft Vertrag, Consent, Fristen vor Resume (CON-014).
- [ ] Entitlement `review.custom_connectors` greift; ohne Entitlement Jobs `paused`, Daten bleiben nutzbar (CT-J-14).

## C. Referenz-Connector und SDK

- [ ] `examples/review-captain-demo-connector/` installierbar ohne Core-Änderung, ohne fremde Tabellenzugriffe (CON-015).
- [ ] `examples/connector-sdk/` kopierbar: Plugin-Header, Beispielklasse, Capability-Manifest, Beispielkonfiguration, Job-Handler, Fixtures, Tests (CON-017).
- [ ] Demo-Connector lädt ohne aktives Review Captain fehlerfrei (CT-R-19, CT-S-08).
- [ ] Developer Guide (CON-018) mit lauffähigen Beispielen gegen aktuellen Code geprüft.

## D. Ende-zu-Ende-Abnahme (CON-016)

Mit Demo-Connector auf einer Testsite:

| Szenario | Erwartung | ✓ |
|---|---|---|
| Abgeschlossenes Erlebnis | genau eine Einladung geplant und versendet | [ ] |
| Gleiches Event erneut | keine zweite Einladung | [ ] |
| Zweites Event, gleiche `experience_id` | keine zweite Einladung | [ ] |
| Refund/Storno vor Versand | geplante Einladung storniert | [ ] |
| Verspätetes Event | nicht eingeplant | [ ] |
| Opt-out/Suppression | kein Versand | [ ] |
| Resolver liefert `null`/`denied` | kein Versand | [ ] |
| `communication_allowed=false` | kein Versand | [ ] |
| Inkompatible Contract-Version | pausiert, Diagnose, kein Fatal | [ ] |
| Timeout → Retry → Erfolg | ein Versand, gleicher `idempotency_key` | [ ] |
| Dauerfehler | Dead-Letter, Admin sieht Fehlercode | [ ] |
| Pause/Disconnect und Resume | keine Aktion während Pause, Neuprüfung beim Resume | [ ] |

## E. Datenschutz- und Sicherheitsprüfung

- [ ] [security-data-matrix.md](security-data-matrix.md) gegen Implementierung geprüft.
- [ ] Log-/Audit-/Diagnose-Snapshot enthält keine E-Mail, Namen, Reviewtexte, Secrets (CT-RR-06).
- [ ] Privacy Exporter/Eraser decken Connector-Reviews und Anfragen ab.
- [ ] Hinweis „keine Sandbox“ in Admin und Doku sichtbar.
- [ ] Escaping aller Connector-Strings verifiziert (CT-R-17).

## F. Drittanbieter-Connector (Checkliste für Entwickler)

- [ ] Schlüssel stabil und regelkonform; keine reservierten Präfixe.
- [ ] Nur deklarierte Fähigkeiten implementiert; Tests CT-S-* grün.
- [ ] Keine E-Mail im Event-Payload; Versandberechtigung nachweisbar.
- [ ] Fehler als `ConnectorException` mit `rc.*`-Code.
- [ ] Eigene Jobs idempotent, Präfix `{key}.`.
- [ ] Getestet mit aktueller Review-Captain-Version und Contract 1.x.

## Freigabe

Freigabe erfolgt, wenn A–E vollständig abgehakt sind. Offene Lizenzpunkte (RC-005) blockieren die technische Abnahme nicht, wohl aber den kommerziellen Release.
