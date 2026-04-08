# ACC Plugin Entrypoint Documentation

## Overview
The `class-wicket-acc-main.php` file is the primary entrypoint for the Wicket Account Centre plugin. It handles initialization, dependency checks, and provides a singleton access point (`WACC()`) for all core services.

## Technical Requirements
- **PHP**: 8.2+
- **WordPress**: 6.6+
- **Dependencies**: 
  - `wicket-wp-base-plugin`
  - `advanced-custom-fields-pro`

## Core Constants
The plugin defines several constants for path and URL management:
- `WICKET_ACC_VERSION`: Current plugin version.
- `WICKET_ACC_PATH` / `WICKET_ACC_URL`: Filesystem path and public URL.
- `WICKET_ACC_UPLOADS_PATH`: Path for plugin-specific uploads.
- `WICKET_ACC_PLUGIN_TEMPLATE_PATH`: Path to internal templates.
- `WICKET_ACC_USER_TEMPLATE_PATH`: Path for theme-level template overrides.

## Initialization Flow
1. **Composer Autoloader**: Loads dependencies from `vendor/`.
2. **Fatal Error Handler**: Registered immediately via `Log::registerFatalErrorHandler()`.
3. **Plugin Setup**: Triggered on `plugins_loaded`.
   - Checks for required plugins (Base Plugin, ACF Pro).
   - Initializes core services.
   - Registers activation/deactivation hooks.

## Service Access (`WACC()`)
The main `WicketAcc` class acts as a service locator. You can access various components using the `WACC()` helper function:

| Method | Service | Purpose |
|        |         |         |
| `WACC()->Mdp()` | `Mdp\Init` | Core MDP API integration |
| `WACC()->Profile()` | `Profile` | User profile data and logic |
| `WACC()->OrganizationManagement()` | `OrganizationManagement` | Org management features |
| `WACC()->Blocks()` | `Blocks` | ACF Block registration and rendering |
| `WACC()->User()` | `User` | User-related utility methods |
| `WACC()->WooCommerce()` | `WooCommerce` | WC endpoint customization |
| `WACC()->Router()` | `Router` | Template routing and redirects |

## Page & Endpoint Management
The entrypoint defines several multilingual slugs for the Account Centre and WooCommerce:
- **Account Index**: `my-account` (EN), `mon-compte` (FR), `mi-cuenta` (ES).
- **WooCommerce Endpoints**: Provides localized versions for `orders`, `subscriptions`, `payment-methods`, etc.

## Template Overrides
The plugin supports a hierarchy for templates:
1. **Theme**: `your-theme/templates-wicket/account-centre/...`
2. **Plugin**: `wicket-wp-account-centre/templates-wicket/account-centre/...`
