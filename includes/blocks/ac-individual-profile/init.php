<?php

namespace WicketAcc\Blocks\IndividualProfile;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Individual Profile Block.
 **/
class init extends Blocks
{
    /**
     * @var string
     * @deprecated Superseded by mdp_json_config; kept for legacy saved blocks.
     */
    protected array $mdp_json_fields = [];

    /**
     * @var array
     * @deprecated Superseded by mdp_json_config; kept for legacy saved blocks.
     */
    protected array $mdp_json_sections = [];

    /**
     * @var array
     */
    protected array $mdp_json_config = [];

    /**
     * Constructor.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;

        // Deprecated: mdp_json_fields/mdp_json_sections are superseded by
        // mdp_json_config (see init_block()); kept working for existing saved blocks.
        $json_fields = get_field('mdp_json_fields');
        $this->mdp_json_fields = json_decode($json_fields, true) ?? [];

        $json_sections = get_field('mdp_json_sections');
        $this->mdp_json_sections = json_decode($json_sections, true) ?? [];

        $json_config = get_field('mdp_json_config');
        $this->mdp_json_config = json_decode((string) $json_config, true) ?? [];

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
        if (is_array($this->mdp_json_config) && $this->mdp_json_config !== []) {
            get_component('widget-profile-individual', [
                'widget_config' => $this->mdp_json_config,
            ]);

            return;
        }

        // Deprecated fallback: mdp_json_fields/mdp_json_sections only render when
        // mdp_json_config is empty/invalid.
        get_component('widget-profile-individual', [
            'fields'   => $this->mdp_json_fields,
            'sections' => $this->mdp_json_sections,
        ]);
    }
}
