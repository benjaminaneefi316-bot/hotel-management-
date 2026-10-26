#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Container entrypoint: bind Apache to $PORT, prepare the app, then start it.
#
# Note: we deliberately do NOT use `set -e`. A non-fatal problem (a seeder
# hiccup, a cache warm-up warning) must not prevent Apache from starting,
# otherwise the platform healthcheck fails and the whole deploy is marked
# failed with no service ever listening.
# ---------------------------------------------------------------------------
set -uo pipefail

PORT="${PORT:-8080}"
echo "[entrypoint] starting (PORT=${PORT}, APP_ENV=${APP_ENV:-unset})"

# --- Apache -----------------------------------------------------------------
# Debian's apache2 can end up with more than one MPM enabled, which makes
# Apache refuse to start with "AH00534: More than one MPM loaded" and leaves
# nothing listening on any port. Force exactly one (prefork, which the PHP
# module needs).
echo "[entrypoint] MPMs before: $(ls /etc/apache2/mods-enabled/ 2>/dev/null | grep mpm | tr '\n' ' ')"
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true
echo "[entrypoint] MPMs after:  $(ls /etc/apache2/mods-enabled/ 2>/dev/null | grep mpm | tr '\n' ' ')"

# Listen on the port the platform expects, plus 80 as a safety net, so the
# edge proxy can reach us whichever port it probes.
{
  echo "Listen 80"
  if [ "${PORT}" != "80" ]; then echo "Listen ${PORT}"; fi
} > /etc/apache2/ports.conf

if [ "${PORT}" = "80" ]; then VHOST_PORTS="*:80"; else VHOST_PORTS="*:80 *:${PORT}"; fi

cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost ${VHOST_PORTS}>
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog /dev/stderr
    CustomLog /dev/stdout combined
</VirtualHost>
EOF

# --- Application ------------------------------------------------------------
mkdir -p storage/framework/{sessions,views,cache} storage/logs storage/app/public
chown -R www-data:www-data storage bootstrap/cache || true
php artisan storage:link >/dev/null 2>&1 || true

# Optional: run migrations on boot (set RUN_MIGRATIONS=true).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "[entrypoint] running migrations..."
  php artisan migrate --force || echo "[entrypoint] WARNING: migrations failed (continuing)"
fi

# Cache configuration/routes/views for production performance.
if [ "${CACHE_APP_CONFIG:-true}" = "true" ]; then
  echo "[entrypoint] caching config/routes/views..."
  php artisan config:cache || echo "[entrypoint] WARNING: config:cache failed (continuing)"
  php artisan route:cache || echo "[entrypoint] WARNING: route:cache failed (continuing)"
  php artisan view:cache || echo "[entrypoint] WARNING: view:cache failed (continuing)"
fi

# Optional: seed the demo dataset. This runs in the background so a large
# first-time seed (60 guests + 80 bookings) can never delay the healthcheck.
# `demo:seed` is guarded: it fills missing guests/bookings and never resets
# accounts, so it is safe to leave RUN_SEEDERS=true on every boot.
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  echo "[entrypoint] seeding demo data in the background (guarded)..."
  nohup php artisan demo:seed >> storage/logs/demo-seed.log 2>&1 &
fi

echo "[entrypoint] launching: $*"
exec "$@"
