FROM php:8.5-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl git libfreetype-dev libicu-dev libjpeg62-turbo-dev libpng-dev \
        libsqlite3-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install bcmath gd intl pcntl pdo_sqlite zip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/copper-eagle.ini
COPY docker/php.dev.ini /usr/local/etc/php/conf.d/copper-eagle-dev.ini

WORKDIR /var/www/html

RUN git config --system --add safe.directory /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/copper-eagle-entrypoint
RUN chmod +x /usr/local/bin/copper-eagle-entrypoint

EXPOSE 8000 5175

ENTRYPOINT ["copper-eagle-entrypoint"]
CMD ["npx", "concurrently", "--kill-others", "--names", "app,vite", "php artisan serve --host=0.0.0.0 --port=8000", "npm run dev -- --host=0.0.0.0 --port=5175"]
