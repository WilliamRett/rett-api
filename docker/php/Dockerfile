FROM php:8.3-fpm-alpine

# deps base
RUN apk add --no-cache \
    bash \
    git \
    curl \
    unzip \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libpq-dev \
    tzdata

# extensões do Laravel + Postgres
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    zip

RUN apk add --no-cache $PHPIZE_DEPS \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && apk del --no-network $PHPIZE_DEPS

# composer (pega do container oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# permissões mínimas
RUN chown -R www-data:www-data /var/www/html

# php-fpm padrão
CMD ["php-fpm"]
