<?php

namespace Wicket_Acc\Blocks;

use Wicket_Acc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Additional Info Block
 **/
if (!class_exists('Block_Additional_Info')) {
	class Block_Additional_Info extends Wicket_Acc
	{
		/**
		 * Constructor
		 */
		public function __construct(
			protected array $block     = [],
			protected bool $is_preview = false,
		) {
			$this->block      = $block;
			$this->is_preview = $is_preview;

			// Display the block
			$this->init_block();
		}

		/**
		 * Init block
		 *
		 * @return void
		 */
		protected function init_block()
		{
			$wicket_settings   = get_wicket_settings();
			$person_id         = wicket_current_person_uuid();
			$environment       = wicket_get_option('wicket_admin_settings_environment');
			$acf_ai_schema     = get_field('additional_info_schema');
			$acf_resource_type = get_field('additional_info_resource_type');
			$acf_org_uuid      = get_field('additional_info_organization_uuid') ?? '';

			if (!$person_id) {
				return;
			}

			// Create schemas_and_overrides payload for component
			$schemas_and_overrides = [];

			foreach ($acf_ai_schema as $ai_to_add) {
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

				if (!empty($resource_uuid)) {
					$schemas_and_overrides[] = [
						'id'         => $schema_uuid,
						'resourceId' => $resource_uuid
					];
				} else {
					$schemas_and_overrides[] = [
						'id'         => $schema_uuid
					];
				}
			}

			get_component('widget-additional-info', [
				'classes'                          => [],
				'resource_type'                    => $acf_resource_type,
				'org_uuid'												 => $acf_org_uuid,
				'schemas_and_overrides'            => $schemas_and_overrides,
			]);
		}
	}
}
