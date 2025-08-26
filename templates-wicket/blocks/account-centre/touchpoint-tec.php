<?php

namespace WicketAcc;

use WicketAcc\Blocks\TouchpointEventCalendar\init as TouchpointEventCalendar;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * $attrs - Attributes for the block container.
 * $block_id - Block ID.
 * $display - Display type: upcoming, past, all
 * $num_results - Number of results to display
 * $total_results - Total results
 * $counter - Counter
 * $display_type - Touchpoint display type: upcoming, past, all
 * $switch_link - Switch link
 * $touchpoints_upcoming - Upcoming touchpoint results
 * $touchpoints_past - Past touchpoint results
 * $is_preview - Is preview?
 */
$attrs = $args['attrs'];
$block_id = $args['block_id'];
$title = $args['title'];
$past_events_title = $args['past_events_title'];
$display = $args['display'];
$num_results = $args['num_results'];
$total_results = $args['total_results'];
$counter = $args['counter'];
$display_type = $args['display_type'];
$switch_link = $args['switch_link'];
$switch_link_past = $args['switch_link'];
$touchpoints_upcoming = $args['touchpoints_upcoming'];
$touchpoints_past = $args['touchpoints_past'];
$is_preview = $args['is_preview'];
$close = $args['close'];
$show_switch_view_link = $args['show_switch_view_link'];
$override_past_events_link = $args['override_past_events_link'];
$override_past_events_link_text = $args['override_past_events_link_text'];
$past_events_link_text = __('See Past Registered Events', 'wicket-acc');
$show_view_more_events = $args['show_view_more_events'];
$use_x_columns = absint($args['use_x_columns']);
$is_ajax_request = $args['is_ajax_request'];
$show_param = "show-{$block_id}";
$num_param = "num-{$block_id}";
$registered_action = $args['registered_action'];

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
            <div class="flex flex-col md:flex-row md:justify-between items-center md:items-center w-full event-section-container">
                <?php if ($display == 'upcoming' && !$single_event) : ?>
                    <?php if (!empty($title)) : ?>
                        <h3
                            class="text-center md:text-left lg:text-left <?php echo defined('WICKET_WP_THEME_V2') ? 'event-section-title' : 'font-bold text-2xl text-dark-100 mb-4 md:mb-0 w-full md:w-auto' ?>">
                            <?php echo esc_html($title); ?>
                        </h3>
                    <?php endif; ?>
                    <?php if ($show_switch_view_link) : ?>
                        <?php
                        $switch_link_past = add_query_arg([$show_param => 'past', $num_param => $num_results], get_permalink());
                        ?>
                        <a href="<?php echo esc_url($switch_link_past); ?>"
                            class="past-link text-center md:text-right font-bold <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-base mb-4 font-bold w-full md:w-auto' ?>"><?php esc_html_e($past_events_link_text, 'wicket-acc'); ?></a>
                    <?php endif; ?>
                <?php elseif ($display == 'past' && !$single_event) : ?>
                    <?php if (!empty($past_events_title) || !empty($title)) : ?>
                        <h3
                            class="font-bold mb-4 md:mb-0 md:text-left text-center text-base <?php echo defined('WICKET_WP_THEME_V2') ? 'event-section-title' : 'w-full md:w-auto' ?>">
                            <?php echo esc_html($past_events_title ?: $title); ?>
                        </h3>
                    <?php endif; ?>
                    <?php if ($show_switch_view_link) : ?>
                        <?php
                        $switch_link = add_query_arg([$show_param => 'upcoming', $num_param => $num_results], get_permalink());
                        ?>
                        <a href="<?php echo esc_url($switch_link); ?>"
                            class="upcoming-link text-center md:text-right mb-4 <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-base mb-4 w-full md:w-auto' ?>"><?php esc_html_e('See Upcoming Registered Events', 'wicket-acc'); ?></a>
                    <?php endif; ?>
                <?php elseif ($single_event) : ?>
                    <?php if (!empty($title)) : ?>
                        <h3
                            class="text-2xl font-bold mb-4 md:mb-0 md:text-left text-center text-base <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'w-full md:w-auto' ?>">
                            <?php esc_html_e('Event Details', 'wicket-acc'); ?>
                        </h3>
                    <?php endif; ?>
                    <a href="javascript:history.back()"
                        class="back-link font-bold text-center md:text-right <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'w-full md:w-auto' ?>"><?php esc_html_e('Go Back ←', 'wicket-acc'); ?></a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (defined('WICKET_WP_THEME_V2')) : ?>
        <?php else: ?>
            <div class="data-quantity text-left mb-3 text-lg">
                <?php esc_html_e('Results:', 'wicket-acc'); ?>
                <span
                    id="total_results-<?php echo $block_id; ?>"><?php echo $total_results; ?></span>
            </div>
        <?php endif; ?>

        <div class="events-list grid gap-4 sm:grid-cols-1 md:grid-cols-<?php echo $use_x_columns; ?> lg:grid-cols-<?php echo $use_x_columns; ?>">
            <?php
            $args = [
                'tp'                    => $event_data,
                'show_view_more_events' => $show_view_more_events,
                'block_id'              => $block_id,
            ];

if ($single_event) {

    WACC()->Blocks()->render_template('touchpoint-tec-card', $args);

} else {

    if (
        (!empty($touchpoints_upcoming) && is_array($touchpoints_upcoming)) ||
        (!empty($touchpoints_past) && is_array($touchpoints_past))
    ) {
        if ($display === 'upcoming') {
            TouchpointEventCalendar::display_touchpoints($touchpoints_upcoming, 'upcoming', $num_results, false, $args);
            $close++;
        } elseif ($display === 'past') {
            TouchpointEventCalendar::display_touchpoints($touchpoints_past, 'past', $num_results, false, $args);
            $close++;
        } elseif ($display === 'all') {
            $all_touchpoints = array_merge(
                is_array($touchpoints_upcoming) ? $touchpoints_upcoming : [],
                is_array($touchpoints_past) ? $touchpoints_past : []
            );

            TouchpointEventCalendar::display_touchpoints($all_touchpoints, 'all', $num_results, false, $args);
            $close++;
        }
    }
}
?>
        </div>
    </div>
    <script>
        let totalElementsElement_<?php echo $block_id; ?> = document.getElementById(
            'total_results-<?php echo $block_id; ?>');

        if (totalElementsElement_<?php echo $block_id; ?> !== null) {
            totalElementsElement_<?php echo $block_id; ?>.innerHTML =
                '<?php echo esc_js($total_results); ?>';
        }
    </script>
</section>
