ARG PHP_IMAGE=php:8.5-cli@sha256:e33c9c494e41d56e18c4e9609b98dbdeac68682916cd7d2917582492a6e15618
FROM composer:2@sha256:0049ce90b432795bf413a738b073963ada1a689e1a8708104684baafe94586ec AS composer

FROM ${PHP_IMAGE}

RUN apt-get update \
 && apt-get install -y --no-install-recommends bash git libicu-dev libonig-dev libzip-dev unzip \
 && docker-php-ext-install intl mbstring zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_VENDOR_DIR=/app/vendor
ENV COMPOSER_CACHE_DIR=/phpunit-cache
WORKDIR /app
CMD ["bash", "-lc", "composer install --no-interaction --prefer-dist && vendor/bin/phpunit --cache-directory=/phpunit-cache && ROOT=. bash scripts/verify/score.sh"]
