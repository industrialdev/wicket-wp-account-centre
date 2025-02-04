<?php

namespace WicketAcc;

use WicketAcc\Blocks\TouchpointMaple\init as TouchpointMaple;

// No direct access
defined( 'ABSPATH' ) || exit;

/*
 * Template for displaying the Maple touchpoints block
 *
 * Available data:
 *
 * $args - Passed data
 **/

$num_results         = $args['num_results'];
$total_results       = $args['total_results'];
$close               = $args['close'];
$counter             = $args['counter'];
$touchpoints_results = $args['touchpoints_results'];
$show_view_more      = $args['show_view_more'];
$is_ajax_request     = $args['is_ajax_request'];
$block_id            = $args['block_id'];
?>

<section <?php echo $args['attrs']; ?>>
	<div class="header">
		<h2><?php echo esc_html( $args['title'] ); ?></h2>
	</div>

	<div class="maple-list">
		<?php
		TouchpointMaple::display_touchpoints( $touchpoints_results, $num_results, false, [
			'show_view_more' => $show_view_more,
		], $block_id );
		?>
	</div>
</section>
