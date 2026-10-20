# Repository layout and cleanup audit

## Maintained source

| Location | Purpose |
| --- | --- |
| `ajax/`, `front/`, `inc/`, `templates/`, `public/` | Plugin runtime and UI |
| `sql/` (including `sql/install.sql`) | Database schema and migrations |
| `locales/` | Translation sources and compiled catalogs |
| `tests/`, `scripts/`, `docker/` | Automated verification, release tooling and disposable development environments |
| `.github/` | CI, release automation and contribution templates |
| `docs/specifications/glpi-ticket-email-client-v2/` | Active behavior contract |
| `scripts/verify/score.sh` | Structural CI gate |
| `.agents/skills/`, `AGENTS.md`, `CONTEXT.md` | Maintained project guidance |
| `docs/adr/`, `docs/verification/`, `docs/wiki/` | Decisions, verification recipes and user documentation |
| `assets/readme/`, `docs/media/`, `docs/pr/`, `docs/images/`, `docs/performance/` | Presentation assets, capture provenance and historical verification evidence |

`.agent/` is exclusively local, ignored agent state. No versioned files, CI commands or build prerequisites live there. `.agents/skills/` is a separate, intentionally versioned collection of maintained project guidance.

## Cleanup performed

- Removed `.agent/forge_state.json` from Git tracking, without deleting the local file. It is execution state, not a product source or reproducible test fixture.
- Moved the active product contract to `docs/specifications/` and its verifier to `scripts/verify/`. Updated CI, development tooling, contributor instructions and project references.
- Archived historical contracts under `docs/archive/contracts/`. The entire `.agent/` tree is now ignored and absent from the Git index.
- Added explicit exclusions for local Composer credentials, Docker overrides, coverage, Playwright reports, caches, logs and editor/patch temporary files. Sanitized `.env.example` templates remain trackable.
- Kept dependency lockfiles, migrations, translation catalogs, release metadata and curated evidence versioned.

## Historical material

| Candidate | Finding and safe next step |
| --- | --- |
| `docs/archive/contracts/ticket-mailer/` | Archived v1 contract, still referenced by the v2 specification and project guidance. Its implementation prompt and legacy verifier are historical evidence, not current instructions. |
| `docs/archive/contracts/namespace-cutover/` | Archived migration contract, not a runtime dependency. |
| `docs/performance/` and `docs/ui-*.md` | Historical measurements, screenshots and experimental JS variants. Reports refer to their raw results and describe how to reproduce comparisons. Keep together if archived; do not mistake variants for shipped UI code. |
| `docs/pr/`, `docs/media/pr-38/`, `docs/images/internal-note-solution-templates/` | PR/UI evidence, not runtime files. Consolidation is possible but requires checking historical PR links in addition to local references. |
| `docs/wiki/Administrator-Guide-Planning.md` | Planning document, not an installation guide. Review separately against the maintained administrator and user documentation before retiring it. |

No historical contracts, evidence or runtime files were deleted merely because they are old or have few local references. The repository already separates runtime code, verification, documentation and assets; a broad directory move would add link churn without a demonstrated runtime benefit.

## Local generated directories

`vendor/`, `node_modules/`, `dist/`, `.phpunit.cache/`, `_files/`, Python caches and browser reports are local/generated and ignored. They are not tracked source clutter. They were not deleted: doing so could remove useful test environments, release artifacts or GLPI runtime data.

## Review scope

This audit inspected tracked files, directory layout, ignored-but-tracked files and local references in guidance, CI, scripts, tests and documentation. It is not a semantic dead-code analysis, an audit of external links in historical PRs, or permission to remove persistent runtime data. No plugin behavior, release packaging or database schema was changed.
