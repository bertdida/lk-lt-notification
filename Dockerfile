FROM php:7.4-apache

RUN docker-php-ext-install pdo pdo_mysql
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /home/appuser
COPY . .