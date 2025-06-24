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

        /** @var Container\Theme_Options_Container $main_options_container */
        $main_options_container = Container::make('theme_options', 'wicket_acc_options', __('ACC Options'));

        /** @var Field\Radio_Field $localization_field */
        $localization_field = Field::make('radio', 'ac_localization', __('Centre/Center localization'));
        $localization_field->set_options([
            'account-centre' => 'Account Centre',
            'account-center' => 'Account Center',
        ])->set_default_value('account-centre');

        /** @var Field\Radio_Field $sidebar_location_field */
        $sidebar_location_field = Field::make('radio', 'acc_sidebar_location', __('Sidebar location'));
        $sidebar_location_field->set_options([
            'left' => 'Left',
            'right' => 'Right',
        ])->set_default_value('right');

        $main_options_container->set_page_parent('edit.php?post_type=my-account')
            ->add_tab(__('Main Options'), [
                $localization_field,
                $sidebar_location_field,
                Field::make('text', 'acc_profile_picture_size', __('Profile Picture Size (in MB)'))
                    ->set_attribute('type', 'number')
                    ->set_default_value(1)
                    ->set_attribute('min', 1)
                    ->set_attribute('max', 7),
                Field::make('image', 'acc_profile_picture_default', __('Default profile picture')),
                Field::make('checkbox', 'acc_global-headerbanner', __('Global sub-header')),
            ]);

        // ACC Pages Fields
        /** @var Field\Association_Field $dashboard_page */
        $dashboard_page = Field::make('association', 'acc_page_dashboard', __('Page: Account Centre Dashboard'));
        $dashboard_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $edit_profile_page */
        $edit_profile_page = Field::make('association', 'acc_page_edit-profile', __('Page: Edit Profile'));
        $edit_profile_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $events_page */
        $events_page = Field::make('association', 'acc_page_events', __('Page: My Events'));
        $events_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $past_events_page */
        $past_events_page = Field::make('association', 'acc_page_events-past', __('Page: Past Events'));
        $past_events_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $jobs_page */
        $jobs_page = Field::make('association', 'acc_page_jobs', __('Page: Jobs'));
        $jobs_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $job_post_page */
        $job_post_page = Field::make('association', 'acc_page_job-post', __('Page: Post a Job'));
        $job_post_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $payments_methods_page */
        $payments_methods_page = Field::make('association', 'acc_page_payments-methods', __('Page: Payments Methods'));
        $payments_methods_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $subscriptions_page */
        $subscriptions_page = Field::make('association', 'acc_page_subscriptions', __('Page: Subscriptions'));
        $subscriptions_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $membership_history_page */
        $membership_history_page = Field::make('association', 'acc_page_membership-history', __('Page: Membership History'));
        $membership_history_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $purchase_history_page */
        $purchase_history_page = Field::make('association', 'acc_page_purchase-history', __('Page: Purchase History'));
        $purchase_history_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $change_password_page */
        $change_password_page = Field::make('association', 'acc_page_change-password', __('Page: Change Password'));
        $change_password_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $orgman_index_page */
        $orgman_index_page = Field::make('association', 'acc_page_orgman-index', __('Page: Organization Management Index'));
        $orgman_index_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $orgman_profile_page */
        $orgman_profile_page = Field::make('association', 'acc_page_orgman-profile', __('Page: Organization Management Profile'));
        $orgman_profile_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        /** @var Field\Association_Field $orgman_members_page */
        $orgman_members_page = Field::make('association', 'acc_page_orgman-members', __('Page: Organization Management Members'));
        $orgman_members_page->set_types([['type' => 'post', 'post_type' => 'my-account']])->set_max(1);

        $main_options_container->add_tab(__('ACC Pages'), [
            $dashboard_page,
            $edit_profile_page,
            $events_page,
            $past_events_page,
            $jobs_page,
            $job_post_page,
            $payments_methods_page,
            $subscriptions_page,
            $membership_history_page,
            $purchase_history_page,
            $change_password_page,
            $orgman_index_page,
            $orgman_profile_page,
            $orgman_members_page,
        ]);

        $has_run = true;
    }
}
