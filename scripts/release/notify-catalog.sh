#!/usr/bin/env bash
# Notify GLPI plugins directory about an update to ticketmailer.
# Replaces .github/workflows/catalog-notify.yml with a local CLI workflow.
set -euo pipefail

token=${GLPI_CATALOG_TOKEN:-}
dry_run=0

while [ $# -gt 0 ]; do
  case "$1" in
    --dry-run)
      dry_run=1
      shift
      ;;
    --token)
      token="$2"
      shift 2
      ;;
    *)
      echo "Usage: $0 [--dry-run] [--token <token>]" >&2
      exit 1
      ;;
  esac
done

if [ "$dry_run" -eq 1 ]; then
  echo "GLPI catalog notification dry-run: would POST to https://plugins.glpi-project.org/api/plugin/update"
  if [ -n "$token" ]; then
    echo "Token: configured (${#token} chars)"
  else
    echo "Token: not set (set GLPI_CATALOG_TOKEN to enable)"
  fi
  exit 0
fi

if [ -z "$token" ]; then
  echo "Error: GLPI_CATALOG_TOKEN is required to notify GLPI directory." >&2
  echo "Set export GLPI_CATALOG_TOKEN=... or pass --token <key>" >&2
  exit 1
fi

echo "Notifying GLPI directory at https://plugins.glpi-project.org/api/plugin/update..."
# No automatic POST retries: a timeout may still have reached the server.
curl --fail-with-body --silent --show-error --connect-timeout 15 --max-time 60 \
  --request POST https://plugins.glpi-project.org/api/plugin/update \
  --data-urlencode "key=$token" \
  --data-urlencode "plugin=ticketmailer"
echo "Catalog notification sent successfully."
