<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Assets Class
 * Handles enqueuing and printing assets
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
     * Enqueue admin assets (CSS & JS)
     *
     * @return void
     */
    public function enqueue_admin_assets()
    {
        wp_enqueue_style('wicket-acc-admin-styles', WICKET_ACC_URL . 'assets/css/wicket-acc-admin-main.css', [], WICKET_ACC_VERSION);
        wp_enqueue_script('wicket-acc-admin-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-admin-main.js', [], WICKET_ACC_VERSION, true);
    }

    /**
     * Enqueue frontend assets (CSS & JS)
     *
     * Enqueues main frontend CSS & JS files, legacy JS file, and WooCommerce assets.
     *
     * @return void
     */
    public function enqueue_frontend_assets()
    {
        global $post;

        // Only on pages related to Wicket Account Centre
        if (!($post->post_type === 'my-account' ||
        str_starts_with($post->post_name, 'organization') ||
        is_woocommerce() ||
            is_account_page() ||
            is_wc_endpoint_url())) {
            return;
        }

        wp_enqueue_style('wicket-acc-frontend-styles', WICKET_ACC_URL . 'assets/css/wicket-acc-main.css', [], WICKET_ACC_VERSION);
        wp_enqueue_script('wicket-acc-frontend-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-main.js', [], WICKET_ACC_VERSION, true);
        wp_enqueue_script('wicket-acc-frontend-legacy-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-legacy.js', [], WICKET_ACC_VERSION, true);
        // https://unpkg.com/htmx.org@2
        wp_enqueue_script('htmx', 'https://unpkg.com/htmx.org@2', [], WICKET_ACC_VERSION, true);
        wp_enqueue_script('htmx-preload', 'https://unpkg.com/htmx-ext-preload@2', ['htmx'], WICKET_ACC_VERSION, true);
        wp_enqueue_script('htmx-alpinemorph', 'https://unpkg.com/htmx-ext-alpine-morph@2', ['htmx'], WICKET_ACC_VERSION, true);
        wp_enqueue_script('htmx-removeme', 'https://unpkg.com/htmx-ext-remove-me@2', ['htmx'], WICKET_ACC_VERSION, true);
    }
}
