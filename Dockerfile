# syntax=docker/dockerfile:1.6

# ---- Builder: install PHP dependencies with Composer (no-dev) ----
FROM composer:2 AS vendor

WORKDIR /app

# Only copy composer files first for better caching
COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-progress \
    --no-interaction \
    --ignore-platform-reqs

# ---- Runtime: slim PHP CLI image ----
FROM php:8.4-cli-alpine AS runtime

WORKDIR /app

# Set UTC timezone; install MySQL PDO extension
ENV TZ=UTC
RUN apk add --no-cache tzdata && \
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone && \
    docker-php-ext-install pdo pdo_mysql && \
    mkdir data

# Copy application source
COPY . /app

# Copy vendor from builder stage (overwrites any host vendor)
COPY --from=vendor /app/vendor /app/vendor

VOLUME /app/data

# Ensure bot entry is executable (not strictly required for CMD below)
RUN chmod +x /app/bin/bot.php || true

# Default command runs the bot
CMD ["php", "bin/bot.php"]
