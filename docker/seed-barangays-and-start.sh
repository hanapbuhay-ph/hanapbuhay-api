#!/bin/sh
set -eu

php artisan db:seed --class=BarangaySeeder --force

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
