#!/usr/bin/env bash
# Builds the Nebo Stage website (website/) into an upload zip for nebostage.com.ng.
# The zip's contents go straight into the domain's public_html. Never commit the zip.
set -euo pipefail
cd "$(dirname "$0")/.."

[ -d node_modules/@fontsource-variable ] || npm ci --no-audit --no-fund
node website/build.mjs

out="$PWD/nebo-stage-website.zip"
rm -f "$out"
(cd website/dist && zip -qr -X "$out" . -x '.DS_Store')
echo "✓ $(du -h "$out" | cut -f1) $out"
