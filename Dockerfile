# ==============================================================================
# Stage 1: Composer dependencies
# ==============================================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ==============================================================================
# Stage 2: Frontend assets build (needs PHP for Wayfinder route generation)
# ==============================================================================
FROM php:8.4-cli-alpine AS frontend

RUN apk add --no-cache nodejs npm

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN npm ci && npm run build

# ==============================================================================
# Stage 3: Production image
# ==============================================================================
FROM php:8.4-fpm-alpine

# Install build deps, compile extensions, then remove build deps in a single layer
RUN apk add --no-cache \
        nginx \
        supervisor \
        ffmpeg \
        libpq \
        libzip \
        icu-libs \
        libpng \
        libjpeg-turbo \
        libwebp \
        freetype \
    && apk add --no-cache --virtual .build-deps \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        linux-headers \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        opcache \
        intl \
        pcntl \
        gd \
        exif \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

# PHP + FPM production configuration (single layer)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=64'; \
        echo 'opcache.max_accelerated_files=30000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.save_comments=1'; \
        echo 'opcache.jit=on'; \
        echo 'opcache.jit_buffer_size=128M'; \
        echo 'upload_max_filesize=100M'; \
        echo 'post_max_size=100M'; \
        echo 'memory_limit=512M'; \
        echo 'expose_php=Off'; \
    } > "$PHP_INI_DIR/conf.d/99-production.ini" \
    && { \
        echo '[www]'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 20'; \
        echo 'pm.start_servers = 4'; \
        echo 'pm.min_spare_servers = 2'; \
        echo 'pm.max_spare_servers = 6'; \
        echo 'pm.max_requests = 1000'; \
    } > /usr/local/etc/php-fpm.d/zz-production.conf

# Config files and directories (rarely change — good cache layer)
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/log/supervisor

WORKDIR /var/www/html

# Application code
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=frontend /app/public/build ./public/build

# Storage and cache directories
RUN mkdir -p \
        storage/logs \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
