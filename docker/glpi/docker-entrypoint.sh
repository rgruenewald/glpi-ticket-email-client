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

# Heal a missing security key before any command reads encrypted config.
if [ ! -s /var/www/html/config/glpicrypt.key ]; then
  php /var/www/html/bin/console security:change_key --allow-superuser --no-interaction || true
  if [ ! -s /var/www/html/config/glpicrypt.key ]; then
    echo "[glpi] unable to create GLPI security key" >&2
    exit 1
  fi
fi

# First-boot install (writes glpi DB schema, creates the
# default admin user, activates plugins/ticketmailer).
# Marker lives on the glpi_config volume so recreate is stable.
if [ "${GLPI_INSTALL:-0}" = "1" ] && [ ! -f /var/www/html/config/.glpi_installed ]; then
  setup-glpi.sh
  touch /var/www/html/config/.glpi_installed
fi


# setup-glpi.sh + CLI run as root → log/cache files end up
# root-owned; apache workers are www-data. Fix every boot.
mkdir -p /var/www/html/files/_log /var/www/html/files/_cache \
         /var/www/html/files/_sessions /var/www/html/files/_tmp \
         /var/www/html/files/_uploads
chown -R www-data:www-data /var/www/html/files /var/www/html/config /etc/glpi || true

exec "$@"
