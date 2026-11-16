#!/usr/bin/env bash
# Generates languages/review-captain.pot (RC-034). Requires WP-CLI (`wp i18n make-pot`).
# Translations: copy the POT to languages/review-captain-de_DE.po / -de_AT.po, translate, then
# `wp i18n make-mo languages` and `wp i18n make-json languages --no-purge` (block editor strings).
set -euo pipefail
cd "$(dirname "$0")/.."
wp i18n make-pot . languages/review-captain.pot \
  --slug=review-captain --domain=review-captain \
  --exclude=vendor,tests,docs,examples,build,node_modules \
  --headers='{"Report-Msgid-Bugs-To":"https://github.com/hastla007/wp-reviewmaster/issues"}'
