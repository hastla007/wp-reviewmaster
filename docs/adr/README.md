# Architecture Decision Records (ADR) – Review Captain

Status: verbindlich ab 2026-11-16 (RC-031). Dieses Verzeichnis ist das Entscheidungsprotokoll für Review Captain Core. `docs/architecture/conventions.md` verweist hierher: **Jede Abweichung von den Konventionen braucht ein angenommenes ADR.**

## Wann ist ein ADR Pflicht?

Ein ADR wird angelegt, bevor Code gemergt wird, der Folgendes ändert:

| Bereich | Beispiele |
|---|---|
| Datenbankschema | neue Tabelle, Spalte, Index, Unique-Key, Änderung von `dedupe_key`/`idempotency_key`-Formeln |
| Öffentliche Verträge | Hooks (`review_captain_*_v1`), Shortcode-/Block-Attribute, REST-Routen, Event-Payloads, Provider-/Connector-Vertrag |
| Rechte | neue/entfernte Capability, geänderte Standardrollen, Multisite-Rechte |
| Lizenz-/Entitlement-Grenzen | was Core vs. Pro besitzt, neue `review.*`-Entitlements |
| Anbieterzugänge | Auth-Modell je Provider, OAuth-App-Eigentümerschaft, Scopes, Aufbewahrungsfristen |
| Sicherheit/Kryptografie | Verschlüsselungsverfahren, Schlüsselquelle, Host-Allowlists |
| Laufzeit | Job-Runner, Retry-Politik, Zeitbudgets |

Kein ADR nötig: Bugfixes, interne Refactorings ohne Vertragsänderung, Texte, Styling.

## Ablauf

1. `0000-template.md` nach `NNNN-kurzer-titel.md` kopieren (fortlaufende vierstellige Nummer, nie wiederverwenden).
2. Status `Vorgeschlagen`, Datum (ISO 8601), Autor:in eintragen; Kontext, Optionen, Entscheidung, Konsequenzen, **Abnahme** ausfüllen.
3. Review im Pull Request. Ein ADR wird mit dem Merge `Angenommen`, wenn mindestens eine verantwortliche Person (Core-Maintainer) zustimmt. Bei Anbieter-/Rechtsthemen zusätzlich Produktverantwortung.
4. Betroffene Dokumente (`conventions.md`, Architektur-Dokumente, `TODO.md`) im **selben** PR anpassen und auf das ADR verlinken.
5. Angenommene ADRs werden inhaltlich **nicht** mehr geändert (nur Tippfehler/Links). Eine neue Entscheidung erzeugt ein neues ADR mit `Ersetzt: ADR-XXXX`; das alte erhält Status `Abgelöst durch ADR-YYYY`.

## Status-Werte

`Vorgeschlagen` → `Angenommen` | `Verworfen`; später ggf. `Abgelöst durch ADR-NNNN` oder `Veraltet` (Kontext entfallen, ohne Nachfolger).

## Index

| Nr. | Titel | Status | Datum |
|---|---|---|---|
| [0001](0001-custom-tables-statt-cpt.md) | Eigene Tabellen statt Custom Post Types für Reviews | Angenommen | 2026-11-16 |
| [0002](0002-hook-praefix-review-captain.md) | Hook-Präfix `review_captain_` statt `wp_review_captain_` | Angenommen | 2026-11-16 |
| [0003](0003-eigener-job-runner-mit-lease.md) | Eigener Job-Runner mit Lease, Action Scheduler optional | Angenommen | 2026-11-16 |
| [0004](0004-repository-wurzel-ist-plugin-wurzel.md) | Repository-Wurzel = Plugin-Wurzel | Angenommen | 2026-11-16 |
| [0005](0005-tokenverschluesselung-libsodium.md) | Tokenverschlüsselung mit libsodium secretbox | Angenommen | 2026-11-16 |
| [0006](0006-quellsprache-der-oberflaechentexte.md) | Deutsch als Quellsprache der Oberflächentexte (Arbeitsannahme) | Vorgeschlagen | 2026-11-16 |
| [0007](0007-oeffentliche-hooks-v1.md) | Öffentliche Hooks von Core v1 und ihre Stabilitätsstufen | Angenommen | 2026-11-16 |
| [0008](0008-auth-modell-betreiber-credential.md) | Plattformzugänge im MVP über Betreiber-Credentials | Abgelehnt (Captain-OAuth-App für Google in 1.0, RC-026A) | 2026-11-16 |
| [0009](0009-connector-lizenzgrenze.md) | Lizenzgrenze für kundenspezifische Connectoren | Angenommen | 2026-11-16 |
| [0010](0010-sortierspalte-sorted-at.md) | Indizierte Sortierspalte `sorted_at` und Keyset-Export | Vorgeschlagen | 2026-11-16 |

Vorgemerkte ADR-Kandidaten (noch nicht angelegt, siehe offene Entscheidungen in `docs/architecture/`):

- Auth-Modell „Captain-OAuth-App vs. Betreiber-Credential“ je Provider (`docs/architecture/provider-auth.md`).
- Zusätzliche Indizes auf `reviews` nach Messung (`(status, created_at)`, `(profile_id, status)`, ggf. FULLTEXT; `refresh_due_at` ist bereits in Schema v1) (`docs/architecture/scaling.md`).
- Jobstatus `cancelled` vs. Abbildung auf `failed` (`docs/architecture/jobs.md`).
- Pro-Tabellenpräfix (`docs/architecture/database.md`).

## Abnahme

- [ ] `docs/adr/` enthält README, Template und die ADRs 0001–0005 mit Status, Datum, Grund und Abnahme.
- [ ] `conventions.md` verweist auf ADRs; jeder Index-Eintrag ist verlinkt und der Status stimmt mit der Datei überein.
- [ ] PR-Vorlage bzw. Review-Checkliste fragt „Ändert dieser PR Schema/Vertrag/Rechte/Anbieterzugang? → ADR verlinkt?“.
- [ ] Kein angenommenes ADR wurde nachträglich inhaltlich geändert (Git-Historie prüfen).
