---
name: ticketmailer-release
description: Prepare and verify ticketmailer Release Please PRs, deterministic packages, release gates and failed-release recovery.
---

# ticketmailer-release

Read ../../../docs/RELEASING.md and ../../../release-please-config.json first. Resolve all commands from the repository root.

1. Inspect git status, current branch, remote main, tags and releases read-only. Never infer a published release from setup.php alone.
2. Run python3 scripts/release/release.py check and the eight offline tests with python3 -m unittest discover -s tests/release -v. Never manually increment only one version surface.
3. Run the project verify skill, build and verify with scripts/release/release.py, then run bash scripts/release/integration.sh with an explicitly selected Docker context. This disposable test does not authorize production or shared-dev database changes.
4. For a release PR, inspect all generated version changes, changelog, compatibility and migration notes. Every required check must have passed. Do not merge, tag, publish or change GitHub protection without explicit task coverage.
5. For failures, distinguish a failed draft from a public release. Follow the recovery section; never move tags, overwrite published assets or manually publish around failed gates.
6. Report exact commit, version, commands/results and untested compatibility. Keep secrets and browser traces out of regular artifacts.

The executable script is the contract for package paths and metadata. Do not duplicate its allowlist or version calculation in prompts.

## Release-PR review standard

Read the release-PR checklist in ../../../docs/RELEASING.md. Keep Release Please's
machine-managed version, changelog and body markers intact. Put additional human
review evidence in a PR comment or linked verification record, not by replacing
the generated body. Administrator-facing release notes explain practical changes;
the technical changelog is not a substitute for upgrade instructions.

For optional screenshots/video load ../ticketmailer-release-media/SKILL.md.
Missing media is never a reason to bypass or delay technical release gates.
