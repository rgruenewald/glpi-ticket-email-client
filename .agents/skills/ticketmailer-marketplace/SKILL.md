---
name: ticketmailer-marketplace
description: Prepare ticketmailer GLPI Plugin Directory and Marketplace metadata from verified GitHub release artifacts without claiming acceptance.
---

# ticketmailer-marketplace

Read ../../../docs/RELEASING.md, especially GLPI directory / Marketplace. Run commands from the repository root.

1. Confirm the intended version is a published, non-draft GitHub release and record its immutable tag/commit. Do not use a development version as publication evidence.
2. Build and verify using python3 scripts/release/release.py; compare the public archive and SHA256SUMS against the verified assets. Review setup.php GLPI compatibility against actual test evidence.
3. Use the generated dist/ticketmailer.xml. Do not manually invent or widen compatibility, author/license facts, historical releases or download URLs.
4. Consult the current official directory submission rules. Resolve metadata hosting, update behavior and historical release requirements before submission. Existing XML is a candidate, not proof of server-side acceptance.
5. Submit or modify the public listing only when that external action is explicitly authorized. Do not send credentials, real ticket content or SMTP secrets.
6. Record the directory URL, submitted metadata version, validation/moderation result and any remaining blockers. Do not call the plugin marketplace-ready merely because an XML file exists.
