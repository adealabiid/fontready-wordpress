# FontReady for Elementor Pro

Version 0.4.4 publishes families into Elementor Pro’s native Custom Fonts manager, with font variants registered as WordPress media attachments. The FontReady dashboard shows connection status and installed font counts.

## User flow

1. Convert on `/font-to-elementor-pro/`, or open a ready kit there from the main converter.
2. Choose Publish to Elementor Pro website and enter the URL.
3. Install and activate Elementor Pro and FontReady; confirm both.
4. Approve publishing as a WordPress administrator. The selected batch returns with the browser and publishing resumes.
5. Open Elementor → Custom Fonts. Refresh the editor to select the family.

No FontReady account or manual connection keys. The nonce-protected WordPress approval remains necessary to authorize installation. Opaque credentials stay in tab session storage and are never sent to FontReady’s server. Selected kits are session-owned and expire after 24 minutes. The new plugin is required for native publishing; the website rejects the older group-only connector.

## Native adapter

`includes/elementor.php` obtains the installed Elementor assets manager and its custom-font type. It calls that object’s `save_meta` method to generate native metadata and CSS rather than copying Elementor implementation code. WordPress APIs create font posts, media records and taxonomy terms. It refuses to overwrite fonts created outside FontReady, registers retries without duplicate families, restores prior metadata after a failed registry write, and invalidates font-manager option caches. Existing draft or trashed families require a decision in Elementor first.

The adapter relies on Elementor’s internal PHP interface. Compatibility with a live Elementor Pro installation is **unverified**. The standard tests use an isolated manager double. An optional source contract loads the supplied Elementor Pro 4.3.1 assets module, font manager, sanitizer and Custom Fonts implementation; it verifies native metadata and CSS with WordPress APIs stubbed. This does not establish live rendering or native variable-font editing support. Run the optional contract with `ELEMENTOR_PRO_SOURCE=/absolute/path/to/elementor-pro python scripts/check.py`. Vendor source is not included in this repository or release ZIP. Live testing is a release gate. The website accepts TTF, OTF, WOFF and WOFF2 and prepares all five Elementor formats. Resource limits apply per request; there is no total stored-font quota.

## Validation

```sh
pip install -r requirements-dev.txt
python scripts/check.py
python scripts/build.py
```

See [the submission plan](docs/WORDPRESS_ORG_SUBMISSION.md). Do not claim a Tested up to version until it has been tested. The website repository is authoritative for the companion flow; `integration/fontready.patch` is a review reference. GPL-2.0-or-later. Author: Ademola Alabi.

Version 0.4.0 installs WOFF2, WOFF, TTF, SVG and EOT together in one native weight/style row. Readable filenames include family, weight, optional style and a short FontReady suffix. Earlier single-format records migrate on publishing. The companion Elementor page starts conversion on upload without format choices and automatically resumes publishing after approval.

The platform owner dashboard is https://fontready.com/owner/. It uses a separate FontReady superuser login, provisioned in the deployed website service with `python manage.py createsuperuser`; see the website README for production setup.

Version 0.4.1 respects variant and format deletions in Elementor, preserves administrator-added variants, and reports how many previously imported variants remain alongside the current upload. Earlier imports are kept until the administrator removes them in Elementor Custom Fonts; fonts are never fabricated from one static upload.

Version 0.4.2 sends the complete JSON package as authenticated upload pieces of at most 128 KiB (base64 request bodies below 256 KiB). WordPress stages one upload per connection in a private system temporary file, verifies SHA-256, then runs the existing atomic five-format importer. Pieces and commit receipts are idempotent. Pending uploads expire after ten minutes, with cleanup on the next request or WordPress cron; disconnect/deactivation removes staged data. A system temporary directory outside the WordPress installation must be writable. The connected dashboard links to conversion rather than asking to connect again.

Version 0.4.3 corrects the base64 length boundary for full 128 KiB upload pieces. The transfer contract now uses the actual production chunk size and a package larger than one chunk.

Version 0.4.4 prepares directory submission metadata, uses a fixed allowlisted safe redirect and checks file permission failures. Official Plugin Check is run in CI against WordPress 7.1.3. Local staging stream operations have narrowly scoped documented exceptions because WordPress filesystem transports do not support seeked chunk writes.
