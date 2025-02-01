FROM php:8.3.12-fpm

WORKDIR /var/www/afr

RUN apt-get update && apt-get install -y \
    nodejs \
    npm \
    zlib1g-dev \
    g++ \
    git \
    libicu-dev \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl opcache pdo pdo_mysql gd zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu


RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

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
