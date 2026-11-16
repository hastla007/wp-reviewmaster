# Merge-Plan Core und Pro nach `main`

**Stand:** 2026-11-16 · **Status:** Vorschlag, **kein PR angelegt** (nur nach Freigabe der Inhaberin).

## Ausgangslage

| Repo | Quellbranch | Ziel | Stand |
|---|---|---|---|
| `hastla007/wp-reviewmaster` | `claude/plugin-todo-abarbeiten-xzv9c8` (enthält `claude/sleepy-gates-6mm217` vollständig) | `main` | 50 Commits voraus, 0 zurück → Fast-Forward-fähig; 414 files changed, 60976 insertions(+), 241 deletions(-) |
| `hastla007/captain-pro` | `claude/review-captain-pro` | `main` | 16 Commits voraus (Add-on `addons/review-captain-pro`, 801 Tests); gehört der Pro-Sitzung |

`claude/sleepy-gates-6mm217` muss **nicht** separat gemergt werden; der Arbeitsbranch ist dessen Obermenge.

## Reihenfolge

1. **Core zuerst** (Pro setzt Core voraus; RC-007: Pro darf keine unveröffentlichte Core-Version verlangen).
   - PR `claude/plugin-todo-abarbeiten-xzv9c8` → `main` (Squash nicht empfohlen: Commits referenzieren Aufgaben-IDs).
   - Vorbedingung: erster CI-Lauf grün – Unit PHP 8.5, PHPCS, Build, DB-Integration MariaDB 12.3 und MySQL 9.7 (MINVER-1; bisher nie gelaufen, Läufe standen in der Warteschlange).
2. **Pro danach** (`captain-pro`), Abstimmung mit der Pro-Sitzung:
   - `Requires PHP: 8.5` übernehmen (MINVER-1, Entscheidung B3).
   - Quellschlüssel an Core-Format angleichen (Pass/Voucher/Form, siehe [Suite-Abgleich](../contracts/suite-abgleich-2026-11-16.md)).
   - Befunde F-04 (`poll_experiences()` ohne Aufrufer, blockiert CON-016), F-05 (Floating-Badge mobil, RC-120), F-11 (Tabellen erst im Folge-Request).
   - Entitlements bleiben bis zur Freigabe RC-005 auf `hold` → Merge ja, Release nein.
3. **Nachbar-Plugins** (unabhängig): Pass `claude/sharp-mendel-xiiwf3` (Review-Vertrag), Voucher PR #16 (`vcap_review_*`).

## Prüfliste vor dem Core-PR

- [ ] CI grün (siehe oben)
- [ ] `vendor/bin/phpunit` lokal grün (Stand: 400 Tests)
- [ ] `php bin/check-plugin-meta.php`, `php bin/list-hooks.php --check`, `php bin/compile-translations.php --check`
- [ ] `tests/e2e/seo-scenarios.sh` (25/25), `functional.cjs` (19/19), `backup-restore.sh`, `woo-flow.php` – Protokolle in `docs/qa/`
- [ ] TODO.md und CHANGELOG aktuell

## Nicht Teil des Merges

Release (RC-141 bis RC-143): Version `1.0.0`, Stable tag, WordPress.org-Upload und Pro-Signatur erst nach Go/No-Go (RC-142).
