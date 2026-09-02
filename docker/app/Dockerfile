ARG PHP_IMAGE_VERSION=8.5-fpm-alpine
ARG NODE_IMAGE_VERSION=24-alpine
FROM node:$NODE_IMAGE_VERSION AS node

FROM php:$PHP_IMAGE_VERSION AS base

COPY --from=node /usr/lib /usr/lib
COPY --from=node /usr/local/share /usr/local/share
COPY --from=node /usr/local/lib /usr/local/lib
COPY --from=node /usr/local/include /usr/local/include
COPY --from=node /usr/local/bin /usr/local/bin
COPY --from=node /opt /opt

RUN apk update && \
    apk add --no-cache bash build-base autoconf zip unzip imagemagick imagemagick-libs imagemagick-dev shadow linux-headers icu-dev oniguruma-dev libzip-dev libxml2-dev pcre-dev libpng-dev

# PHP extensions
RUN docker-php-ext-install intl zip xml pdo pdo_mysql mbstring gd soap pcntl
RUN docker-php-ext-enable intl zip xml pdo pdo_mysql mbstring gd soap pcntl

RUN pecl install imagick
RUN docker-php-ext-enable imagick

COPY ./conf.d/opcache.ini /usr/local/etc/php/conf.d/
COPY ./conf.d/custom.ini /usr/local/etc/php/conf.d/

RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

RUN usermod -u 1000 www-data
RUN usermod -a -G www-data root
RUN mkdir -p /var/www
RUN chown -R www-data:www-data /var/www
RUN mkdir -p /var/www/.composer
RUN chown -R www-data:www-data /var/www/.composer

RUN mkdir -p /var/www/storage/sessions
RUN chown -R www-data:www-data /var/www/storage/sessions
RUN mkdir -p /var/www/storage/cache
RUN chown -R www-data:www-data /var/www/storage/cache
RUN mkdir -p /var/www/storage/views
RUN chown -R www-data:www-data /var/www/storage/views

FROM base AS local-dev

WORKDIR /var/www/project/
