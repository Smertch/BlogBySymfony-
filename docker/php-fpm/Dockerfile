FROM php:8.3-fpm

WORKDIR /application

# System packages required to build PHP extensions for Postgres + Kafka.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libpq-dev \
        librdkafka-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        opcache \
    && pecl install rdkafka redis \
    && docker-php-ext-enable rdkafka redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Forward Docker env vars (e.g. DATABASE_URL) into PHP-FPM workers
RUN sed -i 's/^;clear_env = no/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf

COPY docker-entrypoint.sh /usr/local/bin/blog-docker-entrypoint.sh
RUN chmod +x /usr/local/bin/blog-docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/blog-docker-entrypoint.sh"]
CMD ["php-fpm", "-F"]
