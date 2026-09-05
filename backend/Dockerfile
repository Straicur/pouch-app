# syntax=docker/dockerfile:1
FROM php:8.4-fpm AS base

ARG MAIN_DIR

WORKDIR /var/www/html

# Instalacja rozszerzeń
# gd: thumbnails (Part 4 photo items). tesseract-ocr(-eng/-pol): OCR on photo items —
# a real binary, not a PHP extension, shelled out to via thiagoalessio/tesseract_ocr.
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libcurl4-openssl-dev libgmp-dev libpq-dev make \
    libpng-dev libjpeg62-turbo-dev libwebp-dev \
    tesseract-ocr tesseract-ocr-eng tesseract-ocr-pol \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) intl pdo_mysql pdo_pgsql pgsql zip curl gmp gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# pg_dump/pg_restore/psql — BackupServiceInterface shells out to these directly over
# the network (same "db" host/port as DATABASE_URL), not through the pdo_pgsql PHP
# extension. Debian trixie's own postgresql-client defaults to v17, one major ahead of
# the default pinned server version — a dump taken by a newer client can embed
# session-setup GUCs (e.g. transaction_timeout, added in PG17) the older server's own
# pg_restore/psql then reject, so the PGDG repo is added here to pin an exact version
# match instead. POSTGRES_VERSION must be passed as a build arg matching
# docker-compose.yml's own POSTGRES_VERSION (server image tag) — the two drift silently
# otherwise.
ARG POSTGRES_VERSION=16

RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates gnupg \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc --fail https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $(. /etc/os-release && echo "$VERSION_CODENAME")-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-${POSTGRES_VERSION} \
    && apt-get purge -y --auto-remove gnupg \
    && rm -rf /var/lib/apt/lists/*

RUN echo "date.timezone = UTC" > /usr/local/etc/php/conf.d/timezone.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/php.ini
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN usermod -u 1000 www-data

# Pre-created and owned by www-data so a fresh (root-owned by default) named
# volume mounted here inherits this ownership on its first use — otherwise
# BackupServiceInterface (running as www-data, see USER below) can't mkdir
# into it.
RUN mkdir -p /var/backups/pouch && chown www-data:www-data /var/backups/pouch

# Etap deweloperski
FROM base AS dev
ARG MAIN_DIR
ENV APP_ENV=dev

# Xdebug only in dev — never loaded in prod's image, not even disabled-by-config,
# since it doesn't get installed there at all (perf + attack surface).
RUN apt-get update \
    && apt-get install -y --no-install-recommends autoconf build-essential pkg-config \
    && pecl channel-update pecl.php.net \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get purge -y --auto-remove autoconf build-essential pkg-config \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

USER www-data

# Skopiuj konfigurację Xdebug do conf.d (możesz też podmontować plik przez docker-compose)
COPY docker/php/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Etap produkcyjny: kod i zależności wpieczone w obraz (dev bind-mountuje ./backend
# z hosta zamiast tego — patrz docker-compose.dev.yml). --no-scripts pomija
# auto-scripts (cache:clear + assets:install) — cache:clear łączy się z bazą, której
# nie ma w kontekście builda (cache warmup/migracje idą jako osobny krok po starcie
# kontenera, patrz docker-compose.prod.yml, tak jak dziś composer-install/migrate-dev
# są osobnymi krokami `make start` dla deva). assets:install nie potrzebuje bazy, więc
# jest odpalane osobno, żeby NelmioApiDocBundle (Swagger UI pod /api/doc) miał swoje
# assety w obrazie — to samo public/ kopiuje niżej etap nginx-prod.
FROM base AS prod
ENV APP_ENV=prod
ENV APP_DEBUG=0
COPY --chown=www-data:www-data . .
USER www-data
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction \
    && php bin/console assets:install public --no-interaction

# Backendowy nginx serwuje tylko public/ (fastcgi_pass robi całą resztę w php-fpm) —
# ten etap bierze je bezpośrednio z obrazu prod zamiast bind-mountować ./backend z
# hosta, żeby zawartość, którą nginx widzi, zawsze była dokładnie tym, co composer
# install/assets:install wygenerowały w obrazie (np. public/bundles/), nie tym, co
# akurat leży na hoście.
FROM nginx:stable-alpine AS nginx-prod
COPY --from=prod /var/www/html/public /var/www/html/public
COPY docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
