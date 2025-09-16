<?php

namespace WicketAcc\Admin;

// No direct access
defined('ABSPATH') || exit;

/*
 * Admin file for Wicket Account Centre.
 */

/*
 * Admin class of module.
 */

class AdminSettings extends \WicketAcc\WicketAcc
{
    /**
     * Constructor of class.
     */
    public function __construct()
    {
        // Data migration
        add_action('admin_init', [$this, 'maybeMigrateOldACFOptions']);

        // Keep admin assets enqueue
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Add custom links to the admin menu
        add_action('admin_menu', [$this, 'accCustomAdminLinks']);

        // Reorder the submenu to place our options page third
        add_action('admin_menu', [$this, 'reorderAccSubmenu'], 99);

        // Check ACF JSON folder permissions for legacy Blocks
        add_action('admin_notices', [$this, 'checkAcfJsonFolderPermissions']);

    }

    /**
     * Reorder the 'My Account' submenu items to place 'ACC Main Options' third.
     *
     * This works by finding our options page in the global $submenu array,
     * removing it, and then re-inserting it at the desired position.
     */
    public function reorderAccSubmenu()
    {
        global $submenu;
        $parent_slug = 'edit.php?post_type=my-account';
        $options_page_slug = 'crb_carbon_fields_container_wicket_acc_options.php';

        // Check if the submenu for our parent exists
        if (empty($submenu[$parent_slug])) {
            return;
        }

        $menu_items = $submenu[$parent_slug];
        $options_page_item = null;

        // Find and remove the 'ACC Main Options' item
        foreach ($menu_items as $key => $item) {
            if ($item[2] === $options_page_slug) {
                $options_page_item = $item;
                unset($menu_items[$key]);
                break;
            }
        }

        // If we found our options page, re-insert it at the third position (index 2)
        if ($options_page_item) {
            $new_menu = array_values($menu_items);
            array_splice($new_menu, 2, 0, [$options_page_item]);
            $submenu[$parent_slug] = $new_menu;
        }
    }

    /**
     * Add custom links to the admin menu under 'My Account'.
     * These links were part of the original ACF implementation.
     */
    public function accCustomAdminLinks()
    {
        $parent_slug = 'edit.php?post_type=my-account';

        // Only show the 'Global Header' link if the feature is enabled in the options.
        $acc_banner_enabled = WACC()->getOption('acc_global-headerbanner', false);
        if ($acc_banner_enabled) {
            $global_header_post_id = WACC()->getGlobalHeaderBannerPageId();

            add_submenu_page(
                $parent_slug,
                __('Global Header', 'wicket-acc'),
                __('Global Header', 'wicket-acc'),
                'manage_options',
                'post.php?post=' . $global_header_post_id . '&action=edit'
            );
        }

        add_submenu_page(
            $parent_slug,
            __('Menu Editor', 'wicket-acc'),
            __('Menu Editor', 'wicket-acc'),
            'manage_options',
            'nav-menus.php'
        );
    }

    /**
     * Run a one-time migration from ACF options to Carbon Fields options.
     */
    public function maybeMigrateOldACFOptions()
    {
        // Check if the migration has already run
        if (get_option('wicket_acc_cf_migration_complete')) {
            return;
        }

        $acf_fields_to_migrate = [
            'ac_localization',
            'acc_sidebar_location',
            'acc_profile_picture_size',
            'acc_profile_picture_default',
            'acc_global-headerbanner',
        ];

        foreach ($acf_fields_to_migrate as $field_name) {
            $value = get_field($field_name, 'option');
            carbon_set_theme_option($field_name, $value);
        }

        // Mark the migration as complete
        update_option('wicket_acc_cf_migration_complete', true);
    }

    /**
     * Enqueue scripts and styles for admin.
     */
    public function enqueueAdminAssets()
    {
        // Only on the backend
        if (!is_admin()) {
            return;
        }

        wp_enqueue_script('wicket-acc-admin', WICKET_ACC_URL . 'assets/js/wicket-acc-admin-main.js', ['jquery'], '1.0', true);
        wp_enqueue_style('wicket-acc-admin', WICKET_ACC_URL . 'assets/css/wicket-acc-admin-main.css', false, '1.0');
    }

    /**
     * On ACF pages at the backend,
     * warn the user if /includes/acf-json/ folder is not writable.
     */
    public function checkAcfJsonFolderPermissions()
    {
        // Only on the backend
        if (!is_admin()) {
            return;
        }

        // Comment out environment check for testing
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
            echo '<div class="notice notice-error"><p><strong>ACF JSON folder not writable</strong></p><p>The ' . esc_html($acf_json_folder) . ' folder is not writable. Please make sure the folder is writable by the server.</p><p>Not solving this issue, will result in ACF fields not being saved at plugin level and will not be visible on other sites backend.</p></div>';
        }
    }
}
