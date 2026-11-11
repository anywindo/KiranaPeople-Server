#!/bin/sh
set -e

# Run package discovery with runtime environment
php artisan package:discover --ansi || true

# Run database migrations by default (unless explicitly disabled)
if [ "${RUN_MIGRATIONS}" != "false" ] && [ "${SKIP_MIGRATIONS}" != "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || true
fi

# Cache config, routes, views in production
if [ "${APP_ENV}" = "production" ]; then
    echo "Caching Laravel configuration and routes..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Ensure storage directories exist and have proper permissions
mkdir -p /app/storage/framework/cache/data \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/storage/logs
chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

# Start background queue worker (unless explicitly disabled)
if [ "${START_QUEUE_WORKER}" != "false" ]; then
    echo "Starting background queue worker..."
    php artisan queue:work --sleep=3 --tries=3 --max-time=3600 > /dev/null 2>&1 &
fi

# Start background scheduler loop if enabled
if [ "${START_SCHEDULER}" = "true" ]; then
    echo "Starting background scheduler loop..."
    (while true; do php artisan schedule:run --no-interaction --quiet; sleep 60; done) > /dev/null 2>&1 &
fi

exec "$@"
