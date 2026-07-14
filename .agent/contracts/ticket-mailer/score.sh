#!/usr/bin/env bash
# score.sh — verifier for the ticket-mailer GLPI plugin contract.
# Single pass/fail entrypoint. Strict, deterministic, no installs,
# no background services. Manual runtime checks (GLPI + mailpit)
# live in spec.md and are NOT encoded here.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="${ROOT:-$(cd "$SCRIPT_DIR/../../.." && pwd)}"
cd "$ROOT"

PASS=0
FAIL=0
ok()   { echo "  PASS: $1"; PASS=$((PASS+1)); }
fail() { echo "  FAIL: $1"; FAIL=$((FAIL+1)); }
have() { command -v "$1" >/dev/null 2>&1; }

echo "== ticket-mailer verifier =="
echo "  root: $ROOT"

# --- 1. required files ---------------------------------------------------
for f in setup.php hook.php composer.json docker-compose.yml README.md .gitignore; do
  if [ -f "$f" ]; then ok "file present: $f"; else fail "missing: $f"; fi
done

# --- 2. PHP syntax --------------------------------------------------------
if have php; then
  syntax_err=0
  while IFS= read -r f; do
    if ! php -l "$f" >/dev/null 2>&1; then
      fail "php -l: $f"
      syntax_err=1
    fi
  done < <(find . -path ./vendor -prune -o -path ./node_modules -prune -o -name '*.php' -print 2>/dev/null)
  if [ "$syntax_err" -eq 0 ]; then ok "php -l: all *.php parse"; fi
else
  echo "  SKIP: php not installed"
fi

# --- 3. composer.json valid JSON -----------------------------------------
if [ -f composer.json ]; then
  if have php; then
    if php -r 'json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR); exit(0);' 2>/dev/null; then
      ok "composer.json: valid JSON"
    else
      fail "composer.json: invalid JSON"
    fi
  elif have jq; then
    if jq -e . composer.json >/dev/null 2>&1; then
      ok "composer.json: valid JSON"
    else
      fail "composer.json: invalid JSON"
    fi
  else
    echo "  SKIP: composer.json validation (no php/jq)"
  fi
fi

# --- 4. docker-compose syntax --------------------------------------------
if [ -f docker-compose.yml ]; then
  if have docker && docker compose version >/dev/null 2>&1; then
    if docker compose -f docker-compose.yml config >/dev/null 2>&1; then
      ok "docker compose config: valid"
    else
      fail "docker compose config: invalid"
    fi
  else
    echo "  SKIP: docker compose validation (docker not available)"
  fi
fi

# --- 5. plugin registration in setup.php ---------------------------------
if [ -f setup.php ]; then
  if grep -Eq "define\(\s*['\"]PLUGIN_TICKETMAILER_VERSION['\"]" setup.php; then
    ok "setup.php: PLUGIN_TICKETMAILER_VERSION defined"
  else
    fail "setup.php: PLUGIN_TICKETMAILER_VERSION not defined"
  fi
  if grep -Eq "define\(\s*['\"]PLUGIN_TICKETMAILER_(MIN|MAX)_GLPI['\"]" setup.php; then
    ok "setup.php: PLUGIN_TICKETMAILER_{MIN,MAX}_GLPI defined"
  else
    fail "setup.php: PLUGIN_TICKETMAILER_{MIN,MAX}_GLPI not defined"
  fi
  for k in csrf_compliant post_init item_purge; do
    if grep -q "'$k'" setup.php; then ok "setup.php: hook registered ($k)"
    else fail "setup.php: hook missing ($k)"; fi
  done
else
  fail "setup.php missing (cannot check registration)"
fi

# --- 6. required hook functions ------------------------------------------
for fn in plugin_ticketmailer_install plugin_ticketmailer_uninstall plugin_ticketmailer_post_init; do
  if grep -rqE "function[[:space:]]+${fn}[[:space:]]*\(" setup.php hook.php 2>/dev/null; then
    ok "hook function defined: $fn"
  else
    fail "hook function missing: $fn"
  fi
done

# --- 7. audit table schema -----------------------------------------------
if [ -d sql ]; then
  sql_files=$(find sql -name '*.sql' 2>/dev/null | tr '\n' ' ')
  if [ -n "$sql_files" ]; then
    for col in tickets_id users_id sent_at subject recipients_to recipients_cc recipients_bcc status; do
      if grep -rEq "\b${col}\b" sql/ 2>/dev/null; then
        ok "sql: column referenced ($col)"
      else
        fail "sql: column missing ($col)"
      fi
    done
    if grep -rEqi 'CREATE[[:space:]]+TABLE' sql/ 2>/dev/null; then
      ok "sql: CREATE TABLE statement present"
    else
      fail "sql: no CREATE TABLE found"
    fi
  else
    fail "sql/: no .sql files"
  fi
else
  fail "sql/ directory missing"
fi

# --- 8. no native notification engine in compose path --------------------
# A9 in spec.md. Independence from GLPI's NotificationEvent pipeline.
COMPOSE_PATHS=(inc front ajax)
NOTIF_RE='Notification::raiseEvent|NotificationEvent::raiseEvent|NotificationTarget::getNotificationTargets|NotificationMailing::send'
hits=$(grep -rnE "$NOTIF_RE" "${COMPOSE_PATHS[@]}" 2>/dev/null || true)
if [ -n "$hits" ]; then
  fail "compose path references GLPI native notification engine:"
  echo "$hits" | sed 's/^/         /'
else
  ok "compose path does not use GLPI native notification engine"
fi

# --- 9. uses GLPI's mailer config ----------------------------------------
if [ -d inc ]; then
  if grep -rqE "CFG_GLPI\[['\"](smtp_host|smtp_port|smtp_username|smtp_passwd|smtp_mode)|Config::getConfigurationValue\([^,]+,[[:space:]]*['\"]smtp_" inc/ 2>/dev/null; then
    ok "inc/: references GLPI's SMTP config (CFG_GLPI or Config::getConfigurationValue)"
  else
    fail "inc/: no reference to GLPI's smtp_* config"
  fi
  # negative check: plugin must not define its own smtp host/port/user/pass fields
  if grep -rEni 'addfield.*(smtp_host|smtp_port|smtp_username|smtp_passwd)|smtp_(host|port|username|passwd).*=>' inc/ front/ 2>/dev/null; then
    fail "inc/front: plugin defines its own SMTP config form (should reuse GLPI's)"
  else
    ok "inc/front: no plugin-defined SMTP config form"
  fi
else
  fail "inc/ directory missing"
fi

# --- 10. no hardcoded secrets --------------------------------------------
SECRET_RE='(password|passwd|secret|api[_-]?token|auth[_-]?token)[[:space:]]*[:=][[:space:]]*['\''"][^'\''"[:space:]]+['\''"]'
hits=$(grep -rEni "$SECRET_RE" "${COMPOSE_PATHS[@]}" setup.php hook.php 2>/dev/null || true)
if [ -n "$hits" ]; then
  fail "hardcoded secret detected:"
  echo "$hits" | sed 's/^/         /'
else
  ok "no hardcoded secrets in compose path / setup / hook"
fi

# --- 11. To/CC/BCC fields referenced -------------------------------------
TOKENS_RE='recipients_(to|cc|bcc)|["'\''](to|cc|bcc)["'\''][[:space:]]*=>'
hits=$(grep -rEni "$TOKENS_RE" "${COMPOSE_PATHS[@]}" 2>/dev/null || true)
if [ -n "$hits" ]; then
  ok "To/CC/BCC fields referenced in compose path"
else
  fail "To/CC/BCC fields not referenced in compose path"
fi

# --- 12. forward UI present (full-history only, per OQ1) -----------------
# Forward is now a single email-style mode. A11 mandates a
# forward.* entry (front/templates/ajax) AND a full_history
# reference in the compose path. The legacy "last_message" mode
# is explicitly dropped (OQ1) and is no longer accepted.
FWD_FILES=$(find front/ templates/ ajax/ -regextype posix-extended -regex '.*/forward\.(php|twig|js)' 2>/dev/null || true)
FWD_HIST=$(grep -rEi 'full[_-]?history' inc/ front/ templates/ ajax/ 2>/dev/null || true)
if [ -n "$FWD_FILES" ] && [ -n "$FWD_HIST" ]; then
  ok "forward UI present (forward.* entry + full_history referenced)"
else
  fail "forward UI missing or does not reference full_history"
  [ -z "$FWD_FILES" ] && echo "         no forward.{php,twig,js} entry under front/templates/ajax"
  [ -z "$FWD_HIST" ]  && echo "         no full_history reference in inc/front/templates/ajax"
fi

# --- 13. .gitignore hygiene ----------------------------------------------
if [ -f .gitignore ]; then
  for pat in 'vendor/' 'node_modules/' '\.env' '_files/' '_cache/' '_log/' '_sessions/'; do
    if grep -Eq "$pat" .gitignore; then ok ".gitignore excludes: $pat"
    else fail ".gitignore missing pattern: $pat"; fi
  done
else
  fail ".gitignore missing"
fi

# --- 14. i18n locale files (A16) ----------------------------------------
if [ -d locales ]; then
  for f in locales/ticketmailer.pot locales/ticketmailer.en.po locales/ticketmailer.de.po; do
    if [ -f "$f" ]; then ok "locale present: $f"
    else fail "locale missing: $f"; fi
  done
  if have msgfmt; then
    for f in locales/ticketmailer.en.po locales/ticketmailer.de.po; do
      [ -f "$f" ] || continue
      if msgfmt --check --output-file=/dev/null "$f" 2>/dev/null; then
        ok "msgfmt parses: $f"
      else
        fail "msgfmt parse error: $f"
      fi
    done
  else
    echo "  SKIP: msgfmt not installed (locale parse check skipped)"
  fi
  if grep -rqE "\b__[[:space:]]*\(" inc/ front/ templates/ 2>/dev/null; then
    ok "__() translation function used in user-facing paths"
  else
    fail "no __() calls in inc/front/templates (UI strings not translatable)"
  fi
else
  fail "locales/ directory missing"
fi

# --- summary --------------------------------------------------------------
echo ""
echo "== Result =="
echo "  PASS: $PASS"
echo "  FAIL: $FAIL"
[ "$FAIL" -eq 0 ] || exit 1
exit 0
