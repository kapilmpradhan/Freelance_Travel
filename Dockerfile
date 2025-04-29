# Stage 1: Build dependencies and install Composer
FROM php:8.2-fpm-alpine AS builder

# Install build dependencies for PHP extensions
RUN apk add --no-cache \
    autoconf \
    build-base \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy only Composer files to install dependencies
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && rm -rf /root/.composer

# Stage 2: Runtime image
FROM php:8.2-fpm-alpine

# Install only runtime dependencies
RUN apk add --no-cache \
    libzip \
    && rm -rf /var/cache/apk/*

# Copy PHP extensions and configurations from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php /usr/local/etc/php

# Set working directory
WORKDIR /var/www/html

# Copy PHP-FPM configuration and application files
COPY --from=builder /usr/local/etc/php-fpm.d /usr/local/etc/php-fpm.d
COPY --from=builder /var/www/html/vendor /var/www/html/vendor
COPY . .

# Create required storage directories and files
RUN mkdir -p storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    && touch storage/logs/laravel.log

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Clear and cache Laravel configurations
CMD php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php-fpm