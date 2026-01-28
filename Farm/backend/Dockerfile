FROM php:8.2-apache

# Install system dependencies including OpenSSL for MongoDB
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libssl-dev \
    pkg-config \
    && docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd zip

# Install MongoDB PHP extension with SSL support
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Redis PHP extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Xdebug for debugging
RUN pecl install xdebug && docker-php-ext-enable xdebug

# PHP error logging + Xdebug defaults + Opcache optimization
RUN /bin/sh -c "printf '%s\n' \
    'display_errors=On' \
    'display_startup_errors=On' \
    'log_errors=On' \
    'error_reporting=E_ALL' \
    'error_log=/var/www/html/logs/php_errors.log' \
    'xdebug.mode=off' \
    'xdebug.start_with_request=yes' \
    'xdebug.client_host=host.docker.internal' \
    'xdebug.client_port=9003' \
    'xdebug.discover_client_host=1' \
    'opcache.enable=1' \
    'opcache.memory_consumption=256' \
    'opcache.interned_strings_buffer=16' \
    'opcache.max_accelerated_files=20000' \
    'opcache.revalidate_freq=0' \
    'opcache.validate_timestamps=1' \
    'opcache.fast_shutdown=1' \
    'opcache.enable_cli=1' \
    > /usr/local/etc/php/conf.d/99-debug.ini"

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install PHP dependencies
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Apache configuration
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
