<?php

declare(strict_types=1);

namespace WicketAcc;

use HyperFields\Field;
use HyperFields\HyperFields;

// No direct access
defined('ABSPATH') || exit;

/**
 * HyperFields options pages for Wicket Account Centre.
 *
 * Registers the ACC Main Options page (stored in the wicket_acc_options
 * option) and, when the base plugin is absent, a separate Environments
 * fallback page (stored in the shared wicket_settings option that
 * wicket-wp-base-plugin would otherwise own).
 */
class InitOptions extends WicketAcc
{
    /**
     * Option name for the ACC main options (non-environment settings).
     */
    public const MAIN_OPTION_NAME = 'wicket_acc_options';

    /**
     * Option name for the shared Wicket settings array (environment fields).
     * Owned by wicket-wp-base-plugin when present; ACC only writes here as a
     * fallback when the base plugin is not installed.
     */
    public const SETTINGS_OPTION_NAME = 'wicket_settings';

    /**
     * Menu slug for the main ACC options page. Kept stable because
     * AdminSettings::reorderAccSubmenu() looks it up by this slug.
     */
    public const MAIN_MENU_SLUG = 'wicket_acc_options';

    /**
     * Menu slug for the environments fallback page (base plugin absent).
     */
    public const ENV_MENU_SLUG = 'wicket_acc_environments';

    /**
     * Parent menu slug (the My Account CPT admin screen).
     */
    private const PARENT_SLUG = 'edit.php?post_type=my-account';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMainOptionsPage']);
        add_action('admin_menu', [$this, 'registerEnvironmentsFallbackPage']);
    }

    /**
     * Register the ACC Main Options page.
     *
     * Stored in wicket_acc_options (HyperFields array storage).
     */
    public function registerMainOptionsPage(): void
    {
        $page = HyperFields::makeOptionPage(
            __('ACC Options', 'wicket-acc'),
            self::MAIN_MENU_SLUG
        )
            ->setOptionName(self::MAIN_OPTION_NAME)
            ->setMenuTitle(__('ACC Options', 'wicket-acc'))
            ->setCapability('manage_options')
            ->setParentSlug(self::PARENT_SLUG);

        $section = $page->addSection(
            'acc_main_options',
            __('Main Options', 'wicket-acc')
        );

        $section->addField(
            Field::make('radio', 'ac_localization', __('Centre/Center localization', 'wicket-acc'))
                ->setOptions([
                    'account-centre' => __('Account Centre', 'wicket-acc'),
                    'account-center' => __('Account Center', 'wicket-acc'),
                ])
                ->setDefault('account-centre')
        );

        $section->addField(
            Field::make('radio', 'acc_sidebar_location', __('Sidebar location', 'wicket-acc'))
                ->setOptions([
                    'left' => __('Left', 'wicket-acc'),
                    'right' => __('Right', 'wicket-acc'),
                ])
                ->setDefault('right')
        );

        // Note: HF's number field sanitizes to float without range enforcement.
        // Carbon's min/max attribute guard is lost. Do NOT "fix" with
        // setValidation(['min'=>1,'max'=>7]) — HF treats min/max as string
        // length bounds, not numeric bounds. If range enforcement is needed,
        // use $field->addArg('wps_validate', fn($v) => $v >= 1 && $v <= 7).
        $section->addField(
            Field::make('number', 'acc_profile_picture_size', __('Profile Picture Size (in MB)', 'wicket-acc'))
                ->setDefault(1)
                ->setHelp(__('Allowed range: 1 to 7 MB.', 'wicket-acc'))
        );

        $section->addField(
            Field::make('image', 'acc_profile_picture_default', __('Default profile picture', 'wicket-acc'))
        );

        $section->addField(
            Field::make('text', 'acc_profile_picture_mdp_schema', __('Profile picture MDP schema slug', 'wicket-acc'))
                ->setHelp(__('MDP schema slug that contains the profile picture field. Stable across tenants. Leave blank to use the plugin default.', 'wicket-acc'))
                ->setPlaceholder(Profile::PROFILE_IMAGE_SCHEMA_SLUG_DEFAULT)
        );

        $section->addField(
            Field::make('text', 'acc_profile_picture_mdp_field', __('Profile picture MDP field slug', 'wicket-acc'))
                ->setHelp(__('Field slug within the schema above that stores the profile picture URL. Leave blank to use the plugin default.', 'wicket-acc'))
                ->setPlaceholder(Profile::PROFILE_IMAGE_FIELD_SLUG_DEFAULT)
        );

        $section->addField(
            Field::make('checkbox', 'acc_global-headerbanner', __('Global sub-header', 'wicket-acc'))
        );

        $page->register();
    }

    /**
     * Register the Environments fallback page.
     *
     * Only registered when wicket-wp-base-plugin is NOT installed. The base
     * plugin owns the wicket_settings option and its own settings UI; when it
     * is present this fallback would be redundant and could confuse operators.
     *
     * Stored in wicket_settings (shared array, same shape the base plugin
     * reads via wicket_get_option()).
     */
    public function registerEnvironmentsFallbackPage(): void
    {
        if (class_exists('WicketWP')) {
            return;
        }

        $page = HyperFields::makeOptionPage(
            __('ACC Environment', 'wicket-acc'),
            self::ENV_MENU_SLUG
        )
            ->setOptionName(self::SETTINGS_OPTION_NAME)
            ->setMenuTitle(__('Environment', 'wicket-acc'))
            ->setCapability('manage_options')
            ->setParentSlug(self::PARENT_SLUG);

        // All env sections share one tab id so the page renders as a single
        // screen with one Save button. Using addSection() here would create a
        // separate tab per section (HF's addSection auto-creates a tab keyed by
        // the section id), forcing the admin to Save each section separately.
        $tab_id = 'acc_environment';

        $status_section = $page->addSectionToTab(
            $tab_id,
            'acc_environment_status',
            __('Connection Status', 'wicket-acc')
        );

        $status_section->addField(
            Field::make('html', 'acc_status_html', __('Status', 'wicket-acc'))
                ->setHtml($this->getApiStatusHtml())
        );

        $env_section = $page->addSectionToTab(
            $tab_id,
            'acc_environment_selection',
            __('Wicket Environment', 'wicket-acc')
        );

        $env_section->addField(
            Field::make('radio', 'wicket_admin_settings_environment', __('Wicket Environment', 'wicket'))
                ->setOptions([
                    'stage' => __('Staging', 'wicket'),
                    'prod' => __('Production', 'wicket'),
                ])
        );

        $prod_section = $page->addSectionToTab(
            $tab_id,
            'acc_environment_prod',
            __('Production Settings', 'wicket-acc')
        );

        $this->addEnvironmentFields($prod_section, 'prod');

        $stage_section = $page->addSectionToTab(
            $tab_id,
            'acc_environment_stage',
            __('Staging Settings', 'wicket-acc')
        );

        $this->addEnvironmentFields($stage_section, 'stage');

        $page->register();
    }

    /**
     * Add the five environment-specific fields (api_endpoint, secret_key,
     * person_id, parent_org, wicket_admin) for the given environment slug.
     *
     * @param \HyperFields\OptionsSection $section Target section.
     * @param string                      $env     'prod' or 'stage'.
     * @return void
     */
    private function addEnvironmentFields($section, string $env): void
    {
        $fields = [
            'api_endpoint' => [
                'label' => __('API Endpoint', 'wicket-acc'),
                'placeholder' => 'https://[client]-api.wicketcloud.com',
            ],
            'secret_key' => [
                'label' => __('JWT Secret Key', 'wicket-acc'),
                'placeholder' => '',
            ],
            'person_id' => [
                'label' => __('Person ID', 'wicket-acc'),
                'placeholder' => '',
            ],
            'parent_org' => [
                'label' => __('Parent Org', 'wicket-acc'),
                'placeholder' => '',
            ],
            'wicket_admin' => [
                'label' => __('Wicket Admin', 'wicket-acc'),
                'placeholder' => 'https://[client]-admin.wicketcloud.com',
            ],
        ];

        foreach ($fields as $suffix => $config) {
            $field = Field::make(
                'text',
                "wicket_admin_settings_{$env}_{$suffix}",
                $config['label']
            );

            if (!empty($config['placeholder'])) {
                $field->setPlaceholder($config['placeholder']);
            }

            $section->addField($field);
        }
    }

    /**
     * Get the API connection status HTML.
     *
     * @return string
     */
    public function getApiStatusHtml()
    {
        ob_start();

        // Use the plugin's internal method to check the connection.
        // initClient() returns false on failure, or the client object on success.
        $client = WACC()->Mdp()->initClient();
        $can_connect = $client !== false;

        ?>
        <div style="display: flex; align-items: center; padding: 10px 0;">
            <label style="margin-right: 10px; font-weight: bold;"><?php echo esc_html__('Status', 'wicket'); ?></label>
            <?php if ($can_connect) : ?>
                <span style="background-color: #7ad03a; color: white; padding: 5px 10px; border-radius: 3px;"><?php echo esc_html__('CONNECTED', 'wicket'); ?></span>
            <?php else : ?>
                <span style="background-color: #dc3232; color: white; padding: 5px 10px; border-radius: 3px;"><?php echo esc_html__('NOT CONNECTED', 'wicket'); ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
