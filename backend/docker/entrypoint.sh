#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Container entrypoint: bind Apache to $PORT, prepare the app, then start it.
# ---------------------------------------------------------------------------
set -euo pipefail

PORT="${PORT:-8080}"

# Make Apache listen on the port the host expects.
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:${PORT}>
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog /dev/stderr
    CustomLog /dev/stdout combined
</VirtualHost>
EOF

# Make sure writable paths exist and are writable.
mkdir -p storage/framework/{sessions,views,cache} storage/logs storage/app/public
chown -R www-data:www-data storage bootstrap/cache || true

# Optional: run migrations on boot (set RUN_MIGRATIONS=true for the first deploy
# or when you want the image to apply migrations itself).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running migrations..."
  php artisan migrate --force
fi

# Optional: seed the demo dataset. `demo:seed` is guarded, so it only does
# anything when the database is still empty — safe to leave enabled.
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  echo "Seeding demo data (skipped if data already exists)..."
  php artisan demo:seed || true
fi

# Cache configuration/routes/views for production performance, unless disabled.
if [ "${CACHE_APP_CONFIG:-true}" = "true" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

exec "$@"
