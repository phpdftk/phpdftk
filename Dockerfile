FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install zlib zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# openssl and simplexml are bundled with php:8.4-cli

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-progress --no-scripts

COPY . .
RUN composer dump-autoload

ENTRYPOINT ["vendor/bin/phpunit"]
