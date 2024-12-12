<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Admin file for Wicket Account Centre.
 */

/**
 * Admin class of module.
 */
class AdminSettings extends WicketAcc
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
        //add_filter('acf/load_field', [$this, 'acf_field_description_centre_spelling']);
        add_action('admin_menu', [$this, 'admin_register_submenu_pages']);
        add_action('admin_init', [$this, 'migrate_acc_to_1_3']);
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
        /*add_submenu_page(
            'edit.php?post_type=my-account',
            'WC Endpoints',
            'WC Endpoints',
            'manage_options',
            admin_url('admin.php?page=wc-settings&tab=advanced'),
            null,
            12
        );*/
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

    /**
     * Modify the ACF field description for the Centre/Center spelling.
     *
     * @param array $field The ACF field array.
     *
     * @return array The modified ACF field array.
     */
    public function acf_field_description_centre_spelling($field)
    {
        // Only on the backend
        if (!is_admin()) {
            return $field;
        }

        // Check if it's the correct field by comparing the field name and parent
        if ($field['name'] == 'ac_localization' && $field['parent'] == 'group_66a9987e2539f') {
            // Get current meta value for ac_localization from the options page
            $current_value = get_field('ac_localization', 'option');

            // Check if the current value is an array and format it for display
            if (is_array($current_value)) {
                $current_value = implode(', ', array_map('esc_html', $current_value));
            } else {
                $current_value = esc_html($current_value); // In case it's not an array
            }

            // Append the current value to the field's instructions
            $field['instructions'] .= sprintf(__('<br/>Current DB value: %s', 'wicket-acc'), $current_value);
        }

        return $field;
    }

    /**
     * Migrate ACC to 1.3 with new CPT my-account.
     *
     * @return void
     */
    public function migrate_acc_to_1_3()
    {
        // Only on the backend
        if (!is_admin()) {
            return;
        }

        // Check if we've already changed the CPT to my-account
        if (get_option('wicket_acc_cpt_changed_to_my_account')) {
            // Check if the query parameter 'migrate_to_my_account' is not set or not equal to '1_3'
            if (!isset($_GET['migrate_to_my_account']) || $_GET['migrate_to_my_account'] !== '1_3') {
                return;
            }
        }

        global $wpdb;

        // Do we still have a CPT wicket_acc?
        if (get_post_type_object('wicket_acc')) {
            // Can we query the DB for posts with old CPT slug and change it?
            $wpdb->query("UPDATE $wpdb->posts SET post_type = 'my-account' WHERE post_type = 'wicket_acc'");
        }

        // Get current WooCommerce "myaccount" page ID
        $woo_my_account_page_id = wc_get_page_id('myaccount');

        if ($woo_my_account_page_id > 0) {
            // Does WC my account page have slug = my-account?
            $wc_page = get_post($woo_my_account_page_id);

            if ($wc_page->post_name == 'my-account') {
                // Change page slug to wc-account and title to "WC Account"
                $query = $wpdb->prepare(
                    "UPDATE $wpdb->posts SET post_name = 'wc-account', post_title = 'WC Account' WHERE ID = %d",
                    $woo_my_account_page_id
                );

                $wpdb->query($query);

                // Delete old slug meta
                delete_post_meta($woo_my_account_page_id, '_wp_old_slug');
                delete_post_meta($woo_my_account_page_id, '_wp_old_date');

                // Remove any reference to "my-account" from WooCommerce
                delete_option('_woocommerce_myaccount_page_id');
                delete_option('_woocommerce_myaccount_page_slug');
                delete_option('_woocommerce_myaccount_page_title');

                // Set the new page as the WooCommerce my-account page
                update_option('_woocommerce_myaccount_page_id', $woo_my_account_page_id);
                update_option('_woocommerce_myaccount_page_slug', 'wc-account');
                update_option('_woocommerce_myaccount_page_title', 'WC Account');
            }
        }

        // Migrate ACF field value from acc_page_account-centre to acc_page_dashboard
        if (get_field('acc_page_account-centre', 'option')) {
            $acc_page_account_centre_id = get_field('acc_page_account-centre', 'option');

            if ($acc_page_account_centre_id) {
                update_field('acc_page_dashboard', $acc_page_account_centre_id, 'option');

                // Change the slug of the index page to: dashboard
                $wpdb->update($wpdb->posts, ['post_name' => 'dashboard'], ['ID' => $acc_page_account_centre_id]);
            }

            // Delete old ACF field
            delete_field('acc_page_account-centre', 'option');
        }

        // Rename Global Banner page slug to acc_global-headerbanner
        if (get_field('acc_global-banner', 'option')) {
            $acc_old_headerbanner = get_field('acc_global-banner', 'option');

            if ($acc_old_headerbanner) {
                // Rename post slug
                $wpdb->update($wpdb->posts, ['post_name' => 'acc_global-headerbanner'], ['ID' => $acc_old_headerbanner]);

                // Update new ACF field
                update_field('acc_global-headerbanner', $acc_old_headerbanner, 'option');

                // Delete old ACF field
                delete_field('acc_global-banner', 'option');
            }
        }

        // Flush rewrite rules, because we've changed the CPT slug
        flush_rewrite_rules(false);

        // Empty caches
        wp_cache_flush();

        // Save an option to track that we've changed the CPT to my-account
        update_option('wicket_acc_cpt_changed_to_my_account', true);

        // Show a message to the user
        wp_die('<p align="center"><strong><h2>This screen will be only be shown once.</h2></strong></p><h1>Migrated Account Centre slug to my-account</h1> Next steps:<br/><br/>Ammend every Account Centre menu item with the correct URLs, from /account-centre/ to /my-account/ at the WP <a href="' . admin_url('nav-menus.php') . '" target="_blank">Menu editor</a>.<br/><br/>
		[If WPML is in use] Go to WPML Settings and <a href="' . admin_url('admin.php?page=tm/menu/settings') . '" target="_blank">make my-account CPT Translatable</a>.<br/>Then, on <a href="' . admin_url('admin.php?page=sitepress-multilingual-cms%2Fmenu%2Ftroubleshooting.php') . '" target="_blank">WPML troubleshooting page</a> run these tasks:<br/><ul><li>Set Language Information</li><li>Fix terms count</li><li>Fix post type assignments for translation</li></ul>[If WPML is in use] In the <a href="' . admin_url('edit.php?post_type=my-account') . '" target="_blank">ACC pages</a> make sure to change the language to FR from every page that could wrongly has EN as language (sadly, WPML does not offer an API to change its managed posts CPT, while preserve the page association between languages).<br/><br/>[If WPML is in use] You can use WPML to <a href="https://wpml.org/documentation/getting-started-guide/translating-page-slugs" target="_blank">translate ACC CPT slug</a>, for example, from my-account to mon-compte.<br/><br/>Go to WordPress <a href="' . admin_url('options-permalink.php') . '" target="_blank">Permalinks Settings</a> and save them, to flush WP rewrite rules.<br/><br/>Done.');
    }
}
