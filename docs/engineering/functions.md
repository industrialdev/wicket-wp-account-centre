---
title: "Functions"
audience: [developer, agent]
php_class: Wicket_ACC_Main
source_files: ["src/"]
---

# Global Helper: WACC()

## Overview
The `WACC()` function is the central singleton accessor for the Wicket Account Centre plugin. It provides a standardized way to access all core services and helper methods without relying on global variables.

## Usage
The `WACC()` function returns the singleton instance of the `WicketAcc` class. Services are accessed as dynamic methods:

```php
// Accessing the Profile service
$profile_data = WACC()->Profile()->getUserProfileData();

// Accessing the MDP API integration
$token = WACC()->Mdp()->getToken();

// Accessing helper methods
$is_active = WACC()->Helpers()->is_account_active($user_id);
```

## Available Services
The following services are registered and accessible via `WACC()`:

| Service | Accessor | Purpose |
|         |          |         |
| **MDP API** | `WACC()->Mdp()` | Communication with the Member Data Platform. |
| **Profile** | `WACC()->Profile()` | Logic for individual user profiles. |
| **Org Management** | `WACC()->OrganizationManagement()` | Core logic for organization-level features. |
| **Org Roster** | `WACC()->OrganizationRoster()` | Member/Roster management logic. |
| **Blocks** | `WACC()->Blocks()` | ACF Block registration and template rendering. |
| **WooCommerce** | `WACC()->WooCommerce()` | WooCommerce endpoint and URL management. |
| **Router** | `WACC()->Router()` | URL routing, page IDs, and template resolution. |
| **User** | `WACC()->User()` | User utility methods (metadata, roles, sync). |
| **Helpers** | `WACC()->Helpers()` | General utility functions (formatting, validation). |
| **Log** | `WACC()->Log()` | Centralized logging and error handling. |

## Coding Standards for Helpers
- **Location**: Core logic should reside in the `src/` directory within their respective service classes.
- **Naming**: Use camelCase for methods (`getUserData`) and snake_case for legacy global helpers if necessary.
- **Strict Typing**: All new helper methods should use PHP 8.2+ strict typing for parameters and return values.
- **Security**: Helpers that modify state or fetch sensitive data must perform capability checks and nonce validation.

## Adding a New Helper
To add a new helper method:
1. Identify the most relevant service class in `src/`.
2. Add the method to that class with appropriate visibility (usually `public`).
3. If no service fits, consider adding it to `src/Helpers.php` or creating a new service in `src/Services/`.

## Deprecation
Legacy functions in `includes/helpers.php` and `includes/legacy.php` are being phased out. When using these, a `_doing_it_wrong()` notice or `WACC()->Log()->deprecated()` call should be triggered, pointing to the new `WACC()` equivalent."
