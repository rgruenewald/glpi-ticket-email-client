#!/usr/bin/env bash
# score.sh — deterministic structural verifier for GLPI Ticket Email Client v2.
# Run: ROOT=. bash .agent/contracts/glpi-ticket-email-client-v2/score.sh
# Runtime GLPI/Mailpit checks live in spec.md.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="${ROOT:-$(cd "$SCRIPT_DIR/../../.." && pwd)}"
cd "$ROOT"

PASS=0
FAIL=0
ok() { echo "  PASS: $1"; PASS=$((PASS + 1)); }
fail() { echo "  FAIL: $1"; FAIL=$((FAIL + 1)); }
have() { command -v "$1" >/dev/null 2>&1; }
require_file() {
  if [ -f "$1" ]; then ok "file present: $1"; else fail "missing: $1"; fi
}
require_re() {
  local pattern="$1" file="$2" label="$3"
  if [ -f "$file" ] && grep -Eq "$pattern" "$file"; then ok "$label"; else fail "$label"; fi
}
forbid_re() {
  local pattern="$1" file="$2" label="$3"
  if [ ! -f "$file" ] || ! grep -Eq "$pattern" "$file"; then ok "$label"; else fail "$label"; fi
}

printf '%s\n' '== GLPI Ticket Email Client v2 verifier =='
printf '  root: %s\n' "$ROOT"

# 1. Required v2 implementation surfaces.
for file in \
  setup.php hook.php \
  inc/replypolicy.class.php inc/recipients.class.php inc/mailboxguard.class.php \
  inc/audit.class.php inc/timeline.class.php inc/timelineaction.class.php inc/timelinereply.class.php inc/history.class.php inc/mailer.class.php \
  front/compose.php front/config.form.php front/send.php front/download.php front/history_attachment.php front/log_entry.php \
  ajax/validate_recipients.php ajax/upload.php ajax/upload_image.php \
  templates/compose.html.twig templates/timeline_action.html.twig templates/log_entry.html.twig \
  js/composer.js sql/install.sql sql/update-1.1.0.sql sql/uninstall.sql \
  locales/ticketemailclient.pot locales/ticketemailclient.en.po locales/ticketemailclient.de.po; do
  require_file "$file"
done

# 2. New persistence fields and the minimal policy table.
for field in followups_id timeline_status timeline_error mailbox_override mailbox_matches; do
  require_re "\\b${field}\\b" sql/install.sql "install schema contains $field"
  require_re "\\b${field}\\b" sql/update-1.1.0.sql "upgrade migration contains $field"
done
require_re "pending.*sent.*failed|pending.*failed.*sent" sql/install.sql "audit status supports pending/sent/failed"
require_re "pending.*recorded.*failed|pending.*failed.*recorded" sql/install.sql "timeline status supports pending/recorded/failed"
require_re "reply.*polic|policy" sql/install.sql "install schema defines reply policy persistence"
require_re "entities_id" inc/replypolicy.class.php "reply policy scopes entities"
require_re "profiles_id" inc/replypolicy.class.php "reply policy scopes profiles"
require_re "available.*promoted.*hide_native" inc/replypolicy.class.php "reply policy defines all modes"

# 3. Ticket-context entry and actor defaults.
require_re "E-Mail antworten|Email reply" inc/timelineaction.class.php "ticket UI exposes E-Mail antworten"
forbid_re "Forward ticket|renderForward|ticketmailer_email_forward" inc/timelineaction.class.php "ticket UI does not expose a forwarding action"
require_re "timeline_answer_actions" setup.php "timeline actions use GLPI's native collapse extension point"
require_re "timeline_actions" setup.php "timeline action buttons register beside native Answer"
require_re "renderReply" inc/timelinereply.class.php "reply timeline wrapper delegates rendering"
require_re "include_history" templates/compose.html.twig "compose offers public history inclusion"
require_re "history_attachments" templates/compose.html.twig "compose offers selectable history attachments"
require_re "is_private" inc/history.class.php "history excludes private followups"
require_re "copySelectedAttachments" front/send.php "send copies selected history attachments"
require_re "selected_history_attachments.*!==.*\\[\\]" front/send.php "send copies selected ticket attachments independently of history"
require_re "attachment.preview_url" templates/compose.html.twig "compose offers public attachment previews"
require_re "canViewItem" front/history_attachment.php "attachment preview requires ticket read authorization"
require_re "resolveAttachment" front/history_attachment.php "attachment preview resolves only offered attachments"
if [ ! -e inc/tickettab.class.php ]; then
  ok "standalone email-reply tab removed"
else
  fail "standalone email-reply tab removed"
fi
require_re "CommonITILActor::REQUESTER" inc/timelineaction.class.php "requesters default to To"
require_re "CommonITILActor::OBSERVER" inc/timelineaction.class.php "observers default to CC"
forbid_re "CommonITILActor::ASSIGN" inc/timelineaction.class.php "compose does not default assignees to CC"
require_re "ReplyPolicy|replypolicy|effectivePolicy" inc/timelineaction.class.php "compose reads effective reply policy"

# 4. Strict raw recipient parsing and mailbox override.
require_re "recipients_(to|cc|bcc)_raw" front/send.php "send retains raw recipient input for validation"
require_re "invalid|malformed" inc/recipients.class.php "recipient parser reports malformed tokens"
require_re "invalid.*recipient|recipient.*invalid|malformed" tests/AcceptanceTest.php "tests cover malformed raw recipients"
require_re "MailCollector|glpi_mailcollectors" inc/mailboxguard.class.php "mailbox guard uses GLPI collectors"
require_re "is_active" inc/mailboxguard.class.php "mailbox guard limits lookup to active collectors"
require_re "login" inc/mailboxguard.class.php "mailbox guard matches collector login"
require_re "mailbox_override" templates/compose.html.twig "compose renders explicit mailbox override"
require_re "mailbox_override" front/send.php "send verifies mailbox override"
require_re "mailbox_matches" front/send.php "send records mailbox matches"
require_re "Ticket::getFormURLWithID" front/send.php "send returns to the ticket"
require_re "log_entry.php" front/send.php "incomplete sends stay on their audit detail"
require_re "mailbox" ajax/validate_recipients.php "recipient AJAX reports mailbox warning"
require_re "followup_template_dropdown" templates/compose.html.twig "reply form exposes GLPI followup templates"
require_re "itilfollowuptemplates_id|itilfollowup.php" inc/timelineaction.class.php "forms use GLPI's followup-template API"
require_re "applyFollowupTemplate" js/composer.js "template selection renders through GLPI's endpoint"
require_re "ajaxComplete" js/composer.js "forms initialize after GLPI loads the ticket timeline"
require_re "X-Glpi-Csrf-Token" js/composer.js "template selection sends GLPI's CSRF header"
require_re "tinymce.get" js/composer.js "template selection updates the active TinyMCE editor"
require_re "ticketemailclientSending|spinner-border" js/composer.js "send submit disables duplicate requests with a spinner"
require_re "ticketemailclient-actions.*disabled|cancel\\.classList\\.add\\('disabled'\\)" js/composer.js "send submit disables cancellation"

# 5. Timeline integration: standard followup but no notification delivery call.
require_re "ITILFollowup" inc/timeline.class.php "timeline integration uses ITILFollowup"
require_re "_disablenotif" inc/timeline.class.php "timeline followup suppresses native notifications"
require_re "followups_id" inc/audit.class.php "audit persists followup ID"
require_re "timeline_status" inc/audit.class.php "audit persists timeline status"
require_re "timeline_status" front/send.php "send handles timeline completion state"
forbid_re "NotificationEvent::raiseEvent|Notification::raiseEvent|NotificationMailing::send" front/send.php "send does not invoke GLPI notification delivery"
forbid_re "NotificationEvent::raiseEvent|Notification::raiseEvent|NotificationMailing::send" inc/timeline.class.php "timeline integration does not invoke GLPI notification delivery"

# 6. BCC policy and secure attachment delivery.
require_re "BCC" templates/compose.html.twig "compose exposes BCC"
forbid_re "BCC addresses and attachments will be visible to every ticket reader" templates/compose.html.twig "compose omits the BCC visibility warning"
require_re "recipients_bcc" inc/timeline.class.php "timeline records BCC addresses"
require_re "recipients_bcc" templates/log_entry.html.twig "audit detail displays BCC addresses"
forbid_re "recipients_bcc_count|hidden recipient" templates/log_entry.html.twig "audit detail is not BCC count-only"
require_re "Ticket::canViewItem|canViewItem" front/download.php "downloads require ticket read authorization"
require_re "safeResolveUnder|realpath" front/download.php "download resolves controlled storage path"
forbid_re "href=\"{{[[:space:]]*a\.path" templates/log_entry.html.twig "detail never renders raw attachment paths"
require_re "attachment" inc/timeline.class.php "timeline creates secure attachment links"

# 7. SMTP reuse, CSRF, and translations remain mandatory.
require_re "smtp_host|Config::getConfigurationValue" inc/config.class.php "plugin reuses GLPI SMTP configuration"
require_re "Session::checkLoginUser" front/send.php "send requires login"
require_re "canUpdateItem|canAddFollowups" front/send.php "send requires ticket write/followup permission"
forbid_re 'Session::checkCSRF\(\$_POST\)' front/send.php "send relies on GLPI bootstrap CSRF validation"
require_re "Html::hidden\('_glpi_csrf_token'" front/config.form.php "config form includes GLPI CSRF token"
forbid_re 'Session::checkCSRF\(\$_POST\)' front/config.form.php "config relies on GLPI bootstrap CSRF validation"
require_re "csrf" templates/compose.html.twig "compose contains CSRF protection"
for locale in locales/ticketemailclient.pot locales/ticketemailclient.en.po locales/ticketemailclient.de.po; do
  require_re "mailbox|BCC|reply" "$locale" "locale contains v2 recipient/policy text: $locale"
done

# 8. Parse changed PHP without requiring vendor or a running GLPI instance.
if have php; then
  parse_failed=0
  for file in inc/replypolicy.class.php inc/recipients.class.php inc/mailboxguard.class.php \
              inc/audit.class.php inc/timeline.class.php inc/timelineaction.class.php inc/timelinereply.class.php inc/timelineforward.class.php \
              front/compose.php front/config.form.php front/send.php \
              front/download.php ajax/validate_recipients.php; do
    if [ -f "$file" ] && ! php -l "$file" >/dev/null 2>&1; then
      fail "php syntax: $file"
      parse_failed=1
    fi
  done
  [ "$parse_failed" -eq 0 ] && ok "v2 PHP surfaces parse"
else
  echo '  SKIP: php unavailable; PHP syntax not checked'
fi

printf '\n== Result ==\n'
printf '  PASS: %s\n' "$PASS"
printf '  FAIL: %s\n' "$FAIL"
[ "$FAIL" -eq 0 ]
