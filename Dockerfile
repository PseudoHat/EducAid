FROM php:8.2-cli

# Install system dependencies including Tesseract OCR, ImageMagick, GD build deps, and PostgreSQL dev
RUN apt-get update && apt-get install -y --no-install-recommends \
        tesseract-ocr \
        tesseract-ocr-eng \
        imagemagick \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libpq-dev \
        git \
        unzip \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (GD + PostgreSQL)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo pdo_pgsql pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Install root Composer dependencies first for better layer caching
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress || \
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy application files
COPY . /app

# Expose port
EXPOSE 8080

# Start PHP built-in server with router
CMD ["php", "-S", "0.0.0.0:8080", "router.php"]
