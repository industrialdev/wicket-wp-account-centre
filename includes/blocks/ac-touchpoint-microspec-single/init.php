<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Block: Touchpoint MicroSpec Single View
 **/
class Block_TouchpointMicroSpecSingle extends WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct(
		protected array $block     = [],
		protected bool $is_preview = false,
		protected ?Blocks $blocks = null,
	) {
		$this->block        = $block;
		$this->is_preview   = $is_preview;
		$this->blocks       = $blocks ?? new Blocks();

		// Display the block
		$this->init_block();
	}

	/**
	 * Display the block
	 *
	 * @return void
	 */
	protected function init_block()
	{
		$close = 0;
		$attrs = $this->is_preview ? ' ' : get_block_wrapper_attributes(
			[
				'class' => 'wicket-ac-touchpoint-microspec max-w-5xl mx-auto my-8 p-6'
			]
		);

		if ($this->is_preview) {
			$this->blocks->render_template('preview');

			return;
		}

		$display           = get_field('default_display');
		$registered_action = get_field('registered_action');
		$num_results       = 1;
		$display_type      = 'upcoming';
		$acc_page_events   = get_field('acc_page_events', 'option');

		if (empty($registered_action)) {
			$registered_action = [
				"rsvp_to_event",
				"registered_for_an_event",
				"attended_an_event"
			];
		}

		$tp_id;
		$single_touchpoint_result = $this->get_touchpoint_data($tp_id);
		ray($single_touchpoint_result);

		$args = [
			'block_name'        => 'Touchpoint MicroSpec Single View',
			'block_description' => 'This block displays single registered data for MicroSpec on the front-end.',
			'block_slug'        => 'wicket-ac-touchpoint-microspec-single',
			'attrs'             => $attrs,
			'is_preview'        => $this->is_preview
		];

		// Render block
		$this->blocks->render_template('touchpoint-microspec-single', $args);

		return;
	}

	/**
	 * Get single touchpoint data
	 *
	 * @param int $tp_id Touchpoint ID
	 *
	 * @return array
	 */
	protected function get_touchpoint_data($tp_id)
	{
		$touchpoint_service = get_create_touchpoint_service_id('MicroSpec');
		$touchpoints        = WACC()->get_current_user_touchpoints($touchpoint_service);

		return $touchpoints;
	}
}
