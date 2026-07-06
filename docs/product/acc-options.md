---
title: "Account Centre Options"
audience: [implementer, support]
php_class: WicketAcc
db_option_prefix: acc_, wicket_admin_settings_, wicket_acc_options
---

# Account Centre Options

## Overview
The Account Centre (ACC) options are managed through a centralized settings page in the WordPress admin, powered by **HyperFields**. These settings control global behavior for individual profiles, organization management, and integrations.

## Organization Management Configuration

### Global Settings

#### MDP Affiliation Mode
Determines how organization affiliations are handled.
- **Direct**: Users are directly affiliated with organizations.
- **Cascade**: Users are connected through relationship mapping.
- **Group**: Users are managed through hierarchical groups.

#### Manager Permissions
Toggle capabilities for organization managers:
- Ability to add new relationships/members.
- Ability to assign security roles (e.g., Org Editor).
- Ability to remove members from the roster.

### Configuration Storage
- **Provider**: HyperFields (`estebanforge/hyperfields`).
- **Main options**: stored as a single array in the `wicket_acc_options` WP option (the 7 ACC-specific settings).
- **Environment fields**: stored in the shared `wicket_settings` array option, which `wicket-wp-base-plugin` also reads via `wicket_get_option()`. ACC only writes these as a fallback when the base plugin is absent.
- **Access**: via `WACC()->getOption($key, $default)`, which checks `wicket_acc_options` first, then `wicket_settings`. Note: when a key is absent from both, the caller-supplied `$default` is returned; HyperFields field-level defaults are NOT consulted on the read path, so callers must pass their own fallback.

## Technical Usage

### Fetching Settings
```php
// Get the affiliation mode
$mode = WACC()->getOption('mdp_affiliation_mode', 'direct');

// Check if roster management is enabled
$can_add_members = WACC()->getOption('acc_allow_manager_add_members', false);
```

### Contextual Filtering
Settings are often used to filter the behavior of blocks:
- **Org Roster Block**: Uses permissions to show/hide "Add Member" or "Remove" buttons.
- **Org Profile Block**: Uses settings to toggle specific fields (e.g., Alternate Name).

## Migration history
Settings moved through several storage layers over the plugin's lifetime. Two idempotent migrators run once on `admin_init` and copy values forward so no configuration is lost:

1. **ACF → HyperFields** (`AdminSettings::maybeMigrateOldACFOptions`, `admin_init` priority 10): legacy ACF options copied into `wicket_acc_options`. Skips keys already populated.
2. **Carbon Fields → HyperFields** (`AdminSettings\HFMigration`, `admin_init` priority 5): Carbon's per-field `wp_options` rows copied into the `wicket_acc_options` array. Runs first so Carbon-era values take precedence over older ACF values.

Both migrators leave the source rows in place (non-destructive) and are gated by completion flags.

| ACF Key | HyperFields Key |
|---------|----------------|
| `ac_localization` | `ac_localization` |
| `acc_sidebar_location` | `acc_sidebar_location` |
| `acc_profile_picture_size` | `acc_profile_picture_size` |
| `acc_global-headerbanner` | `acc_global-headerbanner` |
