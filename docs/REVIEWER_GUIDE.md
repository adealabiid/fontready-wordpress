# Reviewer guide

## Start here

FontReady combines a public font-conversion product with a WordPress installation plugin. This repository demonstrates the PHP/WordPress side: explicit administrator authorization, validated REST imports, resumable transfer and native Elementor persistence.

[Try the product](https://fontready.com/font-to-elementor-pro/) · [Read the architecture](ARCHITECTURE.md) · [Inspect the security boundary](SECURITY.md)

## A focused code walkthrough

| Question | Read | Evidence to inspect |
| --- | --- | --- |
| Who can approve publishing? | `FontReady_Plugin::connect()` | Nonce, confirmation, HTTPS and capabilities |
| Who can call the endpoint? | `FontReady_Plugin::authorize()` | Hash comparison, expiry, current capabilities and Origin |
| What is accepted before writes? | `fontready_validate_fonts()` | Metadata, count/size limits, duplicate rejection and format checks |
| How do interrupted uploads recover? | `FontReady_Transfer::receive()` | Recorded offsets, retry hashes, ordered pieces, checksum and receipt |
| How are existing fonts handled? | `FontReady_Elementor::sync()` and `retained_faces()` | Owned-family checks, variant merging and administrator edits |
| What happens on a caught write failure? | `FontReady_Plugin::import()` and adapter `rollback()` | Restored metadata and cleanup of new records/files |

## Concrete behavior in the tests

- Upload regular 400, then bold 700 with the same family: one native family, two weight rows, five format slots per row.
- Retry the bold upload: no duplicate weight row.
- Change or revoke the credential, expire the connection, remove capabilities or supply an untrusted Origin: authorization is rejected.
- Supply malformed font data or metadata: validation rejects the request.
- Interrupt or retry transfer pieces: the modeled transfer preserves ordering and checks package integrity.
- Fail the registry update: the modeled adapter restores prior metadata and removes newly created assets.

Run `python scripts/check.py` after installing PHP CLI and Python dependencies. The reviewed source passed 98 isolated contract checks on 8 October 2026. Read the test doubles alongside the assertions: they make the tested boundary explicit.

## How to assess the sample

Review product choices alongside implementation: no manual API-key setup, fonts managed in the native CMS interface, local asset ownership, and actionable recovery through the companion publishing flow.

Live WordPress/Elementor rendering, compatibility across vendor versions and real database concurrency remain separate validation work. See [the README limitations](../README.md#current-scope-and-limitations).

## Visual evidence

[Dashboard preview](dashboard-preview.png) is rendered from the actual template with isolated fixture data. It demonstrates layout and family/variant presentation; it does not demonstrate a live Elementor installation.

## Stable source reference

The implementation reviewed before this documentation update is [commit 755890e](https://github.com/adealabiid/fontready-wordpress/tree/755890e8f384813d23b58c23078f95a4a7cd601e). Use the documentation update's final commit URL for the application so a reviewer sees the complete presentation.

