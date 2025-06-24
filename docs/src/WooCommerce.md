# ACC WooCommerce Class Documentation

## Overview
The `WooCommerce` class, part of the Wicket Account Centre plugin, handles various integrations and modifications when WooCommerce is active. Its responsibilities include overriding WooCommerce templates, modifying UI elements like the "order again" button, adding custom banners, adjusting tax display in the cart, fixing pagination URLs, and adding WooCommerce-related items to the Account Centre menu.

## Class Definition
```php
namespace WicketAcc;

class WooCommerce extends WicketAcc
{
    /**
     * Constructor.
     * Sets up WooCommerce integration hooks if WooCommerce is active.
     */
    public function __construct();

    /**
     * Override WooCommerce templates.
     *
     * @param string $template Original template path.
     * @param string $template_name Template name (e.g., 'myaccount/my-account.php').
     * @param string $template_path Path to the template directory.
     * @return string Modified template path.
     */
    public function override_woocommerce_template($template, $template_name, $template_path);

    /**
     * Remove "order again" button from WooCommerce order views.
     */
    public function wc_remove_order_again_button();

    /**
     * Adds a global header banner (from ACF options) to WooCommerce My Account pages.
     */
    public function wc_add_acc_banner();

    /**
     * Removes tax totals from the WooCommerce cart page display.
     *
     * @param array $tax_totals Original tax totals.
     * @param object|null $instance WC_Tax instance (optional).
     * @return array Modified tax totals (empty array if on cart page).
     */
    public function remove_cart_tax_totals($tax_totals, $instance = null);

    /**
     * Excludes tax from the WooCommerce cart total calculation for display.
     *
     * @param int $total Original total.
     * @param object|null $instance WC_Tax instance (optional).
     * @return int Modified total without tax if on cart page.
     */
    public function exclude_tax_cart_total($total, $instance = null);

    /**
     * Fixes pagination URLs for the WooCommerce 'orders' endpoint to prevent duplicate slugs.
     *
     * @param string $url Original URL.
     * @param string $endpoint Endpoint name.
     * @param int|string $value Endpoint value.
     * @param string $permalink Base permalink.
     * @return string Fixed URL.
     */
    public function fix_orders_pagination_url($url, $endpoint, $value, $permalink);

    /**
     * Adds WooCommerce-specific menu items to the Wicket Account Centre menu.
     *
     * @param array $items Existing Account Centre menu items.
     * @return array Menu items with WooCommerce items merged in.
     */
    public function add_wc_menu_items($items);
}
```

## Core Functionality

### Constructor `__construct()`
Initializes the WooCommerce integration by setting up various WordPress hooks, but only if WooCommerce is detected as active (`WACC()->isWooCommerceActive()`). The hooks include:
-   `woocommerce_locate_template` for `override_woocommerce_template`.
-   `init` for `wc_remove_order_again_button`.
-   `wicket_header_end` for `wc_add_acc_banner`.
-   `woocommerce_cart_tax_totals` for `remove_cart_tax_totals`.
-   `woocommerce_calculated_total` and `woocommerce_subscriptions_calculated_total` for `exclude_tax_cart_total`.
-   `woocommerce_get_endpoint_url` for `fix_orders_pagination_url`.
-   `wicket_acc_menu_items` for `add_wc_menu_items`.

### Template Overriding `override_woocommerce_template()`
-   If on an admin page, returns the original template.
-   Specifically targets `myaccount/my-account.php` to replace it with `account-centre/page-wc.php` (checking user theme path first, then plugin path).
-   For other WooCommerce endpoints (derived from `$template_name`), if the endpoint is listed in `$this->acc_prefer_wc_endpoints` (a property not defined within this class, likely inherited or dynamically set) and the current page is a Wicket Account Centre page (`WACC()->is_account_page()` is true), it directly `echo`es the content of the WordPress post specified by the `acc_page_{endpoint}` ACF option. This action bypasses the standard WooCommerce template file for these specific endpoints. If no such ACF option is found or the conditions aren't met, it proceeds with the original template path.

### UI Modifications
-   **`wc_remove_order_again_button()`**: Removes the default "Order Again" button from WooCommerce order detail pages.
-   **`wc_add_acc_banner()`**: If the 'acc_global-headerbanner' ACF option is enabled and on a WooCommerce My Account page, it displays the content of the page specified in the ACF option, wrapped in a banner div.

### Cart Tax Display
-   **`remove_cart_tax_totals()`**: If on the cart page (`is_cart()`), it empties the `$tax_totals` array, effectively hiding tax lines.
-   **`exclude_tax_cart_total()`**: If on the cart page, it recalculates the cart total to exclude taxes (`WC()->cart->cart_contents_total + WC()->cart->shipping_total + WC()->cart->fee_total`).

### URL Correction
-   **`fix_orders_pagination_url()`**: For the 'orders' endpoint, if a pagination value is present and the URL contains a duplicate '/orders/orders/' pattern, it corrects it to a single '/orders/'.

### Menu Integration
-   **`add_wc_menu_items()`**: Merges standard WooCommerce My Account menu items (Orders, Downloads, Payment Methods, and Subscriptions if active) into the Wicket Account Centre's main menu. The URLs for these items are generated using `wc_get_account_endpoint_url()`.

## Usage Notes
-   This class heavily relies on the main plugin class instance (`WACC()`) for helper methods like `isWooCommerceActive()`, `is_account_page()`, and `getGlobalHeaderBannerPageId()`.
-   The template overriding logic for general endpoints depends on an ACF option (`acc_page_{endpoint}`) and a property `$this->acc_prefer_wc_endpoints` which is not defined within this class directly.
-   The class modifies the Wicket Account Centre menu (`wicket_acc_menu_items` filter) rather than directly altering the native WooCommerce menu items via `woocommerce_account_menu_items`.
