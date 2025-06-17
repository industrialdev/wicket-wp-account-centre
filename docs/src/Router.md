# ACC Router Class Documentation

## Overview
The `Router` class in the Wicket Account Centre plugin is responsible for managing the WordPress pages that constitute the different sections of the Account Centre. It handles the creation and retrieval of these pages (which are instances of the `my-account` Custom Post Type), controls which templates are loaded for them, and implements various redirection logic to ensure a consistent user experience and handle legacy URLs or WooCommerce integration points.

It does **not** function as a traditional URL parsing router with custom rewrite rules or middleware in the typical sense. Instead, it leverages WordPress's CPT and templating system.

## Class Definition
```php
namespace WicketAcc;

class Router extends WicketAcc
{
    private $acc_page_id_cache = null;
    private $acc_slug_cache = null;

    /**
     * Constructor.
     * Hooks methods into WordPress actions and filters.
     */
    public function __construct()
    {
        if (apply_filters('wicket/acc/router/disable_router', false)) {
            return;
        }

        // DEBUG ONLY: Flush rewrite rules in development environment
        if (defined('WP_ENV') && WP_ENV === 'development') {
            flush_rewrite_rules();
        }

        add_action('admin_init', [$this, 'init_all_pages']);
        add_action('init', [$this, 'acc_pages_template']);
        add_filter('archive_template', [$this, 'custom_archive_template']);
        add_action('plugins_loaded', [$this, 'acc_redirects']);
    }

    // ... other methods ...
}
```

## Core Methods

### Page Management & Initialization

- `get_acc_page_id(): int|null`
  Retrieves the WordPress Page ID of the main Account Centre dashboard page. This ID is typically stored as an ACF option (`acc_page_dashboard`). Results are cached in `$this->acc_page_id_cache`.

- `get_acc_slug(): string|null`
  Retrieves the slug of the main Account Centre dashboard page using `get_acc_page_id()`. Results are cached in `$this->acc_slug_cache`.

- `create_page(string $slug, string $name): int|false`
  Creates a new WordPress page (CPT `my-account`) with the given slug and name. If a page with this slug already has its ID stored in an ACF option (`acc_page_{$slug}`), or if a page with that slug already exists, it returns that page's ID. Otherwise, it creates the page and updates the ACF option. Returns the page ID on success, `false` on failure.

- `get_page_id_by_slug(string $slug): int|false`
  Retrieves the Page ID of a `my-account` CPT entry by its slug. Returns the page ID or `false` if not found.

- `init_all_pages(): void`
  Hooked to `admin_init`. This method is responsible for ensuring that all necessary Account Centre pages (like dashboard, edit profile, etc., as defined within the method) are created. It typically calls `create_page()` for each required section.

- `maybe_create_acc_dashboard_page(): void`
  Ensures the main Account Centre dashboard page exists. If not, it creates it. This method seems to be called internally by `init_all_pages()` or similar initialization logic.

### Template Handling

- `get_wicket_acc_template(int $post_id = null): string|false`
  Determines the correct template file path for a given `my-account` CPT post ID. It checks ACF fields on the post: if `acc_page_template_select` is 'custom', it uses `acc_page_template_file_path`. If 'default', it constructs a path like `page-acc-{$post->post_name}.php`. It checks for the template in the child theme's `wicket-acccount-centre/` directory first, then falls back to the plugin's `templates/account-centre/` directory. Returns the template file path or `false`.

- `is_orgmanagement_page(int $post_id): string|false`
  Checks if the given post ID corresponds to an Organization Management page by inspecting specific ACF fields associated with org management sections (e.g., `acc_page_org_management_index`, `acc_page_org_management_profile`). Returns the slug of the org management section (e.g., 'index', 'profile') if it is an org management page, otherwise `false`.

- `orgman_page_requested(int $post_id): bool`
  Returns `true` if the given `post_id` corresponds to an Organization Management page (by checking `!empty($this->is_orgmanagement_page($post_id))`), `false` otherwise.

- `acc_pages_template(): void`
  Hooked to `init`. This method likely sets up further filters or actions related to template loading for `my-account` CPT pages, possibly using `template_include` to enforce loading templates via `get_wicket_acc_template()`.

- `custom_archive_template(string $template): string`
  Hooked to `archive_template`. Provides a custom template for the `my-account` CPT archive page. This is often used to redirect from the generic archive to a specific page like the dashboard, as direct `template_redirect` hooks can be problematic before the main query runs.

### Redirection Logic

- `acc_redirects(): void`
  Hooked to `plugins_loaded`. Handles various redirection scenarios:
    1.  Redirects the WooCommerce `/wc-account/` index page to the Account Centre dashboard (`/my-account/dashboard/`).
    2.  Redirects old Account Centre slugs (e.g., `/account-centre/`, `/account-center/`) to the new `my-account` based dashboard URL.
    3.  Redirects certain WooCommerce endpoints (e.g., payment methods, orders) away from being rendered within the `my-account` CPT structure if they are better handled by WooCommerce's native templates. This uses an internal list `acc_prefer_wc_endpoints` and `acc_wc_endpoints` for translations.

## Key Properties

- `private $acc_page_id_cache`: Caches the ID of the main dashboard page.
- `private $acc_slug_cache`: Caches the slug of the main dashboard page.
- (Internal) `private $acc_pages`: A private class property, populated within the `init_all_pages()` method, defining the slugs and names of standard Account Centre pages to be created.
- (Internal) `private $acc_wc_endpoints`: A private class property initialized with an array mapping WooCommerce endpoint slugs to their translations, used in redirection logic.
- (Internal) `private $acc_prefer_wc_endpoints`: A private class property initialized with an array listing WooCommerce endpoints that should be forced to use WooCommerce's rendering instead of ACC `my-account` CPT pages, used in redirection logic.

## Usage
The `Router` class is instantiated by the main plugin. Its primary role is to ensure the structural integrity of the Account Centre's page-based navigation system within WordPress and to manage how users are directed to and within these pages.
