FROM php:8.3-fpm-alpine

# pacotes base
RUN apk add --no-cache \
    bash git curl unzip tzdata \
    libzip-dev icu-dev oniguruma-dev libpq-dev $PHPIZE_DEPS

# extensões nativas
RUN docker-php-ext-install pdo pdo_pgsql intl zip

# 👇 instala e habilita a extensão do Redis
RUN pecl install redis \
 && docker-php-ext-enable redis

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# garante que o usuário do php-fpm tenha ownership
RUN chown -R www-data:www-data /var/www/html

# (opcional) se quiser já preparar pastas de escrita
RUN mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache
