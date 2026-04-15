---
title: "Account Centre Options"
audience: [implementer, support]
php_class: WicketAcc
db_option_prefix: acc_, mdp_affiliation_mode
---

# Account Centre Options

## Overview
The Account Centre (ACC) options are managed through a centralized settings page in the WordPress admin, powered by **Carbon Fields**. These settings control global behavior for individual profiles, organization management, and integrations.

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
- **Provider**: Carbon Fields (with ACF fallback for legacy fields).
- **Access**: Via `WACC()->getOption($key, $default)`.

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

## Migration from ACF
The plugin includes a migration routine in `AdminSettings::maybeMigrateOldACFOptions()` that moves legacy ACF options to the new Carbon Fields structure.

| ACF Key | Carbon Fields Key |
|---------|-------------------|
| `ac_localization` | `ac_localization` |
| `acc_sidebar_location` | `acc_sidebar_location` |
| `acc_profile_picture_size` | `acc_profile_picture_size` |
| `acc_global-headerbanner` | `acc_global-headerbanner` |
