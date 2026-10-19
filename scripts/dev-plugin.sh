# Sourced only by scripts/dev interactive. Uses its scoped compose() function.
plugin_row() {
  compose exec -T db sh -c 'exec mariadb -uglpi -p"$MARIADB_PASSWORD" glpi --batch --skip-column-names -e "$1"' sh "$1"
}
plugin_preflight() {
  command -v python3 >/dev/null
  code_version=$(python3 - <<'PY'
import re
from pathlib import Path
s = Path('setup.php').read_text()
m = re.search(r"define[(]'PLUGIN_TICKETMAILER_VERSION', '([0-9]+[.][0-9]+[.][0-9]+)'[)]", s)
if not m:
    raise SystemExit('Cannot determine numeric plugin version from setup.php')
print(m[1])
PY
  )
  table_exists=$(plugin_row "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='glpi_plugins'")
  previous_row=''
  if [ "$table_exists" = 1 ]; then
    previous_row=$(plugin_row "SELECT CONCAT(version, ':', state) FROM glpi_plugins WHERE directory='ticketmailer'")
  fi
  previous_version=${previous_row%:*}
  previous_state=${previous_row##*:}
  if [ -n "$previous_version" ]; then
    python3 - "$previous_version" "$code_version" <<'PY'
import re, sys
old, new = sys.argv[1:]
if not all(re.fullmatch(r'[0-9]+[.][0-9]+[.][0-9]+', v) for v in (old, new)):
    raise SystemExit('Unknown installed version format; refusing automatic update')
if tuple(map(int, old.split('.'))) > tuple(map(int, new.split('.'))):
    raise SystemExit(f'Downgrade blocked: installed {old}, worktree {new}. Use a separate database or restore a matching backup.')
PY
  fi
  schema_hash=$(python3 scripts/dev-schema.py fingerprint)
  db_container=$(compose ps -q db)
  db_volume=$(docker --context rootless inspect "$db_container" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}{{end}}{{end}}')
  test -n "$db_volume"
  db_identity=$(docker --context rootless volume inspect "$db_volume" --format '{{.Name}}:{{.CreatedAt}}')
  table_identity=$(plugin_row "SELECT COALESCE(CAST(CREATE_TIME AS CHAR), '') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='glpi_plugins'")
  schema_instance="$db_identity:$table_identity"
  python3 scripts/dev-schema.py validate --instance "$schema_instance"
  previous_schema=$(python3 scripts/dev-schema.py read --instance "$schema_instance")
  needs_install=0
  if [ -z "$previous_row" ] || [ "$previous_version" != "$code_version" ] || [ "$previous_state" = 6 ] || [ "$previous_state" = 2 ] || [ "$previous_schema" != "$schema_hash" ]; then
    needs_install=1
    # No database/files contents go to logs or to the repository.
    backup_root="$HOME/.local/state/ticketmailer-backups"
    mkdir -p "$backup_root"
    chmod 700 "$backup_root"
    backup_dir=$(mktemp -d "$backup_root/update-XXXXXXXX")
    chmod 700 "$backup_dir"
    (
      umask 077
      compose exec -T db sh -c 'exec mariadb-dump -uglpi -p"$MARIADB_PASSWORD" --single-transaction glpi' > "$backup_dir/database.sql"
      compose run --rm --no-deps -T --entrypoint tar glpi -czf - -C /var/www/html config files > "$backup_dir/config-files.tar.gz"
      test -s "$backup_dir/database.sql"
      test -s "$backup_dir/config-files.tar.gz"
      printf 'Previous: %s
Target: %s
' "$previous_row" "$code_version" > "$backup_dir/versions.txt"
    )
    echo "Pre-update backup: $backup_dir"
  fi
}
plugin_apply() {
  if [ "$needs_install" = 1 ]; then
    compose exec -T --user www-data glpi php bin/console glpi:plugin:install ticketmailer --username=glpi --no-interaction --force
  fi
  active_state=$(plugin_row "SELECT state FROM glpi_plugins WHERE directory='ticketmailer'")
  if [ "$active_state" != 1 ]; then
    compose exec -T --user www-data glpi php bin/console glpi:plugin:activate ticketmailer --no-interaction
  fi
  actual=$(plugin_row "SELECT CONCAT(version, ':', state) FROM glpi_plugins WHERE directory='ticketmailer'")
  if [ "$actual" != "$code_version:1" ]; then
    echo "Plugin readiness failed: expected $code_version:1, got $actual" >&2
    return 1
  fi
  applied_hash=$(python3 scripts/dev-schema.py fingerprint)
  if [ "$applied_hash" != "$schema_hash" ]; then
    echo 'Schema inputs changed during startup; restart make dev.' >&2
    return 1
  fi
  # Installation can create glpi_plugins; resolve its identity after success.
  table_identity=$(plugin_row "SELECT COALESCE(CAST(CREATE_TIME AS CHAR), '') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='glpi_plugins'")
  python3 scripts/dev-schema.py record --instance "$db_identity:$table_identity" --value "$schema_hash"
  echo "READY: ticketmailer $code_version active; worktree $PWD; http://127.0.0.1:8080"
}
