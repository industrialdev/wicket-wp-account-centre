<?php

namespace WicketAcc\Admin;

// No direct access
defined('ABSPATH') || exit;

/**
 * Admin file for Wicket Account Centre.
 */

/**
 * Admin class of module.
 */
class AdminSettings extends \WicketAcc\WicketAcc
{
    /**
     * Constructor of class.
     */
    public function __construct()
    {
        add_action('acf/init', [$this, 'admin_register_options_page']);
        add_action('admin_notices', [$this, 'acf_json_folder_permissions']);
        add_action('admin_enqueue_scripts', [$this, 'acc_admin_assets']);
        add_action('acf/options_page/save', [$this, 'acc_options_save'], 10, 2);
        add_action('admin_menu', [$this, 'admin_register_submenu_pages']);
    }

    /**
     * Enqueue scripts and styles for admin.
     */
    public function acc_admin_assets()
    {
        // Only on the backend
        if (!is_admin()) {
            return;
        }

        wp_enqueue_script('wicket-acc-admin', WICKET_ACC_URL . 'assets/js/wicket-acc-admin-main.js', ['jquery'], '1.0', true);
        wp_enqueue_style('wicket-acc-admin', WICKET_ACC_URL . 'assets/css/wicket-acc-admin-main.css', false, '1.0');
    }

    /**
     * Register options page for ACF.
     */
    public function admin_register_options_page()
    {
        // Only on the backend
        if (!is_admin()) {
            return;
        }

        // Check function exists.
        if (function_exists('acf_add_options_sub_page')) {
            // Add sub page under custom post type
            acf_add_options_sub_page([
                'page_title'  => 'ACC Options',
                'menu_title'  => 'ACC Options',
                'parent_slug' => 'edit.php?post_type=my-account',
                'capability'  => 'manage_options',
                'menu_slug'   => 'wicket_acc_options',
                'redirect'    => false,
                'position'    => 2,
            ]);
        }
    }

    /**
     * Register submenu pages for my-account CPT.
     */
    public function admin_register_submenu_pages()
    {
        // Only on the backend
        if (!is_admin()) {
            return;
        }

        // Shortcut: global banner page
        $acc_global_headerbanner = get_field('acc_global-headerbanner', 'option');

        if ($acc_global_headerbanner) {
            $acc_global_headerbanner_page = get_page_by_path('acc_global-headerbanner', OBJECT, 'my-account');

            $page_id = absint($acc_global_headerbanner_page->ID);

            // Is WPML enabled?
            if (function_exists('icl_get_languages')) {
                $page_id = apply_filters('wpml_object_id', $page_id, 'my-account', true, WACC()->getLanguage());
            }

            $edit_link = get_edit_post_link($page_id);

            add_submenu_page(
                'edit.php?post_type=my-account',
                'Global Header',
                'Global Header',
                'manage_options',
                $edit_link,
                null,
                10
            );
        }

        // Shortcut: Menu Editor
        add_submenu_page(
            'edit.php?post_type=my-account',
            'Menu Editor',
            'Menu Editor',
            'manage_options',
            admin_url('nav-menus.php'),
            null,
            11
        );

        // Shortcut: WooCommerce Endpoints
        // We shouldn't let implementators change this. Why? because of the issues this causes when used with WPML.
    }

    /**
     * On ACF pages at the backend,
     * warn the user if /includes/acf-json/ folder is not writable.
     */
    public function acf_json_folder_permissions()
    {
        // Only on the backend
        if (!is_admin()) {
            return;
        }

        // Exit if not in development/local environment
        if (
            !(
                (defined('WP_ENV') && WP_ENV === 'development') ||
                (defined('WP_ENVIRONMENT_TYPE') && in_array(WP_ENVIRONMENT_TYPE, ['local', 'development'], true))
            )
        ) {
            return;
        }

        $acf_json_folder = WICKET_ACC_PATH . 'includes/acf-json/';

        if (!is_writable($acf_json_folder)) {
            echo '<div class="notice notice-error"><p><strong>ACF JSON folder not writable</strong></p><p>The ' . $acf_json_folder . ' folder is not writable. Please make sure the folder is writable by the server.</p><p>Not solving this issue, will result in ACF fields not being saved at plugin level and will not be visible on other sites backend.</p></div>';
        }
    }

    /**
     * Actions when saving ACC Options (ACF based) in the backend.
     */
    public function acc_options_save($post_id, $menu_slug)
    {
        // Only on the backend
        if (!is_admin()) {
            return;
        }

        if ($menu_slug !== 'wicket_acc_options') {
            return;
        }

        // Flush rewrite rules
        flush_rewrite_rules(false);
    }
}
