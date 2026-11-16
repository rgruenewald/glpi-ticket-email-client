#!/usr/bin/env bash
# Disposable, separately named environment. Never touches scripts/dev volumes.
set -euo pipefail
cd "$(dirname "$0")/../.."
source scripts/ci-timing.sh
asset_dir=${RELEASE_ASSET_DIR:-dist}
shards=${RELEASE_E2E_SHARDS:-1}
shard=${RELEASE_E2E_SHARD:-1}
case "$shards:$shard" in
  1:1|2:1|2:2) ;;
  *) echo "Invalid E2E shard" >&2; exit 1 ;;
esac
prefix="glpi-${RELEASE_GLPI_VERSION:-12.0.2}"
if [ "$shards" -gt 1 ]; then prefix="$prefix-shard-$shard"; fi
timed "$prefix-package-verify" python3 scripts/release/release.py verify --out "$asset_dir"
version=$(tr -d '\n' < VERSION)
context=${RELEASE_DOCKER_CONTEXT:-${TICKETMAILER_DOCKER_CONTEXT:-}}
if [ -z "$context" ]; then
  if docker context inspect rootless >/dev/null 2>&1; then
    context=rootless
  else
    context=default
  fi
fi
export COMPOSE_FILE="$PWD/scripts/release/compose.yml"
export COMPOSE_PROJECT_NAME="ticketmailer-release-$$"
if [ -n "${TICKETMAILER_EVIDENCE_DIR:-}" ]; then
  export TICKETMAILER_E2E_REPORT_DIR="$TICKETMAILER_EVIDENCE_DIR/$prefix"
  mkdir -p "$TICKETMAILER_E2E_REPORT_DIR"
fi
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
  if [ "$status" -ne 0 ]; then
    compose logs --tail 20 glpi >&2 || true
    compose exec -T --user www-data glpi sh -c '
      for file in files/_log/php-errors.log files/_log/sql-errors.log; do
        if [ -f "$file" ]; then tail -40 "$file"; fi
      done
    ' >&2 || true
  fi
  compose down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$tmp"
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
# Candidate archive was validated above, including all paths and entry types.
tar -xzf "$asset_dir/ticketmailer-$version.tar.gz" -C "$tmp"
cp -R "$tmp/ticketmailer/." "$RELEASE_PLUGIN_DIR/"
export RELEASE_FIXTURE_DIR="$tmp/fixtures"
mkdir -p "$RELEASE_FIXTURE_DIR"
cp docker/glpi/e2e-fixture.php scripts/release/migration.php "$RELEASE_FIXTURE_DIR/"
chmod -R a+rX "$tmp"
timed "$prefix-startup" compose up -d --build --wait --wait-timeout 360 glpi mailpit
# Read actual bindings, including Docker-assigned ports (requested port 0).
export TICKETMAILER_BASE_URL="http://$(compose port glpi 80)"
export TICKETMAILER_MAIL_URL="http://$(compose port mailpit 8025)"
# Record the actual installed runtime, not just the build argument.
compose exec -T --user www-data glpi php bin/console --version | tee "$tmp/runtime-version.txt"
grep -Fxq "GLPI CLI ${RELEASE_GLPI_VERSION:-12.0.2}" "$tmp/runtime-version.txt"
if [ -n "${TICKETMAILER_E2E_REPORT_DIR:-}" ]; then
  cp "$tmp/runtime-version.txt" "$TICKETMAILER_E2E_REPORT_DIR/runtime-version.txt"
fi
timed "$prefix-plugin-install" compose exec -T --user www-data glpi php bin/console plugin:install ticketmailer --username=glpi --no-interaction --force
timed "$prefix-plugin-activate" compose exec -T --user www-data glpi php bin/console plugin:activate ticketmailer --no-interaction
# Exercise actual SQL migrations from the legacy schema and compare the full
# result to a clean installation. No previously published release exists yet.
timed "$prefix-schema-snapshot" compose exec -T --user www-data glpi php /usr/local/bin/release-migration.php --snapshot
compose exec -T db mariadb -uglpi -pglpi glpi < scripts/release/legacy-schema.sql
timed "$prefix-migration" compose exec -T --user www-data glpi php /usr/local/bin/release-migration.php
timed "$prefix-migration-repeat" compose exec -T --user www-data glpi php /usr/local/bin/release-migration.php
if [ "${RELEASE_DEPS_READY:-0}" != 1 ]; then
  timed "$prefix-npm-install" npm ci --ignore-scripts
  timed "$prefix-browser-install" npx playwright install chromium
fi
e2e_args=()
# Test-level partitioning across isolated stacks, never parallel in one stack.
if [ "$shards" -gt 1 ]; then e2e_args+=(--fully-parallel --workers=1 --shard="$shard/$shards"); fi
if [ "${RELEASE_E2E_ALL:-0}" = 1 ]; then
  timed "$prefix-e2e" npx playwright test "${e2e_args[@]}"
else
  timed "$prefix-e2e" npx playwright test tests/e2e/compose.spec.js "${e2e_args[@]}"
fi
