# Use the official PHP image
FROM --platform=linux/amd64 php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    curl \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*  # Clean up to reduce image size

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql

# Set working directory
WORKDIR /ft

# Copy composer.lock and composer.json first to leverage Docker cache
COPY composer.json composer.lock /ft/

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Laravel dependencies (temporarily)
RUN composer install --no-scripts --no-autoloader

# Copy the rest of the application files
COPY . /ft

# Run composer install with autoloading after copying files
RUN composer install --optimize-autoloader --no-dev

# Set file permissions
RUN chown -R www-data:www-data /ft/storage /ft/bootstrap/cache

# Run migrations and then serve Laravel application
CMD php artisan migrate && php artisan serve --host=0.0.0.0 --port=8000
