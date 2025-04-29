FROM php:8.2-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    libzip-dev \
    unzip \
    cron \
    redis-tools \
    vim \
    && docker-php-ext-install zip pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Create required storage directories and files
RUN mkdir -p storage/framework/{cache/data,sessions,views} storage/logs && \
    touch storage/logs/laravel.log

# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader --no-cache

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy and run cron setup script
COPY cron-setup.sh /usr/local/bin/cron-setup.sh
RUN chmod +x /usr/local/bin/cron-setup.sh

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php-fpm
