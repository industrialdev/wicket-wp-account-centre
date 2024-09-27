<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Block: Base Block
 *
 **/
class Block_BaseBlock extends WicketAcc
{
    /**
     * Constructor
     */
    public function __construct(
        protected array $block     = [],
        protected bool $is_preview = false,
        protected ?Blocks $blocks  = null,
    ) {
        $this->block        = $block;
        $this->is_preview   = $is_preview;
        $this->blocks       = $blocks ?? new Blocks();

        // Display the block
        $this->display_block();
    }

    /**
     * Display the block
     *
     * @return void
     */
    protected function display_block()
    {
        // Process form
        $process_form = $this->process_form();

        if ($process_form === false) {
            $args = [
                'block_name' => 'wicket-ac/acc-base-block',
                'block_title' => 'ACC Base Block',
                'block_error' => 'There was an error processing the form.',
            ];

            $this->blocks->render_template('error', $args);
        }

        if ($process_form === true) {
            $args = [
                'block_name' => 'wicket-ac/acc-base-block',
                'block_title' => 'ACC Base Block',
            ];

            $this->blocks->render_template('success');
        }

        // Get user profile picture
        $wicket_slug = WACC()->getAccSlug();

        $args = [];

        // Render block
        $this->blocks->render_template('base-block', $args);
    }

    /**
     * Process a form submited to the same block
     *
     * @return bool|void
     */
    public function process_form()
    {
        if (is_admin()) {
            return;
        }

        // No data? no action?
        if (!isset($_POST['action']) || $_POST['action'] !== 'wicket-acc-base-block') {
            return;
        }

        $form = $_POST;

        // Check nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($form['nonce'])), 'wicket-acc-base-block')) {
            return false;
        }

        return true;
    }
}
