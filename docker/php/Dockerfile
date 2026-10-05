FROM php:8.4-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libmagickwand-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip opcache pcntl bcmath exif \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g www-data www-data

WORKDIR /var/www/html
USER www-data
