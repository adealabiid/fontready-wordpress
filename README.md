# FontReady WordPress plugin

Publish converted fonts from FontReady to your WordPress site without a FontReady account.

Version **0.1.0** is a first build. PHP contracts and website export checks pass; live WordPress/Elementor testing remains outstanding.

## Install and use

1. Upload `fontready-wordpress.zip` through WordPress → Plugins → Add New → Upload Plugin.
2. Activate FontReady and open WordPress → FontReady.
3. Generate a temporary key and copy the connection details.
4. Convert fonts at FontReady, choose Publish to WordPress, paste the details and publish.
5. Refresh Elementor and select the family under the FontReady font group.

Step 4 requires deployment of the companion website changes. Fonts remain on WordPress after FontReady's temporary kit expires. No FontReady account or WordPress password is required. The temporary key expires after one hour and can be revoked.

The plugin manages families separately from Elementor Pro's native Custom Fonts manager and the WordPress Font Library. Other themes can apply the family through CSS. Limits: 10 faces per import, 5 MB per font, 25 MB per batch and 100 stored variants. WOFF2 is preferred; WOFF, TTF and OTF are also accepted. See `fontready/readme.txt` for privacy, troubleshooting and lifecycle details.

## Repository contents

- `fontready/`: installable plugin source.
- `fontready-wordpress.zip`: packaged release.
- `scripts/build.py`: deterministic ZIP builder.
- `scripts/check.py` and `tests/plugin.php`: real font fixtures and isolated PHP contract checks.
- `integration/fontready.patch`: companion changes for `adealabiid/fontready`.
- `integration/check_wordpress_ui.cjs`: website publishing contracts; run from the FontReady application root.

## Checks and packaging

Requires Python 3.12 and PHP 7.4 or newer for local checks (development is checked on PHP 8.3):

```sh
pip install -r requirements-dev.txt
python scripts/check.py
python scripts/build.py
```

The WordPress host needs only the plugin ZIP, not Python or FontTools.

## Companion website integration

In a clean FontReady application checkout, inspect and apply `integration/fontready.patch`, then copy this repository's `fontready-wordpress.zip` to the application's `wordpress/fontready-wordpress.zip`. Run Django's checks and test suite, run the UI contract script, and regenerate the application's static preview. Deployment is a separate step.

The patch adds a session-owned, CSRF-protected font export and browser publishing UI. Connection keys go directly from the browser to WordPress, and are not sent to the FontReady server or saved in browser storage. The WordPress REST endpoint accepts browser requests from `https://fontready.com`.

## License

GPL-2.0-or-later. Author: Ademola Alabi.
