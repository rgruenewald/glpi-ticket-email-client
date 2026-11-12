# Release-management verification

Working branch: build/release-management, based on origin/main
3c940f2db6c710dd556a6871eb08439366634187. Existing local main was older;
implementation follows the fetched current product baseline 2.3.1 / GLPI 11.

Executed locally:

- Python release tests: 8 passed, including byte-identical rebuild, checksum
  corruption, unsafe archive paths, symlinks, version/tag drift and XML metadata.
- Package build and verify passed; archive contains only allowlisted runtime files.
- Composer strict validation passed.
- PHPUnit on PHP 8.5.9: 105 tests, 433 assertions passed.
- PHP syntax checks passed for runtime, tests and release migration fixture.
- Node unit tests: 5 passed.
- v2 structural score: 114 passed, 0 failed.
- Disposable packaged GLPI 11.0.8 integration: install/activate, real SQL migrations
  and idempotent migration rerun passed. All 8 existing browser tests passed,
  including SMTP success/outage, attachments/CID, private data guards, mailbox
  override and entity/profile ACL boundaries. Existing E2E recipient locators
  needed adaptation from textbox to the current combobox role.
- Simulated 2.3.2 version in a private copy: PluginIdentityTest passed, 4 tests and
  26 assertions. README and CONTEXT version annotations are maintained by Release
  Please, rather than leaving the old hard-coded identity assertion.
- YAML parsing, shell syntax and git diff --check passed.

Independent review:

- Fresh native Combo session timed out after repeated reading without a result.
- Fresh native Codex medium fallback used, explicitly a same-family exception.
- The first fallback's nonexistent smoke-script finding was rejected against actual
  file bytes. Its hard-coded version-test finding was confirmed and corrected.
- A further fresh fallback review of actual files found no confirmed critical
  defects and independently passed the 8 release tests. It did not rerun integration.
- Final version/documentation consistency changes were then checked by PHPUnit and
  the simulated next-version test described above.

Not executed/claimed:

- PHP 8.2/8.3/8.4 CI matrix, GitHub-hosted runs, live Release Please PR/tag creation,
  GitHub App setup, ruleset changes, public release or marketplace submission.
- A previous published-version upgrade: no remote tags/releases existed. Current
  real SQL fixture is not a substitute for testing an actual prior release later.
- A fully pinned Docker/OS dependency closure or bit identity across zlib versions.

Activation prerequisites and limits are in ../RELEASING.md. No changes were made
to GitHub settings, secrets, main, existing shared development volumes or marketplace.
