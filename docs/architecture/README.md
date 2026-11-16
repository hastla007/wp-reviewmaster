# Architektur-Dokumentation – Review Captain Core

Stand: 2026-11-16. Verbindliche Basis ist [`conventions.md`](conventions.md); Abweichungen nur per ADR ([`../adr/`](../adr/README.md)). Jedes Dokument endet mit einem Abschnitt **Abnahme**; ein Task gilt erst als erledigt, wenn diese Kriterien erfüllt sind (`TODO.md`, Arbeitsregeln).

## Index

| Dokument | Inhalt | Tasks |
|---|---|---|
| [conventions.md](conventions.md) | verbindliche Namen, Tabellen, Capabilities, Hooks, Fehlercodes, Code-Stil | – |
| [error-codes.md](error-codes.md) | Fehlercodes `rc.<bereich>.<code>` und Retry-Klassen (separat gepflegt) | RC-030 |
| [database.md](database.md) | Tabellen, Spalten, Indizes, Optionen, Upgrade/Rollback, Multisite, Pro-Ausblick | RC-028 |
| [capabilities.md](capabilities.md) | Capabilities, Seiten-/Aktions-Mapping, Installation, Super Admin | RC-029 |
| [provider-auth.md](provider-auth.md) | Auth-Modell je Provider (Google GBP, Trustpilot, eTrusted, App Store Connect, Google Play) | RC-026 |
| [retention-matrix.md](retention-matrix.md) | Fristen, Refresh, Löschpfad, Export, Backup, Disconnect je Quelle/Datenart | RC-027 |
| [jobs.md](jobs.md) | Job-Runner, WP-Cron/System-Cron/Action Scheduler, Lease, Idempotenz, Retry/Backoff, API-Budgets, Anzeige | TEC-001–004, TEC-006, TEC-007 |
| [secrets.md](secrets.md) | Tokenverschlüsselung, Schlüsselquelle, Rotation, Fehlverhalten, Logs/Exporte | TEC-008 |
| [multisite.md](multisite.md) | Site-Isolation, Netzwerk-(De-)Aktivierung | TEC-009 |
| [rest-oauth.md](rest-oauth.md) | REST-Regeln, OAuth State/PKCE/Redirect, Profilwahl, Replay | TEC-010 (Abgrenzung TEC-011) |
| [caching.md](caching.md) | Fragment-/Objekt-/Seiten-Cache, CDN, Nonces, Tombstones | TEC-012 |
| [embed-security.md](embed-security.md) | Host-Allowlists, SSRF, click-to-load, Linkattribute | TEC-013 |
| [scaling.md](scaling.md) | Messplan 100k Reviews / 10k Jobs, Seeder, Zielwerte | TEC-014 |
| [reconciliation.md](reconciliation.md) | Upgrade-/Restore-/Klon-Reconciliation | TEC-015 |

ADRs: [0001 Custom Tables](../adr/0001-custom-tables-statt-cpt.md) · [0002 Hook-Präfix](../adr/0002-hook-praefix-review-captain.md) · [0003 Job-Runner](../adr/0003-eigener-job-runner-mit-lease.md) · [0004 Repo-Wurzel](../adr/0004-repository-wurzel-ist-plugin-wurzel.md) · [0005 Verschlüsselung](../adr/0005-tokenverschluesselung-libsodium.md)

## Offene Entscheidungen (Sammelliste)

| Thema | Dokument | Empfehlung | Entscheidet |
|---|---|---|---|
| Captain-OAuth-App vs. Betreiber-Credential (v. a. Google) | [provider-auth.md](provider-auth.md) §8 | MVP Betreiber-Credential; Captain-App später per ADR mit Broker | Produkt + Core |
| Exakte minimale Rollen App Store Connect / Play Console, Trustpilot-Endpunkte je Tarif, eTrusted-Token-Laufzeit | [provider-auth.md](provider-auth.md) | im echten Konto verifizieren (M0) | Adapter-Owner |
| Anbieter-Speicher-/Anzeigefristen außer Trustpilot 24 h | [retention-matrix.md](retention-matrix.md) | Defaults 24 h Refresh / 7 d Anzeige / 30 d Speicherung bis Verifikation | Produkt + Recht |
| Jobstatus `cancelled` statt `failed` für Disconnect-Abbruch | [jobs.md](jobs.md) §6.1 | vorerst `failed`; nur bei Bedarf ADR | Core |
| Zusätzliche Indizes / FULLTEXT | [scaling.md](scaling.md) §5 | nach Messung per ADR | Core |
| REST-Vorschau mit `edit_posts` | [rest-oauth.md](rest-oauth.md), [capabilities.md](capabilities.md) | Arbeitsannahme beibehalten | Core |
| Pro-Tabellenpräfix `review_captain_pro_` | [database.md](database.md) §6 | im Pro-Repo per ADR | Pro |
| Fehlercode für Lease-Ablauf / Mapping Code → Klasse | [jobs.md](jobs.md) §4, §6.2 | mit `error-codes.md` abgleichen | Core |
| Mindestversionen PHP/WP (RC-006) | conventions.md | Arbeitsannahme PHP 8.1 / WP 6.5 | Produkt |

## Abnahme

- [ ] Jedes Dokument im Index existiert, ist verlinkt und enthält einen Abschnitt „Abnahme“.
- [ ] Kein Dokument widerspricht `conventions.md` (Tabellen-, Capability-, Hook-, Fehlercode-Namen); Review-Checkliste abgehakt.
- [ ] Offene Entscheidungen sind entweder per ADR entschieden oder bewusst als Arbeitsannahme markiert, bevor die abhängigen Tasks (M2/M4) starten.
