<?php

namespace WicketAcc;

use WicketAcc\Blocks\TouchpointVitalSource\init as TouchpointVitalSource;

// No direct access
defined( 'ABSPATH' ) || exit;

/*
 * Template for displaying the VitalSource touchpoints block
 *
 * Available data:
 *
 * $args - Passed data
 **/


$num_results           = $args['num_results'];
$total_results         = $args['total_results'];
$close                 = $args['close'];
$counter               = $args['counter'];
$touchpoints_results   = $args['touchpoints_results'];
$show_view_more_events = $args['show_view_more_events'];
$is_ajax_request       = $args['is_ajax_request'];
?>

<section <?php echo $args['attrs']; ?>>
	<div class="header">
		<h2><?php echo esc_html( $args['title'] ); ?></h2>
	</div>

	<div class="vitalsource-list">
		<?php
		TouchpointVitalSource::display_touchpoints( $touchpoints_results, $num_results, false, [] );
		?>
	</div>
</section>
