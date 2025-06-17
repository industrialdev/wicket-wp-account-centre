<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Assets Class
 * Handles enqueuing and printing assets.
 */
class Assets extends WicketAcc
{
    /**
     * Assets constructor.
     *
     * Adds actions to enqueue admin and frontend assets.
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Enqueue admin assets (CSS & JS).
     *
     * @return void
     */
    public function enqueue_admin_assets()
    {
        wp_enqueue_style('wicket-acc-admin-styles', WICKET_ACC_URL . 'assets/css/wicket-acc-admin-main.css', [], WICKET_ACC_VERSION);
        wp_enqueue_script('wicket-acc-admin-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-admin-main.js', [], WICKET_ACC_VERSION, true);
    }

    /**
     * Enqueue frontend assets (CSS & JS).
     *
     * Enqueues main frontend CSS & JS files, legacy JS file, and WooCommerce assets.
     *
     * @return void
     */
    public function enqueue_frontend_assets()
    {
        global $post;

        // Empty $post?
        if (empty($post)) {
            // Get post data
            $post = get_queried_object();
        }

        // Determine if assets should be enqueued based on context.
        // Assets are enqueued if:
        // 1. The current post type IS 'my-account', OR
        // 2. The current page IS one of 'my-account', 'mon-compte', 'mi-cuenta', OR
        // 3. WooCommerce IS active AND it's a relevant WooCommerce page (shop, account, endpoint).
        // If NONE of these conditions are met, we return early.

        $current_post_type = get_post_type(); // Relies on global $post. Returns false if no global $post.

        $is_relevant_page_slug = is_page([
            'my-account',
            'mon-compte',
            'mi-cuenta',
        ]);

        $is_relevant_woocommerce_context = WACC()->isWooCommerceActive() && (
            is_woocommerce() ||
            is_account_page() ||
            is_wc_endpoint_url()
        );

        // If it's NOT the 'my-account' post type, AND
        // it's NOT one of the specified page slugs, AND
        // it's NOT a relevant WooCommerce context,
        // THEN return early and do not enqueue assets.
        if (
            ('my-account' !== $current_post_type) &&
            (!$is_relevant_page_slug) &&
            (!$is_relevant_woocommerce_context)
        ) {
            return;
        }

        // Tailwind CSS Play CDN - Load in development environments or when WP_DEBUG is true
        /*if ((defined('WP_ENV') && in_array(WP_ENV, ['local', 'development'], true)) ||
            (defined('WP_ENVIRONMENT_TYPE') && in_array(WP_ENVIRONMENT_TYPE, ['local', 'development'], true)) ||
            (defined('WP_DEBUG') && WP_DEBUG === true)) {
            wp_enqueue_script('tailwind-css-development', 'https://cdn.tailwindcss.com', [], WICKET_ACC_VERSION, false);
        }*/

        wp_enqueue_style('wicket-acc-frontend-styles', WICKET_ACC_URL . 'assets/css/wicket-acc-main.css', [], WICKET_ACC_VERSION);
        wp_enqueue_script('wicket-acc-frontend-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-main.js', [], WICKET_ACC_VERSION, true);
        wp_enqueue_script('wicket-acc-frontend-legacy-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-legacy.js', [], WICKET_ACC_VERSION, true);
        wp_enqueue_script('wicket-acc-orders', WICKET_ACC_URL . 'assets/js/wicket-acc-orders.js', [], WICKET_ACC_VERSION, true);
        wp_enqueue_script('wicket-acc-subscriptions', WICKET_ACC_URL . 'assets/js/wicket-acc-subscriptions.js', [], WICKET_ACC_VERSION, true);
    }
}
