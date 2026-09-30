FROM php:8.3-fpm-alpine

# Installe facilement les extensions PHP courantes pour Symfony + Doctrine
COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl pdo_mysql zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# git et unzip sont utilisés par Composer pour télécharger les paquets
RUN apk add --no-cache git unzip

WORKDIR /var/www/html