FROM composer:2@sha256:ca11f643d7791ebdd90a34fc20caf11380d434007ae539a9ea75cd9212e13dca AS composer

FROM php:8.5-cli@sha256:50956f094e90a716d43bea28087d447555ad80510e386ab8c9c10d8c70d781a9

RUN apt-get update \
 && apt-get install -y --no-install-recommends bash git libicu-dev libonig-dev libzip-dev unzip \
 && docker-php-ext-install intl mbstring zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_VENDOR_DIR=/vendor
ENV COMPOSER_CACHE_DIR=/phpunit-cache
CMD ["bash", "-lc", "composer install --no-interaction --prefer-dist && /vendor/bin/phpunit --cache-directory=/phpunit-cache && ROOT=. bash .agent/contracts/glpi-ticket-email-client-v2/score.sh"]
