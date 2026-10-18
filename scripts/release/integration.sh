#!/usr/bin/env bash
# Disposable, separately named environment. Never touches scripts/dev volumes.
set -euo pipefail
cd "$(dirname "$0")/../.."
python3 scripts/release/release.py verify
version=$(tr -d '\n' < VERSION)
context=${RELEASE_DOCKER_CONTEXT:-default}
export COMPOSE_FILE="$PWD/scripts/release/compose.yml"
export COMPOSE_PROJECT_NAME="ticketmailer-release-${GITHUB_RUN_ID:-$$}"
export TICKETMAILER_DOCKER_CONTEXT="$context"
export TICKETMAILER_BASE_URL="http://127.0.0.1:${RELEASE_HTTP_PORT:-18080}"
export TICKETMAILER_MAIL_URL="http://127.0.0.1:${RELEASE_MAIL_PORT:-18025}"
tmp=$(mktemp -d)
chmod 755 "$tmp"
export RELEASE_PLUGIN_DIR="$tmp/plugin"
mkdir -p "$RELEASE_PLUGIN_DIR"
compose() { docker --context "$context" compose "$@"; }
cleanup() {
  status=$?
  trap - EXIT
  if [ "$status" -ne 0 ]; then compose logs --tail 20 glpi >&2 || true; compose exec -T glpi sh -c "tail -40 files/_log/sql-errors.log" >&2 || true; fi
  compose down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$tmp"
  exit "$status"
}
trap cleanup EXIT
# Candidate archive was validated above, including all paths and entry types.
tar -xzf "dist/ticketmailer-$version.tar.gz" -C "$tmp"
cp -R "$tmp/ticketmailer/." "$RELEASE_PLUGIN_DIR/"
export RELEASE_FIXTURE_DIR="$tmp/fixtures"
mkdir -p "$RELEASE_FIXTURE_DIR"
cp docker/glpi/e2e-fixture.php scripts/release/migration.php "$RELEASE_FIXTURE_DIR/"
chmod -R a+rX "$tmp"
compose up -d --build --wait --wait-timeout 360 glpi mailpit
compose exec -T --user www-data glpi php bin/console plugin:install ticketmailer --username=glpi --no-interaction --force
compose exec -T --user www-data glpi php bin/console plugin:activate ticketmailer --no-interaction
# Exercise actual SQL migrations from the legacy schema and compare the full
# result to a clean installation. No previously published release exists yet.
compose exec -T --user www-data glpi php /usr/local/bin/release-migration.php --snapshot
compose exec -T db mariadb -uglpi -pglpi glpi < scripts/release/legacy-schema.sql
compose exec -T --user www-data glpi php /usr/local/bin/release-migration.php
compose exec -T --user www-data glpi php /usr/local/bin/release-migration.php
npm ci --ignore-scripts
npx playwright install chromium
npx playwright test tests/e2e/compose.spec.js
