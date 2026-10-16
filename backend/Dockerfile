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
 && (a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true) \
 && a2enmod mpm_prefork rewrite headers

# --- System + PHP extensions ------------------------------------------------
# The php:8.4-apache base image is already built with mbstring, sodium and
# opcache enabled, and with password hashing compiled in against Argon2
# (--with-password-argon2). `password` is therefore NOT a buildable extension
# name (docker-php-ext-install rejects it, which aborts the build). We only
# need to add the two extensions that are genuinely missing.
RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev default-mysql-client \
 && docker-php-ext-install pdo_mysql zip \
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
