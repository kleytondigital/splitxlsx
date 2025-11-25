# syntax=docker/dockerfile:1

FROM php:8.2-cli AS backend_deps
WORKDIR /app/backend

RUN apt-get update && \
apt-get install -y git unzip libzip-dev \
    libpng-dev libjpeg-dev libfreetype6-dev && \
docker-php-ext-configure gd --with-freetype --with-jpeg && \
docker-php-ext-install gd zip && \
rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY backend /app/backend
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader && \
    php artisan config:clear && php artisan cache:clear

FROM node:18-alpine AS frontend_builder
WORKDIR /app/frontend

COPY frontend/package*.json ./
RUN npm ci

COPY frontend /app/frontend
RUN npm run build

FROM php:8.2-cli
ENV APP_PORT=8000 \
    FRONTEND_PORT=3000 \
    NODE_ENV=production \
    PATH="/app/backend/vendor/bin:/app/frontend/node_modules/.bin:${PATH}"

RUN apt-get update && \
    apt-get install -y git unzip libzip-dev libpq-dev \
        libpng-dev libjpeg-dev libfreetype6-dev curl supervisor && \
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd pdo pdo_pgsql zip && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=backend_deps /app/backend /app/backend
COPY --from=frontend_builder /app/frontend /app/frontend

COPY supervisord.conf /etc/supervisor/conf.d/app.conf

RUN useradd -m -r app && chown -R app:app /app

USER app

EXPOSE 8000 3000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]

