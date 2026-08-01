#!/usr/bin/env bash
# docker/glpi/setup-glpi.sh — first-boot install. Creates
# the GLPI DB schema and the default admin user. Fails hard
# on db:install; plugin steps are best-effort.

set -euo pipefail

: "${GLPI_DB_HOST:=db}"
: "${GLPI_DB_NAME:=glpi}"
: "${GLPI_DB_USER:=glpi}"
: "${GLPI_DB_PASSWORD:=glpi}"

cd /var/www/html

# Install the GLPI DB schema only after the entrypoint has confirmed that no
# GLPI schema exists. Do not force or overwrite an existing shared database.
php bin/console db:install \
  --allow-superuser \
  --no-interaction \
  --reconfigure \
  --default-language=en_US \
  --db-host="${GLPI_DB_HOST}" \
  --db-name="${GLPI_DB_NAME}" \
  --db-user="${GLPI_DB_USER}" \
  --db-password="${GLPI_DB_PASSWORD}"

# Install + activate the ticketmailer plugin.
if [ -d plugins/ticketmailer ]; then
  php bin/console plugin:install ticketmailer --username=glpi --allow-superuser --no-interaction || true
  php bin/console plugin:activate ticketmailer --allow-superuser --no-interaction || true
fi

echo "[glpi] first-boot setup complete."
