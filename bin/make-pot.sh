#!/usr/bin/env bash
# Generates languages/review-captain.pot (RC-034). Requires WP-CLI (`wp i18n make-pot`).
# Source language is English (SET-RC-14). Translations: languages/review-captain-de_DE.po ("du") and
# -de_DE_formal.po ("Sie"); de_AT/de_CH are derived by bin/derive-de-variants.php. After updating the POT,
# merge new strings into the .po files, translate them, then run `php bin/compile-translations.php`
# (writes .mo and block-editor .json without WP-CLI; `--check` in CI).
set -euo pipefail
cd "$(dirname "$0")/.."
wp i18n make-pot . languages/review-captain.pot \
  --slug=review-captain --domain=review-captain \
  --exclude=vendor,tests,docs,examples,build,node_modules \
  --headers='{"Report-Msgid-Bugs-To":"https://github.com/hastla007/wp-reviewmaster/issues"}'
