# FontReady for WordPress

Version 0.2.0 connects an Elementor Pro website through a WordPress administrator redirect. Users confirm their domain and Elementor Pro, then return to FontReady without copying credentials. The branded dashboard shows the connection and installed font count.

Install `fontready-wordpress.zip`, activate Elementor Pro and FontReady, and open https://fontready.com/font-to-elementor-pro/. Convert fonts after connecting and choose Install fonts. Refresh Elementor to select the family in the FontReady group. The companion website changes must be deployed first.

Authentication uses a nonce-protected administrator confirmation, a one-time website session state, a fixed service callback and a seven-day opaque credential. Only its hash is stored in WordPress. The browser strips the callback fragment before analytics loads and stores the credential in tab session storage; it is never sent to Django. Reconnecting revokes previous access. Disconnecting in WordPress revokes all access.

Font files are validated and saved locally, with transactional registry replacement, bounded payloads, serialized writes and duplicate-safe retries. No FontReady account is required. Elementor Pro is required; native Custom Fonts records are not created. See `fontready/readme.txt` for privacy, limits and lifecycle details.

## Validation

```sh
pip install -r requirements-dev.txt
python scripts/check.py
python scripts/build.py
```

The PHP contracts use real TTF, OTF, WOFF and WOFF2 fixtures with stubbed WordPress APIs. They are not live WordPress/Elementor compatibility tests. `integration/fontready.patch` contains the companion changes; the website repository is authoritative for the integration and analytics dashboard.

## Before WordPress.org submission

Run a live WordPress/Elementor Pro acceptance test: login redirect, confirmation, import, selector, preview, front-end rendering, caching, disconnect, expiry, subdirectory install and query-style REST routes. Run WordPress Plugin Check and review accessibility and translations. Record the tested WordPress and Elementor versions after those checks; none are claimed here. Submission and deployment are separate steps.

The plugin is GPL-2.0-or-later, includes its source and loads its own dashboard stylesheet locally. The service disclosure and explicit connection consent are in the plugin UI and readme. No telemetry runs on activation. No Elementor logo is included: its published rules require written permission.

Author: Ademola Alabi.
