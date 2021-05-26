FROM php:7.4-apache

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y libzip-dev zlib1g-dev unzip
RUN docker-php-ext-install pdo pdo_mysql zip

ADD https://github.com/ufoscout/docker-compose-wait/releases/download/2.7.3/wait /wait
RUN chmod +x /wait

WORKDIR /home/appuser
COPY . .

RUN chmod +x entrypoint.sh