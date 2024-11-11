# Use the official PHP image
FROM --platform=linux/amd64 php:8.2-cli

# Install system dependencies
RUN apt-get update && \
    apt-get install -y vim libsqlite3-dev curl zip unzip git && \
    docker-php-ext-install pdo_mysql && \
    rm -rf /var/lib/apt/lists/*  # Clean up to reduce image size

# Set working directory
WORKDIR /ft

COPY composer.json /ft/

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Generate a new composer.lock file and install Laravel dependencies
RUN composer install --no-scripts --no-autoloader --no-cache

# Copy the rest of the application files
COPY . /ft

# Create required storage directories and files
RUN mkdir -p storage/framework/cache/data && \
    mkdir -p storage/framework/sessions && \
    mkdir -p storage/framework/views && \
    mkdir -p storage/logs && \
    touch storage/logs/laravel.log

# Run composer install with autoloading after copying files
RUN composer install --optimize-autoloader --no-dev --no-cache

CMD php artisan migrate && \
    nohup php artisan serve --host=0.0.0.0 --port=8000 & \
    php artisan queue:work