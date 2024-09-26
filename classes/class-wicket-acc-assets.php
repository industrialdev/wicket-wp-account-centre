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
        wp_enqueue_style('wicket-acc-admin-styles', WICKET_ACC_URL . 'dist/assets/css/wicket-acc-admin-main.css', [], WICKET_ACC_VERSION);
        wp_enqueue_script('wicket-acc-admin-scripts', WICKET_ACC_URL . 'dist/assets/js/wicket-acc-admin-main.js', [], WICKET_ACC_VERSION, true);
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
        wp_enqueue_style('wicket-acc-frontend-styles', WICKET_ACC_URL . 'dist/assets/css/wicket-acc-main.css', [], WICKET_ACC_VERSION);
        wp_enqueue_script('wicket-acc-frontend-scripts', WICKET_ACC_URL . 'dist/assets/js/wicket-acc-main.js', [], WICKET_ACC_VERSION, true);
        wp_enqueue_script('wicket-acc-frontend-legacy-scripts', WICKET_ACC_URL . 'dist/assets/js/wicket-acc-legacy.js', [], WICKET_ACC_VERSION, true);
    }
}
