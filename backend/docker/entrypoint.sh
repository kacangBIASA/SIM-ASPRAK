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

# Admin pertama (Render gratis tanpa shell): dibuat bila ADMIN_* diisi; dilewati jika sudah ada.
if [ -n "$ADMIN_USERNAME" ] && [ -n "$ADMIN_PASSWORD" ]; then
  php artisan admin:buat "$ADMIN_USERNAME" "${ADMIN_EMAIL:-admin@example.com}" "$ADMIN_PASSWORD" --nama="${ADMIN_NAMA:-Admin}"
fi

# Hanya satu MPM yang boleh aktif (mod_php => prefork); cetak daftar agar mudah didiagnosis di log.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
ls /etc/apache2/mods-enabled | grep -i mpm || true

exec apache2-foreground
