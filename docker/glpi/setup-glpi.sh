#!/usr/bin/env bash
# docker/glpi/setup-glpi.sh — first-boot install. Creates
# the GLPI DB schema, the default admin user, and points
# the SMTP config at mailpit. Fails hard on db:install;
# plugin/SMTP steps are best-effort.

set -euo pipefail

: "${GLPI_DB_HOST:=db}"
: "${GLPI_DB_NAME:=glpi}"
: "${GLPI_DB_USER:=glpi}"
: "${GLPI_DB_PASSWORD:=glpi}"
: "${GLPI_SMTP_HOST:=mailpit}"
: "${GLPI_SMTP_PORT:=1025}"

cd /var/www/html

# Install the GLPI DB schema. config_db.php may already
# exist (entrypoint writes it); --force reinstalls schema
# on an empty/partial DB. No || true — install must succeed.
php bin/console db:install \
  --no-interaction \
  --force \
  --reconfigure \
  --default-language=en_US \
  --db-host="${GLPI_DB_HOST}" \
  --db-name="${GLPI_DB_NAME}" \
  --db-user="${GLPI_DB_USER}" \
  --db-password="${GLPI_DB_PASSWORD}"

# Apply GLPI's core mailer config to point at mailpit.
# Usage: config:set <key> <value> -c <context>
# smtp_mode 1 = SMTP; host may include :port.
php bin/console config:set smtp_mode 1 -c core --no-interaction || true
php bin/console config:set smtp_host "${GLPI_SMTP_HOST}:${GLPI_SMTP_PORT}" -c core --no-interaction || true

# Password fields (smtp_passwd, mailcollector, …) need a sodium
# key under GLPI_CONFIG_DIR. Symlink points at /etc/glpi volume.
php bin/console security:change_key --no-interaction || true

# Install + activate the ticketemailclient plugin (this repo).
# GLPI 10 uses plugin:activate (not plugin:enable).
if [ -d plugins/ticketemailclient ]; then
  php bin/console plugin:install ticketemailclient --username=glpi --no-interaction || true
  php bin/console plugin:activate ticketemailclient --no-interaction || true
fi

echo "[glpi] first-boot setup complete."
