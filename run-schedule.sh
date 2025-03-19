#!/bin/bash

PHP_PATH="/usr/local/bin/php"
PROJECT_PATH=/var/www/html
LOG_FILE="$PROJECT_PATH/storage/logs/schedule.log"
ERROR_LOG="$PROJECT_PATH/storage/logs/schedule_error.log"

$PHP_PATH $PROJECT_PATH/artisan schedule:run >> $LOG_FILE 2>>$ERROR_LOG