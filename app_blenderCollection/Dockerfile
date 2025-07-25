FROM php:8.3-fpm-alpine

# Installation des dépendances système et extensions PHP
RUN apt-get update && apt-get install -y \
    git unzip zip curl libicu-dev libonig-dev libxml2-dev libzip-dev libpq-dev libjpeg-dev libpng-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql opcache zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installation de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Répertoire de travail
WORKDIR /var/www/html

# Copie du projet
COPY . .

# Permissions
RUN chown -R www-data:www-data /var/www/html
USER www-data
