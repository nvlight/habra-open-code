FROM php:8.5-fpm-alpine AS builder

WORKDIR /app

RUN apk add --no-cache \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    linux-headers \
    autoconf \
    g++ \
    make \
    && mkdir -p /tmp/redis && cd /tmp/redis \
    && curl -sSL https://pecl.php.net/get/redis-6.3.0.tgz -o redis.tgz \
    && tar xzf redis.tgz \
    && cd redis-6.3.0 \
    && phpize && ./configure && make -j$(nproc) && make install \
    && docker-php-ext-enable redis \
    && cd / && rm -rf /tmp/redis \
    && docker-php-ext-install pdo_pgsql pcntl zip bcmath mbstring xml

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && rm -rf tests database/migrations database/seeders database/factories

# Production stage
FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
    curl \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    supervisor \
    fcgi \
    && docker-php-ext-install pdo_pgsql pcntl zip bcmath mbstring xml

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

COPY --from=builder /app/vendor /var/www/html/vendor
COPY --from=builder /app/public /var/www/html/public
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
