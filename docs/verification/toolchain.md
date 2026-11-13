# Toolchain policy

## Entry points and pins

Run `mise install` and `mise trust .mise.toml` after reviewing the project configuration.
`./scripts/ci all` re-executes with the pinned Node and Python versions. npm 11.19.1
is bundled with Node 24.22.1 and is checked explicitly. `packageManager` documents
that version. `scripts/toolchain.env` pins Composer, the four PHP matrix images,
and both GLPI archives. Dockerfiles pin base images; Compose uses matching digest-pinned
MariaDB and Mailpit images for development and packaged integration. OS package
repositories are not snapshot-pinned, so these are controlled inputs, not a claim
of bit-identical container rebuilds. Package archives remain deterministic for
the documented Python/zlib toolchain.

Use `bash scripts/composer` rather than an arbitrary host Composer. Each PHP matrix
image is built from the reviewed base and includes the project test extensions.
PHPUnit 11 retains PHP 8.2 support; newer PHPUnit major versions require a separate
PHP compatibility decision. `quick` omits integration, not network access or security.

## Static analysis

PHPStan level 1 runs over all runtime PHP, targeting PHP 8.2 syntax. The explicit
path/count baseline covers missing external GLPI classes, functions and constants
only. Two analysis-only GLPI base declarations allow inheritance to be resolved;
they are never loaded at runtime. New findings and unmatched baseline entries fail.
Do not regenerate the baseline on failure. Replacing missing GLPI symbols with
versioned accurate stubs and increasing the level are reviewed follow-up work;
this first gate is not full GLPI API typing or PHP security taint analysis. CodeQL
continues to analyze JS/TS and Python with its pinned suites and failure policy.

## Evidence and privacy

Every CI invocation keeps a private `.cache/ci/run-*` directory (parent mode 0700).
`summary.json` records HEAD, a hash of the actual tracked/untracked source snapshot,
dirty status, mode, tool versions and the toolchain hash. A nonzero gate or source/HEAD
drift makes the run fail. The directory also contains the command log, matrix JUnit,
PHPStan JSON and per-GLPI Playwright JUnit/HTML/artifacts plus the actual runtime
version. CodeQL and performance retain their existing local artifact locations.
These are local records, not trusted remote attestations. Never upload traces,
source snapshots, logs or reports without reviewing them for secrets and personal
data. No source or security database is uploaded automatically.

## Updates and enforcement

Dependabot handles Composer, npm and Docker inputs, including the test Dockerfile
and release Compose. It does not update `toolchain.env`, mise pins, GLPI archive
digests or the CodeQL lock automatically. Review these together, compare upstream
release digests, run policy tests and both packaged GLPI targets before merging.
The drift contracts reject inconsistent default GLPI versions and service images.

GLPI 12 is primary; GLPI 11 remains supported. GLPI 10 is not supported. Historical
measurements, media and released catalog compatibility remain historical facts.

The project deliberately uses local CI. Hooks can be bypassed. Independent merge
enforcement would require a separately authorized controlled runner and repository
rule change. No branch protection is changed by these tools. Release SBOM/checksums
are unsigned; adopting a signing identity, protected signing key and independent
provenance is a separate operational setup, not something this repository invents.
