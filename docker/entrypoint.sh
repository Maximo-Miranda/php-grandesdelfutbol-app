#!/bin/sh
set -e

echo "Starting application setup..."

# Ensure storage directories exist with proper permissions
mkdir -p /var/www/html/storage/logs \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create storage link if it doesn't exist
php artisan storage:link --no-interaction 2>/dev/null || true

# Run migrations
php artisan migrate --force --no-interaction

# Cache configuration, routes, views, and events
php artisan optimize --no-interaction

echo "Application setup complete. Starting services..."

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
