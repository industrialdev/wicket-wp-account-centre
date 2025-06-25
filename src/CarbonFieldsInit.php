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

        if (!class_exists('Wicket_Main')) {
            $datastore = new WicketSettingsDatastore();

            /** @var Field\Html_Field $status_field */
            $status_field = Field::make('html', 'crb_status_html');
            $status_field->set_html($this->get_api_status_html());

            /** @var Field\Radio_Field $environment_field */
            $environment_field = Field::make('radio', 'wicket_admin_settings_environment', __('Wicket Environment'));
            $environment_field->set_options([
                'stage' => __('Staging', 'wicket'),
                'prod' => __('Production', 'wicket'),
            ])->set_datastore($datastore);

            /** @var Field\Separator_Field $prod_separator */
            $prod_separator = Field::make('separator', 'crb_separator_prod', __('Production Settings'));

            /** @var Field\Text_Field $prod_api_endpoint */
            $prod_api_endpoint = Field::make('text', 'wicket_admin_settings_prod_api_endpoint', __('API Endpoint (Production)'));
            $prod_api_endpoint->set_attribute('placeholder', 'https://[client]-api.wicketcloud.com')->set_datastore($datastore);

            /** @var Field\Text_Field $prod_secret_key */
            $prod_secret_key = Field::make('text', 'wicket_admin_settings_prod_secret_key', __('JWT Secret Key (Production)'));
            $prod_secret_key->set_datastore($datastore);

            /** @var Field\Text_Field $prod_person_id */
            $prod_person_id = Field::make('text', 'wicket_admin_settings_prod_person_id', __('Person ID (Production)'));
            $prod_person_id->set_datastore($datastore);

            /** @var Field\Text_Field $prod_parent_org */
            $prod_parent_org = Field::make('text', 'wicket_admin_settings_prod_parent_org', __('Parent Org (Production)'));
            $prod_parent_org->set_datastore($datastore);

            /** @var Field\Text_Field $prod_wicket_admin */
            $prod_wicket_admin = Field::make('text', 'wicket_admin_settings_prod_wicket_admin', __('Wicket Admin (Production)'));
            $prod_wicket_admin->set_attribute('placeholder', 'https://[client]-admin.wicketcloud.com')->set_datastore($datastore);

            /** @var Field\Separator_Field $stage_separator */
            $stage_separator = Field::make('separator', 'crb_separator_stage', __('Staging Settings'));

            /** @var Field\Text_Field $stage_api_endpoint */
            $stage_api_endpoint = Field::make('text', 'wicket_admin_settings_stage_api_endpoint', __('API Endpoint (Staging)'));
            $stage_api_endpoint->set_attribute('placeholder', 'https://[client]-api.wicketcloud.com')->set_datastore($datastore);

            /** @var Field\Text_Field $stage_secret_key */
            $stage_secret_key = Field::make('text', 'wicket_admin_settings_stage_secret_key', __('JWT Secret Key (Staging)'));
            $stage_secret_key->set_datastore($datastore);

            /** @var Field\Text_Field $stage_person_id */
            $stage_person_id = Field::make('text', 'wicket_admin_settings_stage_person_id', __('Person ID (Staging)'));
            $stage_person_id->set_datastore($datastore);

            /** @var Field\Text_Field $stage_parent_org */
            $stage_parent_org = Field::make('text', 'wicket_admin_settings_stage_parent_org', __('Parent Org (Staging)'));
            $stage_parent_org->set_datastore($datastore);

            /** @var Field\Text_Field $stage_wicket_admin */
            $stage_wicket_admin = Field::make('text', 'wicket_admin_settings_stage_wicket_admin', __('Wicket Admin (Staging)'));
            $stage_wicket_admin->set_attribute('placeholder', 'https://[client]-admin.wicketcloud.com')->set_datastore($datastore);

            $main_options_container->add_tab(__('Environments'), [
                $status_field,
                $environment_field,
                $prod_separator,
                $prod_api_endpoint,
                $prod_secret_key,
                $prod_person_id,
                $prod_parent_org,
                $prod_wicket_admin,
                $stage_separator,
                $stage_api_endpoint,
                $stage_secret_key,
                $stage_person_id,
                $stage_parent_org,
                $stage_wicket_admin,
            ]);
        }

        $has_run = true;
    }

    /**
     * Get the API connection status HTML.
     *
     * @return string
     */
    public function get_api_status_html()
    {
        ob_start();

        // Use the plugin's internal method to check the connection.
        // initClient() returns false on failure, or the client object on success.
        $client = WACC()->MdpApi->initClient();
        $can_connect = $client !== false;

        ?>
        <div style="display: flex; align-items: center; padding: 10px 0;">
            <label style="margin-right: 10px; font-weight: bold;"><?php echo __('Status', 'wicket'); ?></label>
            <?php if ($can_connect) : ?>
                <span style="background-color: #7ad03a; color: white; padding: 5px 10px; border-radius: 3px;"><?php echo __('CONNECTED', 'wicket'); ?></span>
            <?php else : ?>
                <span style="background-color: #dc3232; color: white; padding: 5px 10px; border-radius: 3px;"><?php echo __('NOT CONNECTED', 'wicket'); ?></span>
            <?php endif; ?>
        </div>
<?php
                return ob_get_clean();
    }
}
