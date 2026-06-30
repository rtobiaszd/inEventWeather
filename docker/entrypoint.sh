#!/bin/sh
set -e

cd /var/www/html

# Bind mount do Windows não preserva permissões — garante que PHP pode escrever
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Garante vendor/ mesmo após "docker compose down -v" (volume zerado)
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ vazio — instalando dependências..."
    composer install --no-dev --optimize-autoloader --no-interaction --no-security-blocking --no-scripts
    echo "[entrypoint] Dependências instaladas."
fi

# Registra service providers (equivale ao post-autoload-dump)
php artisan package:discover --ansi 2>/dev/null || true

echo "[entrypoint] Aguardando MySQL em ${DB_HOST}:${DB_PORT}..."
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'),getenv('DB_USERNAME'),getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 2
done
echo "[entrypoint] MySQL disponível."

echo "[entrypoint] Executando migrations..."
php artisan migrate --force

echo "[entrypoint] Executando seeders..."
php artisan db:seed --force

echo "[entrypoint] Cacheando config, rotas e eventos..."
php artisan config:cache
php artisan route:cache
php artisan event:cache
echo "[entrypoint] Iniciando php-fpm..."
exec php-fpm
