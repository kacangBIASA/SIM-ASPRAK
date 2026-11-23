#!/bin/sh
set -e
PORT="${PORT:-10000}"

# Apache mendengarkan port yang diberikan Render.
echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Cache konfigurasi memakai variabel lingkungan saat ini, lalu migrasi basis data (tanpa seeder).
php artisan config:cache
php artisan route:cache
php artisan migrate --force

exec apache2-foreground
