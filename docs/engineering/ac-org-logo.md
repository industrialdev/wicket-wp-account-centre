---
title: "AC Org Logo Block"
audience: [developer, agent, implementer]
php_class: WicketAcc\Blocks\OrgLogo\init
source_files: ["includes/blocks/ac-org-logo/init.php", "includes/blocks/ac-org-logo/render.php", "includes/blocks/ac-org-logo/block.json", "src/OrganizationProfile.php", "src/Helpers.php"]
---

# AC Org Logo Block

## Overview

The Org Logo block manages an organization's logo: upload, storage, and the 404 fallback chain that mirrors the profile-picture fallback. It mirrors the profile-picture block structure but writes to the `organization-logos/` uploads directory.

## Block Architecture

### Directory Structure

```
includes/blocks/ac-org-logo/
├── block.json         # Block registration
├── init.php           # WicketAcc\Blocks\OrgLogo\init
└── render.php         # Template renderer
```

Extends `WicketAcc\Blocks` (see [base-block.md](base-block.md)).

## Core Functionality

### Image Source Resolution

The block resolves the current logo URL with the same fallback chain used for profile pictures:

1. Direct organization attribute (`organization.logo_url` from the current org payload).
2. ACF / option attachment lookup (`getAttachmentUrlFromOption`).
3. 404 fallback returns the configured default URL when neither source resolves to a working image (`src/ProfilePictureFallback.php`).

### File Management

- Upload path: `WICKET_ACC_UPLOADS_PATH . 'organization-logos/'`
- Upload URL: `WICKET_ACC_UPLOADS_URL . 'organization-logos/'`
- Supported extensions: `jpg`, `jpeg`, `png`, `gif`, `webp`
- Max size: configured per site via the `acc_profile_picture_size` option (default `1` MB, minimum `1` MB)

### Org Resolution

- Reads the org UUID from `?org_uuid=` or `?org_id=`.
- Falls back to the user's `org_editor`-bound orgs when the URL is empty (handled the same way as `ac-org-profile`).

### Form Handling

- Nonce verification
- File upload processing through the base-block ajax endpoint
- Error handling and feedback surfaced through `error_message` / `error_type` block props

## Recent Changes

- 404 fallback added: when neither the org payload nor the ACF option resolves to a working image, the configured default logo URL is rendered (`src/ProfilePictureFallback.php`).

## Related Documentation

- [Base Block](base-block.md)
- [Profile Picture Block](ac-profile-picture.md) — same fallback chain, applied to persons.