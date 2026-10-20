# Historical contracts

These contracts are retained for provenance, not as current implementation instructions or CI gates.

- `ticket-mailer/`: superseded v1 product contract, implementation prompt and legacy verifier.
- `namespace-cutover/`: historical namespace migration contract.

The active product contract is [the v2 specification](../../specifications/glpi-ticket-email-client-v2/spec.md). Run `bash scripts/verify/score.sh` from the repository root for the maintained structural gate. Do not replay the archived implementation prompt as a new task.
