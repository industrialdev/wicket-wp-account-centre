---
title: "AC Profile Picture Block"
audience: [developer, agent, implementer]
php_class: WicketAcc\Blocks\ProfilePicture\init
source_files: ["includes/blocks/ac-profile-picture/init.php", "includes/blocks/ac-profile-picture/render.php", "includes/blocks/ac-profile-picture/block.json", "src/Profile.php", "src/ProfilePictureFallback.php", "src/ProfileImageMdpFieldSettings.php", "src/Mdp/Schema.php", "src/Helpers.php"]
---

# AC Profile Picture Block

## Overview

The Profile Picture block manages user profile image uploads, the MDP-backed avatar fallback chain, and the explicit delete flow that wipes the image from both local storage and the MDP person record.

## Block Architecture

### Directory Structure

```
includes/blocks/ac-profile-picture/
├── block.json         # Block registration
├── init.php           # Block initialization (WicketAcc\Blocks\ProfilePicture\init)
├── render.php         # Template renderer
└── block-styles.css   # Profile picture styles
```

The block extends `WicketAcc\Blocks` (see [base-block.md](base-block.md)).

## Core Functionality

### Image Source Resolution

The block reads the current picture from the MDP, with a deterministic fallback chain:

1. Direct `person.profile_picture` URL from the current person payload.
2. ACF/option attachment lookup (`getAttachmentUrlFromOption`) — used when the MDP does not carry a profile picture.
3. A 404 fallback returns the configured default URL when neither source resolves to a working image (`src/ProfilePictureFallback.php`).

The profile picture schema is identified by slug (`profile-picture`), not by tenant UUID, so the same schema name works across every site (`src/Profile.php`, `src/InitOptions.php`).

### File Management

- Configurable upload path: `WICKET_ACC_UPLOADS_PATH . 'profile-pictures/'`
- Supported extensions: `jpg`, `jpeg`, `png`, `gif`
- Maximum file size: configurable per block, validated in the upload handler
- Automatic file cleanup on updates (old file replaced, not orphaned)
- Filename normalization so the same upload twice does not leave dead copies

### Delete Flow

There is an explicit, fail-closed delete flow (`Wicket()->profile()->clear_profile_image()` and the corresponding `profile-image delete` route in the block):

1. Local file deleted from uploads directory.
2. `wicket_delete_profile_image()` posts the delete to the MDP via the Wicket helper layer.
3. The call is skipped entirely if the user has no current profile image, so the absence of an image does not produce a 404-ish failure on the MDP.

The delete flow is wired into `docs/engineering/hooks.md` (`profile-image delete` action hook) so plugins or themes can react to a profile-picture removal.

## MDP Profile Image Sync

After a successful upload, the block writes the new image URL into one MDP
additional_info field, so downstream systems (such as Higher Logic) can read it.
The target field is configurable per site, because the field slug is not the
same on every tenant (OBA uses `profprofile.memberphotourl`; others use
`personal_info.photo_link`).

### Setting

A single ACC Option, `acc_profile_picture_mdp_field_ref` (stored in the
`wicket_acc_options` array), holds a composite ref: `{schema_slug}|{field_slug}`.
The default is empty, which means **No syncing**: the upload stays local to
WordPress and the MDP call is skipped entirely (`Profile::syncProfileImageToMdp()`
short-circuits and returns true).

The setting renders as a grouped `<select>` in **ACC Options** (one optgroup per
MDP widget schema, field slugs inside). The list is sourced dynamically from the
tenant `json_schemas` endpoint (`WicketAcc\Mdp\Schema::getProfileImageFieldOptions()`),
cached in a transient, and refreshed on demand by a **Refresh fields** button that
calls `POST /wicket-acc/v1/profile-image-mdp-fields/refresh`.

| | |
|---|---|
| Option key | `acc_profile_picture_mdp_field_ref` |
| Storage | composite `{schema}\|{field}`, or empty for No syncing |
| REST refresh | `POST /wicket-acc/v1/profile-image-mdp-fields/refresh` (`manage_options`) |
| Owner class | `WicketAcc\ProfileImageMdpFieldSettings` |

### Validation and drift

The ref is validated at **settings-save time** only (`sanitizeField`), never on
the upload path. If the selected field is later removed from the MDP, the
previous value is kept (a warning is logged); the upload path reads the stored
ref as a plain string and never queries schemas.

### Migration

A one-time migration (`ProfileImageMdpFieldSettings::maybeMigrateLegacyRef()`,
guarded by the `wicket_acc_mdp_field_ref_migrated` flag) reads the legacy free-text
pair `acc_profile_picture_mdp_schema` / `acc_profile_picture_mdp_field` and writes
the composite ref. An empty legacy pair is a valid terminal state (No syncing).

### Form Handling

- Secure nonce verification
- File upload processing through the base-block ajax endpoint
- Remove-picture form action invokes the explicit delete flow
- Error handling and feedback surfaced through `error_message` / `error_type` block props

## Recent Changes

- Added the configurable MDP profile image field picker (ACC Option `acc_profile_picture_mdp_field_ref`). Default is No syncing (local only). Replaces the two legacy free-text slug options.
- Migrated the legacy slug pair into the composite ref once on upgrade.
- Identified the profile picture schema by slug instead of tenant-specific UUID.
- Fixed default redirect and double-slash URL bugs in `src/Helpers.php` and `src/Profile.php`.
- Added a 404 fallback path that returns the configured default when no file exists.
- Removed the legacy ACF fallback from `getAttachmentUrlFromOption` (the MDP-first chain is now the source of truth).
- Skip the MDP profile-image clear when no image exists.
- Added the explicit `profile-image delete` flow documented above.

## Related Guides

- [Change Your Profile Picture](../guides/change-profile-picture.md) — end-user guide for uploading, updating, or removing the photo.