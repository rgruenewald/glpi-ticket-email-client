# PR 28 visual comparison

Actual browser screenshots from an isolated GLPI 11.0.8 installation using a fresh database, a synthetic ticket and the built-in demo account. No production data or SMTP delivery was used.

- Before: plugin commit `dd61009`, 1920 x 1080 viewport.
- After: UI revision following `8122857`, captured at 1440 x 1080 to verify the narrower toolbar. The viewport difference is intentional and disclosed.
- Same synthetic ticket, database and user. All interface options enabled.
- `before-actions.png` / `after-actions.png`: native Answer plus standalone plugin Reply versus the revised native-style connected group without duplicate Answer.
- `before-settings.png` / `after-settings.png`: flat settings versus grouped Ticket actions, Email dialog and Behavior switches.
- `after-actions-de.png` / `after-settings-de.png`: the revised interface in German.

Images are unmodified full-page browser captures. Native approval actions remain available and are not plugin replacements. Browser probes checked translated plugin labels and native Task/Document form opening in English and German. This does not prove send behavior, all profiles, all themes or all viewport sizes.
