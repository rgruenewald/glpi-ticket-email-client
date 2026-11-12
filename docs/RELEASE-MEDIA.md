# Optional UI and release media

## Scope

Screenshots are the default for visible PR changes. A short video can explain a
composer flow, recipient selection or ticket action better than still images.
Internal refactoring, security fixes and migrations normally need clear release
notes, not a video. Media never substitutes for a test or becomes a release gate.

No video renderer, HyperFrames integration or new dependency is installed by this
process. This document defines a repeatable capture specification, not an executed
capture recipe. Record a successful pilot before claiming that a renderer works.

## Capture specification

Choose and record these inputs before capture:

| Input | Default / rule |
| --- | --- |
| Source | Exact after commit and, for comparisons, exact before commit |
| Environment | Disposable GLPI fixture; record GLPI, PHP and browser versions |
| Data | Synthetic tickets, attachments and example.test mail only |
| Language | English for public project media; record an intentional alternative |
| Viewport | 1440 x 1000 CSS pixels, device scale factor 1 |
| Theme and zoom | Light theme, 100% browser zoom |
| Screenshot | PNG, same viewport and state before/after |
| Video target | MP4/H.264, 1440 x 1000, 30 fps, usually 15–45 seconds, no audio |
| Scenario | Explicit initial state, actions and expected visible result |
| Output location | Private temporary directory outside Git and synchronized folders |

Video format is an output target, not a claim that Playwright exports it directly.
If conversion is needed, first verify an available local tool and record its version
and command. Browser recordings need not be byte-identical. Stable fixtures and
steps matter more than matching encoded bytes.

## Browser capture

Read docs/verification/docker-e2e.md and inspect playwright.config.js and the
relevant tests/e2e scenario. Existing scenarios prove behavior; use them as a
starting point without weakening their assertions. There is no universal capture
command yet. A reviewed scenario must specify its setup and cleanup before use.

Do not start scripts/dev without checking that switching its shared worktree mounts
is acceptable. The release integration script owns a disposable stack and cleans
it up automatically, but is a test harness, not a stable media-capture API. Do not
remove its cleanup to obtain recordings. A capture pilot must establish its own
explicit environment lifecycle or use an authorized existing local environment.

For before/after captures preserve each version's runtime dependencies and fixture
requirements. Do not assume the same database can safely move backward across a
migration. Reset isolated fixtures instead of rolling production data back.

## Editing and HyperFrames pilot

Real product captures are the source. An optional renderer may add titles,
callouts, cuts and transitions. Do not recreate an unimplemented interaction as if
it were a recording of working software. Label illustrative mockups clearly.

Before introducing HyperFrames, identify the exact product and read current
primary documentation. Confirm:

- local versus hosted processing and where captured frames are sent;
- installation/version pinning, license and any cost;
- supported input/export formats and rendering commands;
- whether a local, no-upload path satisfies the task;
- a small synthetic-data pilot and reproducible rerun.

Prefer a local path. External uploads, accounts or charges need explicit task
coverage. No HyperFrames API or render command is assumed here. If adopted after
an authorized pilot, preserve a source manifest and executable local recipe rather
than depending on a free-form prompt alone.

## Provenance and review

Keep a small sidecar record with selected media:

- source commit(s), scenario, fixture and tool versions;
- viewport, language, render/export settings and commands actually used;
- whether the file is a raw capture, edited capture or illustrative mockup;
- cuts, speed changes or animations that affect interpretation;
- review result for privacy, accuracy, readability and correspondence to release.

Review all selected media, including recipient fields, attachments, URLs and
background notifications. Never attach session traces or cookies as public media.
Do not include real tickets, addresses or SMTP credentials, even in a private PR.

Publish only selected, reviewed assets under an explicit publication task. Provide
alt text for screenshots and a short text summary of each video. Keep the ordinary
release notes understandable without watching it. Hosting and GitHub embedding
support must be checked before choosing links or committing large binary files.
