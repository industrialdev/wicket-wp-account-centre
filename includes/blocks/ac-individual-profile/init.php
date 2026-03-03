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
     */
    protected array $mdp_json_fields = [];

    /**
     * Constructor.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;

        $json_fields = get_field('mdp_json_fields');
        $this->mdp_json_fields = json_decode($json_fields, true) ?? [];

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
        get_component('widget-profile-individual', [
            'fields' => $this->mdp_json_fields,
        ]);
    }
}
