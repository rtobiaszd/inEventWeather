FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    curl \
    libxml2-dev \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        bcmath \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia tudo primeiro para que artisan exista antes do composer install
COPY backend/ .

# --no-audit: ignora advisory de segurança (bloqueante no Composer 2.7+)
# --no-scripts: evita rodar "php artisan package:discover" aqui (artisan precisa do .env)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-security-blocking --no-scripts

RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-www.conf
RUN chmod +x /entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
