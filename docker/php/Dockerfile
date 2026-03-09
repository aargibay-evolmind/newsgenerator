FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libicu-dev \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
# Install Node.js (Version 20.x)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Use existing www-data user and modify its UID/GID
RUN usermod -u ${UID} www-data && groupmod -g ${GID} www-data

RUN mkdir -p /var/www/backend

RUN chown -R www-data:www-data /var/www/backend

# Set working directory
WORKDIR /var/www/backend

# Use the customized user
USER www-data
