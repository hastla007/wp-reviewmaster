# M0 – Entscheidungsunterlagen

Stand: 2026-11-16. Alle Dokumente sind **Entwürfe** (`[~]` in [TODO.md](../../TODO.md)): Das Ergebnis ist ausgearbeitet, aber Freigabe durch Produkt, Shop, Recht oder eine externe Aktion (Anbieterzugang) steht aus. Jedes Dokument listet seine offenen Punkte unter „Offen / Freigabe“.

| Task | Dokument | Was fehlt bis `[x]` |
|---|---|---|
| RC-001 | [Produktnamen und Slugs](rc-001-namen-slugs.md) | Slug-Verfügbarkeit WordPress.org, Markenprüfung, Freigabe |
| RC-002 | [1.0-Scope](rc-002-scope-1-0.md) | Produktfreigabe der Plattform- und Releasezuordnung |
| RC-003 | [Free/Pro-Grenze](rc-003-free-pro-grenze.md) | Produktfreigabe (u. a. CSV-Export im Core) |
| RC-004 | [Pro-Planmodell](rc-004-planmodell.md) | Preise und Supportzeiten (Shop) |
| RC-005 | [Lizenz-Entitlements](rc-005-entitlements.md) | Freigabe neues Recht `review.social_proof` |
| RC-006 | [Kompatibilitätsziele](rc-006-kompatibilitaet.md) | konkrete Versionsnummern am Feature-Freeze |
| RC-007 | [Support und Releasekanäle](rc-007-support-release.md) | Reaktionsziele, Security-Adresse |
| RC-008 | [Provider-Matrix](rc-008-provider-matrix.md) | Verifikation ⚠-Angaben an Primärdoku, Anbieteranfragen ❓ |
| RC-009 – RC-013 | [Anbieterzugänge](rc-009-013-anbieterzugaenge.md) | Freischaltungen und Testkonten (extern) |
| RC-014 | [Datenschutz/Kommunikation](rc-014-datenschutz-kommunikation.md) | juristische Prüfung DE/AT |
| RC-015 | [Medienrechte](rc-015-medienrechte.md) | juristische Freigabe Einwilligungstext |
| RC-016 | [Marken/Trustmarks](rc-016-marken-trustmarks.md) | Richtlinienprüfung je Anbieter |

## Gefundene Widersprüche und ihre Auflösung (Vorschlag)

1. **Plattformen in Pro 1.0** – Konzept §13 vs. §15 Nr. 1 → aufgelöst in RC-002 (5 Plattformen sichtbar, 3 nativ synchronisiert).
2. **CSV-Export Free oder Pro** – Konzept §5 vs. TODO RC-044 → aufgelöst in RC-003 (manueller Export Core, geplante Reports Pro).
3. **Fehlendes Recht für Social-Proof-Funktionen** – Konzept §5/§6 → neues Recht `review.social_proof` in RC-005.
4. **Anfrage-Engine in 1.0** – Konzept §13 vs. §15 Nr. 3 → RC-002 nimmt Anfrage-Engine mit WooCommerce-Auslöser in Pro 1.0 auf.
