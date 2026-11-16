#!/usr/bin/env bash
# Builds a reproducible release ZIP of the free core plugin (RC-033).
# Usage: bin/build-zip.sh [version]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
VERSION="${1:-$(grep -E '^\s*\*\s*Version:' review-captain.php | awk '{print $3}')}"
CONST_VERSION="$(grep -E "define\( 'REVIEW_CAPTAIN_VERSION'" review-captain.php | sed -E "s/.*'([^']+)' \);/\1/")"
if [[ "$VERSION" != "$CONST_VERSION" ]]; then
  echo "Version mismatch: header $VERSION vs constant $CONST_VERSION" >&2
  exit 1
fi
STABLE="$(grep -E '^Stable tag:' readme.txt | awk '{print $3}')"
echo "Building review-captain $VERSION (readme stable tag: $STABLE)"
OUT="$ROOT/build"
STAGE="$OUT/review-captain"
rm -rf "$OUT" && mkdir -p "$STAGE"
# Allowlist of shipped paths – docs, tests, dev config are excluded.
for p in review-captain.php uninstall.php readme.txt LICENSE src templates assets blocks languages; do
  [[ -e "$p" ]] && cp -R "$p" "$STAGE/"
done
find "$STAGE" -name '.DS_Store' -delete
# Reproducible: fixed mtimes and sorted entries.
find "$STAGE" -exec touch -h -d '2026-01-01T00:00:00Z' {} +
( cd "$OUT" && find review-captain -type f | LC_ALL=C sort | zip -X -q "review-captain-$VERSION.zip" -@ )
echo "$OUT/review-captain-$VERSION.zip"
