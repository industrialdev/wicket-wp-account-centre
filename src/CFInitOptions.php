<?php

namespace WicketAcc;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

// No direct access
defined('ABSPATH') || exit;

/**
 * Carbon Fields Init Class.
 */
class CFInitOptions extends WicketAcc
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
        // Fix for Pantheon servers: Define Carbon Fields URL before auto-detection
        // On Pantheon, the directory_to_url method fails to correctly identify
        // the plugin's vendor directory, resulting in incorrect asset URLs
        if (!defined('Carbon_Fields\URL')) {
            $carbon_fields_url = WICKET_ACC_URL . 'vendor/htmlburger/carbon-fields/';
            define('Carbon_Fields\URL', $carbon_fields_url);
        }

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

        $main_options_container->set_page_parent('edit.php?post_type=my-account');

        $main_options_container->add_tab(
            __('Main Options'),
            [
                Field::make('radio', 'ac_localization', __('Centre/Center localization'))
                    ->set_options([
                        'account-centre' => 'Account Centre',
                        'account-center' => 'Account Center',
                    ])->set_default_value('account-centre'),
                Field::make('radio', 'acc_sidebar_location', __('Sidebar location'))
                    ->set_options([
                        'left' => 'Left',
                        'right' => 'Right',
                    ])->set_default_value('right'),
                Field::make('text', 'acc_profile_picture_size', __('Profile Picture Size (in MB)'))
                    ->set_attribute('type', 'number')
                    ->set_default_value(1)
                    ->set_attribute('min', 1)
                    ->set_attribute('max', 7),
                Field::make('image', 'acc_profile_picture_default', __('Default profile picture')),
                Field::make('checkbox', 'acc_global-headerbanner', __('Global sub-header')),
            ]
        );

        if (!class_exists('WicketWP')) {
            $datastore = new CFWicketSettingsDatastore();

            $main_options_container->add_tab(
                __('Environments'),
                [
                    Field::make('html', 'crb_status_html')->set_html($this->get_api_status_html()),
                    Field::make('radio', 'wicket_admin_settings_environment', __('Wicket Environment'))
                        ->set_options([
                            'stage' => __('Staging', 'wicket'),
                            'prod' => __('Production', 'wicket'),
                        ])->set_datastore($datastore),
                    Field::make('separator', 'crb_separator_prod', __('Production Settings')),
                    Field::make('text', 'wicket_admin_settings_prod_api_endpoint', __('API Endpoint (Production)'))
                        ->set_attribute('placeholder', 'https://[client]-api.wicketcloud.com')->set_datastore($datastore),
                    Field::make('text', 'wicket_admin_settings_prod_secret_key', __('JWT Secret Key (Production)'))
                        ->set_datastore($datastore),
                    Field::make('text', 'wicket_admin_settings_prod_person_id', __('Person ID (Production)'))
                        ->set_datastore($datastore),
                    Field::make('text', 'wicket_admin_settings_prod_parent_org', __('Parent Org (Production)'))
                        ->set_datastore($datastore),
                    Field::make('text', 'wicket_admin_settings_prod_wicket_admin', __('Wicket Admin (Production)'))
                        ->set_attribute('placeholder', 'https://[client]-admin.wicketcloud.com')->set_datastore($datastore),
                    Field::make('separator', 'crb_separator_stage', __('Staging Settings')),
                    Field::make('text', 'wicket_admin_settings_stage_api_endpoint', __('API Endpoint (Staging)'))
                        ->set_attribute('placeholder', 'https://[client]-api.wicketcloud.com')->set_datastore($datastore),
                    Field::make('text', 'wicket_admin_settings_stage_secret_key', __('JWT Secret Key (Staging)'))
                        ->set_datastore($datastore),
                    Field::make('text', 'wicket_admin_settings_stage_person_id', __('Person ID (Staging)'))
                        ->set_datastore($datastore),
                    Field::make('text', 'wicket_admin_settings_stage_parent_org', __('Parent Org (Staging)'))
                        ->set_datastore($datastore),
                    Field::make('text', 'wicket_admin_settings_stage_wicket_admin', __('Wicket Admin (Staging)'))
                        ->set_attribute('placeholder', 'https://[client]-admin.wicketcloud.com')->set_datastore($datastore),
                ]
            );
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

        $client = WACC()->Mdp()->initClient();

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
