#!/usr/bin/env bash
#
# Builds the upload package for shared hosting without a terminal
# (DirectAdmin, cPanel): PHP dependencies and front-end assets included,
# installed from the browser at /install (D69). See docs/DEPLOYMENT.md.
#
#   scripts/build-directadmin.sh [output.zip]
#
# The zip holds two folders that go side by side in the domain folder:
#   nebo/          the application (kept out of reach of browsers)
#   public_html/   the website: index.php, .htaccess, build assets, icons
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$(realpath -m "${1:-$ROOT/nebo-stage-directadmin.zip}")"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
cd "$ROOT"

echo "→ Building front-end assets"
npm ci --no-audit --no-fund --silent
npm run build --silent

echo "→ Copying the application"
APP="$STAGE/nebo"
mkdir -p "$APP"
git ls-files -z --cached --others --exclude-standard \
  | grep -zv -E '^(tests/|website/|node_modules/|\.github/|deploy/|scripts/|docs/|public/build/|\.env|phpunit\.xml|\.editorconfig|\.gitattributes|\.gitignore|vite\.config\.js|package(-lock)?\.json|nebo-stage-.*\.zip)' \
  | xargs -0 -I{} cp --parents {} "$APP/"
mkdir -p "$APP/public"
cp -r public/build "$APP/public/build"

echo "→ Installing PHP dependencies (no dev packages)"
(cd "$APP" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --quiet)
# Packages installed from source carry their git history; the host needs none of it.
find "$APP/vendor" -name .git -type d -prune -exec rm -rf {} +
rm -f "$APP/vendor/laravel/framework/bin/splitsh-lite"

echo "→ Arranging nebo/ and public_html/"
mv "$APP/public" "$STAGE/public_html"
cp deploy/directadmin/index.php "$STAGE/public_html/index.php"
cp deploy/directadmin/env.directadmin "$APP/.env.directadmin"
cp deploy/directadmin/INSTALL.txt "$STAGE/INSTALL.txt"
printf '# Never serve the application folder.\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n' > "$APP/.htaccess"
for dir in storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
  mkdir -p "$APP/$dir"
done
rm -f "$APP/bootstrap/cache/"*.php.tmp "$APP/storage/logs/"*.log
echo "$(date -u +%Y.%m.%d) ($(git rev-parse --short HEAD)$(git diff --quiet HEAD -- || echo '+changes'))" > "$APP/VERSION"

echo "→ Zipping"
rm -f "$OUT"
(cd "$STAGE" && zip -qr -X "$OUT" nebo public_html INSTALL.txt)
echo "✓ $(du -h "$OUT" | cut -f1)  $OUT  (version $(cat "$APP/VERSION"))"
