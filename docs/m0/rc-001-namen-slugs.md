# RC-001 – Produktnamen und Slugs

**Status:** Entwurf – Freigabe offen  
**Stand:** 2026-11-16  
**Quellen:** [Konzept, Kopfdaten und Abschnitt 6/10](../../konzept.md), [TODO.md](../../TODO.md)

## Entscheidungsvorschlag

| Element | Core (kostenlos) | Pro-Add-on |
|---|---|---|
| Produktname (UI, Doku, Shop) | Review Captain | Review Captain Pro |
| Plugin-Slug / Ordner | `review-captain` | `review-captain-pro` |
| Hauptdatei | `review-captain/review-captain.php` | `review-captain-pro/review-captain-pro.php` |
| Textdomain | `review-captain` | `review-captain-pro` |
| Domain Path | `/languages` | `/languages` |
| PHP-Namespace | `ReviewCaptain\` | `ReviewCaptainPro\` (nach Captain-Pro-Add-on-Kernel-Konvention, ggf. unter dem Captain-Pro-Vendor-Präfix) |
| Konstanten-Präfix | `REVIEW_CAPTAIN_` | `REVIEW_CAPTAIN_PRO_` |
| Funktions-/Hook-Präfix | `review_captain_` (intern), öffentliche Verträge `wp_review_captain_*_v{n}` | `review_captain_pro_` |
| Options-/Meta-Präfix | `review_captain_` bzw. `_review_captain_` | `review_captain_pro_` |
| Tabellenpräfix | `{$wpdb->prefix}rc_` (z. B. `wp_rc_reviews`) | keine eigenen Tabellen ohne Freigabe; Pro-Tabellen ebenfalls `rc_` mit Besitzvermerk im Schema |
| Capabilities | `rc_*` (z. B. `rc_moderate_reviews`) – Liste in RC-029 | dieselben Core-Capabilities, keine Parallelrechte |
| REST-Namespace | `review-captain/v1` | `review-captain-pro/v1` |
| Shortcode | `[review_captain_widget id="…"]` (versioniert über Attribut `v`) | Pro erweitert nur Attribute, keinen zweiten Shortcode-Namen |
| Block-Namespace | `review-captain/*` | `review-captain-pro/*` |
| WP-CLI | `wp review-captain …` | `wp review-captain pro …` (Subcommand) |
| Lizenz-Produktkey | – (Core ohne Lizenz) | `review-captain-pro` |
| Entitlement-Präfix | – | `review.` |
| Repository | `hastla007/wp-reviewmaster` | `hastla007/captain-pro/addons/review-captain-pro` |

## Begründung

- Slug und Textdomain müssen für WordPress.org identisch sein; `review-captain` ist kurz, sprechend und passt zur Captain-Suite-Namenslogik (Form Captain, Pass Captain, Voucher Captain).
- Der Repository-Name `wp-reviewmaster` bleibt historisch bestehen; er erscheint nicht im Plugin, in der Textdomain oder im Release-ZIP. Der ZIP-Ordner heißt immer `review-captain`.
- Kurzpräfix `rc_` nur für Tabellen und Capabilities (Länge, Lesbarkeit); alle globalen PHP-Symbole liegen im Namespace bzw. tragen das lange Präfix, um Kollisionen mit Fremdplugins zu vermeiden.

## Prüfungen vor Freigabe

- [ ] Verfügbarkeit des Slugs `review-captain` im WordPress.org-Verzeichnis prüfen (Slug wird bei Einreichung vergeben; Kollision → Alternative `captain-reviews` vorhalten).
- [ ] Markenrechtliche Kurzprüfung „Review Captain“ in den Zielmärkten (DE/AT/EU).
- [ ] Abgleich mit der Captain-Pro-Build-Registry: Namespace-Konvention und Vendor-Präfix des Add-on-Kernels übernehmen.
- [ ] Tabellenpräfix `rc_` gegen bekannte Plugins mit gleichem Präfix prüfen; bei Konflikt `revcap_`.

## Offen / Freigabe

- Produktverantwortung bestätigt Namen und Slugs.
- Captain-Pro-Maintainer bestätigt Namespace des Add-ons.
