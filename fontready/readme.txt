=== FontReady ===
Contributors: adealabiid
Tags: fonts, typography, elementor, custom fonts
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 0.4.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert fonts with FontReady and install them on your Elementor Pro website without copying connection keys.

== Description ==
FontReady connects your WordPress website to the font conversion service at https://fontready.com. Installing and activating the plugin enables font installation. Connection requires your administrator account, HTTPS, an active Elementor Pro installation, and confirmation of your website domain.

The dashboard has a connection view and a count of installed font families and variants, using FontReady's branding. Font files are hosted locally in uploads/fontready. They are published into Elementor Pro’s native Custom Fonts manager and registered in the WordPress media library. This plugin does not create records in the WordPress block editor Font Library.

FontReady is independent and is not affiliated with or endorsed by Elementor.

== Installation ==
1. Open https://fontready.com/font-to-elementor-pro/ and upload one font. Conversion starts automatically without format choices. You can also open a ready kit from FontReady’s main converter.
2. Choose Publish to Elementor Pro website and enter your website URL.
3. Install and activate Elementor Pro and FontReady if needed. Upload fontready-wordpress.zip in Plugins → Add New → Upload Plugin.
4. Confirm both plugins are installed and active, then publish.
5. Approve through your WordPress administrator account. The selected kit returns with you and publishing resumes.
6. Find your fonts in Elementor → Custom Fonts and refresh the Elementor editor to select them.

The companion website release must be deployed before the connection is available. Download kits expire 24 minutes after conversion. If installation takes longer, convert again.

== External service and privacy ==
Font conversion requires the FontReady service. Read https://fontready.com/privacy/ for conversion storage, statistics, website analytics and connection details before using the service.

The plugin makes no automatic outbound requests or telemetry on activation. On explicitly connecting, the browser shares your confirmed domain, installed font totals and successful installation reports with FontReady. Conversion dates, family names, variant counts and output formats are retained separately from expiring uploaded files for usage statistics.

The authentication credential is handled automatically, stored in browser tab session storage, and sent directly to WordPress. FontReady's server never receives it. WordPress stores its SHA-256 hash, authorizing administrator and expiry. A connection lasts seven days and can be revoked in the dashboard. A new connection revokes the previous one. Connect again if you close the tab or the session expires.

Fonts are sent directly from your browser to WordPress, never to Elementor's servers. Your fonts remain after the FontReady kit expires. Deactivation revokes access and stops legacy FontReady-group font loading. Native Elementor font records and their media assets are retained for Elementor to manage. Uninstalling keeps those files and registry to preserve your website assets; remove them manually only when they are no longer in use.

== Frequently Asked Questions ==
= Do I need a connection key or account? =
No copying, generating or pasting keys is required. Use your existing WordPress administrator login. No FontReady account is needed.

= Is Elementor Pro required? =
Yes. Activate Elementor Pro before connecting. Fonts are created in Elementor Custom Fonts using the installed Elementor font manager.

= Can I install any font? =
Only install fonts you have permission to use on your website. Upload TTF, OTF, WOFF or WOFF2. The service prepares WOFF2, WOFF, TTF, SVG and EOT together. These five files occupy one matching weight/style row in Elementor. Direct OTF REST imports are rejected.

== Limits and troubleshooting ==
The Elementor page accepts one uploaded font at a time. Ready kits may contain up to 10 variants, 5 MB per generated file and 25 MB total. All five output formats are installed together. Variable WOFF/WOFF2 ranges are preserved; legacy outline conversion may use a default static instance. Identical retries do not duplicate files. Matching family, weight and style replaces the existing variant.

HTTPS, REST API access and forwarding of the Authorization header are required. Server rules must allow OPTIONS, GET and POST to the FontReady REST routes. CORS allows only https://fontready.com. Both pretty and query-style WordPress REST routes work. PHP post_max_size, memory_limit and proxy limits must accommodate a base64 JSON payload of up to about 36 MB.

If an installation response is lost, check the installed count before retrying. Clear page caches and refresh Elementor after installing. If a terminated PHP process leaves a write lock, deactivate and reactivate the plugin when no imports are running.

== Changelog ==

= 0.4.3 =
* Correct base64 padding allowance for full 128 KiB upload pieces.

= 0.4.2 =
* Transfer font packages in small authenticated requests, verify the complete package, then install all formats together.
* Connected dashboard button now says Convert & publish fonts.

= 0.4.1 =
* Respect font variants removed in Elementor and preserve manually added variants.
* Report retained imports separately from the current publication.

= 0.4.0 =
* Publish WOFF2, WOFF, TTF, SVG and EOT into one native Elementor variant.
* Readable family/weight filenames; secure SVG and EOT validation.
* Automatic one-upload conversion flow and accurate variant totals.

= 0.3.1 =
* Correct the Elementor assets-manager lookup for native Custom Fonts publishing.
* Add optional contract tests against owner-supplied Elementor Pro source.
= 0.3.0 =
Native Elementor Custom Fonts records and media attachments; convert-first Elementor publishing page; selected-kit recovery through administrator approval; removal of the total stored-variant quota.

= 0.3.0 =
Seamless administrator redirect connection, Elementor Pro confirmation, redesigned branded dashboard, local font hosting and consent-based connection statistics.

= 0.1.0 =
Initial preview release.
