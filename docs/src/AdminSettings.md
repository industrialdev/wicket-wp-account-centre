# ACC AdminSettings Class Documentation

## Overview
The `AdminSettings` class manages the admin-facing aspects of the Wicket Account Centre plugin. It leverages Advanced Custom Fields (ACF) to create a dedicated "ACC Options" page, rather than using the standard WordPress Settings API. This class is responsible for initializing the options page, adding helpful admin menu shortcuts, enqueuing admin-specific assets, and handling post-save actions.

## Class Definition
```php
namespace WicketAcc\Admin;

class AdminSettings extends \WicketAcc\WicketAcc
{
    /**
     * Constructor of class.
     */
    public function __construct();

    /**
     * Enqueue scripts and styles for admin.
     */
    public function acc_admin_assets();

    /**
     * Register options page for ACF.
     */
    public function admin_register_options_page();

    /**
     * Register submenu pages for my-account CPT.
     */
    public function admin_register_submenu_pages();

    /**
     * Warn the user if /includes/acf-json/ folder is not writable.
     */
    public function acf_json_folder_permissions();

    /**
     * Actions when saving ACC Options (ACF based) in the backend.
     */
    public function acc_options_save($post_id, $menu_slug);
}
```

## Core Functionality

### ACF Options Page
The primary function of this class is to register an options page using `acf_add_options_sub_page`. This creates an "ACC Options" sub-menu item under the "My Account" custom post type menu.

All settings fields displayed on this page are defined and managed through an ACF Field Group, not within this class.

### Admin Menu Shortcuts
The `admin_register_submenu_pages` method adds convenient links to the "My Account" CPT admin menu:
- A direct link to edit the "Global Header" page. This is determined by first checking the `acc_global-headerbanner` ACF option. The code then attempts to find a `my-account` CPT post with the slug `acc_global-headerbanner` using `get_page_by_path()`. If found, its ID (with WPML adjustment) is used to generate an edit link. *Note: There might be a slight mismatch if the ACF option stores an ID directly rather than relying on the fixed slug `acc_global-headerbanner` for the lookup.*
- A shortcut to the WordPress Menu Editor (`nav-menus.php`).
- *Note: A previously considered shortcut for WooCommerce Endpoints was intentionally omitted due to complexities with WPML, as noted in the source code.*

### Asset Enqueueing
The `acc_admin_assets` method enqueues a custom JavaScript file (`wicket-acc-admin-main.js`) and a stylesheet (`wicket-acc-admin-main.css`) for use in the WordPress admin area. These are located in the plugin's `assets/js/` and `assets/css/` directories respectively.

### ACF JSON Folder Permissions
In development environments (where `WP_ENV` is 'development' or `WP_ENVIRONMENT_TYPE` is 'local' or 'development'), the `acf_json_folder_permissions` method checks if the plugin's `includes/acf-json/` directory is writable. If not, it displays a persistent admin notice to alert the developer, as this is critical for saving ACF field configurations locally within the plugin.

### Post-Save Hook
The `acc_options_save` method is hooked to ACF's `acf/options_page/save` action. When the "ACC Options" page (identified by its `$menu_slug` parameter being `wicket_acc_options`) is saved, this method calls `flush_rewrite_rules(false)` to ensure any changes potentially affecting URLs are immediately applied. The `$post_id` parameter in this context typically refers to `options` or `option` for ACF options pages.

## How to Manage and Access Settings

**Defining Settings:**
All plugin options are defined visually using an ACF Field Group that is configured to display on the "ACC Options" page. The field group should be set to show if 'Options Page is equal to ACC Options'.

**Accessing Settings:**
Since settings are managed by ACF, you should use ACF's functions to retrieve their values in your code. The most common function is `get_field()`, specifying `'option'` as the second parameter to target options page fields.

### Usage Example

```php
// To get a value from the ACC Options page, use get_field() with 'option'.

// Example: Get the 'Enable Feature X' toggle value (boolean)
$is_feature_enabled = get_field('acc_enable_feature_x', 'option');

if ($is_feature_enabled) {
    // Do something awesome
}

// Example: Get the 'Global Header Banner Page' ID (Post Object or ID)
$header_page_id = get_field('acc_global-headerbanner', 'option'); // Returns Post Object or ID based on ACF field settings

// Ensure you handle the return type appropriately, e.g., if it's a Post Object:
// if ($header_page_id instanceof WP_Post) { $actual_id = $header_page_id->ID; }
```
