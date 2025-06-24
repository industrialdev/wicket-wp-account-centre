<?php

namespace WicketAcc;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

// No direct access
defined('ABSPATH') || exit;

/**
 * Carbon Fields Init Class.
 */
class CarbonFieldsInit extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Carbon Fields setup.
        add_action('after_setup_theme', [$this, 'bootCarbonFields']);
        add_action('carbon_fields_register_fields', [$this, 'mainACCOptionsPage']);
    }

    /**
     * Boot Carbon Fields.
     */
    public function bootCarbonFields()
    {
        // Boot Carbon Fields. The autoloader now handles everything.
        \Carbon_Fields\Carbon_Fields::boot();
    }

    /**
     * Create the plugin options page with Carbon Fields.
     */
    public function mainACCOptionsPage()
    {
        // Ensure this runs only once per request, as it may be hooked to both init and carbon_fields_register_fields
        static $has_run = false;
        if ($has_run) {
            return;
        }
        $main_options_container = Container::make('theme_options', 'wicket_acc_options', __('ACC Main Options'))
            ->set_page_parent('edit.php?post_type=my-account')
            ->add_tab(__('Main Options'), [
                Field::make('radio', 'ac_localization', __('Centre/Center localization'))
                    ->set_options([
                        'account-centre' => 'Account Centre',
                        'account-center' => 'Account Center',
                    ])
                    ->set_default_value('account-centre'),
                Field::make('radio', 'acc_sidebar_location', __('Sidebar location'))
                    ->set_options([
                        'left' => 'Left',
                        'right' => 'Right',
                    ])
                    ->set_default_value('right'),
                Field::make('text', 'acc_profile_picture_size', __('Profile Picture Size (in MB)'))
                    ->set_attribute('type', 'number')
                    ->set_default_value(1)
                    ->set_attribute('min', 1)
                    ->set_attribute('max', 7),
                Field::make('image', 'acc_profile_picture_default', __('Default profile picture')),
                Field::make('checkbox', 'acc_global-headerbanner', __('Global sub-header')),
            ])
            ->add_tab(__('ACC Pages'), [
                Field::make('association', 'acc_page_dashboard', __('Page: Account Centre Dashboard'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_edit-profile', __('Page: Edit Profile'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_events', __('Page: My Events'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_events-past', __('Page: Past Events'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_jobs', __('Page: Jobs'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_job-post', __('Page: Post a Job'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_payments-methods', __('Page: Payments Methods'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_subscriptions', __('Page: Subscriptions'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_membership-history', __('Page: Membership History'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_purchase-history', __('Page: Purchase History'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_change-password', __('Page: Change Password'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_orgman-index', __('Page: Organization Management Index'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_orgman-profile', __('Page: Organization Management Profile'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
                Field::make('association', 'acc_page_orgman-members', __('Page: Organization Management Members'))
                    ->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1),
            ]);

        $has_run = true;
    }
}
