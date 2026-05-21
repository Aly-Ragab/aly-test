# ==========================================
# Stage 1: Build Dependencies
# ==========================================
FROM php:8.4-cli-alpine AS builder

# System packages (including build tools and extension dependencies)
RUN apk add --no-cache \
    git unzip libzip-dev sqlite-dev autoconf g++ make \
    icu-dev libxml2-dev oniguruma-dev curl-dev

# Install PHP extensions (only the ones that need compilation)
RUN docker-php-ext-install \
    zip pdo pdo_sqlite \
    mbstring xml curl

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /srv/app

# Leverage Docker cache layers by copying composer files first
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-progress

# Copy the rest of the application source code
COPY . .

# Run optimize autoloader dumps
RUN composer dump-autoload --optimize --classmap-authoritative

# ==========================================
# Stage 2: Final Runtime Image
# ==========================================
FROM php:8.4-cli-alpine AS runtime

# Install native runtime dependencies
RUN apk add --no-cache \
    libzip \
    sqlite \
    bash

# Copy PHP extensions from builder stage
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

WORKDIR /srv/app

# Copy built application dependencies and codebase from Stage 1
COPY --from=builder /srv/app ./

# Create the internal var directory and set permissions for data/logs storage
RUN mkdir -p var && chown -R www-data:www-data var

# Copy initialization assets explicitly
COPY scripts/ ./scripts/
RUN chmod +x scripts/entrypoint.sh

# Set the execution environment variable defaults
ENV APP_ENV=dev

# Register the entrypoint script wrapper
ENTRYPOINT ["scripts/entrypoint.sh"]

# Keep the container running or override via docker-compose entrypoints
CMD ["php", "-a"]
