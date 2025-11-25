# ===========================
#  STAGE 1 - DEPENDÊNCIAS PHP
# ===========================
FROM php:8.2-cli AS backend_deps

WORKDIR /app/backend

RUN apt-get update && \
    apt-get install -y git unzip libzip-dev libpq-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY backend/ /app/backend


# ===========================
#   STAGE 2 - FRONTEND BUILD
# ===========================
FROM node:18-alpine AS frontend_builder

WORKDIR /app/frontend

COPY frontend/package*.json ./
RUN npm ci

COPY frontend/ /app/frontend
RUN npm run build


# ===========================
#   STAGE 3 - RUNTIME FINAL
# ===========================
FROM php:8.2-cli

ENV APP_PORT=8000 \
    FRONTEND_PORT=3000 \
    NODE_ENV=production \
    PATH="/app/backend/vendor/bin:/app/frontend/node_modules/.bin:${PATH}"

WORKDIR /app

RUN apt-get update && \
    apt-get install -y git unzip libzip-dev libpq-dev \
        libpng-dev libjpeg-dev libfreetype6-dev curl supervisor && \
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd pdo pdo_pgsql zip && \
    rm -rf /var/lib/apt/lists/*

COPY --from=backend_deps /app/backend /app/backend
COPY --from=frontend_builder /app/frontend /app/frontend

COPY supervisord.conf /etc/supervisor/conf.d/app.conf
COPY start.sh /app/start.sh

RUN chmod +x /app/start.sh

RUN useradd -m -r app

RUN mkdir -p /app/backend/storage/framework/cache \
    && mkdir -p /app/backend/storage/framework/sessions \
    && mkdir -p /app/backend/storage/framework/views \
    && mkdir -p /app/backend/storage/logs \
    && mkdir -p /app/backend/storage/app/private \
    && mkdir -p /app/backend/bootstrap/cache

RUN chown -R app:app /app && \
    chmod -R 775 /app/backend/storage /app/backend/bootstrap/cache && \
    touch /app/backend/storage/logs/laravel.log && \
    chmod 664 /app/backend/storage/logs/laravel.log

USER app

EXPOSE 8000 3000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]
