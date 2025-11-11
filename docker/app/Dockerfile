FROM php:8.2-apache

# Arguments
ARG APACHE_DOCUMENT_ROOT=/var/www/html/public

# Install system deps and PHP extensions commonly required by PrestaShop 9
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libjpeg62-turbo-dev libpng-dev libfreetype6-dev \
        libzip-dev libonig-dev libxml2-dev locales \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache document root
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Working directory where PrestaShop project will live (mounted as volume)
WORKDIR /var/www/html

# Copy entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
