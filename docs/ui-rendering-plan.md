# UI rendering performance plan

- Baseline: `2e41f2a`, the revision mounted in the local GLPI test instance, not main `dd61009`.
- Scope: ticket-button compose dialog, editable email editor, internal-note mode, return to email, CC/BCC and attachment rendering remain part of the form, not separate reveal interactions. No send, SMTP, ACL or data-model changes.
- Measure real Chromium rendering at 1440x1000 and 390x844 with synthetic ticket 137. Start at DOM click dispatch; finish after readiness and two animation frames. Record first open separately and repeated samples with median/p95. Target: p95 below 200 ms, not merely server response time.
- Identify the slow path, add regression coverage, make a minimal plugin-owned change without modifying GLPI. Re-run the same browser harness.
- Run v2 structural gate, PHP/JS regression tests and fresh independent review. Document anything still over target.

Progress: baseline and optimized measurements completed, regression coverage and v2 gate passed, independent review completed. Warm interactions meet the observed 200 ms target; first editor open remains 260-271 ms. PHPUnit unavailable. Local stack restored. Full evidence: docs/ui-rendering-performance.md.
