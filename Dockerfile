FROM php:8.2-cli

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app

COPY . /app/

RUN mkdir -p /app/uploads/vendors /app/uploads/avatars /app/uploads/reviews /app/rate_limits \
    && chmod -R 777 /app/uploads /app/rate_limits

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "router.php"]
