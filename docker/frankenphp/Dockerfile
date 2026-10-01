FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions @composer intl pdo_pgsql opcache zip apcu

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

FROM base AS dev

RUN install-php-extensions xdebug
COPY docker/frankenphp/dev.ini $PHP_INI_DIR/conf.d/zz-dev.ini

FROM base AS prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/frankenphp/prod.ini $PHP_INI_DIR/conf.d/zz-prod.ini

# Dependencies first, to benefit from the Docker layer cache
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer dump-env prod \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup \
    && chown -R www-data:www-data var
