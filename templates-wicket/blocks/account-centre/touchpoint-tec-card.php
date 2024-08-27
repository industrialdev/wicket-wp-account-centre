<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * tp - Touchpoint data
 */

$tp         = $args['tp'];
$event_data = urlencode(base64_encode(json_encode($tp)));

if ($tp['attributes']['data']['location']) {
	$location = $tp['attributes']['data']['location'];

	// Check if location contains NO letters. If so, set to false
	if (!preg_match('/[a-zA-Z]/', $location)) {
		$location = false;
	}
} else {
	$location = false;
}
?>
<div class="event-card my-4 p-4 border border-gray-200 rounded-md shadow-md" data-uuid="<?php echo $tp['id']; ?>">
	<a href="<?php $tp['attributes']['data']['url']; ?>" class="event-card-link">
		<p class="text-sm font-bold mb-2 event-type">
			<?php //echo $tp['attributes']['data']['BadgeType'];
			?>
		</p>
		<h3 class="text-lg font-bold mb-2 event-name">
			<?php echo $tp['attributes']['data']['event_title']; ?>
		</h3>
		<p class="text-sm mb-2 event-date">
			<?php echo date('M', strtotime($tp['attributes']['data']['start_date'])) . '-' . date('j', strtotime($tp['attributes']['data']['start_date'])) . '-' . date('Y', strtotime($tp['attributes']['data']['start_date'])) . ' | ' . date('g:i a', strtotime($tp['attributes']['data']['start_date'])) . ' - ' . date('g:i a', strtotime($tp['attributes']['data']['end_date'])); ?>
		</p>
		<?php if ($location) : ?>
			<p class="text-sm event-location">
				<strong><?php esc_attr_e('Location:', 'wicket-acc'); ?></strong>
			</p>
		<?php endif; ?>
	</a>
</div>
