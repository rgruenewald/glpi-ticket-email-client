FROM composer:2@sha256:629d4ef35e75349d452851637d37a40ac33a09d6ac010139020603d79713d9bf AS composer

FROM php:8.5-cli@sha256:f5d2b71350cdc3c2fc807d6679f5bdac2898d7558945105e46308e4659fb37ac

RUN apt-get update \
 && apt-get install -y --no-install-recommends bash git libicu-dev libonig-dev libzip-dev unzip \
 && docker-php-ext-install intl mbstring zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_VENDOR_DIR=/vendor
ENV COMPOSER_CACHE_DIR=/phpunit-cache
CMD ["bash", "-lc", "composer install --no-interaction --prefer-dist && /vendor/bin/phpunit --cache-directory=/phpunit-cache && ROOT=. bash .agent/contracts/glpi-ticket-email-client-v2/score.sh"]
