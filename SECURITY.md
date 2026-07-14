# Security policy

## Supported versions

Security fixes are made against the latest released `1.4.x` version and the current development branch. Older releases may receive guidance but are not guaranteed to receive patches.

## Reporting a vulnerability

Do **not** open a public issue for a suspected vulnerability.

Until a dedicated private reporting channel is published, contact the repository maintainer privately through the contact method listed on the repository profile. Include:

- affected ticketmailer and GLPI versions;
- a minimal reproduction or proof of concept;
- impact and required permissions;
- whether the issue exposes ticket data, recipients, attachments, SMTP configuration, or allows duplicate delivery.

Please do not include production credentials, personal data, or full ticket content. The maintainer will acknowledge a report, assess scope, and coordinate disclosure and a fix where possible.

## Security boundaries

Ticketmailer relies on GLPI authentication, CSRF protection, ticket permissions, configured SMTP transport, and GLPI's document directory. Administrators remain responsible for GLPI patching, TLS and SMTP configuration, database and document-directory backups, access control, and data-retention policy.

The plugin intentionally records full BCC recipient lists in ticket-visible audit and timeline records. Treat this as a product behavior, not a confidentiality guarantee for BCC recipients within GLPI.
