# Background compose preparation

The ticket view now schedules one compose fetch during browser idle time after page load (fallback: 250 ms timer; idle deadline: 2 s). The same mounted modal, native form, dropdowns and TinyMCE instances are reused on click and reopen. A click before preparation finishes shares its pending request. Failed background fetches remain silent; a click retries and falls back to the ACL-protected compose page if loading fails. No persistent cache or draft storage is introduced.

The plugin owns its modal shell instead of calling GLPI's createModalWindow helper, whose show handler loads the body on every show. Native server-side compose rendering, bootstrap Modal and editor scripts remain unchanged. The shell moves to document.body so its hidden timeline container cannot suppress rendering. Its owner marker remains in the ticket tab: on an AJAX tab replacement, old editors/modal are removed during AJAX completion. Full navigation destroys the document. A successful native submit retains the existing navigation and server-side ACL checks. No data is sent while preparing.

Native note layout preparation is once per panel. Hidden preparation additionally requires guarding absent select2 labels. Timeline synchronization listens to modal shown/hidden events so the reply action is restored after closing.

## Measurement

GLPI 11.0.2, Chromium 156.0.8078.4, synthetic ticket 137, no throttling. The same readiness script waits for the ticket network-idle before clicking; this explicitly gives background preparation time. Therefore these results measure prepared click latency, not a cold click or eliminated work. Early clicks remain subject to fetch/editor latency.

| Interaction | Desktop ms | Mobile ms |
| --- | --- | --- |
| First prepared click, single observation | 34.6 | 28.2 |
| Reopen, observed p95 of 9 | 28.2 | 30.3 |
| Internal-note mode, observed p95 of 10 | 36.1 | 31.9 |
| Email mode, observed p95 of 10 | 31.9 | 31.9 |

All observed prepared interactions were below 200 ms. Source base 2e41f2a plus this worktree's uncommitted changes; prior first-open medians were 270 ms desktop / 259 ms mobile. Raw data: performance/ui-render-preload.json. The work is shifted to the background, not removed. Idle scheduling avoids running before the window load event, but editor initialization can still consume main-thread time afterward; no claim is made that page load or responsiveness is universally unaffected.

## Checks

- Four Playwright tests: desktop/mobile preparation without visible opening/backdrop, real editor width, draft retention through five switch/close/reopen cycles, one compose fetch, navigation invalidation, failed background fetch retry and shared in-flight request on double click, plus teardown of an open modal on AJAX owner replacement with backdrop/body cleanup. No mail sent.
- PHPUnit: 87 tests, 347 assertions passed using the existing private test vendor volume.
- V2 structural gate: 111 passed, 0 failed; JavaScript regression scripts passed.
- Final fresh native Combo review: no substantiated findings. Earlier unreliable reviews were discarded; one documented Codex fallback was also used.
- Local test stack restored to its original docker-e2e mounts after measurement.
- Existing GLPI-native editor and dropdown APIs are inspected from the actual installed version; no GLPI/vendor changes.

The reusable lifecycle and performance checks do not replace a future SMTP/upload/template end-to-end matrix or compatibility testing on GLPI 10. The actual browser version tested here is GLPI 11.0.2.
