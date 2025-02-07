<?php

namespace WicketAcc;

use DateTime;
use DateTimeZone;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * tp - Touchpoint data
 */
$tp = $args['tp'];
$event_data = urlencode(base64_encode(json_encode($tp)));
$location = false;

if (isset($tp['attributes']['data']['location']) && $tp['attributes']['data']['location']) {
    $location = $tp['attributes']['data']['location'];

    // Check if location contains NO letters. If so, set to false
    if (!preg_match('/[a-zA-Z]/', $location)) {
        $location = false;
    }
}

// Convert $tp['attributes']['data']['start_date']
// Example output: 2024-11-19 9:00 AM EST
$start_date = explode(' ', $tp['attributes']['data']['start_date']);
$start_date_day = date('j', strtotime($tp['attributes']['data']['start_date']));
$start_date_month = date('M', strtotime($tp['attributes']['data']['start_date']));
$timezone = new DateTimeZone(get_option('timezone_string') ?: 'UTC');
$datetime = new DateTime($tp['attributes']['data']['start_date'], $timezone);
$start_date_full = $datetime->format('F j, Y');
$start_time = $datetime->format('g:i a');

// Extract timezone from original string if present
$start_parts = explode(' ', $tp['attributes']['data']['start_date']);
$timezone_abbr = isset($start_parts[3]) ? ' ' . $start_parts[3] : '';
$start_time .= $timezone_abbr;

// Now for the end date
$end_date = explode(' ', $tp['attributes']['data']['end_date']);
$end_date_day = date('j', strtotime($tp['attributes']['data']['end_date']));
$end_date_month = date('M', strtotime($tp['attributes']['data']['end_date']));
$end_datetime = new DateTime($tp['attributes']['data']['end_date'], $timezone);
$end_date_full = $end_datetime->format('F j, Y');
$end_time = $end_datetime->format('g:i a');

// Extract timezone from original string if present
$end_parts = explode(' ', $tp['attributes']['data']['end_date']);
$end_timezone_abbr = isset($end_parts[3]) ? ' ' . $end_parts[3] : '';
$end_time .= $end_timezone_abbr;
?>

<div class="event-card <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'my-0 p-6 border border-gray-200 gap-6 rounded-md shadow-md flex flex-row' ?>"
    data-uuid="<?php echo $tp['id']; ?>">
    <div class="event-date-box <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'bg-primary-100 text-white w-[72px] h-[72px] p-2 rounded-md flex flex-col items-center justify-center' ?>">
        <div class="flex flex-col items-center gap-1">
            <div class="<?php echo defined('WICKET_WP_THEME_V2') ? 'event-date-box-month' : 'text-sm font-medium uppercase' ?>">
                <?php echo $start_date_month; ?>
            </div>
            <div class="<?php echo defined('WICKET_WP_THEME_V2') ? 'event-date-box-day' : 'text-2xl font-bold' ?>">
                <?php echo $start_date_day; ?>
            </div>
        </div>
    </div>

    <div class="flex-auto md:w-auto event-content-wrap space-y-4">
        <?php if (isset($tp['attributes']['data']['BadgeType']) && $tp['attributes']['data']['BadgeType']) : ?>
            <div class="event-type <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm font-medium mb-1' ?>">
                <?php echo $tp['attributes']['data']['BadgeType']; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($tp['attributes']['data']['url']) && $tp['attributes']['data']['url']) : ?>
            <a href="<?php echo $tp['attributes']['data']['url']; ?>" class="event-card-link">
            <?php endif; ?>
            <h3 class="event-name <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-xl font-bold leading-tight mb-4' ?>">
                <?php echo $tp['attributes']['data']['event_title']; ?>
            </h3>
            <?php if (isset($tp['attributes']['data']['url']) && $tp['attributes']['data']['url']) : ?>
            </a>
        <?php endif; ?>
        <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
            <strong><?php _e('Date:', 'wicket-acc'); ?></strong> <?php echo $start_date_full; ?> - <?php echo $end_date_full; ?>
        </p>
        <p class="event-time <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
            <strong><?php _e('Time:', 'wicket-acc'); ?></strong> <?php echo $start_time; ?> - <?php echo $end_time; ?>
        </p>
        <?php if ($location) : ?>
            <p class="event-location <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <strong><?php _e('Location:', 'wicket-acc'); ?></strong> <?php echo $tp['attributes']['data']['location']; ?>
            </p>
        <?php endif; ?>
    </div>
</div>
