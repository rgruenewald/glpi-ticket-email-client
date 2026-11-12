# UI rendering baseline and optimization

Follow-up: `ui-first-open-profile.md` records five fresh first-open runs, a stricter editor-identity readiness check, the discarded lazy-note experiment, and a warm desktop mode-switch outlier of 202.1 ms. The table below is the original run, not a guarantee that all warm interactions stay below 200 ms.

## Scope and method

Measured the ticket reply button opening the compose modal, its editable TinyMCE editor, internal-note mode and return to email. CC/BCC and attachments are already rendered, not separate reveal controls in this revision. No email was sent. Synthetic local ticket 137, GLPI 11.0.2, Chromium 156.0.8078.4, headless, no CPU/network throttling. Baseline source: `2e41f2a` (the existing Docker test branch), not main `dd61009`. Optimized source: that revision plus the uncommitted `perf/ui-rendering` changes.

Each viewport uses a new browser context, logs in and loads the ticket before measuring. Start is synchronous DOM click dispatch, excluding Playwright actionability overhead; finish is editor initialized and connected (or native note editor initialized) followed by two animation frames. This is a readiness/paint-opportunity proxy, not a compositor screenshot or guarantee that the entire screen has settled. Ten opens per viewport: one first-open observation, nine reopen observations, ten mode switches. First-open is a single observation, not a statistically meaningful p95. Warm p95 uses nearest rank, which is the maximum at this sample size. Caches are not explicitly cleared, so first-open does not mean cold infrastructure.

## Results

Times in milliseconds, median / observed p95 except first open (single sample).

| Viewport | Interaction | Before | After | Under 200 ms after |
| --- | --- | --- | --- | --- |
| 1440 x 1000 | First open, editor ready | 278.3 | 270.7 | No |
| 1440 x 1000 | Reopen | 31.7 / 32.8 | 19.8 / 30.5 | Yes |
| 1440 x 1000 | Internal note | 123.1 / 147.4 | 91.1 / 128.3 | Yes |
| 1440 x 1000 | Return to email | 136.0 / 233.5 | 124.0 / 157.1 | Yes |
| 390 x 844 | First open, editor ready | 278.0 | 260.4 | No |
| 390 x 844 | Reopen | 31.2 / 32.5 | 25.1 / 27.2 | Yes |
| 390 x 844 | Internal note | 76.8 / 82.1 | 101.4 / 125.8 | Yes, slower than baseline |
| 390 x 844 | Return to email | 140.0 / 147.1 | 100.2 / 142.8 | Yes |

These are local observations, not a universal SLA. Mobile note mode regressed in this run; no improvement is claimed there. The initial open still exceeds the target. The compose HTTP response alone was about 38-51 ms in exploratory probes, so server response time is not the same as editor readiness. Further first-open work needs profiling native editor initialization, not silently preloading ticket data or replacing GLPI editor behavior.

## Changes

- Prepare native note structure once per mounted panel instead of repeating DOM restructuring on every mode switch. Mode visibility and modal title still update on every switch.
- Coalesce timeline class-mutation notifications into one pending animation frame; remove the four delayed synchronization callbacks per notification. Keep Bootstrap collapse and AJAX synchronization paths.
- Add a regression assertion proving mode switches do not repeat native-note preparation.

No changes to sending, SMTP, ACLs, schema, strings, GLPI/vendor code or draft persistence.

## Reproduction

Use an existing isolated local GLPI fixture with the tested plugin source mounted. Do not point at production.

```sh
PLAYWRIGHT_MODULE=/absolute/path/to/node_modules/playwright \
BASE_URL=http://127.0.0.1:8080 TICKET_ID=137 SAMPLES=10 \
node scripts/ui-render-baseline.cjs > /tmp/ui-render-results.json
```

The local test stack was temporarily mounted to this worktree for measurements and restored to its original docker-e2e source afterwards. Raw observations: `docs/performance/ui-render-before.json` and `docs/performance/ui-render-after.json`.

## Verification

V2 structural gate: PASS 111, FAIL 0. JavaScript regression scripts and syntax checks passed. Independent native Combo review returned no concrete findings for timeline synchronization and one-time preparation; its claimed additional test counts were not accepted as verification evidence. It did not perform its own browser run. PHPUnit could not run: no local PHP/vendor runner and no PHPUnit executable in the available test image. No SMTP smoke was run because delivery behavior is unchanged.
