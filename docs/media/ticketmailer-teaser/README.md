# Ticketmailer teaser: local pilot

## Provenance and scope

- Plugin source commit: `20df4fa4f8efb563ad80a06977a1e145934f92a4` (README changes uncommitted).
- Runtime: documented disposable local GLPI 11.0.8 + Mailpit stack, plugin 2.4.0.
- Capture: Chromium from the repository's pinned Playwright, English, 1440 × 1080, device scale 1.
- Fixture: repository `docker/glpi/e2e-fixture.php`, synthetic ticket and example.test recipients only. Local public test login, no production credentials.
- Screenshot: direct compose fallback, email mode, subject and rich-text body filled; cropped to the composer (x=250, y=70, width=1180, height=930). No UI elements fabricated or changed.
- Output: 16 seconds, 1920 × 1080, 24 fps, silent H.264 MP4. Screenshot fades and editorial captions, not a recording of a send or runtime proof of delivery. No email was sent in this capture.
- Review: current UI and three exported scene frames inspected for clipping, legibility and privacy. Only synthetic recipients/message and ticket marker are visible.

## HyperFrames pilot

Official source: https://github.com/heygen-com/hyperframes and its skills under `skills/hyperframes`, `skills/hyperframes-cli` and `skills/hyperframes-core`. The homepage's singular hyperframe GitHub link returned 404; the plural repository is the working source.

Verified npm package: **hyperframes 0.8.177**, Apache-2.0. Local rendering/export confirmed; Node 22+ and FFmpeg required. GSAP **3.14.2** is the animation dependency, subject to its own upstream license. Neither dependency is bundled in this repository. No cloud renderer, paid API, account, voice service, or external asset upload was used. Anonymous CLI telemetry was disabled before rendering. Catalog word search was inspected; no fitting restrained screenshot-and-caption preset was selected. No optional search model downloaded.

## Reproduce

From the repository root, start and prepare the documented disposable environment using `scripts/dev up` and `scripts/dev prepare`. Ensure ticketmailer is installed and enabled per the installation guide. Do not use a shared or production instance. Capture creates a new synthetic ticket.

```bash
node docs/media/ticketmailer-teaser/capture.cjs
mkdir -p /tmp/ticketmailer-hyperframes-render
cd /tmp/ticketmailer-hyperframes-render
npm init -y
npm install --save-exact hyperframes@0.8.177 gsap@3.14.2
npx --no-install hyperframes telemetry disable
```

Copy this directory's `index.html` into that temporary project, copy `assets/readme/email-compose-current.png` from the repository as `compose.png`, and copy `node_modules/gsap/dist/gsap.min.js` to `gsap.min.js`. Then run:

```bash
npx --no-install hyperframes lint
npx --no-install hyperframes render . --output ticketmailer-teaser.mp4 --fps 24
```

The source composition is seekable via the HyperFrames GSAP timeline contract. The README embeds an animated WebP derived from the MP4 so the overview is visible directly on the GitHub repository homepage. A separate MP4 link preserves the original video. This does not rely on unsupported HTML video embedding, promise autoplay of a video player, or require an external upload. No public upload or release publication performed.

## README hero preview

Convert the existing MP4 locally with FFmpeg (verified with 8.0.1):

```bash
ffmpeg -hide_banner -loglevel error \
  -i assets/readme/ticketmailer-teaser.mp4 \
  -vf "fps=12,scale=960:-1:flags=lanczos" \
  -c:v libwebp_anim -lossless 0 -quality 70 -loop 0 -an \
  assets/readme/ticketmailer-teaser.webp
```

The preview preserves the full 16-second composition at 960 × 540 and 12 fps, loops without sound, and is about 281 KiB. It is a presentation asset, not new runtime or delivery evidence. To pause the animation, use browser image controls if available or open the MP4 separately.
