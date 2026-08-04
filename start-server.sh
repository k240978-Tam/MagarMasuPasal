#!/bin/bash
set -e

# Get the port from the PORT environment variable, default to 8080
PORT=${PORT:-8080}

# Update Apache to listen on the specified port
sed -i "s/Listen 8080/Listen $PORT/" /etc/apache2/ports.conf

# Run migrations
php artisan migrate --force

# Start Apache in foreground
apache2ctl -D FOREGROUND
