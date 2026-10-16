# Frequent-action performance baseline

## Scope

Baseline: commit `dd61009`. Only public attachment discovery changed. Recipient parsing and mailbox matching are control cases, not claimed optimizations. Mailbox matching already uses a normalized hash set. No SMTP, ACL, schema, UI, or delivery changes.

Attachment discovery now batches document-link lookup by source type instead of issuing one lookup per public followup. Empty source lists are skipped. Links are regrouped into the original source order before deduplication, preserving filename-sort tie behavior. Document loading, filesystem checks and natural filename sorting remain unchanged.

## Method

Local Docker image ticketmailer-test:latest, PHP 8.5.9 CLI, OPcache disabled, JIT disabled, network disabled, read-only source mounts. Synthetic deterministic addresses and ticket metadata; a private temporary file is created and removed inside the disposable container. Five warmups, nine samples; each sample uses 30 parser calls, 20 mailbox calls or 3 attachment calls. Times are per-call medians in microseconds. The script also prints sample minimum/maximum and SHA-256 result hashes. Baseline and optimized hashes matched for all six cases.

The baseline and optimized runs use the **same harness**. An optional first argument selects a different source root. For the comparison below, /baseline was a read-only mount of main at dd61009. Ensure that source root still has the intended commit before repeating the measurement.

~~~sh
docker run --rm --network none \
  -v "$PWD:/app:ro" -v /path/to/baseline:/baseline:ro \
  -w /app --entrypoint sh ticketmailer-test:latest -c \
  'php -d opcache.enable_cli=0 scripts/benchmark-frequent-actions.php /baseline; php -d opcache.enable_cli=0 scripts/benchmark-frequent-actions.php'
~~~

## Results

| Action / size | Baseline median us | Optimized median us | Metadata queries before / after | Document loads |
|---|---:|---:|---:|---:|
| Parse / 10 addresses | 8.387 | 8.350 | 0 / 0 | 0 |
| Parse / 1000 addresses | 827.943 | 817.108 | 0 / 0 | 0 |
| Mailboxes / 10 collectors and recipients | 10.756 | 10.787 | 1 / 1 | 0 |
| Mailboxes / 1000 collectors and recipients | 1070.480 | 1077.494 | 1 / 1 | 0 |
| Attachments / 10 public followups | 24.633 | 13.984 | 12 / 3 | 10 |
| Attachments / 1000 public followups | 104236.249 | 2091.334 | 1002 / 3 | 1000 |

Attachments: about 43% less local processing time in the small case and 98% less in the large case. Unchanged controls vary with timing noise. Sample ranges: attachments/10 baseline 24.433-42.596 us, optimized 13.879-14.560 us; attachments/1000 baseline 101805.948-108285.600 us, optimized 2070.453-2172.419 us.

## Interpretation and limits

The database double scans in-memory tables and models array criteria, not a database optimizer or network latency. **These speedups are not real GLPI request-latency claims.** Metadata queries count followup enumeration and document-link lookup only. Document::getFromDB is modeled separately as document_loads; real GLPI still performs per-document loading. File checks use one shared physical file with distinct synthetic document records, not distinct physical attachments. Additional intermediate arrays use O(number of links) memory; peak-memory comparison is not included.

The concrete GLPI 11.0.6 DBmysqlIterator source was inspected: nonempty array values are compiled to IN criteria; empty IN lists are rejected, hence the explicit empty-list guard. This is source inspection, not a live GLPI/database integration test. No running GLPI service was changed, no SMTP messages were sent, and no production data was read.

Regression tests enforce three metadata queries with public followups, two without public followups, exclusion of private/foreign-ticket links, deduplication, source-order ties, metadata, unavailable files, and selected-document resolution. Timing values are deliberately not hard pass/fail gates.

## Verification

- Before product changes: 86 existing tests / 342 assertions passed; first three new regression tests also passed against the baseline.
- After changes: 90 tests / 356 assertions passed, PHP syntax checks passed, both JavaScript test files passed, v2 structural gate passed (0 FAIL; PASS count varies with scanned files).
- Independent fresh native Pi review found a misleading query-count label; fixed by explicitly reporting metadata queries and document loads separately. No product-code findings. The configured combo/review route did not provide independently confirmed backend-family identity; cross-family independence is therefore not claimed.

## Main integration verification

Merged origin/main at `a6b34f6` into the performance branch without conflicts. On the integrated tree, PHPUnit passed with 105 tests / 433 assertions; the structural gate passed with 115 PASS / 0 FAIL. PHP syntax checks passed and the benchmark still reports three attachment metadata queries in both sizes. A fresh independent native Pi integration review found no findings and additionally verified randomized PHPUnit order (seed 375), all four JavaScript test files, and array/IN handling in the local GLPI image source. No live GLPI/database, browser or SMTP test was run.
