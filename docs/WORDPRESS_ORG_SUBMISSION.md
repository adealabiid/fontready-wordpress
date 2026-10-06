# FontReady WordPress.org submission plan

Status: planning and development. Not submitted. Native Elementor compatibility and Plugin Check results are not yet verified on a live WordPress installation.

## 1. Finish the release gates

- Test the exact release ZIP on an HTTPS staging site with licensed Elementor Pro. Record WordPress, PHP, Elementor and Elementor Pro versions. Test the screenshot’s native Custom Fonts list, variation count, preview, editor selection and front-end rendering.
- Test the complete browser journey: conversion first; carry a kit from the main converter; download and install the plugin while keeping that kit; approve after login; return with the same kit; publish once; open Custom Fonts.
- Test repeat publishing, multiple weights/styles, OTF-to-WOFF2 wrapping, failed imports, existing-name collisions, deleted/draft/trashed fonts, REST query routes, subdirectory sites, expired kits and disconnected access.
- Specifically inspect current native variable-font metadata and editor behavior. Do not claim native variable-font support until verified. The adapter currently uses the custom/static manager interface and retains weight ranges in its variant data.
- Test deactivation, reactivation and uninstall. Native font records and media assets are retained; confirm that Elementor continues rendering them independently of FontReady.
- Review all Plugin Check findings, escaping, translations, accessibility, file-write paths and dependency behavior. Resolve errors and record justified warnings.
- Supply owner-approved service terms alongside the privacy notice. Verify both URLs are public before submitting. Check the final readme’s service disclosure against actual behavior.

## 2. Prepare the submission package

Use the GPL-compatible, readable plugin source and its release ZIP. Keep the plugin name FontReady; confirm slug availability and the submitting WordPress.org account before upload. Keep required Elementor Pro clearly disclosed without impersonating Elementor. No Elementor logo is included.

Validate `readme.txt`. Match its Stable tag with the PHP header version. Add Tested up to only after the recorded tests. Prepare actual screenshots of the dashboard, convert-and-publish flow and native Custom Fonts result. Add release notes and a support contact the owner monitors. Keep service consent explicit, and do not add tracking on activation or total stored-font quotas.

## 3. Run Plugin Check

Install the official Plugin Check plugin in staging. Use Tools → Plugin Check, or WP-CLI. Static checks alone are insufficient for the complete journey. Run runtime checks too, using the currently documented CLI setup. Export results and attach the test record to the release review. Plugin Check does not replace manual review.

## 4. Submit and respond to review

Sign in with the chosen WordPress.org account and upload the final ZIP for review. Monitor the account email and respond to requested changes. Submission is a separate owner-approved action; no submission has been made here.

After approval, publish the plugin through the assigned SVN repository. GitHub remains the development repository. Keep directory releases synchronized with reviewed versions, and verify the ZIP served on FontReady matches the released source.

## Official references reviewed

- Developer entry and submission sequence: https://wordpress.org/plugins/developers/
- Detailed guidelines, including licensing, service disclosure, consent, quotas and completed plugins: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Readme standard: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- Plugin Check and runtime checking: https://wordpress.org/plugins/plugin-check/
- Developer FAQ: https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/

No acceptance, approval date or review duration is promised.
