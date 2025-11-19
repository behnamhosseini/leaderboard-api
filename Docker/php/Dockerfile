FROM php:8.3-fpm


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

# Install PCOV for code coverage (faster than Xdebug)
RUN pecl install pcov && docker-php-ext-enable pcov && \
    echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini && \
    echo "pcov.directory=/var/www/web/src" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/web
COPY web/composer.json web/composer.lock* ./

RUN composer install --optimize-autoloader

# Copy PHP-FPM pool configuration for high performance (before copying all files)
COPY Docker/php/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf

COPY web/ .

EXPOSE 9000

CMD ["php-fpm"]

