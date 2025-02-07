<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/*
 * Template for displaying a single VitalSource touchpoint card
 *
 * Available data:
 *
 * $args - Passed data
 **/

$touchpoint = wp_parse_args($args['tp']);
$product_name = $touchpoint['attributes']['data']['product_name'];
$ebook_url = $touchpoint['attributes']['data']['ebook_url'];

?>

<a class="vitalsource-card" href="<?php echo esc_url($ebook_url); ?>" target="_blank">
	<?php echo esc_html($product_name); ?>
</a>
