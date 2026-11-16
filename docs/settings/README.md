# Einstellungen – Spezifikation

Alle globalen Einstellungen liegen in der Option `review_captain_settings` (`[tab][key]`), Schema-Version in `review_captain_settings_version`. Objekt-Einstellungen (Quellen, Kampagnen, Formulare, Widgets) liegen am Objekt.

| Datei | Task | Inhalt |
|---|---|---|
| [inventory.md](inventory.md) | SET-001, UX-009 | jede Option mit Schlüssel, Seite, Scope, Owner, Default, Capability, Speicherort; entfernte Duplikate |
| [structure.md](structure.md) | SET-002, UX-010, UX-011 | Menü, Tab-Baum, Einfach/Erweitert, kontextabhängige Felder |
| [option-schema.md](option-schema.md) | SET-003 | Typ, Validierung, Default, Scope, Datenschutzklasse, Entitlement, Migration (**Vertrag mit der PHP-Umsetzung**) |
| [inheritance.md](inheritance.md) | SET-004 | Vererbung global → Objekt → Attribut, Override-Arten |
| [defaults-reset.md](defaults-reset.md) | SET-005 | sichere Defaults, Reset vs. Trennen vs. Datenlöschung |
| [pro-matrix.md](pro-matrix.md) | SET-006 | Core/Pro je Feld, Verhalten bei Lizenzablauf |
| [audit.md](audit.md) | SET-019 | Änderungshistorie ohne Secrets/Reviewtext |
| [reference.md](reference.md) | SET-023 | Benutzer-Doku je Einstellung |
| [release-gate.md](release-gate.md) | SET-024 | Release-Kriterien je Einstellung |

Änderungen an Schlüsseln oder Defaults erfordern gleichzeitige Anpassung von `option-schema.md`, PHP-Schema, `reference.md` und eine Migrationsregel.
