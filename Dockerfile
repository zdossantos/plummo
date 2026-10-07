FROM php:8.4-apache AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev curl \
    && docker-php-ext-install pdo_mysql zip opcache \
    && pecl install redis-6.3.0 && docker-php-ext-enable redis \
    && a2enmod rewrite && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/plummo.conf \
    && a2enconf plummo
WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
FROM php-base AS dependencies
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
FROM dependencies AS assets
COPY --from=oven/bun:1.3.14 /usr/local/bin/bun /usr/local/bin/bun
RUN bun install --frozen-lockfile && bun run build
FROM php-base AS runtime
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build
RUN chmod -R ug+rwX storage bootstrap/cache
HEALTHCHECK --interval=30s --timeout=5s CMD curl --fail http://localhost/up || exit 1
EXPOSE 80
