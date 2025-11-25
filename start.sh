#!/bin/sh
cd /app/backend

php artisan config:clear
php artisan cache:clear
php artisan optimize

echo "Laravel inicializado e otimizado!"
exec "$@"
