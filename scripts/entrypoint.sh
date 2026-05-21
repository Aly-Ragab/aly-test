#!/bin/sh
set -e

# Ensure the var folder exists with correct permissions
mkdir -p var
chown -R www-data:www-data var

# Initialize the SQLite database file using the native sqlite3 CLI binary
if [ ! -f var/data.db ]; then
    echo "Initializing fresh SQLite database schema..."
    sqlite3 var/data.db < scripts/init.sql
    chown www-data:www-data var/data.db
fi

# Execute the main container command (keeps the container or worker alive)
exec "$@"
