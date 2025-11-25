#!/bin/sh
cd /app/backend

# Garante que todas as pastas necessárias existam
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/app/private
mkdir -p bootstrap/cache

# Garante permissões corretas
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Cria arquivo de log se não existir
touch storage/logs/laravel.log 2>/dev/null || true
chmod 664 storage/logs/laravel.log 2>/dev/null || true

php artisan config:clear
php artisan cache:clear
php artisan optimize

echo "Laravel inicializado e otimizado!"
exec "$@"
