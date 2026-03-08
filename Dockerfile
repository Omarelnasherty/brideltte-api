FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite headers

# Install MySQL PDO
RUN docker-php-ext-install pdo pdo_mysql

# Set document root to /var/www/html
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Copy API files
COPY . /var/www/html/

# Create upload directories
RUN mkdir -p /var/www/html/uploads/vendors \
    /var/www/html/uploads/avatars \
    /var/www/html/uploads/reviews \
    /var/www/html/rate_limits \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/rate_limits

# Configure Apache
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

# Use PORT env variable from Railway
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE ${PORT}

CMD ["apache2-foreground"]
