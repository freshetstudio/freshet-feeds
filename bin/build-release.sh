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
# Also tags HEAD v{version} locally when the tree is clean; pushing is manual.

set -e

cd "$(dirname "$0")/.."

# Build output counts as published — the exclude list below and .gitignore are
# separate lists, and only this one decides what a customer receives. So assert
# it rather than trust it. Filename rules come from .freshet/content-guard.sh,
# the same source the pre-commit hook and CI use.
assert_shippable() {
  local zip="$1" stray
  if ! unzip -Z1 "$zip" | bash .freshet/content-guard.sh --paths; then
    echo "Release blocked: internal file inside $zip" >&2
    exit 1
  fi
  stray=$(unzip -Z1 "$zip" | grep -iE '\.md$' || true)
  if [ -n "$stray" ]; then
    echo "Release blocked: repo docs inside $zip — readme.txt is the only doc that ships:" >&2
    echo "$stray" | sed 's/^/  /' >&2
    exit 1
  fi
}

# A shipped ZIP has to be reproducible from a commit, so every release build
# leaves a tag behind. Local only — pushing stays a deliberate, separate step.
tag_release() {
  local tag="v${VERSION}" existing

  if ! git rev-parse --git-dir >/dev/null 2>&1; then
    echo "Not a git checkout — skipping tag $tag." >&2
    return
  fi

  existing=$(git rev-parse -q --verify "refs/tags/$tag^{commit}" || true)
  if [ -n "$existing" ]; then
    if [ "$existing" = "$(git rev-parse HEAD)" ]; then
      echo "Tag $tag already on HEAD."
      return
    fi
    echo "Release built, but $tag already points at another commit — bump the version." >&2
    exit 1
  fi

  if [ -n "$(git status --porcelain)" ]; then
    echo "Uncommitted changes — not tagging. Commit, then: git tag -a $tag -m \"freshet-feeds ${VERSION}\"" >&2
    return
  fi

  git tag -a "$tag" -m "freshet-feeds ${VERSION}"
  echo "Tagged $tag — push it with: git push origin $tag"
}

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
  --exclude='.freshet' \
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

# The "prepare" script runs bash .freshet/install-hooks.sh on every npm install,
# but .freshet is excluded above — so `npm install` in the shipped package would
# fail. Strip it from the staged copy only; the dev repo keeps its hook install.
node -e "const fs=require('fs'),f='$STAGE/package.json',p=JSON.parse(fs.readFileSync(f));if(p.scripts){delete p.scripts.prepare}fs.writeFileSync(f,JSON.stringify(p,null,4)+'\n')"

(cd dist && zip -qr "$(basename "$ZIP")" freshet-feeds)
assert_shippable "$ZIP"

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
assert_shippable "$WPORG_ZIP"

rm -rf "$STAGE"

# Restore dev dependencies for local work.
composer install --quiet

echo "Built $ZIP ($(du -h "$ZIP" | cut -f1))"
echo "Built $WPORG_ZIP ($(du -h "$WPORG_ZIP" | cut -f1))"

tag_release
