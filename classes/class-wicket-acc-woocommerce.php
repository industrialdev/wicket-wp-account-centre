<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Account Centre
 * WooCommerce
 *
 * @package Wicket
 */

/**
 * WooCommerce Class
 */
class WooCommerce extends WicketAcc
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // HPOS compatibility
        add_action('before_woocommerce_init', [$this, 'HPOS_Compatibility']);

        // Override templates
        add_filter('woocommerce_locate_template', [$this, 'override_woocommerce_template'], 10, 3);

        // Remove order again button
        add_action('init', [$this, 'wc_remove_order_again_button']);

        // Add global header banner as ACC pages
        add_action('wicket_header_end', [$this, 'wc_add_acc_banner'], PHP_INT_MAX);

        // Remove tax totals
        add_filter('woocommerce_cart_tax_totals', [$this, 'remove_cart_tax_totals'], 10, 2);
        add_filter('woocommerce_calculated_total', [$this, 'exclude_tax_cart_total'], 10, 2);
        add_filter('woocommerce_subscriptions_calculated_total', [$this, 'exclude_tax_cart_total']);

        // Mark order as completed when it is paid
        //add_action('woocommerce_payment_complete_order_status_processing', [$this, 'mark_order_as_completed']);
    }

    /**
     * HPOS compatibility for WooCommerce
     *
     * @link https://developer.woocommerce.com/docs/hpos-extension-recipe-book/
     *
     * @return void
     */
    public function HPOS_Compatibility()
    {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    /**
     * Override WooCommerce templates
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
            $user_template   = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/page-wc.php';

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
            if (!is_account_page()) {
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
     * Remove "order again" button from orders and order table inside WooCommerce my account
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
        if (!is_account_page()) {
            return;
        }

        $acc_global_headerbanner_page_id = WACC()->getGlobalHeaderBannerPageId();
        $acc_global_banner_page          = get_post($acc_global_headerbanner_page_id);

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
     * Automatically mark an order as "completed" when it is paid.
     *
     * @param int $order_id The order ID.
     *
     * @return void
     */
    public function mark_order_as_completed($order_id)
    {
        // Check if ACF:acc_wc_auto_complete_orders option is enabled
        if (!get_field('acc_wc_auto_complete_orders', 'option')) {
            return;
        }

        $order = wc_get_order($order_id);

        // Order successfully paid?
        if ($order->has_status('processing') && $order->is_paid()) {
            $order->update_status('completed');
        }
    }
}
