FROM php:8.3.12-fpm

WORKDIR /var/www/afr

RUN apt-get update && apt-get install -y \
    ca-certificates \
    curl \
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
    && update-ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl opcache pdo pdo_mysql gd zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu


COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Node.js
RUN curl -sL https://deb.nodesource.com/setup_current.x -o nodesource_setup.sh
RUN bash nodesource_setup.sh
RUN apt-get install nodejs -y
RUN npm install npm@latest -g
RUN command -v node
RUN command -v npm

COPY ./src /var/www/afr

RUN chown -R www-data:www-data /var/www/afr

EXPOSE 9000
