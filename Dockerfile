# syntax=docker/dockerfile:1

FROM php:8.4-apache

# 1. Instalacija sistemskih paketa i PHP ekstenzija
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip

# 2. Instalacija Composera
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Omogući Apache mod_rewrite
RUN a2enmod rewrite

# 4. Podesi Apache Document Root direktno na podfolder projekta
ENV APACHE_DOCUMENT_ROOT /var/www/html/PregledLicnihFinansija/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Postavi radni direktorijum i kopiraj sve fajlove projekta
WORKDIR /var/www/html
COPY . /var/www/html

# 6. AUTOMATSKA INSTALACIJA COMPOSER DEPENDENCIJA
RUN composer install --working-dir=PregledLicnihFinansija --no-interaction --optimize-autoloader --ignore-platform-reqs

# 7. Postavi dozvole za Laravel unutar podfoldera
RUN mkdir -p /var/www/html/PregledLicnihFinansija/storage /var/www/html/PregledLicnihFinansija/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/PregledLicnihFinansija/storage /var/www/html/PregledLicnihFinansija/bootstrap/cache

# 8. Koristi razvojnu PHP konfiguraciju
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# 9. Pripremi entrypoint skriptu 
RUN chmod +x /var/www/html/docker-entrypoint.sh
RUN sed -i 's/\r$//' /var/www/html/docker-entrypoint.sh

# 10. Pokreni skriptu pri startu kontejnera
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]