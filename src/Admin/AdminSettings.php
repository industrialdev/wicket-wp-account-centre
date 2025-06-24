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
            'acc_page_dashboard',
            'acc_page_edit-profile',
            'acc_page_events',
            'acc_page_events-past',
            'acc_page_jobs',
            'acc_page_job-post',
            'acc_page_payments-methods',
            'acc_page_subscriptions',
            'acc_page_membership-history',
            'acc_page_purchase-history',
            'acc_page_change-password',
            'acc_page_orgman-index',
            'acc_page_orgman-profile',
            'acc_page_orgman-members',
        ];

        foreach ($acf_fields_to_migrate as $field_name) {
            $value = get_field($field_name, 'option');

            if (str_starts_with($field_name, 'acc_page_')) {
                $post_id = !empty($value) ? intval($value) : 0;

                // If the post ID is valid and the post exists, save the value in an array of strings.
                if ($post_id > 0 && get_post_status($post_id)) {
                    $post_type = get_post_type($post_id);
                    $new_value = ['post:' . $post_type . ':' . $post_id];
                    carbon_set_theme_option($field_name, $new_value);
                } else {
                    // Otherwise, purge the invalid/empty value from the database by saving an empty array.
                    carbon_set_theme_option($field_name, []);
                }
            } else {
                // For non-association fields, just save the value directly.
                carbon_set_theme_option($field_name, $value);
            }
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
}
