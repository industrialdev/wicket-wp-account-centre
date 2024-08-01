<?php

namespace WicketAcc;

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

$attrs               = $args['attrs'];
$display             = $args['display'];
$num_results         = $args['num_results'];
$total_results       = $args['total_results'];
$counter             = $args['counter'];
$display_type        = $args['display_type'];
$switch_link         = $args['switch_link'];
$touchpoints_results = $args['touchpoints_results'];
$is_preview          = $args['is_preview'];
$close               = $args['close'];
?>
<section <?php echo $attrs; ?>>
	<div class="container">
		<div class="header flex justify-between items-center mb-6">
			<?php
			if ($display == 'upcoming') {
			?>
				<h2 class="text-2xl font-bold"><?php esc_html_e('Upcoming Registered Events', 'wicket-acc'); ?></h2>
				<a href="<?php echo $switch_link; ?>" class="past-link font-bold"><?php esc_html_e('See Past Registered Events →', 'wicket-acc'); ?></a>
			<?php
			}

			if ($display == 'past') {
			?>
				<h2 class="text-2xl font-bold"><?php esc_html_e('Past Registered Events', 'wicket-acc'); ?></h2>
				<a href="<?php echo $switch_link; ?>" class="upcoming-link font-bold"><?php esc_html_e('See Upcoming Registered Events →', 'wicket-acc'); ?></a>
			<?php
			}
			?>
		</div>

		<div class="events-list grid gap-6">
			<?php
			if ($display == 'upcoming' || $display == 'all') {
				Block_TouchpointMicroSpec::display_touchpoints($touchpoints_results['data'], 'upcoming', $num_results);

				$close++;
			}

			if ($display == 'past' || $display == 'all') {
				Block_TouchpointMicroSpec::display_touchpoints($touchpoints_results['data'], 'past', $num_results);

				$close++;
			}
			?>
		</div>
	</div>
</section>
