#!/bin/bash
#
# Rasterise the wp.org page assets from their SVG sources in .wordpress-org/.
#
#   bash bin/render-assets.sh
#
# wp.org banners/screenshots must be PNG (SVG is only accepted for the icon,
# which therefore ships as-is). macOS has no ImageMagick/PIL, so we rasterise
# with qlmanage (WebKit) — which pads its output to a square and top-left-aligns
# the content — then top-crop to the exact target size with bin/pngcrop.js.
#
# SVG sources whose content is near-square are authored on a SQUARE canvas so
# qlmanage renders them 1:1 (it "covers" non-square SVGs, clipping them).

set -euo pipefail
cd "$(dirname "$0")/.."

DIR=".wordpress-org"
CROP="node bin/pngcrop.js"

render() { # <svg> <render-size> <out.png> <crop-height>
  qlmanage -t -s "$2" -o "$DIR" "$DIR/$1" >/dev/null 2>&1
  $CROP "$DIR/${1%.svg}.svg.png" "$DIR/$3" "$4"
  rm -f "$DIR/${1%.svg}.svg.png"
}

# banner.svg is 772x250; render at 772 and 1544, crop to the two banner sizes.
render banner.svg      772  banner-772x250.png   250
render banner.svg     1544  banner-1544x500.png  500
# screenshot-1.svg is a 1280x1280 square; crop to 1280x960.
render screenshot-1.svg 1280 screenshot-1.png    960

echo "✓ Assets rendered in $DIR/ (icon.svg ships as-is)"
