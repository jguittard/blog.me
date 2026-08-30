# syntax=docker/dockerfile:1
FROM php:8.5-fpm-trixie

# install-php-extensions pulls the right system libs for each extension.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
      intl \
      zip \
      pdo_mysql \
      opcache \
      bcmath \
      gd \
      sockets \
      redis \
      xdebug

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# mezzio/mezzio-tooling caps its php constraint at ~8.4.1 (conservative, no 8.5
# release yet). Ignore only the *upper* bound so `composer install` works on 8.5
# while a genuinely missing ext/lower-bound still fails.
ENV COMPOSER_IGNORE_PLATFORM_REQ=php+

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# Remap www-data (the php-fpm pool user) to the host user so files written
# through the bind mount are owned correctly on Linux hosts.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data \
    && usermod  -o -u "${UID}" -g "${GID}" www-data

WORKDIR /var/www/html
