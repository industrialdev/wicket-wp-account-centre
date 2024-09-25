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
$location   = false;

if (isset($tp['attributes']['data']['location']) && $tp['attributes']['data']['location']) {
    $location = $tp['attributes']['data']['location'];

    // Check if location contains NO letters. If so, set to false
    if (!preg_match('/[a-zA-Z]/', $location)) {
        $location = false;
    }
}
?>
<div class="event-card my-4 p-4 border border-gray-200 rounded-md shadow-md" data-uuid="<?php echo $tp['id']; ?>">
	<a href="?event-id=<?php echo $tp['id']; ?>&event-data=<?php echo $event_data; ?>" class="event-card-link">
		<?php if (isset($tp['attributes']['data']['BadgeType']) && $tp['attributes']['data']['BadgeType']) : ?>
			<p class="text-sm font-bold mb-2 event-type">
				<?php echo $tp['attributes']['data']['BadgeType'];
		    ?>
			</p>
		<?php endif; ?>
		<?php if (isset($tp['attributes']['data']['url']) && $tp['attributes']['data']['url']) : ?>
			<a href="<?php echo $tp['attributes']['data']['url']; ?>" class="event-card-link">
			<?php endif; ?>
			<h3 class="text-lg font-bold mb-2 event-name">
				<?php echo $tp['attributes']['data']['EventName']; ?>
			</h3>
			<?php if (isset($tp['attributes']['data']['url']) && $tp['attributes']['data']['url']) : ?>
			</a>
		<?php endif; ?>
		<p class="text-sm mb-2 event-date">
			<?php echo date('M', strtotime($tp['attributes']['data']['StartDate'])) . '-' . date('j', strtotime($tp['attributes']['data']['StartDate'])) . '-' . date('Y', strtotime($tp['attributes']['data']['StartDate'])) . ' | ' . date('g:i a', strtotime($tp['attributes']['data']['StartDate'])) . ' - ' . date('g:i a', strtotime($tp['attributes']['data']['EndDate'])); ?>
		</p>
		<?php if ($location) : ?>
			<p class="text-sm event-location">
				<strong><?php _e('Location:', 'wicket-acc'); ?></strong> <?php echo $tp['attributes']['data']['location']; ?>
			</p>
		<?php endif; ?>
	</a>
</div>
