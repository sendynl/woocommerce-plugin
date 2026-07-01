#!/usr/bin/env bash
# Bundle the project into a zip file for a pre-release.
# Mirrors what .github/workflows/publish_release.yml does, but locally.

set -euo pipefail

SLUG="sendy"

composer install --no-dev --no-interaction --optimize-autoloader
npm ci
npm run build

rm -f "$SLUG.zip"
TMPDIR=$(mktemp -d)

rsync -rc --exclude-from=".distignore" . "$TMPDIR/trunk/"

ln -s "$TMPDIR/trunk" "$TMPDIR/$SLUG"
cd "$TMPDIR"
zip -r "$OLDPWD/$SLUG.zip" "$SLUG"
cd "$OLDPWD"

rm -rf "$TMPDIR"

echo "Release package created: $SLUG.zip"
