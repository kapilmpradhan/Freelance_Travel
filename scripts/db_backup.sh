#!/bin/bash

# Variables
CONTAINER_NAME=<container-name>
DB_NAME=<db-name>
DB_USER=<db-user>
DB_PASSWORD=<db-password>
LOCAL_DUMP_PATH=$(pwd)
DUMP_FILE_NAME="db_backup_$(date +'%Y-%m-%d').sql"

echo "Starting database dump..."
docker exec "$CONTAINER_NAME" sh -c "mysqldump --no-tablespaces -u $DB_USER -p'$DB_PASSWORD' $DB_NAME" > "$LOCAL_DUMP_PATH/$DUMP_FILE_NAME"

if [ $? -eq 0 ]; then
    echo "Database dump completed successfully."

    # Remove previous dumps except the latest one
    echo "Removing old dump files..."
    find "$LOCAL_DUMP_PATH" -maxdepth 1 -type f -name 'db_backup_*.sql' ! -name "$DUMP_FILE_NAME" -exec rm -f {} \;
    echo "Old dumps removed."
else
    echo "Database dump failed!"
    exit 1
fi
