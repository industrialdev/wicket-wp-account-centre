<?php

namespace WicketAcc\Blocks\AdditionalInfo;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Additional Info Block.
 **/
class init extends Blocks
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;

        // Display the block
        $this->init_block();
    }

    /**
     * Init block.
     *
     * @return void
     */
    protected function init_block()
    {
        $wicket_settings = get_wicket_settings();
        $person_id = wicket_current_person_uuid();
        $environment = wicket_get_option('wicket_admin_settings_environment');
        $acf_ai_schema = get_field('additional_info_schema');
        $acf_resource_type = get_field('additional_info_resource_type');
        $acf_org_uuid = get_field('additional_info_organization_uuid') ?? '';
        $acf_use_slugs = get_field('additional_info_use_slugs_instead_of_schema_ids');

        if (!$person_id) {
            return;
        }

        if (empty($acf_org_uuid) && isset($_GET['org_id'])) {
            $acf_org_uuid = $_GET['org_id'];
        }

        // Child organization compatibility. Needs to be after the check for an org_id
        if (isset($_GET['child_org_id']) && !empty($_GET['child_org_id'])) {
            $parent_org_uuid = $acf_org_uuid;
            $acf_org_uuid = $_GET['child_org_id'];
        }

        // Create schemas_and_overrides payload for component
        $schemas_and_overrides = [];

        if (!is_array($acf_ai_schema)) {
            return;
        }

        foreach ($acf_ai_schema as $ai_to_add) {
            // First determine if the conditional date range activation is in use
            $activate_date_from = $ai_to_add['resource_override_activation_range']['date_range_from'] ?? '';
            $activate_date_to = $ai_to_add['resource_override_activation_range']['date_range_to'] ?? '';
            $date_range_conditional_in_use = false;
            $date_range_conditional_is_active = false;

            if (!empty($activate_date_from) && !empty($activate_date_to)) {
                $date_range_conditional_in_use = true;

                $date_from_timestamp = strtotime($activate_date_from);
                $date_to_timestamp = strtotime($activate_date_to);
                $now_timestamp = time();

                if (($date_from_timestamp <= $now_timestamp) && ($now_timestamp <= $date_to_timestamp)) {
                    $date_range_conditional_is_active = true;
                }
            }

            // Build array based on slug vs ID
            if ($acf_use_slugs) {
                $schema_slug = $ai_to_add['schema_slug'] ?? '';
                $resource_slug = $ai_to_add['schema_override_resource_slug'] ?? '';

                if (empty($schema_slug)) {
                    continue;
                }

                $ai_added = false;
                if ($date_range_conditional_in_use) {
                    if ($date_range_conditional_is_active && !empty($resource_slug) && $ai_to_add['schema_use_override']) {
                        $schemas_and_overrides[] = [
                            'slug'           => $schema_slug,
                            'resourceSlug'   => $resource_slug,
                            'showAsRequired' => $ai_to_add['show_as_required'] ?? false,
                        ];
                        $ai_added = true;
                    }
                } else {
                    if (!empty($resource_slug) && $ai_to_add['schema_use_override']) {
                        $schemas_and_overrides[] = [
                            'slug'           => $schema_slug,
                            'resourceSlug'   => $resource_slug,
                            'showAsRequired' => $ai_to_add['show_as_required'] ?? false,
                        ];
                        $ai_added = true;
                    }
                }

                if (!$ai_added) {
                    $schemas_and_overrides[] = [
                        'slug'           => $schema_slug,
                        'showAsRequired' => $ai_to_add['show_as_required'] ?? false,
                    ];
                }
            } else {
                $schema_uuid = '';
                $resource_uuid = '';

                if ($environment == 'prod') {
                    $schema_uuid = $ai_to_add['schema_uuid_prod'] ?? '';
                } else {
                    $schema_uuid = $ai_to_add['schema_uuid_stage'] ?? '';
                }

                if ($ai_to_add['schema_use_override']) {
                    if ($environment == 'prod') {
                        $resource_uuid = $ai_to_add['schema_override_resource_uuid_prod'] ?? '';
                    } else {
                        $resource_uuid = $ai_to_add['schema_override_resource_uuid_stage'] ?? '';
                    }
                }

                if (empty($schema_uuid)) {
                    continue;
                }

                $ai_added = false;
                if ($date_range_conditional_in_use) {
                    if ($date_range_conditional_is_active && !empty($resource_uuid)) {
                        $schemas_and_overrides[] = [
                            'id'             => $schema_uuid,
                            'resourceId'     => $resource_uuid,
                            'showAsRequired' => $ai_to_add['show_as_required'] ?? false,
                        ];
                        $ai_added = true;
                    }
                } else {
                    if (!empty($resource_uuid)) {
                        $schemas_and_overrides[] = [
                            'id'             => $schema_uuid,
                            'resourceId'     => $resource_uuid,
                            'showAsRequired' => $ai_to_add['show_as_required'] ?? false,
                        ];
                        $ai_added = true;
                    }
                }

                if (!$ai_added) {
                    $schemas_and_overrides[] = [
                        'id'             => $schema_uuid,
                        'showAsRequired' => $ai_to_add['show_as_required'] ?? false,
                    ];
                }
            }
        }

        get_component('widget-additional-info', [
            'classes'               => $this->block['className'] ?? [],
            'resource_type'         => $acf_resource_type,
            'org_uuid'              => $acf_org_uuid,
            'schemas_and_overrides' => $schemas_and_overrides,
        ]);
    }
}
