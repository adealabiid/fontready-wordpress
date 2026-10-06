# FontReady submission copy

## Intro

Your fonts. Ready for Elementor.

FontReady takes your font from upload to website. Convert a TTF, OTF, WOFF or WOFF2 font, preview your typography, and publish WOFF2, WOFF, TTF, SVG and EOT together into Elementor Pro Custom Fonts. Approve through your WordPress administrator account, then keep creating — no FontReady account or connection keys to copy. Installed fonts stay hosted on your own website.

## Additional Information

FontReady is developed by Ademola Alabi and connects WordPress to the font conversion service at https://fontready.com. Conversion runs on the service; the plugin validates and installs the resulting font files locally in Elementor Pro Custom Fonts. Elementor Pro is required and is not bundled. FontReady is independent of Elementor.

The complete workflow is: upload a font on FontReady, confirm the website URL and that Elementor Pro and FontReady are active, approve publishing in WordPress, then return to publish the prepared font. No FontReady account is required. Five output formats are grouped in one weight/style variant rather than creating five separate font families.

The plugin performs no outbound telemetry on activation. Approval discloses sharing the domain, installed font totals and successful publishing reports. Publishing credentials stay in the browser tab; WordPress stores a hash and expiry. Access can be revoked in the plugin dashboard. Only font assets are transferred; no executable code is downloaded from the service. There is no total installed-font quota or paid feature gate in the plugin.

Plugin Check passed on WordPress 7.1.3 / PHP 8.3. Local staging stream and permission operations have narrowly scoped PHPCS exceptions explained in the source; seeked writes support idempotent upload-piece retries. GET pairing values populate a confirmation form; connecting requires a capability check and nonce-verified POST.

Privacy: https://fontready.com/privacy/
Service terms: https://fontready.com/terms/

## Before pasting

Confirm the service terms URL is deployed and accessible. This check does not certify full live Elementor rendering or WordPress.org approval. Use the WordPress.org account that represents the owner; no WordPress.org contributor username has been guessed. Directory icons are kept in the repository's top-level assets folder for the assigned SVN repository after approval, outside the installable plugin ZIP.
