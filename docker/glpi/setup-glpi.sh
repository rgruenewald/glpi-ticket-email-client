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
  --allow-superuser \
  --no-interaction \
  --force \
  --reconfigure \
  --default-language=en_US \
  --db-host="${GLPI_DB_HOST}" \
  --db-name="${GLPI_DB_NAME}" \
  --db-user="${GLPI_DB_USER}" \
  --db-password="${GLPI_DB_PASSWORD}"

# Encrypted fields need GLPI's sodium key under GLPI_CONFIG_DIR.
# Create it before SMTP credentials are written so persisted credentials
# always match the active key.
if [ ! -s /etc/glpi/glpicrypt.key ]; then
  php bin/console security:change_key --allow-superuser --no-interaction || true
fi

# Apply GLPI's core mailer config to point at mailpit.
# smtp_mode 1 = SMTP; host and port are configured separately.
php bin/console config:set smtp_mode 1 -c core --allow-superuser --no-interaction || true
php bin/console config:set smtp_host "${GLPI_SMTP_HOST}" -c core --allow-superuser --no-interaction || true
php bin/console config:set smtp_port "${GLPI_SMTP_PORT}" -c core --allow-superuser --no-interaction || true
php bin/console config:set smtp_username "" -c core --allow-superuser --no-interaction || true
php bin/console config:set smtp_passwd "" -c core --allow-superuser --no-interaction || true


# Install + activate the ticketmailer plugin.
if [ -d plugins/ticketmailer ]; then
  php bin/console plugin:install ticketmailer --username=glpi --allow-superuser --no-interaction || true
  php bin/console plugin:activate ticketmailer --allow-superuser --no-interaction || true
fi

echo "[glpi] first-boot setup complete."
