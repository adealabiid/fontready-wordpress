# FontReady WordPress.org submission plan

Status: submission preparation. Not submitted. Elementor Pro 4.3.1 source contracts pass against the owner-supplied package, including manager lookup, metadata and CSS generation with WordPress APIs stubbed. Live Elementor rendering remains unverified by automated browser testing. Official Plugin Check now runs in `.github/workflows/wordpress-directory.yml`, including runtime checks on WordPress 6.8.3 / PHP 8.3. Check the latest successful run before uploading.

## 1. Finish the release gates

- Test the exact release ZIP on an HTTPS staging site with licensed Elementor Pro. Record WordPress, PHP, Elementor and Elementor Pro versions. Test the screenshot’s native Custom Fonts list, variation count, preview, editor selection and front-end rendering.
- Test the complete browser journey: conversion first; carry a kit from the main converter; download and install the plugin while keeping that kit; approve after login; return with the same kit; publish once; open Custom Fonts.
- Test repeat publishing, multiple weights/styles, all five formats in one native row from TTF/OTF/WOFF/WOFF2 inputs, readable filenames and migration from older single-format records, failed imports, existing-name collisions, deleted/draft/trashed fonts, REST query routes, subdirectory sites, expired kits and disconnected access.
- Specifically inspect current native variable-font metadata and editor behavior. Do not claim native variable-font support until verified. The adapter currently uses the custom/static manager interface and retains weight ranges in its variant data.
- Test deactivation, reactivation and uninstall. Native font records and media assets are retained; confirm that Elementor continues rendering them independently of FontReady.
- Review all Plugin Check findings, escaping, translations, accessibility, file-write paths and dependency behavior. Resolve errors and record justified warnings.
- Service terms and privacy are at https://fontready.com/terms/ and https://fontready.com/privacy/. Verify the deployed URLs before submitting. Check the final readme’s service disclosure against actual behavior.

## 2. Prepare the submission package

Use the GPL-compatible, readable plugin source and its release ZIP. Keep the plugin name FontReady; confirm slug availability and the submitting WordPress.org account before upload. Keep required Elementor Pro clearly disclosed without impersonating Elementor. No Elementor logo is included.

Validate `readme.txt`. Match its Stable tag with the PHP header version. Tested up to refers to the CI WordPress 6.8.3 activation and Plugin Check environment, not full live Elementor rendering. Prepare actual screenshots of the dashboard, convert-and-publish flow and native Custom Fonts result. Add release notes and a support contact the owner monitors. Keep service consent explicit, and do not add tracking on activation or total stored-font quotas.

## 3. Run Plugin Check

Install the official Plugin Check plugin in staging. Use Tools → Plugin Check, or WP-CLI. Static checks alone are insufficient for the complete journey. Run runtime checks too, using the currently documented CLI setup. Export results and attach the test record to the release review. Plugin Check does not replace manual review.

## 4. Submit and respond to review

Sign in with the chosen WordPress.org account and upload the final ZIP for review. Monitor the account email and respond to requested changes. No submission has been made here. The submitting WordPress.org username must be confirmed; no guessed contributor account is included.

After approval, publish the plugin through the assigned SVN repository. GitHub remains the development repository. Keep directory releases synchronized with reviewed versions, and verify the ZIP served on FontReady matches the released source.

## Official references reviewed

- Developer entry and submission sequence: https://wordpress.org/plugins/developers/
- Detailed guidelines, including licensing, service disclosure, consent, quotas and completed plugins: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Readme standard: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- Plugin Check and runtime checking: https://wordpress.org/plugins/plugin-check/
- Developer FAQ: https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/

## Filesystem review notes

Private staging uses local streams, recorded seek offsets and owner-only permissions. FTP/SSH WP_Filesystem transports cannot implement this offset protocol. The corresponding PHPCS exceptions are scoped to the exact local calls and are documented in source. Font asset chmod is also local because native font media is written locally. These exceptions are presented for human review, not blanket exclusions. GET pairing values only populate a confirmation form; mutations require the administrator capability and fontready_manage nonce.

No acceptance, approval date or review duration is promised.
