# Production image for Railway (PHP 8.2 + Composer + Symfony)
FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN chmod +x bin/railway-build.sh bin/railway-start.sh bin/railway-jwt-keys.sh

# Build-time defaults; Railway injects real values at runtime.
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_SECRET=build-time-secret-change-in-railway \
    DATABASE_URL="mysql://build:build@127.0.0.1:3306/build?serverVersion=8.0&charset=utf8mb4" \
    APP_URL=http://localhost \
    DEFAULT_URI=http://localhost \
    CORS_ALLOW_ORIGIN='^https?://.*' \
    JWT_PASSPHRASE=build-time-passphrase \
    JWT_SECRET_KEY=config/jwt/private.pem \
    JWT_PUBLIC_KEY=config/jwt/public.pem

RUN bash bin/railway-build.sh

EXPOSE 8000

CMD ["bash", "bin/railway-start.sh"]
