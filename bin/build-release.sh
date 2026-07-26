#!/bin/bash
#
# Build distributable plugin ZIPs.
#
#   bash bin/build-release.sh
#     → dist/freshet-feeds-{version}-wporg.zip   (no UpdateChecker — directory guideline 8)
#     → dist/freshet-feeds-{version}.zip         (direct sales; updates opt-in via constant)
#
# Ships: PHP sources, compiled block (build/), templates, fixtures, autoloader.
# Excludes: dev tooling, tests, block JS sources, repo docs.

set -e

cd "$(dirname "$0")/.."

VERSION=$(grep -m1 "FRESHET_FEEDS_VERSION" freshet-feeds.php | sed "s/.*'\([0-9.]*\)'.*/\1/")
STAGE="dist/freshet-feeds"
ZIP="dist/freshet-feeds-${VERSION}.zip"

echo "Building freshet-feeds ${VERSION}..."

npm run build --silent
composer install --no-dev --quiet --optimize-autoloader

rm -rf dist
mkdir -p "$STAGE"

rsync -a \
  --exclude='.git*' \
  --exclude='.wordpress-org' \
  --exclude='.wporg-svn' \
  --exclude='node_modules' \
  --exclude='dist' \
  --exclude='tests' \
  --exclude='bin' \
  --exclude='data/fixtures/rss2-sample.xml' \
  --exclude='data/fixtures/atom-youtube.xml' \
  --exclude='data/fixtures/bluesky-feed.json' \
  --exclude='phpcs.xml.dist' \
  --exclude='phpunit.xml.dist' \
  --exclude='package-lock.json' \
  --exclude='webpack.config.js' \
  --exclude='CLAUDE.md' \
  --exclude='CREDENTIALS.md' \
  --exclude='DEPLOY.md' \
  --exclude='ROADMAP.md' \
  --exclude='TODO.md' \
  --exclude='README.md' \
  --exclude='.phpunit.cache' \
  --exclude='.vscode' \
  --exclude='.DS_Store' \
  ./ "$STAGE/"

(cd dist && zip -qr "$(basename "$ZIP")" freshet-feeds)

# wp.org variant (directory guidelines): strip the ENTIRE remote-license stack
# — no license checks, no update injection, no upsell surfaces ship to the
# directory (Guidelines 5 & 8). Plugin.php falls back to UnlimitedLicense via
# file checks. Regenerate the optimized classmap so no entry points at
# stripped files.
rm "$STAGE/src/License/UpdateChecker.php" \
   "$STAGE/src/License/RemoteLicense.php" \
   "$STAGE/src/License/LicenseClient.php" \
   "$STAGE/src/Provider/LinkedIn/ProxyLinkedInClient.php" \
   "$STAGE/src/Admin/LicenseSection.php"
(cd "$STAGE" && composer dump-autoload --no-dev --optimize --quiet)
WPORG_ZIP="dist/freshet-feeds-${VERSION}-wporg.zip"
(cd dist && zip -qr "$(basename "$WPORG_ZIP")" freshet-feeds)

rm -rf "$STAGE"

# Restore dev dependencies for local work.
composer install --quiet

echo "Built $ZIP ($(du -h "$ZIP" | cut -f1))"
echo "Built $WPORG_ZIP ($(du -h "$WPORG_ZIP" | cut -f1))"
