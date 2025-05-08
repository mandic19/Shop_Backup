FROM phpdockerio/php:8.4-fpm
WORKDIR "/application"

# Install selected extensions and other stuff
RUN apt-get update \
    && apt-get -y --no-install-recommends install \
        php8.4-mysql \
        php8.4-bcmath \
        cron \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \
    && mkdir -p /etc/cron.d \
    && touch /var/log/cron.log

# Copy crontab file and startup script
COPY crontab /etc/cron.d/crontab
COPY start.sh /start.sh

# Setup permissions
RUN chmod 0644 /etc/cron.d/crontab \
    && crontab /etc/cron.d/crontab \
    && chmod +x /start.sh

# Run the command on container startup
CMD ["/start.sh"]
