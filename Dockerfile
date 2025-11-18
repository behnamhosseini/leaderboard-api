FROM php:8.3-cli


RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql

RUN pecl install redis && docker-php-ext-enable redis
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY composer.json composer.lock* ./

RUN composer install --no-dev --optimize-autoloader
COPY . .
EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]

