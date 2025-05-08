#!/bin/bash
set -e

echo "Starting services..."

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm8.4 --daemonize

# Start cron
echo "Starting cron service..."
cron

echo "All services started successfully!"
echo "Watching cron log..."

# Keep container running and display logs
exec tail -f /var/log/cron.log
