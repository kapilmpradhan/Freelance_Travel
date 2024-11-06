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
COPY composer.json /ft/

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Generate a new composer.lock file and install Laravel dependencies
RUN composer install --no-scripts --no-autoloader --no-cache

# Copy the rest of the application files
COPY . /ft

# Copy the .env file
COPY .env /ft/.env

# Run composer install with autoloading after copying files
RUN composer install --optimize-autoloader --no-dev --no-cache

# Run migrations and then serve Laravel application
CMD php artisan migrate && php artisan serve --host=0.0.0.0 --port=8000
