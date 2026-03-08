FROM php:8.2-cli

# Install MySQL PDO
RUN docker-php-ext-install pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html

# Copy API files
COPY . /var/www/html/

# Create upload directories
RUN mkdir -p /var/www/html/uploads/vendors \
    /var/www/html/uploads/avatars \
    /var/www/html/uploads/reviews \
    /var/www/html/rate_limits \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/rate_limits

# Copy startup script
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
