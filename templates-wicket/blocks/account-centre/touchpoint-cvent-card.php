<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * tp - Touchpoint data
 */
$tp = $args['tp'];
$event_data = urlencode(base64_encode(json_encode($tp)));
$is_session = $tp['attributes']['code'] === 'session_registration';
$title = '';

if ($is_session) {
    $title = $tp['attributes']['data']['session_name'];
} else {
    $title = $tp['attributes']['data']['event_name'];
}

$event_link = $tp['attributes']['data']['url'];

// Convert $tp['attributes']['data']['start_date']
$raw_start_date = $tp['attributes']['data']['start_time'];
$start_date = explode(' ', $raw_start_date);
$start_date_day = date('j', strtotime($raw_start_date));
$start_date_month = date('M', strtotime($raw_start_date));
$start_date_full = date('m-d-Y', strtotime($raw_start_date));

// Convert $tp['attributes']['data']['end_date']
$raw_end_date = $tp['attributes']['data']['end_time'];
$end_date = explode(' ', $raw_end_date);
$end_date_day = date('j', strtotime($raw_end_date));
$end_date_month = date('M', strtotime($raw_end_date));
$end_date_full = date('m-d-Y', strtotime($raw_end_date));
?>

<div class="event-card cvent <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'my-0 p-4 border border-gray-200 gap-4 rounded-md shadow-md flex flex-col md:flex-row' ?>"
	data-uuid="<?php echo $tp['id']; ?>">
	<div
		class="event-date-box <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'bg-primary-100 text-white w-[58px] h-[64px] p-[10px_10px] rounded-[var(--corner-radiusradius-050)] flex flex-col items-center justify-between gap-[8px]' ?>">
		<div class="flex flex-col items-center">
			<div class="<?php echo defined('WICKET_WP_THEME_V2') ? 'event-date-box-month' : 'text-sm font-bold' ?>">
				<?php echo $start_date_month; ?>
			</div>
			<div class="<?php echo defined('WICKET_WP_THEME_V2') ? 'event-date-box-day' : 'text-xl font-bold' ?>">
				<?php echo $start_date_day; ?>
			</div>
		</div>
	</div>

	<div class="flex-auto md:w-auto event-content-wrap">
		<?php if ($event_link) : ?>
			<a href="<?php echo $event_link; ?>" class="event-card-link" target="_blank">
			<?php endif; ?>

			<?php if ($title) : ?>
				<h3 class="event-name <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-lg font-bold mb-2' ?>">
					<?php echo $title; ?>
				</h3>
			<?php endif; ?>

			<?php if ($event_link) : ?>
			</a>
		<?php endif; ?>
		<p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm' ?>">
			<strong><?php _e('Date:', 'wicket-acc'); ?></strong> <?php echo $start_date_full; ?> -
			<?php echo $end_date_full; ?>
		</p>
	</div>
</div>