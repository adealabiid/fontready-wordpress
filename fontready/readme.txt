=== FontReady ===
Contributors: adealabiid
Tags: fonts, typography, elementor, custom fonts
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert fonts with FontReady and install them on your Elementor Pro website without copying connection keys.

== Description ==
FontReady connects your WordPress website to the font conversion service at https://fontready.com. Installing and activating the plugin enables font installation. Connection requires your administrator account, HTTPS, an active Elementor Pro installation, and confirmation of your website domain.

The dashboard has a connection view and a count of installed font families and variants, using FontReady's branding. Font files are hosted locally in uploads/fontready. They appear in Elementor's FontReady font group. This plugin does not create records in Elementor Pro's native Custom Fonts manager or the WordPress Font Library.

FontReady is independent and is not affiliated with or endorsed by Elementor.

== Installation ==
1. Upload fontready-wordpress.zip in Plugins → Add New → Upload Plugin and activate it.
2. Open https://fontready.com/font-to-elementor-pro/.
3. Enter your domain, confirm Elementor Pro, and continue to WordPress.
4. Sign in as an administrator and confirm the website. You return to FontReady automatically.
5. Convert your fonts and choose Install fonts.
6. Refresh Elementor, then select your family under Typography → Font Family → FontReady.

The companion website release must be deployed before the connection is available.

== External service and privacy ==
Font conversion requires the FontReady service. Read https://fontready.com/privacy/ for conversion storage, statistics, website analytics and connection details before using the service.

The plugin makes no automatic outbound requests or telemetry on activation. On explicitly connecting, the browser shares your confirmed domain, installed font totals and successful installation reports with FontReady. Conversion dates, family names, variant counts and output formats are retained separately from expiring uploaded files for usage statistics.

The authentication credential is handled automatically, stored in browser tab session storage, and sent directly to WordPress. FontReady's server never receives it. WordPress stores its SHA-256 hash, authorizing administrator and expiry. A connection lasts seven days and can be revoked in the dashboard. A new connection revokes the previous one. Connect again if you close the tab or the session expires.

Fonts are sent directly from your browser to WordPress, never to Elementor's servers. Your fonts remain after the FontReady kit expires. Deactivation revokes access and stops font loading, while preserving installed files and registry for reactivation. Uninstalling keeps those files and registry to preserve your website assets; remove them manually only when they are no longer in use.

== Frequently Asked Questions ==
= Do I need a connection key or account? =
No copying, generating or pasting keys is required. Use your existing WordPress administrator login. No FontReady account is needed.

= Is Elementor Pro required? =
Yes. Activate Elementor Pro before connecting. Families are registered in the FontReady group in its font selector.

= Can I install any font? =
Only install fonts you have permission to use on your website. WOFF2 is preferred. WOFF, TTF and OTF are also supported. SVG is not imported.

== Limits and troubleshooting ==
Up to 10 faces per import, 5 MB per face, 25 MB total and 100 stored variants. One preferred output per face is installed. Variable weight ranges are preserved. Identical retries do not duplicate files. Matching family, weight and style replaces the existing variant.

HTTPS, REST API access and forwarding of the Authorization header are required. Server rules must allow OPTIONS, GET and POST to the FontReady REST routes. CORS allows only https://fontready.com. Both pretty and query-style WordPress REST routes work. PHP post_max_size, memory_limit and proxy limits must accommodate a base64 JSON payload of up to about 36 MB.

If an installation response is lost, check the installed count before retrying. Clear page caches and refresh Elementor after installing. If a terminated PHP process leaves a write lock, deactivate and reactivate the plugin when no imports are running.

== Changelog ==
= 0.2.0 =
Seamless administrator redirect connection, Elementor Pro confirmation, redesigned branded dashboard, local font hosting and consent-based connection statistics.

= 0.1.0 =
Initial preview release.
