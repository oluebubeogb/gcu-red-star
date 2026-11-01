#!/bin/sh
# The Red Star boot hook — runs inside serversideup/php's entrypoint
# (copied to /etc/entrypoint.d/60-redstar.sh) before nginx/php-fpm start.
set -e
cd /var/www/html

echo "[redstar] preparing storage"
mkdir -p storage/app/public storage/app/private storage/app/magazines storage/framework/cache/data \
         storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
[ -e public/storage ] || php artisan storage:link || true

if [ -z "${APP_KEY}" ]; then
  echo "[redstar] ERROR: APP_KEY is empty. Generate one with: php artisan key:generate --show" >&2
  exit 1
fi

tries=0
until php artisan db:show --json >/dev/null 2>&1; do
  tries=$((tries+1))
  if [ "$tries" -ge "${REDSTAR_DB_WAIT_TRIES:-30}" ]; then
    echo "[redstar] database not reachable after $tries tries — continuing; migrate will report the error" >&2
    break
  fi
  echo "[redstar] waiting for database ($tries)…"
  sleep 2
done

echo "[redstar] php artisan migrate --force"
php artisan migrate --force

# Seeds an admin only if SEED_ADMIN_EMAIL + SEED_ADMIN_PASSWORD are set (idempotent).
php artisan db:seed --force || echo "[redstar] seed step failed — continuing" >&2

echo "[redstar] caching config/routes/views"
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan optimize
echo "[redstar] ready"
