# syntax=docker/dockerfile:1
FROM php:8.4-fpm AS base

ARG MAIN_DIR

WORKDIR /var/www/html

# Instalacja rozszerzeń
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libcurl4-openssl-dev libgmp-dev libpq-dev make \
    && docker-php-ext-install -j$(nproc) intl pdo_mysql pdo_pgsql pgsql zip curl gmp \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN echo "date.timezone = UTC" > /usr/local/etc/php/conf.d/timezone.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/php.ini
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN usermod -u 1000 www-data

# Install Xdebug (PECL) and enable it
RUN apt-get update \
    && apt-get install -y --no-install-recommends autoconf build-essential pkg-config \
    && pecl channel-update pecl.php.net \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get purge -y --auto-remove autoconf build-essential pkg-config \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Etap deweloperski
FROM base AS dev
ARG MAIN_DIR
ENV APP_ENV=dev
USER www-data

# Skopiuj konfigurację Xdebug do conf.d (możesz też podmontować plik przez docker-compose)
COPY docker/php/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
