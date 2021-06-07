FROM php:7.4-fpm

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y libzip-dev zlib1g-dev unzip libgmp-dev
RUN ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/local/include/
RUN docker-php-ext-configure gmp
RUN docker-php-ext-install pdo pdo_mysql zip gmp

WORKDIR /var/www
COPY . /var/www

RUN chmod +x entrypoint.sh
EXPOSE 9000