# ---------------------------------------------------------------------------
# Production image for the Secure Hotel Management System API.
#
# NOTE: this file is provided as deployment scaffolding. It was written and
# reviewed but NOT built on the development machine (Docker is not installed
# there). Validate it once in your CI/host before relying on it.
#
# Build:  docker build -t hotel-api .
# Run:    docker run -p 8080:8080 --env-file .env hotel-api
# ---------------------------------------------------------------------------
FROM php:8.4-apache

# --- Apache: serve only the Laravel public/ directory -----------------------
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PORT=8080

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf \
      /etc/apache2/apache2.conf \
      /etc/apache2/conf-available/*.conf \
 && a2enmod rewrite headers

# --- System + PHP extensions ------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev libonig-dev libsodium-dev libargon2-dev default-mysql-client \
 && docker-php-ext-configure password --with-password-argon2 \
 && docker-php-ext-install pdo_mysql mbstring sodium zip opcache password \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy the application and install production dependencies.
COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["apache2-foreground"]
