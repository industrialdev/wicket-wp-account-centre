# ACC Registers Class Documentation

## Overview
The `Registers` class is responsible for setting up essential WordPress components for the Wicket Account Centre plugin. This includes registering the custom post type used for Account Centre pages, custom navigation menus, and handling the loading of specific page templates for the Account Centre.

## Class Definition
```php
namespace WicketAcc;

class Registers extends WicketAcc
{
    /**
     * Constructor.
     * Hooks methods into WordPress actions and filters.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_nav_menu']);
        add_filter('theme_my-account_templates', [$this, 'register_acc_page_template'], 10, 3);
        add_filter('template_include', [$this, 'load_acc_page_template'], 10, 1);
    }

    // Method definitions follow...
}
```

## Core Methods

### `register_post_type(): void`
Hooked to `init`.
Registers the `my-account` custom post type. This CPT is used to create and manage pages within the Wicket Account Centre.
- **Key Arguments for `register_post_type`:**
    - `labels`: Defines UI text for the CPT (e.g., "Account Centre", "Add New Page").
    - `public`: `true`
    - `publicly_queryable`: `true`
    - `show_ui`: `true`
    - `show_in_menu`: `true`
    - `menu_icon`: Uses `WICKET_ACC_URL . '/assets/images/wicket-logo-20-white.svg'`.
    - `supports`: `['title', 'page-attributes', 'editor', 'custom-fields', 'revisions', 'thumbnail']`.
    - `has_archive`: `true`
    - `hierarchical`: `true`
    - `rewrite`: `true`
    - `show_in_rest`: `true`
    - `exclude_from_search`: `true`
    - `query_var`: `true`
    - `capability_type`: `'post'`
    - `menu_position`: `30`
    - `show_in_nav_menus`: `true`

### `register_nav_menu(): void`
Hooked to `init`.
Registers two custom navigation menu locations for use within the Account Centre:
- `wicket-acc-nav`: Primary Account Centre Menu.
- `wicket-acc-nav-secondary`: Secondary Account Centre Menu.
These allow administrators to assign WordPress menus to specific locations in the Account Centre templates.

### `register_acc_page_template(array $page_templates, WP_Theme $theme, WP_Post|null $post): array`
Hooked to `theme_my-account_templates`.
Adds custom page templates to the selection dropdown in the page editor, specifically for posts of the `my-account` CPT.
- It checks if the current post being edited is of type `my-account`.
- If so, it adds the following templates if their files exist in `WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/'`:
    - `account-centre/page-acc.php` (Displayed as "ACC Page")
    - `account-centre/page-acc-org_id.php` (Displayed as "ACC Page with Org Selector")
- The template keys in the returned array are prefixed with `templates-wicket/`.

### `load_acc_page_template(string $template): string`
Hooked to `template_include`.
Ensures that the correct plugin-provided page template is loaded when one of the custom Account Centre page templates (`page-acc.php` or `page-acc-org_id.php`) is selected for a `my-account` CPT entry.
- It checks the slug of the page template requested for the current page.
- If it matches one of the ACC custom templates (and is not `search.php`), it overrides the `$template` path to point to the corresponding file within the plugin's `templates/account-centre/` directory.

## Features

- **Custom Post Type:** Manages a dedicated `my-account` CPT for all Account Centre related pages.
- **Navigation Menus:** Provides dedicated theme locations for Account Centre navigation.
- **Page Templates:** Integrates custom page templates (`page-acc.php`, `page-acc-org_id.php`) specifically for the `my-account` CPT, allowing different layouts/functionalities for Account Centre pages.
- **Template Loading:** Ensures plugin-specific templates are correctly loaded when selected for an Account Centre page.

## Usage
The `Registers` class is instantiated by the main plugin file and its methods are hooked into WordPress core actions and filters. No direct interaction is typically needed by developers using the plugin, but understanding its registrations is key to customizing Account Centre behavior or appearance.
