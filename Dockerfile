FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip libpng-dev libonig-dev libxml2-dev zip

RUN docker-php-ext-install pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

CMD php artisan migrate --force && \
    php artisan storage:link \
    php artisan queue:work --daemon & \
    php artisan serve --host=0.0.0.0 --port=8000 