<?php

/**
 * Wicket Touchpoint Event Calendar Block
 **/

namespace Wicket_AC\Blocks\AC_Touchpoint_Event_Calendar;

function init($block = [], $is_preview)
{
	$close = 0;
	$attrs = get_block_wrapper_attributes(
		[
			'class' => 'wicket-ac-touchpoints flex flex-col gap-8'
		]
	);

	echo '<div ' . $attrs . '>';

	if ($is_preview) {
		echo '[Block: Touchpoint for TEC (The Event Calendar)]';

		return;
	}

	$num_results       = 2; //get_field('page_results');
	$display           = get_field('default_display');
	$registered_action = get_field('registered_action');
	$environment       = get_option('wicket_admin_settings_environment');

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
		display_events($events['past'], 'upcoming', $num_results);
		$close++;
	}

	if ($display == 'past' || $display == 'all') {
		display_events($events['past'], 'past', $num_results);
		$close++;
	}

	echo '</div>';
	echo '</div>';
}

function display_events($event_touchpoints, $display_type, $num_results)
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
			<p>
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
