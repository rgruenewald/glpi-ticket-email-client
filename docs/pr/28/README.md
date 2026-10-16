# PR 28 visual comparison

Actual browser screenshots from an isolated GLPI 11.0.8 installation using a fresh database, a synthetic ticket and the built-in demo account. No production data, SMTP delivery or existing application installation was used.

- Before: plugin commit `dd61009`.
- After: plugin commit `8239ac4`.
- Same database, user, ticket, settings, viewport (1920 x 1080), browser and capture procedure. Full-page screenshots may exceed viewport height.
- `before-actions.png` / `after-actions.png`: existing native action and plugin Reply versus the configurable connected action group.
- `before-settings.png` / `after-settings.png`: global settings before and after adding eight interface switches.

Images are unmodified browser captures. They demonstrate rendered appearance, not full behavioral or responsive acceptance. At 1440 px, an exploratory capture showed the enlarged action group partially clipped by the existing toolbar; this narrower-viewport limitation is not resolved by these screenshots. The action label also retains the current translation behavior (German reply label in the English interface).
