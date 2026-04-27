# ─── Build arguments ──────────────────────────────────────────────────────────
ARG PHP_VERSION=8.4
ARG COMPOSER_VERSION=2.8

# ═══════════════════════════════════════════════════════════════════════════════
# Stage: base — extensions communes à dev et prod
# ═══════════════════════════════════════════════════════════════════════════════
FROM php:${PHP_VERSION}-fpm-alpine AS base

# Dépendances système
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    zip \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    libsodium-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    linux-headers \
    $PHPIZE_DEPS

# Extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    intl \
    mbstring \
    pdo \
    pdo_mysql \
    zip \
    opcache \
    sodium \
    gd \
    sysvsem

# Redis (depuis PECL)
RUN pecl install redis-6.1.0 \
 && docker-php-ext-enable redis

# APCu
RUN pecl install apcu \
 && docker-php-ext-enable apcu

# Composer
COPY --from=composer:${COMPOSER_VERSION} /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ═══════════════════════════════════════════════════════════════════════════════
# Stage: dev — outils de développement + Xdebug
# ═══════════════════════════════════════════════════════════════════════════════
FROM base AS dev

# Xdebug pour la couverture de tests
RUN pecl install xdebug-3.4.0 \
 && docker-php-ext-enable xdebug

COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/php/conf.d/php-dev.ini /usr/local/etc/php/conf.d/php-dev.ini

# Utilisateur non-root (bonne pratique sécurité)
RUN addgroup -g 1000 app \
 && adduser -u 1000 -G app -s /bin/bash -D app \
 && chown -R app:app /app

USER app

# ═══════════════════════════════════════════════════════════════════════════════
# Stage: prod — image optimisée
# ═══════════════════════════════════════════════════════════════════════════════
FROM base AS prod

COPY docker/php/conf.d/php-prod.ini /usr/local/etc/php/conf.d/php-prod.ini

# Copier le code source
COPY --chown=www-data:www-data . .

# Installer les dépendances sans dev
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-progress \
    --optimize-autoloader \
    --classmap-authoritative

# Warmup du cache Symfony
RUN APP_ENV=prod APP_SECRET=placeholder php bin/console cache:warmup --env=prod

RUN chown -R www-data:www-data /app/var

USER www-data
