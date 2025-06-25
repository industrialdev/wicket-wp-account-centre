<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * WooCommerce Integration Class
 * Handles WooCommerce functionality when available.
 */
class WooCommerce extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('init', [$this, 'initialize']);
    }

    /**
     * Initialize hooks and filters for WooCommerce integration.
     */
    public function initialize()
    {
        // Only run if WooCommerce is active
        if (!WACC()->isWooCommerceActive()) {
            return;
        }

        // Override templates
        add_filter('woocommerce_locate_template', [$this, 'override_woocommerce_template'], 10, 3);

        // Remove order again button
        add_action('init', [$this, 'wc_remove_order_again_button']);

        // Add global header banner as ACC pages
        //add_action('wicket_header_end', [$this, 'wc_add_acc_banner'], PHP_INT_MAX);

        // Remove tax totals
        add_filter('woocommerce_cart_tax_totals', [$this, 'remove_cart_tax_totals'], 10, 2);
        add_filter('woocommerce_calculated_total', [$this, 'exclude_tax_cart_total'], 10, 2);
        add_filter('woocommerce_subscriptions_calculated_total', [$this, 'exclude_tax_cart_total']);

        // Fix pagination URLs for orders endpoint
        add_filter('woocommerce_get_endpoint_url', [$this, 'fix_orders_pagination_url'], 10, 4);

        // Add WooCommerce endpoints to account pages
        add_filter('wicket_acc_menu_items', [$this, 'add_wc_menu_items']);
    }

    /**
     * Override WooCommerce templates.
     *
     * @param string $template
     * @param string $template_name
     * @param string $template_path
     *
     * @return string
     */
    public function override_woocommerce_template($template, $template_name, $template_path)
    {
        if (is_admin()) {
            return $template;
        }

        // WC myaccount to ACC dashboard
        if ($template_name === 'myaccount/my-account.php') {
            $plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/page-wc.php';
            $user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/page-wc.php';

            if (file_exists($user_template)) {
                return $user_template;
            }

            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        // Determine the endpoint name from $template_name (ex: myaccount/payment-methods.php = payment-methods)
        $wc_endpoint = explode('/', $template_name);
        $wc_endpoint = end($wc_endpoint);
        $wc_endpoint = explode('.', $wc_endpoint);
        // grab the first element of the array
        $wc_endpoint = array_shift($wc_endpoint);

        // If we are seeing a WC endpoint from $acc_prefer_wc_endpoints
        if (in_array($wc_endpoint, $this->acc_prefer_wc_endpoints)) {
            // Only on WooCommerce pages
            if (!WACC()->is_account_page()) {
                return $template;
            }

            // We need to load the content of the post with slug $wc_endpoint from CPT my-account
            $acc_post_id = get_field('acc_page_' . $wc_endpoint, 'option');

            if ($acc_post_id) {
                // Get post content and display it
                $acc_post_content = get_post($acc_post_id)->post_content;
                echo $acc_post_content;
            }
        }

        return $template;
    }

    /**
     * Remove "order again" button from orders and order table inside WooCommerce my account.
     *
     * @return void
     */
    public function wc_remove_order_again_button()
    {
        remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');
    }

    /**
     * Adds the global header banner to the WooCommerce my account navigation.
     *
     * Will only add the banner if the 'acc_global-headerbanner' option is enabled.
     *
     * @return void
     */
    public function wc_add_acc_banner()
    {
        $acc_banner_enabled = get_field('acc_global-headerbanner', 'option');

        if (!$acc_banner_enabled) {
            return;
        }

        // Only on WooCommerce myaccount pages
        if (!WACC()->is_account_page()) {
            return;
        }

        $acc_global_headerbanner_page_id = WACC()->getGlobalHeaderBannerPageId();
        $acc_global_banner_page = get_post($acc_global_headerbanner_page_id);

        // What happened here?
        if (empty($acc_global_banner_page)) {
            return;
        }

        // Banner content
        $acc_global_banner_content = '<div class="wicket-acc alignfull wp-block-wicket-banner">';
        $acc_global_banner_content .= apply_filters('the_content', $acc_global_banner_page->post_content);
        $acc_global_banner_content .= '</div>';

        echo $acc_global_banner_content;
    }

    /**
     * Removes the tax totals from the cart page.
     *
     * @param array $tax_totals An associative array of tax rates to totals.
     * @param object $instance The WC_Tax object.
     *
     * @return array Empty array.
     */
    public function remove_cart_tax_totals($tax_totals, $instance = null)
    {
        if (!is_cart()) {
            return $tax_totals;
        }

        $tax_totals = [];

        return $tax_totals;
    }

    /**
     * Removes the tax totals from the cart totals.
     *
     * @param int $total The total.
     * @param object $instance The WC_Tax object.
     *
     * @return int The total without tax.
     */
    public function exclude_tax_cart_total($total, $instance = null)
    {
        if (!is_cart()) {
            return $total;
        }

        $total = round(WC()->cart->cart_contents_total + WC()->cart->shipping_total + WC()->cart->fee_total, WC()->cart->dp);

        return $total;
    }

    /**
     * Fix pagination URLs for orders endpoint.
     *
     * @param string $url The URL.
     * @param string $endpoint The endpoint.
     * @param int $value The value.
     * @param string $permalink The permalink.
     *
     * @return string The fixed URL.
     */
    public function fix_orders_pagination_url($url, $endpoint, $value, $permalink)
    {
        // Only modify URLs for the orders endpoint with pagination
        if ($endpoint === 'orders' && is_numeric($value)) {
            // Check if the URL has the duplicate "orders" pattern
            if (strpos($url, '/orders/orders/') !== false) {
                // Fix the URL by replacing the duplicate pattern
                $url = str_replace('/orders/orders/', '/orders/', $url);
            }
        }

        return $url;
    }

    /**
     * Add WooCommerce menu items to account menu.
     *
     * @param array $items Current menu items.
     * @return array Modified menu items.
     */
    public function add_wc_menu_items($items)
    {
        if (!WACC()->isWooCommerceActive()) {
            return $items;
        }

        // Add WooCommerce menu items
        $wc_items = [
            'orders' => [
                'title' => __('Orders', 'wicket-acc'),
                'url' => wc_get_account_endpoint_url('orders'),
            ],
            'downloads' => [
                'title' => __('Downloads', 'wicket-acc'),
                'url' => wc_get_account_endpoint_url('downloads'),
            ],
            'payment-methods' => [
                'title' => __('Payment Methods', 'wicket-acc'),
                'url' => wc_get_account_endpoint_url('payment-methods'),
            ],
        ];

        // Add subscriptions if WooCommerce Subscriptions is active
        if (class_exists('WC_Subscriptions')) {
            $wc_items['subscriptions'] = [
                'title' => __('Subscriptions', 'wicket-acc'),
                'url' => wc_get_account_endpoint_url('subscriptions'),
            ];
        }

        return array_merge($items, $wc_items);
    }
}
