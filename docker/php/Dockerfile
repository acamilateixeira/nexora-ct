# syntax=docker/dockerfile:1

FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache git unzip mysql-client \
 && docker-php-ext-install pdo_mysql opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-fpm.d/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf

ARG UID=1000
ARG GID=1000
RUN group="$(getent group "${GID}" | cut -d: -f1)" \
 && if [ -z "${group}" ]; then addgroup -g "${GID}" app && group=app; fi \
 && adduser -D -u "${UID}" -G "${group}" app

WORKDIR /var/www/html

FROM base AS dev

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
 && pecl install xdebug-3.5.3 \
 && docker-php-ext-enable xdebug \
 && apk del .build-deps
COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

USER app

FROM base AS vendor
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* vite.config.js tailwind.config.js postcss.config.js ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY resources ./resources
RUN npm run build

FROM base AS prod

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr_json
COPY docker/php/conf.d/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache-prod.ini
COPY --chown=app:app . .
COPY --chown=app:app --from=vendor /var/www/html/vendor ./vendor
COPY --chown=app:app --from=assets /app/public ./public
RUN composer dump-autoload --optimize --no-dev \
 && rm -rf docker tests .env.testing* \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R app:app storage bootstrap/cache

USER app
