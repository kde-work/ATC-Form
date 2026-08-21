FROM php:8.5-fpm

ARG UID=1000
ARG GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Расширения для Laravel 13: pdo_mysql, gd, intl, zip, pcntl, xdebug.
RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
        xdebug \
        @composer

COPY local.ini /usr/local/etc/php/conf.d/zz-local.ini

# Совпадение UID/GID с пользователем хоста, чтобы storage и cache писались без root.
RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g www-data www-data \
    && mkdir -p /var/www/html \
    && chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html
