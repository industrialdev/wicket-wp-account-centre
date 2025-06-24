# ACC Assets Class Documentation

## Overview
The `Assets` class is responsible for enqueuing all scripts and styles for the Wicket Account Centre plugin. It handles both admin and front-end assets, ensuring they are loaded only when needed and versioned using the `WICKET_ACC_VERSION` constant.

## Class Definition
```php
namespace WicketAcc;

class Assets extends WicketAcc
{
    /**
     * Assets constructor.
     */
    public function __construct();

    /**
     * Enqueue admin assets (CSS & JS).
     */
    public function enqueue_admin_assets();

    /**
     * Enqueue frontend assets (CSS & JS).
     */
    public function enqueue_frontend_assets();
}
```

## Core Functionality

The `Assets` class automates the process of loading CSS and JavaScript files by hooking into WordPress actions during its construction.

### Initialization
The `__construct` method adds two main actions:
- `add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);`
- `add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);`

This ensures that the correct assets are loaded for the admin area and the front-end.

### Admin Assets
The `enqueue_admin_assets()` method loads the following files on all admin pages, versioned with `WICKET_ACC_VERSION`:
- **CSS**: `assets/css/wicket-acc-admin-main.css` (handle: `wicket-acc-admin-styles`)
- **JavaScript**: `assets/js/wicket-acc-admin-main.js` (handle: `wicket-acc-admin-scripts`)

### Frontend Assets
The `enqueue_frontend_assets()` method includes conditional logic to prevent assets from loading on every page. 

First, it ensures the global `$post` object is available for context checking, calling `get_queried_object()` if `$post` is initially empty.

Assets are only enqueued if one of the following conditions is met:
- The current post type (obtained via `get_post_type()`) is `my-account`.
- The current page slug (checked via `is_page()`) is one of `my-account`, `mon-compte`, or `mi-cuenta`.
- WooCommerce is active (checked via `WACC()->isWooCommerceActive()`) AND the current page is a relevant WooCommerce page (checked via `is_woocommerce()`, `is_account_page()`, or `is_wc_endpoint_url()`).

If these conditions are met, the following files are loaded, all versioned with `WICKET_ACC_VERSION`:
- **CSS**: `assets/css/wicket-acc-main.css` (handle: `wicket-acc-frontend-styles`)
- **JavaScript**:
  - `assets/js/wicket-acc-main.js` (handle: `wicket-acc-frontend-scripts`)
  - `assets/js/wicket-acc-legacy.js` (handle: `wicket-acc-frontend-legacy-scripts`)
  - `assets/js/wicket-acc-orders.js` (handle: `wicket-acc-orders`)
  - `assets/js/wicket-acc-subscriptions.js` (handle: `wicket-acc-subscriptions`)

Additionally, the source code contains a commented-out section for potentially loading the Tailwind CSS Play CDN (`https://cdn.tailwindcss.com`) in development or debug environments. This is not active by default.

## Usage
This class is designed to work automatically. It is instantiated once in the main plugin file, and its constructor handles all the necessary hooks for enqueuing assets. No further developer interaction is required.

**Example Instantiation:**
```php
// In the main plugin file
new \WicketAcc\Assets();
```
