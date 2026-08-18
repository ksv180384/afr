FROM php:8.3.12-fpm

WORKDIR /var/www/afr

RUN apt-get update && apt-get install -y \
    ca-certificates \
    curl \
    nodejs \
    npm \
    zlib1g-dev \
    g++ \
    git \
    gnupg \
    libicu-dev \
    openssl \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    && update-ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install intl opcache pdo pdo_mysql gd zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu


COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Node.js
RUN command -v node
RUN command -v npm

COPY ./src /var/www/afr

RUN chown -R www-data:www-data /var/www/afr

EXPOSE 9000
