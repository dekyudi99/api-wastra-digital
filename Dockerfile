FROM php:8.2-apache

# 1. Install dependencies sistem & PHP extensions
RUN apt-get update && apt-get install -y \
    git curl unzip libpng-dev libonig-dev libxml2-dev zip \
    && docker-php-ext-install pdo pdo_mysql gd

# 2. Aktifkan modul Apache yang dibutuhkan Laravel
RUN a2enmod rewrite ssl proxy proxy_http

# 3. Ubah DocumentRoot Apache ke folder /public Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# 5. Install dependencies & permission
RUN composer install --no-dev --optimize-autoloader \
    && chmod -R 777 storage bootstrap/cache

# 6. Script startup untuk migrasi dan queue
CMD php artisan migrate --force && \
    (php artisan queue:work --daemon &) && \
    apache2-foreground