FROM php:8.1-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    curl \
    unzip \
    git \
&& docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www

# Copy the Laravel app inside the container
COPY . /var/www

# Install all PHP dependencies
RUN composer install

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
