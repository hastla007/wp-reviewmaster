# Review Captain Connector-SDK (CON-017)

Kopierbares Skelett für einen eigenen Connector nach [Connector-Vertrag v1](../../docs/connectors/contract-v1.md). Ausführliche Anleitung: [Developer Guide](../../docs/connectors/developer-guide.md).

## Inhalt

| Datei | Zweck |
|---|---|
| `my-review-connector.php` | Plugin-Header, Registrierung über `review_captain_connectors_v1`, Jobhandler über `review_captain_process_job_v1` |
| `src/Connector.php` | Beispielklasse mit `experience_source`, `recipient_resolver`, `context_provider` |
| `src/JobHandler.php` | Handler für eigene Jobtypen `my-connector.*` |
| `capabilities.json` | Capability-Manifest (Dokumentation der Fähigkeiten, Jobtypen und Datenflüsse) |
| `config.example.json` | Beispielkonfiguration |
| `tests/fixtures/experience-completed.json` | Lokale Entwicklungsfixture eines Ereignisses |
| `tests/ConnectorTest.php` | Contract-Tests (CT-S-01 … CT-S-07) |

## Schnellstart

1. Ordner kopieren und umbenennen (z. B. `acme-review-connector`).
2. Platzhalter ersetzen (siehe Kopfkommentar in `my-review-connector.php`): Schlüssel, Namespace, Textdomain, Funktionen des Quellsystems (`my_source_*`).
3. Nicht benötigte Interfaces **und** die zugehörigen Einträge in `capabilities()` entfernen – jede deklarierte Fähigkeit braucht ihr Interface, sonst wird der Connector als inkompatibel pausiert.
4. Tests aus dem Review-Captain-Repository ausführen:

   ```bash
   vendor/bin/phpunit --bootstrap tests/bootstrap.php examples/connector-sdk/tests
   ```

## Regeln (Kurzfassung)

- `key()` nie ändern; reservierte Präfixe `rc-`, `review-captain` und Suite-Schlüssel (`woocommerce`, `pass-captain` …) sind nicht erlaubt.
- Ereignisse enthalten **keine** E-Mail-Adressen, Namen oder Texte – nur `recipient_ref`.
- `communication_allowed` nur `true` bei nachweisbarer Versandberechtigung.
- Fehler als `ConnectorException( 'rc.<bereich>.<code>', 'Meldung ohne personenbezogene Daten' )`.
- Keine Zugriffe auf Review-Captain-Tabellen; Jobs nur über die Core-Job-API (`Plugin::instance()->get( 'jobs' )->enqueue( … )`).
- Neu registrierte Connectoren sind inaktiv; Aktivierung und Ausführung übernimmt Review Captain Pro (`review.custom_connectors`).
