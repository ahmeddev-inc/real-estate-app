FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

# تثبيت الاعتماديات
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm

# تثبيت إضافات PHP
RUN docker-php-ext-install pdo pdo_pgsql gd zip bcmath

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# نسخ التطبيق
COPY . .

# صلاحيات المجلدات
RUN chown -R www-data:www-data /var/www/html/storage
RUN chmod -R 755 /var/www/html/storage

CMD ["php-fpm"]
