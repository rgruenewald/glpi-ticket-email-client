# docker/glpi/Dockerfile — GLPI 11 image with the
# ticketemailclient plugin auto-installed. Pinned to a
# specific minor version so updates don't drift.

FROM php:8.2-apache

ENV GLPI_VERSION=11.0.8 \
    DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        mariadb-client \
        unzip \
        git \
        libldap2-dev \
        libxml2-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libcurl4-openssl-dev \
        libonig-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) bcmath curl mbstring mysqli ldap xml intl gd opcache \
 && rm -rf /var/lib/apt/lists/*

# Install GLPI upstream release.
RUN set -eux; \
    curl -fsSL "https://github.com/glpi-project/glpi/releases/download/${GLPI_VERSION}/glpi-${GLPI_VERSION}.tgz" \
        -o /tmp/glpi.tgz; \
    tar -xzf /tmp/glpi.tgz -C /var/www/html/ --strip-components=1; \
    rm -f /tmp/glpi.tgz; \
    chown -R www-data:www-data /var/www/html

# GLPI 11 requires the public directory as Apache's document root.
RUN set -eux; \
    a2enmod rewrite; \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf; \
    sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf; \
    sed -i '/<\/VirtualHost>/i\    FallbackResource /index.php' /etc/apache2/sites-available/000-default.conf

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY setup-glpi.sh         /usr/local/bin/setup-glpi.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/setup-glpi.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
