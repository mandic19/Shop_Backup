#!/bin/bash
set -e

echo "Starting services..."

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm8.4 --daemonize

# Start cron
echo "Starting cron service..."
cron

# Start supervisor
echo "Starting supervisor..."
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

echo "All services started successfully!"
echo "Watching logs..."

# Keep container running and tail logs
exec tail -f /var/log/cron.log /var/log/worker.out.log /var/log/worker.err.log
