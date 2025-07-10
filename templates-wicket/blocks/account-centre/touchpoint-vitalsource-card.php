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
$redemption_code = $touchpoint['attributes']['data']['redemption_code'];
$created_at_raw = $touchpoint['attributes']['created_at'];
$created_at = date('m/d/Y', strtotime($created_at_raw));
?>

<div class="vitalsource-card">
	<a href="<?php echo esc_url($ebook_url); ?>" class="vitalsource-card__title" target="_blank">
		<?php echo esc_html($product_name); ?>
	</a>

	<?php if ($redemption_code) : ?>
		<div class="vitalsource-card__redemption-code">
			<?php esc_html_e('Redemption Codes:', 'wicket-acc'); ?>
			<?php echo esc_html($redemption_code); ?>
		</div>
	<?php endif; ?>

	<div class="vitalsource-card__created-at">
		<?php esc_html_e('Purchase Date:', 'wicket-acc'); ?>
		<?php echo esc_html($created_at); ?>
	</div>
</div>
