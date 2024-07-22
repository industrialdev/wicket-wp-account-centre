<?php

/**
 * Wicket Touchpoint Service Micropoint Block
 **/

namespace Wicket_AC\Blocks\AC_Touchpoint_Service_Micropoint;

if (!class_exists('Wicket_Acc_Touchpoint_Service_Micropoint')) {
	class Wicket_Acc_Touchpoint_Service_Micropoint
	{
		/**
		 * Constructor
		 */
		public function __construct(
			protected bool $is_preview = false,
		) {
			$this->is_preview = $is_preview;

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
			$is_preview = $this->is_preview;
			$close = 0;
			$attrs = get_block_wrapper_attributes(
				[
					'class' => 'wicket-ac-touchpoints flex flex-col gap-8'
				]
			);

			echo '<div ' . $attrs . '>';

			if ($is_preview) {
				echo '[Block: Touchpoint for Service Micropoint]';

				return;
			}

			$num_results       = 2;
			$display           = get_field('default_display');
			$registered_action = get_field('registered_action');

			$touchpoint_service = get_create_touchpoint_service_id('Service Micropoint');
			$touchpoints        = wicket_get_current_user_touchpoints($touchpoint_service);

			if (empty($registered_action)) {
				$registered_action = [
					"rsvp_to_event",
					"registered_for_an_event",
					"attended_an_event"
				];
			}

			if (!empty($_REQUEST['display'])) {
				$default_display = sanitize_text_field($_REQUEST['display']);

				if (in_array($default_display, ['upcoming', 'past', 'all'])) {
					$display = $default_display; // Sanitized
				}
			}

			if (empty($display)) {
				$display = 'all';
			}

			if (!empty($_REQUEST['num_results'])) {
				$num_results = absint($_REQUEST['num_results']);
			}

			// &filter%5Baction_eq%5D=RSVP%20to%20event
			// &filter%5Baction_eq%5D=Registered%20for%20an%20event
			// &filter%5Baction_eq%5D=Attended%an%20event
			// array(3) { [0]=> string(10) "registered" [1]=> string(4) "rsvp" [2]=> string(6) "attend" }

			// pre-filter events based on time. Add upcoming events
			foreach ($touchpoints['data'] as $key => $tp) {
				if (!in_array($tp['attributes']['code'], $registered_action)) {
					continue;
				}
				if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) > time()) {
					$events['upcoming']['data'][] = $tp;
				} else if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) < time()) {
					$events['past']['data'][] = $tp;
				}
			}

			foreach ($touchpoints['data'] as $key => $tp) {
				if (!in_array($tp['attributes']['code'], $registered_action)) {
					continue;
				}
				if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) > time()) {
					$events['upcoming']['data'][] = $tp;
				} else if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) < time()) {
					$events['past']['data'][] = $tp;
				}
			}

			foreach ($touchpoints['data'] as $key => $tp) {
				if (!in_array($tp['attributes']['code'], $registered_action)) {
					continue;
				}
				if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) > time()) {
					$events['upcoming']['data'][] = $tp;
				} else if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) < time()) {
					$events['past']['data'][] = $tp;
				}
			}

			foreach ($touchpoints['data'] as $key => $tp) {
				if (!in_array($tp['attributes']['code'], $registered_action)) {
					continue;
				}
				if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) > time()) {
					$events['upcoming']['data'][] = $tp;
				} else if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) < time()) {
					$events['past']['data'][] = $tp;
				}
			}

			echo '<div class="grid" x-data="{show: false}">';

			if ($display == 'upcoming') {
				echo '
			<div class="touchpoint_header">
				<a class="mb-5 mt-8 block" href="?display=past">See Past Registered Events</a>
			</div>';
			}

			if ($display == 'past') {
				echo '
		<div class="touchpoint_header">
			<a class="mb-5 mt-8 block" href="?display=upcoming">See Upcoming Registered Events</a>
		</div>';
			}

			if ($display == 'upcoming' || $display == 'all') {
				//display_events($events['past'], 'upcoming', $num_results);
				$close++;
			}

			if ($display == 'past' || $display == 'all') {
				//display_events($events['past'], 'past', $num_results);
				$close++;
			}

			echo '</div>';
			echo '</div>';
		}
	}
}

/**
 * Initialize the block
 *
 * @param array $block
 */
function init($block = [], $is_preview)
{
	// Is ACF enabled?
	if (function_exists('acf_get_field')) {
		new Wicket_Acc_Touchpoint_Service_Micropoint($is_preview);
	}
}
