<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Individual Profile Block
 **/
class Block_Profile extends WicketAcc
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
		get_component('widget-profile-individual', []);
	}
}
