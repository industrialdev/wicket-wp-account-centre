<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * tp - Touchpoint data
 * display_event_info - Display event info: in_page, in_modal
 */

$tp         = $args['tp'];
$event_data = base64_encode(json_encode($tp));
?>
<div class="event-card my-4 p-4 border border-gray-200 rounded-md shadow-md" data-uuid="<?php echo $tp['id']; ?>">
	<?php if ($args['display_event_info'] == 'in_page') : ?>
		<a href="?event-id=<?php echo $tp['id']; ?>&event-data=<?php echo $event_data; ?>" class="event-card-link">
		<?php endif; ?>
		<p class="text-sm font-bold mb-2 event-type">
			<?php echo $tp['attributes']['data']['BadgeType']; ?>
		</p>
		<h3 class="text-lg font-bold mb-2 event-name">
			<?php echo $tp['attributes']['data']['EventName']; ?>
		</h3>
		<p class="text-sm mb-2 event-date">
			<?php echo date('M', strtotime($tp['attributes']['data']['StartDate'])) . '-' . date('j', strtotime($tp['attributes']['data']['StartDate'])) . '-' . date('Y', strtotime($tp['attributes']['data']['StartDate'])) . ' | ' . date('g:i a', strtotime($tp['attributes']['data']['StartDate'])) . ' - ' . date('g:i a', strtotime($tp['attributes']['data']['EndDate'])); ?>
		</p>
		<p class="text-sm event-location hidden">
			<strong><?php esc_attr_e('Location:', 'wicket-acc'); ?></strong>
		</p>
		<?php if ($args['display_event_info'] == 'in_page') : ?>
		</a>
	<?php endif; ?>
	<?php
	if ($args['display_event_info'] == 'in_modal') {
		WACC()->Blocks->render_template('modal-touchpoint-microspec', $tp);
	}
	?>
</div>
