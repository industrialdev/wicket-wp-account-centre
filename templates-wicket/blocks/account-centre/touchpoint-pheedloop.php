<?php

namespace WicketAcc;

use WicketAcc\Blocks\TouchpointPheedloop\init as TouchpointPheedloop;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * $attrs - Attributes for the block container.
 * $display - Display type: upcoming, past, all
 * $num_results - Number of results to display
 * $total_results - Total results
 * $counter - Counter
 * $display_type - Touchpoint display type: upcoming, past, all
 * $switch_link - Switch link
 * $touchpoints_results - Touchpoint results
 * $is_preview - Is preview?
 */

// @formatter:off
$attrs = $args['attrs'];
$title = $args['title'];
$display = $args['display'];
$num_results = $args['num_results'];
$total_results = $args['total_results'];
$counter = $args['counter'];
$display_type = $args['display_type'];
$switch_link = $args['switch_link'];
$switch_link_past = $args['switch_link'];
$touchpoints_results = $args['touchpoints_results'];
$is_preview = $args['is_preview'];
$close = $args['close'];
$override_past_events_link = $args['override_past_events_link'];
$override_past_events_link_text = $args['override_past_events_link_text'];
$past_events_link_text = __('See Past Registered Events', 'wicket-acc');
$show_view_more_events = $args['show_view_more_events'];
$use_x_columns = absint($args['use_x_columns']);
$is_ajax_request = $args['is_ajax_request'];
// @formatter:on

// Process event data from URL
$single_event = false;
$event_id = isset($_REQUEST['event-id']) ? sanitize_text_field($_REQUEST['event-id']) : '';
$event_data = isset($_REQUEST['event-data']) ? sanitize_text_field($_REQUEST['event-data']) : '';

if (!empty($event_id) && !empty($event_data)) {
    $event_data = json_decode(base64_decode($event_data), true);

    if (!empty($event_data)) {
        $event_data['id'] = $event_id;
    }

    $single_event = true;
}

// If override_past_events_link is not empty, use it
if (!empty($override_past_events_link)) {
    $switch_link_past = $override_past_events_link;
    $past_events_link_text = $override_past_events_link_text;
}
?>
<section <?php echo $attrs; ?>>
	<div class="container events_<?php echo $display; ?>">
		<div class="header flex flex-col mb-6">

			<div
				class="flex flex-col md:flex-row md:justify-between items-center md:items-center w-full <?php echo defined('WICKET_WP_THEME_V2') ? 'event-section-container' : '' ?>">
				<?php if ($display == 'upcoming' && !$single_event) : ?>
					<?php if (!empty($title)) : ?>
						<h3
							class="text-center md:text-left lg:text-left <?php echo defined('WICKET_WP_THEME_V2') ? 'event-section-title' : 'font-bold text-2xl text-dark-100 mb-4 md:mb-0 w-full md:w-auto' ?>">
							<?php echo esc_html($title); ?>
						</h3>
					<?php else : ?>
						<h3
							class="text-center md:text-left lg:text-left <?php echo defined('WICKET_WP_THEME_V2') ? 'event-section-title' : 'font-bold text-2xl text-dark-100 mb-4 md:mb-0 w-full md:w-auto' ?>">
							<?php esc_html_e('Upcoming Registered Events', 'wicket-acc'); ?>
						</h3>
					<?php endif; ?>
					<a href="<?php echo $switch_link_past; ?>"
						class="past-link text-center md:text-right  <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-base mb-4 font-bold w-full md:w-auto' ?>"><?php esc_html_e($past_events_link_text, 'wicket-acc'); ?></a>
				<?php elseif ($display == 'past' && !$single_event) : ?>
					<h3
						class="text-center md:text-left lg:text-left <?php echo defined('WICKET_WP_THEME_V2') ? 'event-section-title' : 'font-bold text-2xl text-dark-100 mb-4 md:mb-0 w-full md:w-auto' ?>">
						<?php esc_html_e('Past Registered Events', 'wicket-acc'); ?>
					</h3>
					<a href="<?php echo $switch_link; ?>"
						class="upcoming-link text-center md:text-right  <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-base mb-4 font-bold w-full md:w-auto' ?>"><?php esc_html_e('See Upcoming Registered Events', 'wicket-acc'); ?></a>
				<?php elseif ($display == 'all' && !$single_event) : ?>
					<h3
						class="text-center md:text-left lg:text-left <?php echo defined('WICKET_WP_THEME_V2') ? 'event-section-title' : 'font-bold text-2xl text-dark-100 mb-4 md:mb-0 w-full md:w-auto' ?>">
						<?php esc_html_e('All Events', 'wicket-acc'); ?>
					</h3>
					<a href="<?php echo $switch_link; ?>"
						class="upcoming-link text-center md:text-right  <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-base mb-4 font-bold w-full md:w-auto' ?>"><?php esc_html_e('See Upcoming Registered Events', 'wicket-acc'); ?></a>
				<?php elseif ($single_event) : ?>
					<h3
						class="text-center md:text-left lg:text-left <?php echo defined('WICKET_WP_THEME_V2') ? 'event-section-title' : 'font-bold text-2xl text-dark-100 mb-4 md:mb-0 w-full md:w-auto' ?>">
						<a href="javascript:history.back()"
							class="upcoming-link text-center md:text-right  <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-base mb-4 font-bold w-full md:w-auto' ?>"><?php esc_html_e('Go Back ←', 'wicket-acc'); ?></a>
					<?php endif; ?>
			</div>
		</div>

		<?php if (defined('WICKET_WP_THEME_V2')) : ?>
			<?php //?>
		<?php else : ?>
			<div class="data-quantity text-left mb-3 text-lg">
				Results: <span id="total_results"><?php echo $total_results; ?></span>
			</div>
		<?php endif; ?>

		<div
			class="events-list grid gap-4 sm:grid-cols-1 md:grid-cols-<?php echo $use_x_columns; ?> lg:grid-cols-<?php echo $use_x_columns; ?>">
			<?php
            if ($single_event) {
                $args = [
                    'tp'                    => $event_data,
                    'show_view_more_events' => $show_view_more_events,
                ];

                WACC()->Blocks->render_template('touchpoint-pheedloop-card', $args);
            } else {

                if ($display == 'upcoming' || $display == 'all') {
                    TouchpointPheedloop::display_touchpoints($touchpoints_results['data'], 'upcoming', $num_results, false, $args);

                    $close++;
                }

                if ($display == 'past' || $display == 'all') {
                    TouchpointPheedloop::display_touchpoints($touchpoints_results['data'], 'past', $num_results, false, $args);

                    $close++;
                }
            }
?>
		</div>
	</div>
</section>
