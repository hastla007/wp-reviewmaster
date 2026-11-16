#!/usr/bin/env bash
# Reproducible release ZIP for the free core (RC-033, RC-141). Output: dist/review-captain-<version>.zip
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(sed -n 's/^ \* Version:[[:space:]]*//p' review-captain.php | head -1)
STABLE=$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -1)
if [ "$VERSION" != "$STABLE" ]; then
	echo "Version mismatch: plugin header $VERSION vs readme.txt stable tag $STABLE" >&2
	exit 1
fi
grep -q "define( 'REVIEW_CAPTAIN_VERSION', '$VERSION' )" review-captain.php || { echo "REVIEW_CAPTAIN_VERSION constant does not match $VERSION" >&2; exit 1; }
rm -rf build dist && mkdir -p build/review-captain dist
rsync -a --exclude-from=.distignore ./ build/review-captain/
# No development files or secrets in the package.
if find build/review-captain \( -name '*.env' -o -name '*.pem' -o -name '*.p8' -o -name 'composer.*' -o -name 'phpunit*' \) | grep -q .; then
	echo "Forbidden file in package" >&2
	exit 1
fi
# Deterministic timestamps and order.
find build/review-captain -exec touch -d '2026-01-01 00:00:00' {} +
(cd build && find review-captain -type f | LC_ALL=C sort | zip -q -X -@ "../dist/review-captain-$VERSION.zip")
echo "Built dist/review-captain-$VERSION.zip"
