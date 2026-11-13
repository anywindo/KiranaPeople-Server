# Stage 1: Build frontend assets with Node & Vite
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

# Stage 2: Main FrankenPHP runtime
FROM dunglas/frankenphp:1-php8.4-bookworm

# Strip elevated Linux capabilities so frankenphp can execute in unprivileged cloud containers (Render)
RUN setcap -r /usr/local/bin/frankenphp

# Install required PHP extensions for Laravel, PostgreSQL, Excel, and Image manipulation
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    intl \
    bcmath \
    opcache \
    pcntl \
    redis

# Copy composer binary from official composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy dependency definition files first for better layer caching
COPY composer.json composer.lock ./

# Install Composer dependencies (production only, skip scripts until entrypoint)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy the rest of the application code
COPY . /app

# Copy compiled frontend assets from assets stage
COPY --from=assets /app/public/build /app/public/build

# Copy custom Caddyfile
COPY Caddyfile /etc/caddy/Caddyfile

# Copy and setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Prepare storage & bootstrap cache permissions
RUN mkdir -p /app/storage/framework/cache/data \
             /app/storage/framework/sessions \
             /app/storage/framework/views \
             /app/storage/logs \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Render injects the PORT env var (e.g. 10000); fallback to unprivileged 8080
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
