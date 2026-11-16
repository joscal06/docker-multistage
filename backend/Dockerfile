# syntax=docker/dockerfile:1

# =============================================================================
# Backend · API REST de e-commerce (Laravel 12, PHP 8.2)
# Build multi-stage con dos imágenes finales (targets):
#   · api → PHP-FPM con el código de Laravel        (docker build --target api)
#   · web → Nginx como proxy inverso hacia PHP-FPM  (docker build --target web)
# =============================================================================

ARG PHP_VERSION=8.2
ARG NGINX_VERSION=1.28

# -----------------------------------------------------------------------------
# Etapa 1 · php-base: PHP-FPM Alpine + extensiones que pide la actividad
# -----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS php-base

# La imagen oficial ya trae compiladas mbstring, openssl, tokenizer, xml, ctype
# y json; solo falta pdo_mysql. Se añade opcache para producción. Las
# herramientas de compilación ($PHPIZE_DEPS) se instalan y se borran en la
# MISMA capa para que no queden en la imagen.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql opcache \
 && apk del .build-deps \
 && rm -rf /tmp/* \
 && for ext in pdo_mysql mbstring openssl tokenizer xml ctype json; do \
      php -m | grep -qix "$ext" || { echo "Falta la extensión $ext"; exit 1; }; \
    done

WORKDIR /var/www/html

# -----------------------------------------------------------------------------
# Etapa 2 · vendor: dependencias de Composer (solo producción)
# Composer se toma de su imagen oficial y NO llega a la imagen final.
# -----------------------------------------------------------------------------
FROM php-base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

# 1) Solo composer.json/composer.lock → esta capa se reutiliza de la caché
#    mientras no cambien las dependencias, aunque cambie el código.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-autoloader \
                     --prefer-dist --no-interaction --no-progress

# 2) Con el código presente se genera el autoloader optimizado (classmap).
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# -----------------------------------------------------------------------------
# Etapa 3 · api: imagen final de PHP-FPM
# -----------------------------------------------------------------------------
FROM php-base AS api

# php.ini de producción + OPcache + pool de FPM con /ping para el HEALTHCHECK.
# fcgi aporta cgi-fcgi, el cliente FastCGI con el que se consulta /ping.
RUN apk add --no-cache fcgi \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/zz-produccion.ini /usr/local/etc/php/conf.d/zz-produccion.ini
COPY docker/php/zz-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/entrypoint

# Código de la aplicación (sin vendor/, ver .dockerignore) y, de la etapa de
# build, ÚNICAMENTE la carpeta vendor/.
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor

# Manifiesto de paquetes de Laravel (lo que haría el script post-autoload-dump)
# y permisos de escritura solo donde Laravel escribe.
RUN php artisan package:discover --ansi \
 && rm -rf docker \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache

# PHP-FPM corre completo con el usuario sin privilegios www-data
USER www-data

EXPOSE 9000

HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=6 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
      cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Etapa 4 · web: Nginx como proxy inverso (solo archivos públicos estáticos)
# -----------------------------------------------------------------------------
FROM nginx:${NGINX_VERSION}-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
# Nginx solo necesita public/ (favicon, robots.txt…); el PHP lo ejecuta el
# contenedor api a través de FastCGI.
COPY public /var/www/html/public
EXPOSE 80
HEALTHCHECK --interval=10s --timeout=5s --start-period=10s --retries=6 \
  CMD wget -qO- http://127.0.0.1/up >/dev/null || exit 1
