# Security policy

## Supported versions

Security fixes target the latest published release and the current development branch. Older releases may receive guidance but are not guaranteed patches.

## Reporting a vulnerability

Do **not** open a public issue for a suspected vulnerability. Use GitHub's private vulnerability reporting form from this repository's **Security** tab by selecting **Report a vulnerability**.

Include:

- affected GLPI Ticket Email Client and GLPI versions;
- a minimal reproduction or proof of concept;
- impact and required permissions;
- whether the issue exposes ticket data, recipients, attachments, SMTP configuration, or allows duplicate delivery.

Do not include production credentials, personal data, or full ticket content. Redact sensitive values and provide only the minimum data needed to reproduce the issue. The maintainer will assess the report and coordinate disclosure and a fix where possible; this policy does not promise a response or remediation deadline.

For an accepted report that requires confidential collaborative patching, an administrator may manually select **Start a temporary private fork** from the draft security advisory. GitHub Actions, required status checks, and branch rules do not provide the normal gates in that fork. The confidential diff therefore requires manual review and safe local tests. After confidential integration, all normal repository checks must pass before public release.

## Security boundaries

GLPI Ticket Email Client relies on GLPI authentication, CSRF protection, ticket permissions, configured SMTP transport, and GLPI's document directory. Administrators remain responsible for GLPI patching, TLS and SMTP configuration, database and document-directory backups, access control, and data-retention policy.

The plugin intentionally records full BCC recipient lists in ticket-visible audit and timeline records. Treat this as a product behavior, not a confidentiality guarantee for BCC recipients within GLPI.

GitHub CodeQL does not analyze this plugin's PHP source. Repository CodeQL results cover only GitHub Actions and JavaScript/TypeScript selected by GitHub's default setup; they are not comprehensive PHP application scanning.
