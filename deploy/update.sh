#!/usr/bin/env bash
# Social CRM — safe update for an ALREADY-INSTALLED site.
# Run from the server as the app user:  bash deploy/update.sh
# It NEVER touches: .env, storage/ (media + installed.lock), engine session
# folders (WhatsApp/Telegram logins), or the database contents beyond
# running new migrations. /install stays locked the whole time.
set -euo pipefail
cd "$(dirname "$0")/.."

echo "── 0. Safety checks ─────────────────────────────"
[ -f .env ] || { echo "ERROR: no .env here — this is not the installed app root."; exit 1; }
[ -f storage/app/installed.lock ] || echo "NOTE: no installed.lock yet — this update will create it (keeps /install closed)."
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "ERROR: server working tree has local changes. Commit/stash them first — update aborted, nothing changed."
  git status --short; exit 1
fi
mkdir -p storage/app/backups
cp .env "storage/app/backups/.env.bak-$(date +%Y%m%d%H%M%S)"
echo "OK: .env backed up to storage/app/backups/"

echo "── 1. Maintenance mode ──────────────────────────"
php artisan down || true

echo "── 2. Pull new code (fast-forward only) ─────────"
git pull --ff-only

echo "── 3. PHP dependencies ──────────────────────────"
composer install --no-dev --optimize-autoloader --no-interaction

echo "── 4. Database migrations (only new ones run) ───"
php artisan migrate --force

echo "── 5. Keep the installer locked ─────────────────"
php artisan install:mark || true

echo "── 6. Caches + queue workers ────────────────────"
php artisan optimize:clear
php artisan horizon:terminate || true   # Horizon respawns under PM2 with new code

echo "── 7. Engines + web (PM2, secret from env file) ─"
if [ -f storage/app/installer/engine-env.sh ]; then
  set -a; . storage/app/installer/engine-env.sh; set +a
fi
if [ -z "${INTERNAL_API_SECRET:-}" ] && grep -q '^INTERNAL_API_SECRET=' .env; then
  export INTERNAL_API_SECRET="$(grep '^INTERNAL_API_SECRET=' .env | cut -d= -f2- | tr -d '\"')"
fi
pm2 restart ecosystem.config.cjs --update-env

echo "── 8. Back live ─────────────────────────────────"
php artisan up
APP_URL_VAL="$(grep '^APP_URL=' .env | cut -d= -f2- | tr -d '\"')"
echo "Health check: ${APP_URL_VAL}/login"
curl -s -o /dev/null -w "login page HTTP %{http_code}\n" "${APP_URL_VAL}/login" || echo "(curl check failed — verify in a browser)"
echo "DONE. Test: dashboard loads, one Telegram/WhatsApp test message in + out."
