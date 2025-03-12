#!/bin/bash

# Get the PHP binary path dynamically
PHP_BIN=$(which php)
if [ -z "$PHP_BIN" ]; then
  echo "PHP binary not found."
  exit 1
fi

# Create a log file for cron
touch /var/log/cron.log
chown www-data:www-data /var/log/cron.log

# Add cron job for Laravel schedule
echo "* * * * * www-data $PHP_BIN /var/www/html/artisan schedule:run >> /var/log/cron.log 2>&1" > /etc/cron.d/laravel-scheduler
chmod 0644 /etc/cron.d/laravel-scheduler
crontab /etc/cron.d/laravel-scheduler

# Start the cron service
echo "Running cron service"
cron

# Start job execution service
echo "Running job execution service"
$PHP_BIN /var/www/html/artisan queue:work --timeout=0