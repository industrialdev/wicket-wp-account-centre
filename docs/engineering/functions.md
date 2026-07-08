---
title: "Functions: WACC()"
audience: [developer, agent]
php_class: WicketAcc\WicketAcc
source_files: ["class-wicket-acc-main.php", "src/Helpers.php", "src/Profile.php", "src/ProfilePictureFallback.php", "src/Services/"]
---

# Global Helper: WACC()

## Overview

`WACC()` is the central singleton accessor for the Wicket Account Centre plugin. It returns the singleton instance of `WicketAcc` and exposes every registered service as a dynamic method.

## Usage

```php
// Accessing the Profile service
$profile_data = WACC()->Profile()->getUserProfileData();

// Accessing the MDP API integration
$token = WACC()->Mdp()->getToken();

// Accessing helper methods
$is_active = WACC()->Helpers()->is_account_active($user_id);

// Explicit profile-image delete flow
WACC()->Profile()->clear_profile_image($user_id);
```

## Available Services

| Service | Accessor | Purpose |
|---|---|---|
| **MDP API** | `WACC()->Mdp()` | Communication with the Member Data Platform. |
| **Profile** | `WACC()->Profile()` | User profile data and the explicit profile-image delete flow. |
| **Org Management** | `WACC()->OrganizationManagement()` | Org-level features (page injection, settings, etc.). |
| **Org Profile** | `WACC()->OrganizationProfile()` | Org profile fields and logo handling. |
| **Org Roster** | `WACC()->OrganizationRoster()` | Member/roster management facade. |
| **Blocks** | `WACC()->Blocks()` | ACF block registration and rendering. |
| **WooCommerce** | `WACC()->WooCommerce()` | WC endpoint and URL management. |
| **Router** | `WACC()->Router()` | URL routing, page IDs, template resolution. |
| **User** | `WACC()->User()` | User utility methods (metadata, roles, sync). |
| **Helpers** | `WACC()->Helpers()` | General utility functions (formatting, validation). |
| **Log** | `WACC()->Log()` | Centralized logging and error handling. |
| **Settings** | `WACC()->Settings()` | Plugin settings service. |
| **Notification** | `WACC()->Notification()` | Notification service. |
| **Language** | `WACC()->Language()` | Active language resolution (WPML/Polylang/site default). |
| **Shortcodes** | `WACC()->Shortcodes()` | Shortcode registration. |
| **Assets** | `WACC()->Assets()` | Asset enqueue helpers. |

## Coding Standards

- **Location**: Core logic lives in the `src/` directory inside the relevant service class.
- **Naming**: Use camelCase for methods (`getUserData`), snake_case for legacy global helpers only when unavoidable.
- **Strict typing**: All new helper methods use PHP 8.2+ strict typing for parameters and return values.
- **Security**: Helpers that modify state or fetch sensitive data must perform capability checks and nonce validation.

## Adding a New Helper

1. Identify the most relevant service class in `src/`.
2. Add the method with the appropriate visibility (usually `public`).
3. If no service fits, add it to `src/Helpers.php` or create a new service in `src/Services/`.

## Deprecation

Legacy functions in `includes/helpers.php` and `includes/legacy.php` are being phased out. Use them only when wrapping a new `WACC()` equivalent, and trigger `_doing_it_wrong()` or `WACC()->Log()->deprecated()` so the migration path is visible. See [deprecated-functions.md](deprecated-functions.md).

## OrgMan Singleton

The in-tree `WicketORM\` org-roster library uses its own singleton: `WicketORM\OrgMan::getInstance()`. It is **not** accessed through `WACC()`. It is booted by the plugin entrypoint on `after_setup_theme` priority 20 (see [plugin-entrypoint.md](plugin-entrypoint.md)).