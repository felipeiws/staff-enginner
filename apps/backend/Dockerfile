# syntax=docker/dockerfile:1.7
FROM composer:2.8 AS dependencies
WORKDIR /workspace

COPY apps/backend/composer.json apps/backend/composer.lock ./apps/backend/
COPY packages/contracts-php ./packages/contracts-php
WORKDIR /workspace/apps/backend
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.3-fpm-alpine AS runtime
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && addgroup -S -g 10001 atlas && adduser -S -D -H -u 10001 -G atlas atlas
WORKDIR /var/www/atlas
COPY --chown=atlas:atlas apps/backend ./
COPY --chown=atlas:atlas packages/contracts-php /var/packages/contracts-php
COPY --from=dependencies --chown=atlas:atlas /workspace/apps/backend/vendor ./vendor
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R atlas:atlas storage bootstrap/cache
COPY --chown=atlas:atlas docker/backend-entrypoint.sh /usr/local/bin/atlas-entrypoint
RUN chmod +x /usr/local/bin/atlas-entrypoint
USER atlas
EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/atlas-entrypoint"]
CMD ["php-fpm", "-F"]
