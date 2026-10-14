#!/bin/sh
set -eu

php artisan migrate --force
php artisan db:seed --class=ServiceCategorySeeder --force

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
