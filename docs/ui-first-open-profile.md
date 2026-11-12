# First-open profiling

## Result

The first usable editor still exceeds 200 ms. Five fresh Chromium runs per viewport with the corrected readiness predicate produced:

| Viewport | Samples ms | Median ms | Maximum ms |
| --- | --- | --- | --- |
| 1440 x 1000 | 260.9, 266.4, 285.1, 270.3, 274.7 | 270.3 | 285.1 |
| 390 x 844 | 266.1, 241.5, 258.6, 254.9, 280.0 | 258.6 | 280.0 |

Conditions match the earlier report: GLPI 11.0.2, Chromium 156.0.8078.4, local synthetic ticket 137, no throttling. Source is base `2e41f2a` plus the existing perf/ui-rendering changes. Warm infrastructure is not a cold-cache test. The timing predicate now additionally requires plugin initialization and editor.getElement() identity with the current textarea. Earlier measurements remain historical observations, not guaranteed SLAs. A separate five-cycle follow-up saw one desktop return-to-email at 202.1 ms, so even warm interactions are not reliably always under 200 ms.

## Critical path

CDP CPU sampling plus in-page event/resource timestamps on an unmodified editor lifecycle showed this representative sequence from the click:

- Modal shown: 7.3 ms.
- Compose request: starts 2.5 ms, completes 46.2 ms.
- Email tinymce.init: 102.8 ms; AddEditor: 103.9 ms.
- Hidden native-note tinymce.init: 115.2 ms.
- Email editor init event: 228.5 ms.
- Hidden note editor init event: 250.5 ms.

The request is not the main delay. Both editors initialize on the first email open. Native-note initialization and DOM work compete with the visible email editor. CPU samples prominently include native setAttribute/appendChild and TinyMCE routines; this establishes a contention hypothesis, not an exact allocation of all elapsed time. Instrumentation itself has overhead; profile timings are separate from the five-run benchmark. Raw timestamp and benchmark evidence: `performance/ui-first-open-profile.json`.

## Experiment and decision

Tested wrapping the server-rendered native note form in an inert template and materializing it only when internal-note mode is selected. This used the same native form and did not change GLPI/vendor files or send data. After clearing Twig cache, the email editor init event fell to 148.9 ms in a diagnostic run. However, rapid repeated reopen/switch cycles exposed missing native-note readiness and initialization ordering problems. The experiment also exposed existing assumptions that select2 rendered labels already exist during note preparation.

**The entire lazy-native-form experiment was reverted.** No template, native submission, or editor lifecycle change remains. Reporting the fast diagnostic event as a successful sub-200 ms result would be misleading: it is not the same endpoint as the readiness benchmark and the interaction regression disqualifies the implementation.

A robust further implementation should use a GLPI-supported on-demand editor/form lifecycle, account for modal reloads and fresh dropdown/file-upload initialization, and verify native-note submission, templates, uploads and draft preservation. This is materially larger than a safe timing-only patch. No prefetch of ticket content or third-party source modifications were introduced.

## Checks

Existing JS regression tests, script syntax, v2 structural gate and diff whitespace check rerun. Browser first-open profiling and repeated modal/mode measurements performed; no mail sent. Local test stack restored to its original docker-e2e mounts after testing. PHPUnit remains unavailable as documented in the original report.
