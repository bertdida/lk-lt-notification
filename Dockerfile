FROM php:7.4-apache

RUN docker-php-ext-install pdo pdo_mysql
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

ADD https://github.com/ufoscout/docker-compose-wait/releases/download/2.7.3/wait /wait
RUN chmod +x /wait

WORKDIR /home/appuser
COPY . .

RUN chmod +x entrypoint.sh