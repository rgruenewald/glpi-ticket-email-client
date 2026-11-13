# ADR 0002: GLPI 12 primary, GLPI 11 supported

- Status: Accepted by the project owner
- Supersedes: the GLPI 11-only compatibility scope in the v2 contract

GLPI 12 is the primary runtime. GLPI 11 remains supported; GLPI 10 is not supported. The current advertised range is 11.0.0 through 12.0.99. Widening that range requires a separate decision. PHP 8.2 remains the plugin language floor; each GLPI release also imposes its own PHP requirements.

The packaged integration gate must exercise GLPI 12.0.2 and GLPI 11.0.8 in separate disposable databases with synthetic data and Mailpit. Passing unit tests is not a runtime compatibility claim. Historical screenshots, measurements, changelogs and published catalog ranges retain their actual versions. Documentation distinguishes the target matrix from tests actually executed.

Local CI remains the chosen execution model. Hooks are bypassable; local evidence is not a server-enforced status. Changing repository protections or introducing hosted execution requires separate authorization.
