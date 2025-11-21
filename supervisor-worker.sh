#!/bin/bash
cd /var/www/freelancetravel.com || exit 1

php artisan "$@"
