# Security, privacy and review scope

## Implemented controls

| Boundary | Control in source |
| --- | --- |
| Administrator approval | Nonce, explicit confirmation, HTTPS, Elementor Pro and capability checks |
| Publishing access | Random credential, stored SHA-256 hash, constant-time comparison, seven-day expiry and current user capability checks |
| Browser origin | Exact FontReady origin when supplied; scoped CORS headers; bearer authorization remains required |
| Metadata | Bounded family syntax, numeric weights/ranges, styles and format allowlists |
| Asset content | Strict base64 decoding; format signatures and selected structural checks; restricted SVG tags/attributes |
| Resource use | 5 MiB per asset, 25 MiB decoded batch, up to 10 variants / 50 entries, 36 MiB JSON package, 128 KiB upload pieces |
| Staging | Private local temporary file, mode 0600, connection-bound state, ordered pieces and SHA-256 verification |
| Persistence | Generated filenames, plugin-owned native records, option locks, rollback for caught failures |
| Revocation | Disconnect removes the connection and staged upload; deactivation also removes locks |

These controls do not constitute an independent security audit. Font-file checks do not fully parse every format. Users must hold embedding rights for uploaded fonts.

## Data handling

The plugin stores connection metadata, font metadata, file paths/URLs and native record references in WordPress. It cannot accurately be described as collecting or storing “no user information”: the connection contains the approving WordPress user ID.

The approval callback sends the site URL, REST endpoints, expiry, state and credential to the browser via a FontReady URL fragment. URL fragments are not part of normal HTTP request URLs, but browser-side scripts can read them. Companion website code and third-party scripts therefore remain part of the trust boundary.

The reviewed plugin source contains no analytics SDK or outbound telemetry client. The companion conversion service has its own data handling and policies; this document does not certify those systems.

Installed fonts are public web assets hosted by the WordPress site. Disconnecting stops publishing access; it does not uninstall published fonts. Manage those fonts through Elementor.

## Repository history review — 8 October 2026

Reviewed main head: `755890e8f384813d23b58c23078f95a4a7cd601e`.

- Enumerated all 19 commits returned for main, back to the initial commit.
- Retrieved and scanned all 95 unique historical text blobs, including the companion integration patch.
- Examined matches for common GitHub tokens, AWS access key IDs, Google API keys, private-key headers, credential assignments and credential-bearing URLs.
- Matches reviewed were fixed test passwords, deliberate `user:pass@site.example` rejection fixtures and cookie-name parsing.
- No actual credentials were identified by these patterns in the reviewed text.
- Author name/email are present in commit metadata and will be visible when the repository is public.

Scope excludes binary contents (including historical ZIPs/images), other branches/tags, GitHub settings and external/deployed services. This was a targeted pattern review, not a guarantee that the repository contains no secrets.

## Known operational limitations

- No lifetime storage quota is implemented.
- Option-based locks serialize modeled writes but abrupt termination may leave a lock behind.
- Rollback handles caught failures; this is not a database transaction.
- Elementor's internal interface can change.
- WordPress security middleware can block REST requests independently of the plugin.

