FROM php:8.2-fpm

# Set working directory
WORKDIR /api

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json .

# Generate a new composer.lock file and install Laravel dependencies
RUN composer install --no-scripts --no-autoloader --no-cache

# Copy existing application code to the container
COPY . .

# Create required storage directories and files
RUN mkdir -p storage/framework/cache/data && \
    mkdir -p storage/framework/sessions && \
    mkdir -p storage/framework/views && \
    mkdir -p storage/logs && \
    touch storage/logs/laravel.log

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
