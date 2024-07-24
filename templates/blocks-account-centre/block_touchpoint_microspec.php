<?php
// No direct access
defined('ABSPATH') || exit;

/**
 * Available variables:
 *
 * $attrs - Attributes for the block container.
 * $display - Display type: upcoming, past, all
 * $num_results - Number of results to display
 * $total_results - Total results
 * $counter - Counter
 * $display_type - Touchpoint display type: upcoming, past, all
 *
 * @var $this Wicket_Acc_Block_Ajax_Touchpoint_Microspec
 */
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
				$this->display_touchpoints($touchpoints_results['data'], 'upcoming', $num_results);
				$close++;
			}

			if ($display == 'past' || $display == 'all') {
				$this->display_touchpoints($touchpoints_results['data'], 'past', $num_results);
				$close++;
			}
			?>
		</div>
	</div>
</section>
