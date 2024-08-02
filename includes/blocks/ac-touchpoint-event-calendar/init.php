<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Touchpoint Event Calendar Block
 **/
class Block_TouchpointEventCalendar extends WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct(
		protected array $block     = [],
		protected bool $is_preview = false,
		protected ?Blocks $blocks = null,
	) {
		$this->block      = $block;
		$this->is_preview = $is_preview;
		$this->blocks     = $blocks ?? new Blocks();

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
		$close = 0;
		$attrs = get_block_wrapper_attributes(
			[
				'class' => 'wicket-acc-block wicket-ac-touchpoints-tec flex flex-col gap-8'
			]
		);

		echo '<section ' . $attrs . '>';

		if ($this->is_preview) {
			$args = [
				'block_name'          => 'Touchpoint TEC',
				'block_description'   => 'This block displays registered data for The Events Calendar on the front-end.',
				'block_slug'          => 'wicket-ac-touchpoint-tec',
			];

			$this->blocks->render_template('preview', $args);

			return;
		}

		$num_results       = get_field('page_results');
		$display           = get_field('default_display');
		$registered_action = get_field('registered_action');
		$environment       = get_option('wicket_admin_settings_environment');
		$events            = [];

		$events_calendar_touchpoint_service = get_create_touchpoint_service_id('Events Calendar');
		$event_touchpoints                  = wicket_get_current_user_touchpoints($events_calendar_touchpoint_service);

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
			$display = 'upcoming';
		}

		if (!empty($_REQUEST['num_results'])) {
			$num_results = absint($_REQUEST['num_results']);
		}

		// &filter%5Baction_eq%5D=RSVP%20to%20event
		// &filter%5Baction_eq%5D=Registered%20for%20an%20event
		// &filter%5Baction_eq%5D=Attended%an%20event
		// array(3) { [0]=> string(10) "registered" [1]=> string(4) "rsvp" [2]=> string(6) "attend" }

		// pre-filter events based on time. Add upcoming events
		foreach ($event_touchpoints['data'] as $key => $tp) {
			if (!in_array($tp['attributes']['code'], $registered_action)) {
				continue;
			}
			if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) > time()) {
				$events['upcoming']['data'][] = $tp;
			} else if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) < time()) {
				$events['past']['data'][] = $tp;
			}
		}

		foreach ($event_touchpoints['data'] as $key => $tp) {
			if (!in_array($tp['attributes']['code'], $registered_action)) {
				continue;
			}
			if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) > time()) {
				$events['upcoming']['data'][] = $tp;
			} else if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) < time()) {
				$events['past']['data'][] = $tp;
			}
		}

		foreach ($event_touchpoints['data'] as $key => $tp) {
			if (!in_array($tp['attributes']['code'], $registered_action)) {
				continue;
			}
			if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) > time()) {
				$events['upcoming']['data'][] = $tp;
			} else if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) < time()) {
				$events['past']['data'][] = $tp;
			}
		}

		foreach ($event_touchpoints['data'] as $key => $tp) {
			if (!in_array($tp['attributes']['code'], $registered_action)) {
				continue;
			}
			if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) > time()) {
				$events['upcoming']['data'][] = $tp;
			} else if (isset($tp['attributes']['data']['start_date']) && strtotime($tp['attributes']['data']['start_date']) < time()) {
				$events['past']['data'][] = $tp;
			}
		}

?>
		<div class="header flex justify-between items-center mb-6">
			<?php
			if ($display == 'upcoming') {
				$switch_link = add_query_arg(
					[
						'display' => 'past'
					],
					remove_query_arg('display')
				);
			?>
				<h2 class="font-bold"><?php esc_html_e('Upcoming Registered Events', 'wicket-acc'); ?></h2>
				<a href="<?php echo $switch_link; ?>" class="past-link font-bold"><?php esc_html_e('See Past Registered Events →', 'wicket-acc'); ?></a>

			<?php
			}

			if ($display == 'past') {
				$switch_link = add_query_arg(
					[
						'display' => 'upcoming'
					],
					remove_query_arg('display')
				);
			?>
				<h2 class="font-bold"><?php esc_html_e('Past Registered Events', 'wicket-acc'); ?></h2>
				<a href="<?php echo $switch_link; ?>" class="upcoming-link font-bold"><?php esc_html_e('See Upcoming Registered Events →', 'wicket-acc'); ?></a>
			<?php
			}

			?>
		</div>
		<div class="grid" x-data="{show: false}">
			<?php

			if ($display == 'upcoming' || $display == 'all') {
				if (isset($events['upcoming']) && !empty($events['upcoming'])) {
					$this->display_events($events['upcoming'], 'upcoming', $num_results);
					$close++;
				}
			}

			if ($display == 'past' || $display == 'all') {
				if (isset($events['past']) && !empty($events['past'])) {
					$this->display_events($events['past'], 'past', $num_results);
					$close++;
				}
			}
			?>
		</div>
		<?php
		echo '</section>';
	}

	protected function display_events($event_touchpoints, $display_type, $num_results)
	{
		// No data
		if (empty($event_touchpoints['data'])) {
			_e('<p>You do not have any ' . $display_type . ' events at this time.</p>', 'wicket-acc');
			return;
		}

		echo "<h2> " . ucfirst($display_type) . " Events: " . count($event_touchpoints['data']) . "</h2>";

		$counter = 0;

		foreach ($event_touchpoints['data'] as $tp) :
		?>
			<?php if ($tp['attributes']['code'] == 'registered_for_an_event') : ?>
				<?php $counter++; ?>
				<?php if (isset($tp['attributes']['data']['start_date'])) : ?>
					<div class='wicket-ac-display-events touchpoint_row mb-3 flex' id="event-<?php echo $counter; ?>">
						<div class='shadow-4 rounded-100 p-5 w-full sm:w-2/3 event_touchpoint_row_content'>
							<div class="calendar_entry">
								<?php echo date('M', strtotime($tp['attributes']['data']['start_date'])); ?><br>
								<?php echo date('j', strtotime($tp['attributes']['data']['start_date'])); ?>
							</div>
							<div class="calendar_content">
								<?php if ($tp['attributes']['data']['event_type'] != 'Not set') {
									echo '<div class="event_type">';
									echo $tp['attributes']['data']['event_type'];
									echo '</div>';
								} ?>
								<a target='_blank' href='<?php echo $tp['attributes']['data']['url'] ?>'>
									<?php
									echo '<div class="event_title">';
									echo $tp['attributes']['data']['event_title'];
									echo '</div>';
									?>
									<span class='sr-only'><?php esc_html_e('(opens in a new window)', 'wicket-acc'); ?></span></a>
								<?php
								echo '<div class="event_date">';
								?>
								<span class="label"><?php esc_html_e('Date:', 'wicket-acc'); ?></span>
								<?php echo date('F j, Y, g:i a', strtotime($tp['attributes']['data']['start_date'])) ?> - <?php echo date('F j, Y, g:i a', strtotime($tp['attributes']['data']['end_date'])) ?>
								<?php if ($tp['attributes']['data']['location'] && strlen($tp['attributes']['data']['location']) > 10) : ?>
									<?php
									echo '</div>';
									?>
									<?php
									echo '<div class="event_location">';
									?><span class="label"><?php esc_html_e('Location:', 'wicket-acc'); ?></span>
									<?php echo $tp['attributes']['data']['location'] ?>
									<?php
									echo '</div>';
									?>
								<?php endif; ?>
								<?php
								if (isset($tp['attributes']['data']['event_additional_fields'])) :
									foreach ($tp['attributes']['data']['event_additional_fields'] as $meta) :
										foreach ($meta as $label => $value) :
								?>
											<div class='event_landing_custom_field'>
												<strong><?php echo $label; ?>: </strong>
												<span><?php echo $value; ?></span>
											</div>
								<?php
										endforeach;
									endforeach;
								endif;
								?>
							</div>
						</div>
					</div>
				<?php endif; ?>

			<?php endif; ?>

			<?php if ($counter == $num_results) : ?>
				<p class="text-center">
					<a href='#' class='touchpoint_show_more_cta' id='touchpoint_show_more_cta' @click.prevent="show = !show" x-show="!show"><?php esc_html_e('Show More', 'wicket-acc'); ?></a>
				</p>
				<div x-show="show" x-transition class='touchpoint_show_more'>
				<?php endif; ?>

				<?php if ($counter == count($event_touchpoints['data']) && count($event_touchpoints['data']) > $num_results) : ?>
				</div>
			<?php endif; ?>
<?php
		endforeach;
	}
}
