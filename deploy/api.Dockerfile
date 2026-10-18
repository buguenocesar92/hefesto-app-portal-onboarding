# Imagen del backend del Portal de Onboarding de Hefesto.
#
# Versiones fijadas a propósito: una imagen que se mueve sola hace imposible saber qué
# se probó. La versión de PHP y la de las extensiones van anotadas, y `composer install`
# usa el archivo composer.lock que acompaña al código.

FROM php:8.3-fpm-alpine3.20

# Extensiones que necesita Laravel con PostgreSQL y Redis.
RUN apk add --no-cache \
        icu-dev \
        libpq-dev \
        postgresql-dev \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        bcmath \
    && apk del $PHPIZE_DEPS

# Extensión de Redis para la caché y las colas.
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

COPY --from=composer:2.8.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Primero solo el manifiesto, para que la instalación de paquetes se reutilice mientras
# el código cambie.
COPY composer.json composer.lock* ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
    || composer install --no-dev --no-interaction --no-progress --prefer-dist

COPY . .

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

# La aplicación no necesita permisos de escritura en el código.
RUN chmod -R ug+rwx storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]