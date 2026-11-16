#!/usr/bin/env bash
# Generates languages/review-captain.pot (RC-034). Requires WP-CLI (`wp i18n make-pot`).
# Source language is German (du). Translations: languages/review-captain-en_US.po and -de_DE_formal.po
# (RC-138). After updating the POT, merge new strings into the .po files, translate them, then run
# `php bin/compile-translations.php` (writes .mo and block-editor .json without WP-CLI; `--check` in CI).
set -euo pipefail
cd "$(dirname "$0")/.."
wp i18n make-pot . languages/review-captain.pot \
  --slug=review-captain --domain=review-captain \
  --exclude=vendor,tests,docs,examples,build,node_modules \
  --headers='{"Report-Msgid-Bugs-To":"https://github.com/hastla007/wp-reviewmaster/issues"}'
