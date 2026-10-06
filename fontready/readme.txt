=== FontReady ===
Contributors: adealabiid
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish converted fonts from FontReady to WordPress without a FontReady account.

== Description ==
FontReady receives font files from your browser and stores them in your site's uploads/fontready directory. Families appear in Elementor's FontReady font group. Other themes can use the generated font-family via CSS. Fonts are managed in WordPress → FontReady; this plugin does not create entries in Elementor Pro's native Custom Fonts manager or the WordPress block editor Font Library.

== Installation ==
1. Upload fontready-wordpress.zip in Plugins → Add New → Upload Plugin.
2. Activate FontReady and open its dashboard menu.
3. Generate a temporary connection key and copy the displayed JSON connection details.
4. Convert your fonts at https://fontready.com. WOFF2 is preferred.
5. Paste the connection details in Publish to WordPress, confirm font usage rights, and publish.
6. Refresh the Elementor editor, then select your family under Typography → Font Family → FontReady.

The website publishing feature must be deployed before step 5 is available.

== Privacy and service use ==
No FontReady account is required. Conversion takes place at https://fontready.com under its temporary-file policy. Publishing sends the converted bytes directly from your browser to the HTTPS WordPress endpoint you select; the connection key is not sent to FontReady's server or saved in browser storage. Your WordPress site stores only a SHA-256 hash of the key, its owner and expiry. Keys expire after one hour and can be revoked. Fonts remain on WordPress after the original FontReady kit expires.

The plugin itself makes no outbound requests to FontReady, provides no telemetry, and does not send fonts to Elementor's servers. Deactivating the plugin revokes the connection and stops font loading, but preserves files and the font registry for reactivation. Removal through the plugin deletes that family's files. Uninstalling keeps the files and registry so deletion does not break a later reinstall unexpectedly.

== Limits ==
WOFF2, WOFF, TTF and OTF only. No SVG or ZIP extraction on WordPress. Up to 10 faces per import, 5 MB per face, 25 MB total and 100 stored variants. One preferred output per face is imported. Variable weight ranges are preserved in CSS. The same family/weight/style updates the existing variant; identical retries do not add duplicates. Conflicting faces in one batch are rejected.

== Troubleshooting ==
Use HTTPS in both WordPress URL settings. Security plugins and server rules must permit OPTIONS/POST to /wp-json/fontready/v1/fonts and forward Authorization. CORS permits only https://fontready.com. Default WordPress query-style REST endpoints are supported. PHP post_max_size, memory_limit and any proxy body limit must accommodate a base64 JSON import (up to about 36 MB). Try fewer fonts if the host's limits are lower.

If the browser cannot confirm the result, inspect the plugin's published fonts before retrying. Page caching may need clearing. Generate a new key after expiry or revocation. If a terminated PHP process leaves a change lock, deactivate and reactivate the plugin when no imports are running.

== Changelog ==
= 0.1.0 =
Initial preview release: temporary connections, validated imports, local font hosting, family management and Elementor font selector registration.
