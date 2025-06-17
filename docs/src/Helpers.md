# ACC Helpers Class Documentation

## Overview
The `Helpers` class provides a collection of utility methods and helper functions used throughout the Wicket Account Centre plugin. These methods are accessible via the `WACC()` global function (which returns an instance of `MethodRouter`, which then delegates to `Helpers`) and handle tasks like retrieving user data, managing page URLs, rendering components, and checking for dependencies.

## Class Definition
```php
namespace WicketAcc;

class Helpers extends WicketAcc
{
    // This class does not have its own constructor defined.
    // It inherits from WicketAcc.
}
```

## Core Methods

### User and Session
- `getCurrentPerson(): object`: Retrieves the current person data from the MDP via `wicket_current_person()`.
- `getAccSlug(): string`: Gets the localized slug for the Account Centre page. Relies on WPML if active and an internal array `acc_index_slugs` for translations, defaulting to 'en'.
- `getAccName(): string`: Gets the localized name for the Account Centre (e.g., "Account Centre" or "Account Center"). Retrieves the `ac_localization` option (ACF field) and determines the name based on its value, defaulting to "Account Centre".
- `getLanguage(): string`: Gets the current language using WPML's `$sitepress->get_current_language()`. Defaults to 'en' if WPML (`$sitepress`) is not available.

### Page and Template Management
- `getGlobalHeaderBannerPageId(): int`: Retrieves the page ID for the global header banner content. This content is expected to be a 'my-account' CPT with the slug `acc_global-headerbanner`. Handles WPML translation of the page ID.
- `renderAccSidebar(): void`: Renders the Account Centre sidebar template. It first checks for `account-centre/sidebar.php` in the child theme (`WICKET_ACC_USER_TEMPLATE_PATH`), then in the plugin's template path (`WICKET_ACC_PLUGIN_TEMPLATE_PATH`), and includes it if found.
- `renderGlobalSubHeader(): void`: Renders the global sub-header banner content. It uses `getGlobalHeaderBannerPageId()` and an ACF option `acc_global-headerbanner` to fetch and display the content of the banner page.
- `is_account_page(): bool`: Checks if the current page is a singular 'account' CPT page or the 'account' CPT archive page.
- `get_account_page_url(string $endpoint = ''): string`: Gets the URL for the main account page (slug: 'account'). If an `$endpoint` is provided, it's appended to the URL. Falls back to `home_url()` if the 'account' page isn't found.
- `get_account_menu_items(): array`: Retrieves an array of account menu items (dashboard, edit profile, change password, organization management). This array is filterable via `wicket_acc_menu_items`.
- `render_account_menu(string $menu_location = 'wicket-acc-nav'): void`: Renders the account navigation menu. It first tries to render a WordPress nav menu assigned to `$menu_location` using `wicket_acc_menu_walker`. If no menu is assigned, it falls back to rendering a list from `get_account_menu_items()`.

### Environment and Theme
- `isWooCommerceActive(): bool`: Checks if the WooCommerce plugin is active by verifying `class_exists('WooCommerce')`.
- `getThemePath(): string`: Gets the absolute path to the current theme/child theme directory using `get_stylesheet_directory()`, ensuring a trailing slash with `trailingslashit()`.
- `getThemeURL(): string`: Gets the URL to the current theme/child theme directory using `get_stylesheet_directory_uri()`, ensuring a trailing slash with `trailingslashit()`.

## Usage Example

```php
// Get the current person's data
$person = WACC()->getCurrentPerson(); // Equivalent to WACC()->Helpers->getCurrentPerson()

// Get the URL for the 'edit-profile' page
$edit_profile_url = WACC()->get_account_page_url('edit-profile');

// Check if WooCommerce is active before running Woo-specific code
if (WACC()->isWooCommerceActive()) {
    // ...
}

// Render the account sidebar
WACC()->renderAccSidebar();
```

## Error Handling
- Methods are generally designed to fail gracefully, returning `null`, empty arrays/strings, or default values (e.g., `get_account_page_url` falls back to `home_url()`).
- Template rendering methods check for file existence before inclusion.
