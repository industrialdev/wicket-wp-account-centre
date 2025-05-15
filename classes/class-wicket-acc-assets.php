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

        // Only on pages related to Wicket Account Centre
        if (!(
            (isset($post) && $post instanceof \WP_Post && (
                $post->post_type === 'my-account' || str_starts_with($post->post_name, 'organization')
            )) || is_woocommerce() || is_account_page() || is_wc_endpoint_url()
        )) {
            return;
        }

        // Tailwind CSS Play CDN - Load in development environments or when WP_DEBUG is true
        if ((defined('WP_ENV') && in_array(WP_ENV, ['local', 'development'], true)) ||
            (defined('WP_ENVIRONMENT_TYPE') && in_array(WP_ENVIRONMENT_TYPE, ['local', 'development'], true)) ||
            (defined('WP_DEBUG') && WP_DEBUG === true)) {
            wp_enqueue_script('tailwind-css-development', 'https://cdn.tailwindcss.com', [], WICKET_ACC_VERSION, false);
        }

        wp_enqueue_style('wicket-acc-frontend-styles', WICKET_ACC_URL . 'assets/css/wicket-acc-main.css', [], WICKET_ACC_VERSION);
        wp_enqueue_script('wicket-acc-frontend-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-main.js', [], WICKET_ACC_VERSION, true);
        wp_enqueue_script('wicket-acc-frontend-legacy-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-legacy.js', [], WICKET_ACC_VERSION, true);
        wp_enqueue_script('wicket-acc-orders', WICKET_ACC_URL . 'assets/js/wicket-acc-orders.js', [], WICKET_ACC_VERSION, true);
        wp_enqueue_script('wicket-acc-subscriptions', WICKET_ACC_URL . 'assets/js/wicket-acc-subscriptions.js', [], WICKET_ACC_VERSION, true);
    }
}
