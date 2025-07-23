<?php

namespace WicketAcc;

use WicketAcc\Blocks\TouchpointVitalSource\init as TouchpointVitalSource;

// No direct access
defined('ABSPATH') || exit;

/*
 * Template for displaying the VitalSource touchpoints block
 *
 * Available data:
 *
 * $args - Passed data
 **/

$num_results = $args['num_results'];
$total_results = $args['total_results'];
$close = $args['close'];
$counter = $args['counter'];
$touchpoints_results = $args['touchpoints_results'];
$show_view_more = $args['show_view_more'];
$is_ajax_request = $args['is_ajax_request'];
$title = $args['title'];
$description = $args['description'];
?>

<section <?php echo $args['attrs']; ?>>
	<div class="header">
		<?php if ($title) : ?>
			<h2><?php echo esc_html($title); ?></h2>
		<?php endif; ?>
	</div>

	<?php if ($description) : ?>
		<div class="description">
			<?php echo wp_kses_post($description); ?>
		</div>
	<?php endif; ?>



	<div class="vitalsource-list">
		<?php
        TouchpointVitalSource::display_touchpoints($touchpoints_results, $num_results, false, [
            'show_view_more' => $show_view_more,
        ]);
?>
	</div>
</section>