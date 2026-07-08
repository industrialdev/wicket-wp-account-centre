---
title: "Plugin Entrypoint"
audience: [developer, agent]
php_class: WicketAcc\WicketAcc
source_files: ["class-wicket-acc-main.php", "src/WicketAcc.php", "src/Log.php"]
---

# ACC Plugin Entrypoint

## Overview

`class-wicket-acc-main.php` is the primary entry point for the `wicket-wp-account-centre` plugin. It bootstraps the `WicketAcc` service locator and boots the in-tree `WicketORM\OrgMan` orchestrator.

## Technical Requirements

- PHP 8.2+
- WordPress 6.6+
- Required plugins:
  - `wicket-wp-base-plugin`
  - `advanced-custom-fields-pro`

## Core Constants

The plugin defines several constants for path and URL management:

- `WICKET_ACC_VERSION` — current plugin version.
- `WICKET_ACC_PATH` / `WICKET_ACC_URL` — filesystem path and public URL.
- `WICKET_ACC_UPLOADS_PATH` / `WICKET_ACC_UPLOADS_URL` — path and URL for plugin-specific uploads.
- `WICKET_ACC_PLUGIN_TEMPLATE_PATH` — path to internal templates.
- `WICKET_ACC_USER_TEMPLATE_PATH` — path for theme-level template overrides.

## Initialization Flow

1. **Composer autoloader** loads dependencies from `vendor/`.
2. **Fatal error handler** is registered immediately via `Log::registerFatalErrorHandler()`.
3. **`plugins_loaded` (priority 10)** fires `WicketAcc::get_instance()->plugin_setup()`. This is the actual setup hook.
4. **`after_setup_theme` (priority 20)** boots `WicketORM\OrgMan` (`OrgMan::getInstance()`) when the class is available. The deferred priority lets theme-level `wicket/org-roster/config` filters register before `OrgMan` reads the config.

```php
use WicketORM\OrgMan;

// Inside plugin_setup()
add_action(
    'after_setup_theme',
    static function (): void {
        if (class_exists(OrgMan::class)) {
            OrgMan::getInstance();
        }
    },
    20,
);
```

`OrgMan::getInstance()` is gated by a defer-guard that yields to the standalone WicketORM plugin if it is also active. See [BACKWARDS-COMPATIBILITY.md](../ORM/engineering/BACKWARDS-COMPATIBILITY.md).

## Service Access (`WACC()`)

`WicketAcc` is a magic-method service locator. Access components through the `WACC()` helper:

| Method | Service | Purpose |
|---|---|---|
| `WACC()->Mdp()` | `Mdp\Init` | Core MDP API integration |
| `WACC()->Profile()` | `Profile` | User profile data and logic |
| `WACC()->OrganizationManagement()` | `OrganizationManagement` | Org management features |
| `WACC()->OrganizationProfile()` | `OrganizationProfile` | Org profile fields + logo |
| `WACC()->OrganizationRoster()` | `OrganizationRoster` | Org roster facade |
| `WACC()->Blocks()` | `Blocks` | ACF block registration and rendering |
| `WACC()->User()` | `User` | User utility methods |
| `WACC()->WooCommerce()` | `WooCommerce` | WC endpoint customization |
| `WACC()->Router()` | `Router` | Template routing and redirects |
| `WACC()->Shortcodes()` | `Shortcodes` | Shortcode registration |
| `WACC()->Settings()` | `Settings` | Plugin settings service |
| `WACC()->Notification()` | `Notification` | Notification service |
| `WACC()->Language()` | `Language` | Active language resolution (WPML/Polylang/site default) |
| `WACC()->Helpers()` | `Helpers` | Internal helpers facade |
| `WACC()->Assets()` | `Assets` | Asset enqueue helpers |

## Pages And Endpoints

`WicketAcc` registers multilingual slugs for the Account Centre and WooCommerce:

- **Account Index**: `my-account` (EN), `mon-compte` (FR), `mi-cuenta` (ES).
- **WooCommerce endpoints**: localized versions for `orders`, `subscriptions`, `payment-methods`, `edit-account`, etc.

Custom Wicket pages registered:

- `edit-profile`
- `events`
- `jobs`
- `job-post`
- `change-password`
- `organization-management` (legacy `org-management`)
- `organization-profile` (legacy `org-management-profile`)
- `organization-members` (legacy `org-management-members`)
- `organization-members-bulk`
- `organization-contacts` (rendered only when `contacts.enabled = true`)

## Template Overrides

The plugin supports a hierarchy for templates:

1. Theme: `your-theme/templates-wicket/account-centre/...`
2. Plugin: `wicket-wp-account-centre/templates-wicket/account-centre/...`

## OrgMan Boot Order

`OrgMan` reads the `wicket/org-roster/config` filter during boot. Theme overrides registered in `custom/org-roster.php` must register before `after_setup_theme` priority 20, which is the standard child-theme `functions.php` window.