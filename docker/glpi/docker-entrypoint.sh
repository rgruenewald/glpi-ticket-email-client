#!/usr/bin/env bash
# docker/glpi/docker-entrypoint.sh — entry point for the
# glpi container. Waits for the database, then runs the
# GLPI install / setup script (idempotent) before exec'ing
# the CMD.

set -euo pipefail

: "${GLPI_DB_HOST:=db}"
: "${GLPI_DB_NAME:=glpi}"
: "${GLPI_DB_USER:=glpi}"
: "${GLPI_DB_PASSWORD:=glpi}"

# Some Docker hosts advertise IPv6 DNS without routing it. Prefer IPv4-mapped
# addresses so GLPI SMTP does not time out before trying the reachable A record.
if ! grep -Eq '^[[:space:]]*precedence[[:space:]]+::ffff:0:0/96[[:space:]]+100' /etc/gai.conf; then
  echo 'precedence ::ffff:0:0/96  100' >> /etc/gai.conf
fi

echo "[glpi] waiting for database ${GLPI_DB_HOST} …"
for i in {1..30}; do
  # MariaDB client in Debian trixie defaults to SSL; our
  # compose db has no TLS — disable for the health wait.
  if mysqladmin ping -h "${GLPI_DB_HOST}" -u "${GLPI_DB_USER}" -p"${GLPI_DB_PASSWORD}" --ssl=OFF --silent; then
    break
  fi
  sleep 2
done

# GLPI 11 resolves configuration and its security key from /var/www/html/config.
# Keep the DB config on the named volume so encrypted values survive restarts.
mkdir -p /var/www/html/config
if [ ! -e /var/www/html/config/config_db.php ]; then
  rm -f /var/www/html/config/config_db.php
  cat > /var/www/html/config/config_db.php <<PHP
<?php
class DB extends DBmysql {
   public \$dbhost     = '${GLPI_DB_HOST}';
   public \$dbuser     = '${GLPI_DB_USER}';
   public \$dbpassword = '${GLPI_DB_PASSWORD}';
   public \$dbdefault  = '${GLPI_DB_NAME}';
}
PHP
fi

# Use the database schema as installation state. The config and database volumes
# can be removed independently, so a marker in the config volume is not reliable.
glpi_schema_exists="$(
  mariadb \
    --host="${GLPI_DB_HOST}" \
    --user="${GLPI_DB_USER}" \
    --password="${GLPI_DB_PASSWORD}" \
    --database="${GLPI_DB_NAME}" \
    --ssl=OFF \
    --batch --skip-column-names \
    --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'glpi_configs'"
 )"
# Install only into an empty database. Existing shared test data must survive a
# missing or regenerated config volume.
if [ "${GLPI_INSTALL:-0}" = "1" ] && [ "${glpi_schema_exists}" = "0" ]; then
  setup-glpi.sh
fi

# Heal a missing security key after the database is installed. Running this
# command before db:install makes a fresh GLPI instance exit without a key.
if [ ! -s /var/www/html/config/glpicrypt.key ]; then
  php /var/www/html/bin/console security:change_key --allow-superuser --no-interaction || true
  if [ ! -s /var/www/html/config/glpicrypt.key ]; then
    echo "[glpi] unable to create GLPI security key" >&2
    exit 1
  fi
fi

# Configure GLPI core SMTP from the container environment on every boot.
# Leave GLPI's existing mail configuration untouched when no host is supplied.
if [ -n "${GLPI_SMTP_HOST:-}" ]; then
  : "${GLPI_SMTP_PORT:=587}"
  : "${GLPI_SMTP_MODE:=3}"
  : "${GLPI_SMTP_USERNAME:=}"
  : "${GLPI_SMTP_PASSWORD:=}"
  php /var/www/html/bin/console config:set smtp_mode "${GLPI_SMTP_MODE}" -c core --allow-superuser --no-interaction
  php /var/www/html/bin/console config:set smtp_host "${GLPI_SMTP_HOST}" -c core --allow-superuser --no-interaction
  php /var/www/html/bin/console config:set smtp_port "${GLPI_SMTP_PORT}" -c core --allow-superuser --no-interaction
  php /var/www/html/bin/console config:set smtp_username "${GLPI_SMTP_USERNAME}" -c core --allow-superuser --no-interaction
  php /var/www/html/bin/console config:set smtp_passwd "${GLPI_SMTP_PASSWORD}" -c core --allow-superuser --no-interaction
fi

# setup-glpi.sh + CLI run as root → log/cache files end up
# root-owned; apache workers are www-data. Fix every boot.
mkdir -p /var/www/html/files/_log /var/www/html/files/_cache \
         /var/www/html/files/_sessions /var/www/html/files/_tmp \
         /var/www/html/files/_uploads
chown -R www-data:www-data /var/www/html/files /var/www/html/config
find /var/www/html/plugins -type d -exec chmod 755 {} +
find /var/www/html/plugins -type f -exec chmod 644 {} +

exec "$@"
