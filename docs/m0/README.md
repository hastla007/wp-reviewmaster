# M0 – Entscheidungsunterlagen

Stand: 2026-11-16, abgeglichen mit dem Core-Stand (`claude/sleepy-gates-6mm217`) und Review Captain Pro (`captain-pro`, `claude/review-captain-pro`). Alle Dokumente sind **Entwürfe** (`[~]` in [TODO.md](../../TODO.md)): Das Ergebnis ist ausgearbeitet, aber Freigabe durch Produkt, Shop, Recht oder eine externe Aktion (Anbieterzugang) steht aus. Jedes Dokument listet seine offenen Punkte unter „Offen / Freigabe“.

| Task | Dokument | Was fehlt bis `[x]` |
|---|---|---|
| RC-001 | [Produktnamen und Slugs](rc-001-namen-slugs.md) | Slug-Verfügbarkeit WordPress.org, Markenprüfung, Freigabe |
| RC-002 | [1.0-Scope](rc-002-scope-1-0.md) | Produktfreigabe der Plattform- und Releasezuordnung |
| RC-003 | [Free/Pro-Grenze](rc-003-free-pro-grenze.md) | Produktfreigabe (u. a. CSV-Export im Core) |
| RC-004 | [Pro-Planmodell](rc-004-planmodell.md) | Preise und Supportzeiten (Shop) |
| RC-005 | [Lizenz-Entitlements](rc-005-entitlements.md) | Freigabe neues Recht `review.social_proof` |
| RC-006 | [Kompatibilitätsziele](rc-006-kompatibilitaet.md) | konkrete Versionsnummern am Feature-Freeze |
| RC-007 | [Support und Releasekanäle](rc-007-support-release.md) | Reaktionsziele, Security-Adresse |
| RC-008 | abgeschlossen im Core: [docs/providers/matrix.md](../providers/matrix.md) | – |
| RC-009 – RC-013 | [Anbieterzugänge](rc-009-013-anbieterzugaenge.md) | Freischaltungen und Testkonten (extern) |
| RC-014 | [Datenschutz/Kommunikation](rc-014-datenschutz-kommunikation.md) | juristische Prüfung DE/AT |
| RC-015 | [Medienrechte](rc-015-medienrechte.md) | juristische Freigabe Einwilligungstext |
| RC-016 | [Marken/Trustmarks](rc-016-marken-trustmarks.md) | Richtlinienprüfung je Anbieter |

## Gefundene Widersprüche und ihre Auflösung (Vorschlag)

1. **Plattformen in Pro 1.0** – Konzept §13 vs. §15 Nr. 1 → RC-002. Hinweis: Das Pro-Add-on hat alle fünf Adapter gebaut; „nativer Sync“ darf trotzdem erst nach echtem Verbindungstest beworben werden.
2. **CSV-Export Free oder Pro** – Konzept §5 vs. TODO RC-044 → RC-003 (umgesetzt: Export im Core).
3. **Recht für Social-Proof-Funktionen** – umgesetzt unter `review.advanced_widgets`; eigenes Recht `review.social_proof` nur auf Wunsch des Inhabers (RC-005).
